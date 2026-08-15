<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\Auth\ServiceAccountTokenProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettings;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\KeycloakUsersApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function getenv;

/**
 * Base for the end-to-end suite that runs against a real Keycloak (see docker-compose.yml). These
 * tests prove the wire contract the unit tests cannot: that Keycloak 26.5.3 actually accepts every
 * URL/verb/body and returns the shape the DTOs parse.
 *
 * Opt-in: the suite skips unless `KEYCLOAK_E2E_BASE_URL` is set, so a normal `phpunit` run (and CI
 * without the service) stays fast and hermetic. Connection defaults match realm-import.json.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected KeycloakTransport $transport;

    private string $baseUrl;

    private string $realm;

    protected function setUp(): void
    {
        $baseUrl = getenv('KEYCLOAK_E2E_BASE_URL');
        if ($baseUrl === false || $baseUrl === '') {
            self::markTestSkipped('Set KEYCLOAK_E2E_BASE_URL to run the Keycloak end-to-end tests (see tests/Integration/docker-compose.yml).');
        }

        $this->baseUrl = $baseUrl;
        $this->realm = self::env('KEYCLOAK_E2E_REALM', 'test-realm');

        $connection = new KeycloakSettings(
            $this->baseUrl,
            $this->realm,
            self::env('KEYCLOAK_E2E_CLIENT_ID', 'admin-api'),
            self::env('KEYCLOAK_E2E_CLIENT_SECRET', 'e2e-secret'),
        );

        $settings = new readonly class($connection) implements KeycloakSettingsProvider {
            public function __construct(private KeycloakSettings $connection) {}

            public function get(): KeycloakSettings
            {
                return $this->connection;
            }
        };

        $client = new Client(['timeout' => 10]);
        $httpFactory = new HttpFactory();

        $this->transport = new KeycloakTransport(
            $settings,
            $client,
            $httpFactory,
            $httpFactory,
            new ServiceAccountTokenProvider($settings, $client, $httpFactory, $httpFactory),
        );
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    /**
     * Log a seeded user in via the public `e2e-login` client's direct-access-grant, so a real user
     * **session** and a **LOGIN event** exist server-side — the deterministic precondition the sessions
     * and events E2E tests read back.
     */
    protected function loginAsUser(string $username, string $password = 'changeit'): void
    {
        (new Client(['http_errors' => true, 'timeout' => 10]))->post(
            $this->baseUrl . '/realms/' . $this->realm . '/protocol/openid-connect/token',
            [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => 'e2e-login',
                    'username' => $username,
                    'password' => $password,
                    'scope' => 'openid',
                ],
            ],
        );
    }

    /**
     * Resolve a seeded user's id by exact username — the shared starting point for the per-feature
     * tests that all operate on the imported user "jane".
     */
    protected function seededUserId(string $username = 'jane'): KeycloakUserId
    {
        $users = new KeycloakUsersApiImplementation($this->transport);
        $match = $users->list($username, 0, 1, null);

        self::assertFalse($match->isEmpty(), "seed user \"$username\" is missing from the imported realm");

        return $match->all()[0]->id;
    }
}
