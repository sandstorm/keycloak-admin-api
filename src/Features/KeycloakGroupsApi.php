<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto\KeycloakGroups;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The realm-group membership slice of the Keycloak Admin port.
 *
 * Membership mutations are exposed as **single-op** add/remove calls (not a bulk "set groups"): the
 * add/remove **diff** is computed and applied by the caller (the manage-groups UI action), so partial
 * failure stays visible per item. This interface never assumes atomicity across a diff.
 *
 * Every method throws {@see KeycloakAuthenticationException} on 401/403 (the one catchable, friendly
 * case); all other failures propagate to be logged. Both mutations are idempotent.
 */
interface KeycloakGroupsApi
{
    public function getUserGroups(KeycloakUserId $userId): KeycloakGroups;

    public function listRealmGroups(?string $search = null): KeycloakGroups;

    public function addUserToGroup(KeycloakUserId $userId, string $groupId): void;

    public function removeUserFromGroup(KeycloakUserId $userId, string $groupId): void;
}
