<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto\KeycloakGroups;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The realm-group membership slice of the Keycloak Admin port.
 *
 * Membership mutations are exposed as **single-op** add/remove calls (not a bulk "set groups"): the
 * add/remove **diff** is computed and applied by the caller (the manage-groups UI action), so partial
 * failure stays visible per item. This interface never assumes atomicity across a diff.
 *
 * Every method throws {@see UnexpectedKeycloakResponseException} on any non-2xx or transport failure;
 * read its ->statusCode (401/403 = denied, the one a UI turns into a friendly notice). Both mutations
 * are idempotent.
 */
interface KeycloakGroupsApi
{
    public function getUserGroups(KeycloakUserId $userId): KeycloakGroups;

    public function listRealmGroups(?string $search = null): KeycloakGroups;

    public function addUserToGroup(KeycloakUserId $userId, string $groupId): void;

    public function removeUserFromGroup(KeycloakUserId $userId, string $groupId): void;
}
