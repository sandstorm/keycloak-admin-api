<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto\KeycloakGroups;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function http_build_query;
use function rawurlencode;

/**
 * @internal access via the {@link KeycloakGroupsApi} contract.
 */
final readonly class KeycloakGroupsApiImplementation implements KeycloakGroupsApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function getUserGroups(KeycloakUserId $userId): KeycloakGroups
    {
        return KeycloakGroups::fromRawResponse(
            $this->transport->getJson('users/' . rawurlencode($userId->value) . '/groups'),
        );
    }

    public function listRealmGroups(?string $search = null): KeycloakGroups
    {
        $query = ['briefRepresentation' => 'true'];
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }

        return KeycloakGroups::fromRawResponse(
            $this->transport->getJson('groups?' . http_build_query($query)),
        );
    }

    public function addUserToGroup(KeycloakUserId $userId, string $groupId): void
    {
        // Body-LESS PUT — Keycloak takes the group purely from the URL. A JSON body (even `{}`) makes the
        // endpoint 500 ("unknown_error"), so we send no payload / no Content-Type at all.
        $this->transport->put('users/' . rawurlencode($userId->value) . '/groups/' . rawurlencode($groupId));
    }

    public function removeUserFromGroup(KeycloakUserId $userId, string $groupId): void
    {
        $this->transport->delete('users/' . rawurlencode($userId->value) . '/groups/' . rawurlencode($groupId));
    }
}
