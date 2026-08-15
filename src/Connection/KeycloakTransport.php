<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

use const JSON_THROW_ON_ERROR;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sandstorm\KeycloakAdminApi\Connection\Auth\KeycloakTokenProvider;
use UnexpectedValueException;

use function array_values;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;
use function trim;

/**
 * The single gateway to the Keycloak Admin API. It owns the HTTP client and the URL plumbing, and
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
     * The HTTP client is injected fully built by the consumer — this library is app-independent and
     * PSR-18/PSR-17 based, so it binds to no concrete HTTP client (Guzzle, Symfony HttpClient, …).
     * Consumers must inject a client that never body-logs: admin responses carry user PII. The token
     * provider is bound once by the consumer (from config) — the transport never selects it and never
     * falls back.
     */
    public function __construct(
        private readonly KeycloakSettingsProvider $settings,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly KeycloakTokenProvider $tokenProvider,
    ) {}

    /**
     * GET an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function get(string $path): ResponseInterface
    {
        return $this->send('GET', $path);
    }

    /**
     * GET an admin API path and decode its JSON body into an array (object or list).
     *
     * @return array<int|string, mixed>
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied), UnexpectedValueException when the body is not a JSON array/object
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
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function postJson(string $path, array $body): ResponseInterface
    {
        return $this->send('POST', $path, json_encode((object) $body, JSON_THROW_ON_ERROR));
    }

    /**
     * PUT a JSON body to an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @param  array<int|string, mixed>  $body
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function putJson(string $path, array $body): ResponseInterface
    {
        return $this->send('PUT', $path, json_encode((object) $body, JSON_THROW_ON_ERROR));
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
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function putList(string $path, array $jsonList): ResponseInterface
    {
        return $this->send('PUT', $path, json_encode(array_values($jsonList), JSON_THROW_ON_ERROR));
    }

    /**
     * PUT an admin API path with **no body** at all (no `Content-Type: application/json`), authenticated.
     *
     * Distinct from {@see putJson()}, which always sends a JSON body — even for `[]` it emits `{}`, and
     * Keycloak's body-less endpoints (e.g. add-user-to-group `PUT /users/{id}/groups/{groupId}`) 500 on
     * an unexpected JSON payload. Use this when the endpoint takes the target purely from the URL.
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function put(string $path): ResponseInterface
    {
        return $this->send('PUT', $path);
    }

    /**
     * DELETE an admin API path (relative to {baseUrl}/admin/realms/{realm}/), authenticated.
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    public function delete(string $path): ResponseInterface
    {
        return $this->send('DELETE', $path);
    }

    /**
     * Build the authenticated request, send it, and translate the outcome.
     *
     * PSR-18 clients — unlike Guzzle's `http_errors` mode — do NOT throw on 4xx/5xx: only a transport
     * failure (connection refused, timeout, DNS) raises {@see ClientExceptionInterface}. So the HTTP
     * error handling here is driven by inspecting the returned status, not by a catch.
     *
     * @throws UnexpectedKeycloakResponseException on a transport failure or any non-2xx (read ->statusCode; 401/403 = denied)
     */
    private function send(string $method, string $path, ?string $jsonBody = null): ResponseInterface
    {
        $endpointLabel = $method . ' ' . $path;

        // Resolve the bearer OUTSIDE the try: a token-provision failure (e.g. a bad service-account
        // secret, a missing SSO session) is a setup/config problem for developers to fix — it must
        // propagate as-is, not be mis-wrapped as a request-level auth/unavailable failure below.
        $bearer = $this->tokenProvider->currentBearer();

        $request = $this->requestFactory
            ->createRequest($method, $this->adminUrl($path))
            ->withHeader('Authorization', 'Bearer ' . $bearer)
            ->withHeader('Accept', 'application/json');

        if ($jsonBody !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($jsonBody));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            // Transport-level failure (connection error, timeout, DNS) → no response, so no status.
            throw new UnexpectedKeycloakResponseException(
                sprintf('Keycloak admin %s request failed: %s', $endpointLabel, $exception->getMessage()),
                1750000006,
                null,
                '',
                $exception,
            );
        }

        $status = $response->getStatusCode();
        if ($status < 400) {
            return $response;
        }

        // Any non-2xx is the single failure type — the caller reads ->statusCode to react (401/403 =
        // denied, the one a UI turns into a friendly notice; 5xx = outage/retryable; 404/409 = etc).
        // Carry Keycloak's own error body: the fastest clue when debugging a missing audience/role.
        $upstream = trim((string) $response->getBody());

        throw new UnexpectedKeycloakResponseException(
            sprintf('Keycloak admin %s returned an unexpected HTTP %d: %s', $endpointLabel, $status, $upstream !== '' ? $upstream : '(no body)'),
            1750000007,
            $status,
            $upstream,
        );
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
