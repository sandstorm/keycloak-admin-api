<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

use const JSON_THROW_ON_ERROR;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Connection\Auth\KeycloakTokenProvider;
use UnexpectedValueException;

use function array_values;
use function is_array;
use function json_decode;
use function sprintf;
use function trim;

/**
 * The single gateway to the Keycloak Admin API. It owns the Guzzle client and the URL plumbing, and
 * exposes the admin API as path-in/response-out verbs.
 *
 * Authentication is delegated to an injected {@see KeycloakTokenProvider}: every request transparently
 * carries a valid bearer token. The token provider, the raw client and the URL plumbing are
 * intentionally private and never exposed — callers only ever deal in admin API paths.
 *
 * @internal Reached indirectly via the feature interfaces (e.g. {@see \Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi}).
 */
final class KeycloakTransport
{
    /**
     * The Guzzle client is injected fully built by the consumer — this library is app-independent and
     * does not construct HTTP clients. Consumers must inject a client that never body-logs: admin
     * responses carry user PII. The token provider is bound once by the consumer (from config) — the
     * transport never selects it and never falls back.
     */
    public function __construct(
        private readonly KeycloakSettingsProvider $settings,
        private readonly Client $client,
        private readonly KeycloakTokenProvider $tokenProvider,
    ) {}

    /**
     * GET an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function get(string $path): ResponseInterface
    {
        return $this->send('GET', $path, []);
    }

    /**
     * GET an admin API path and decode its JSON body into an array (object or list).
     *
     * @return array<int|string, mixed>
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure, UnexpectedValueException when the body is not a JSON array/object
     */
    public function getJson(string $path): array
    {
        $decoded = json_decode((string) $this->get($path)->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            // Broken endpoint contract — surface it (propagates + gets logged), never a friendly notice.
            throw new UnexpectedValueException(sprintf('Keycloak admin GET %s did not return a JSON array/object.', $path), 1750000014);
        }

        return $decoded;
    }

    /**
     * POST a JSON body to an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function postJson(string $path, array $body): ResponseInterface
    {
        return $this->send('POST', $path, [RequestOptions::JSON => (object) $body]);
    }

    /**
     * PUT a JSON body to an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @param  array<int|string, mixed>  $body
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function putJson(string $path, array $body): ResponseInterface
    {
        return $this->send('PUT', $path, [RequestOptions::JSON => (object) $body]);
    }

    /**
     * PUT a JSON **array** (list) body to an admin API path, authenticated.
     *
     * Distinct from {@see putJson()}: that one casts the body to `(object)`, which corrupts a JSON list
     * into `{"0":…}`. Keycloak's `execute-actions-email` demands a bare array (`["UPDATE_PASSWORD"]`), so
     * this send keeps the list shape. `array_values()` guarantees sequential keys → a JSON array.
     *
     * @param  array<array-key, mixed>  $jsonList
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function putList(string $path, array $jsonList): ResponseInterface
    {
        return $this->send('PUT', $path, [RequestOptions::JSON => array_values($jsonList)]);
    }

    /**
     * PUT an admin API path with **no body** at all (no `Content-Type: application/json`), authenticated.
     *
     * Distinct from {@see putJson()}, which always sends a JSON body — even for `[]` it emits `{}`, and
     * Keycloak's body-less endpoints (e.g. add-user-to-group `PUT /users/{id}/groups/{groupId}`) 500 on
     * an unexpected JSON payload. Use this when the endpoint takes the target purely from the URL.
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function put(string $path): ResponseInterface
    {
        return $this->send('PUT', $path, []);
    }

    /**
     * DELETE an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    public function delete(string $path): ResponseInterface
    {
        return $this->send('DELETE', $path, []);
    }

    /**
     * @param  array<string, mixed>  $guzzleOptions
     *
     * @throws KeycloakAuthenticationException on 401/403, RuntimeException on any other request failure
     */
    private function send(string $method, string $path, array $guzzleOptions): ResponseInterface
    {
        $endpointLabel = $method . ' ' . $path;

        // Resolve the bearer OUTSIDE the try: a token-provision failure (e.g. a bad service-account
        // secret, a missing SSO session) is a setup/config problem for developers to fix — it must
        // propagate as-is, not be mis-wrapped as a request-level auth/unavailable failure below.
        $bearer = $this->tokenProvider->currentBearer();

        try {
            return $this->client->request(
                $method,
                $this->adminUrl($path),
                $guzzleOptions + [
                    RequestOptions::HEADERS => [
                        'Authorization' => 'Bearer ' . $bearer,
                        'Accept' => 'application/json',
                    ],
                ],
            );
        } catch (GuzzleException $exception) {
            $response = $exception instanceof RequestException ? $exception->getResponse() : null;
            $status = $response?->getStatusCode();

            // 401/403 = the caller is not accepted / not permitted → the one catchable, friendly
            // outcome. Anything else (connection error, timeout, 5xx) = Keycloak could not serve the
            // request → a plain RuntimeException that propagates and gets logged.
            if ($status === 401 || $status === 403) {
                // Carry Keycloak's own error body (Guzzle's message truncates it) — the fastest clue
                // when debugging a missing audience/role. These 401/403 bodies carry no user PII.
                // $response is non-null here: a null response could not have yielded a 401/403 status.
                $upstream = trim((string) $response->getBody());

                throw new KeycloakAuthenticationException(
                    sprintf('Keycloak admin %s was not authorized (HTTP %d): %s', $endpointLabel, $status, $upstream !== '' ? $upstream : $exception->getMessage()),
                    1750000005,
                    $exception,
                );
            }

            throw new RuntimeException(
                sprintf('Keycloak admin %s request failed: %s', $endpointLabel, $exception->getMessage()),
                1750000006,
                $exception,
            );
        }
    }

    /**
     * Build an admin API URL: {baseUrl}/admin/realms/{realm}/{path}.
     */
    private function adminUrl(string $path): string
    {
        return sprintf(
            '%s/admin/realms/%s/%s',
            $this->settings->get()->baseUrl,
            $this->settings->get()->realm,
            trim($path, '/'),
        );
    }
}
