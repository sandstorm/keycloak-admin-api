<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUser;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUsers;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The user read/search slice of the Keycloak Admin port. The list/count pair backs a
 * server-side-paginated table: Keycloak has no local mirror, so every page is a live query, and the
 * total is a separate call because the list response carries no count.
 *
 * Every method throws {@see KeycloakAuthenticationException} on 401/403 (the one catchable, friendly
 * case); all other failures propagate as RuntimeException/UnexpectedValueException to be logged.
 */
interface KeycloakUsersApi
{
    /**
     * `search` is Keycloak's infix match over username/email/first/last name; `first`/`max` are the
     * offset/limit. There is no sort parameter — the order is Keycloak's own (username).
     */
    public function list(?string $search, int $first, int $max, ?bool $enabled): KeycloakUsers;

    public function count(?string $search, ?bool $enabled): int;

    /**
     * The full (non-brief) representation — carries custom attributes that {@see self::list()} omits.
     */
    public function getById(KeycloakUserId $id): KeycloakUser;
}
