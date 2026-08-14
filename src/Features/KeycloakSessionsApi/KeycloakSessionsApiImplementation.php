<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\Dto\KeycloakSessions;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function rawurlencode;

/**
 * @internal access via the {@link KeycloakSessionsApi} contract.
 */
final readonly class KeycloakSessionsApiImplementation implements KeycloakSessionsApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function getSessions(KeycloakUserId $userId): KeycloakSessions
    {
        return KeycloakSessions::fromRawResponse(
            $this->transport->getJson('users/' . rawurlencode($userId->value) . '/sessions'),
        );
    }

    public function logoutAll(KeycloakUserId $userId): void
    {
        // Empty-body POST — Keycloak expects no payload; postJson sends `{}`, which the endpoint accepts.
        $this->transport->postJson('users/' . rawurlencode($userId->value) . '/logout', []);
    }
}
