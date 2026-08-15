<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection\Auth;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;
use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;

use function http_build_query;
use function sprintf;
use function time;
use function trim;

/**
 * OAuth2 `client_credentials` token provider: the confidential service-account client authenticates
 * as a single shared identity. The token is cached in-memory for the lifetime of this instance and
 * refreshed on demand (with a safety margin before real expiry).
 *
 * Self-contained — depends only on the settings seam and an injected PSR-18 client (+ PSR-17
 * factories). The client must never body-log: the token request carries the client secret.
 */
final class ServiceAccountTokenProvider implements KeycloakTokenProvider
{
    /**
     * Refresh the token this many seconds before its real expiry to avoid edge-of-expiry
     * failures on a slow round-trip.
     */
    private const int TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS = 30;

    private ?KeycloakAccessToken $cachedToken = null;

    public function __construct(
        private readonly KeycloakSettingsProvider $settings,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function currentBearer(): string
    {
        if ($this->cachedToken !== null && $this->cachedToken->isStillValidNow()) {
            return $this->cachedToken->accessToken;
        }

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->settings->get()->clientId,
            'client_secret' => $this->settings->get()->clientSecret,
        ]);

        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenUrl())
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream($body));

        // A transport failure (connection refused, timeout, DNS) raises ClientExceptionInterface and is
        // left to propagate raw — a broken token round-trip is an operational/config problem, never a
        // per-caller outcome (the transport resolves the bearer outside its try for exactly this reason).
        $response = $this->client->sendRequest($request);

        // PSR-18 does not throw on 4xx/5xx. A 4xx here means the grant itself was rejected → the
        // service-account CONFIG is wrong (secret/client). A 5xx means Keycloak could not serve it.
        $status = $response->getStatusCode();

        if ($status !== 200) {
            $upstream = trim((string) $response->getBody());
            throw new UnexpectedKeycloakResponseException(
                sprintf('Keycloak rejected the service-account client_credentials grant (HTTP %d): %s', $status, $upstream !== '' ? $upstream : '(no body)'),
                1750000017,
                $status,
                $upstream,
            );
        }

        $this->cachedToken = KeycloakAccessToken::fromTokenResponseBody(
            (string) $response->getBody(),
            time(),
            self::TOKEN_EXPIRY_SAFETY_MARGIN_SECONDS,
        );

        return $this->cachedToken->accessToken;
    }

    /**
     * Build the realm token endpoint URL.
     */
    private function tokenUrl(): string
    {
        return sprintf(
            '%s/realms/%s/protocol/openid-connect/token',
            $this->settings->get()->baseUrl,
            $this->settings->get()->realm,
        );
    }
}
