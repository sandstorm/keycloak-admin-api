<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;

use function sprintf;
use function time;

/**
 * OAuth2 `client_credentials` token provider: the confidential service-account client authenticates
 * as a single shared identity. The token is cached in-memory for the lifetime of this instance and
 * refreshed on demand (with a safety margin before real expiry).
 *
 * Self-contained — depends only on the settings seam and an injected Guzzle client. The client must
 * never body-log: the token request carries the client secret.
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
        private readonly Client $client,
    ) {}

    public function currentBearer(): string
    {
        if ($this->cachedToken !== null && $this->cachedToken->isStillValidNow()) {
            return $this->cachedToken->accessToken;
        }

        $response = $this->client->post(
            $this->tokenUrl(),
            [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->settings->get()->clientId,
                    'client_secret' => $this->settings->get()->clientSecret,
                ],
                RequestOptions::HEADERS => ['Accept' => 'application/json'],
            ],
        );

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
