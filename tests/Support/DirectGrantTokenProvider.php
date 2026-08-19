<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Support;

use GuzzleHttp\Client;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Connection\Auth\KeycloakTokenProvider;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Test-only token provider that obtains a **real user** access token via the realm's public
 * direct-access-grant client (`e2e-login`) and hands it to the transport verbatim — so every Admin-API
 * call is made *as that user*, and Keycloak (not our code) decides what the caller may do.
 *
 * This is the library-layer stand-in for act-as-user auth: it proves the wire contract honours a
 * per-user identity (and therefore FGAP) purely at the API layer, with **no** framework, plugin, or
 * heloufir coupling. The production act-as-user provider ({@see \Sandstorm\FilamentKeycloakAdmin\Auth\FilamentSsoTokenProvider})
 * lives in the consuming plugin; this fake exists only so the lib's own E2E suite can exercise the
 * same path.
 *
 * The token is fetched once and cached for this instance's lifetime — E2E tests are short-lived and a
 * direct-grant access token easily outlives a single test.
 */
final class DirectGrantTokenProvider implements KeycloakTokenProvider
{
    private ?string $accessToken = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $realm,
        private readonly string $username,
        private readonly string $password = 'changeit',
        private readonly string $clientId = 'e2e-login',
    ) {}

    public function currentBearer(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = (new Client(['http_errors' => true, 'timeout' => 10]))->post(
            $this->baseUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token',
            [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => $this->clientId,
                    'username' => $this->username,
                    'password' => $this->password,
                    'scope' => 'openid',
                ],
            ],
        );

        $token = json_decode((string) $response->getBody(), true);
        $accessToken = is_array($token) ? ($token['access_token'] ?? null) : null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException("Direct-grant for \"$this->username\" in \"$this->realm\" returned no access_token.", 1755600001);
        }

        return $this->accessToken = $accessToken;
    }
}
