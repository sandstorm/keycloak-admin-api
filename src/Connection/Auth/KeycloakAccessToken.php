<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection\Auth;

use const JSON_THROW_ON_ERROR;

use function assert;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function time;

/**
 * A bearer token obtained via the OAuth2 client_credentials grant against Keycloak, together with the
 * absolute Unix timestamp at which it should no longer be used.
 *
 * Owns the decoding and validation of the raw token endpoint response body.
 */
final readonly class KeycloakAccessToken
{
    private function __construct(
        public string $accessToken,
        public int $expiresAt,
    ) {}

    /**
     * Decode and validate a raw Keycloak token endpoint response body into a token.
     *
     * Lets JsonException propagate on malformed JSON.
     *
     * @param  int  $now  current Unix timestamp, used as the basis for the expiry
     * @param  int  $safetyMarginSeconds  refresh this many seconds before the real expiry to
     *                                    avoid edge-of-expiry failures on a slow round-trip
     *
     * @throws \JsonException
     */
    public static function fromTokenResponseBody(string $body, int $now, int $safetyMarginSeconds): self
    {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($payload), 'Keycloak token response was not a JSON object.');

        $accessToken = $payload['access_token'] ?? null;
        assert(is_string($accessToken) && $accessToken !== '', 'Keycloak token response did not contain an access_token.');

        $expiresIn = $payload['expires_in'] ?? null;
        $lifetime = is_int($expiresIn) ? $expiresIn : 60;

        return new self($accessToken, $now + $lifetime - $safetyMarginSeconds);
    }

    /**
     * Whether the token is still safe to use right now.
     */
    public function isStillValidNow(): bool
    {
        return time() < $this->expiresAt;
    }
}
