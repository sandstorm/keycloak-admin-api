<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable, ordered collection of {@see KeycloakUser} — one page of the users table. The
 * server-side total for pagination is a *separate* call ({@see \Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi::count()}),
 * not this collection's `count()`, which is only the size of this page.
 *
 * @extends KeycloakCollection<KeycloakUser>
 */
final class KeycloakUsers extends KeycloakCollection
{
    /**
     * Parse a Keycloak users list response. Non-array rows are skipped so one malformed entry never
     * loses the whole page.
     *
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $users = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $users[] = KeycloakUser::fromRawResponse($row);
            }
        }

        return new self($users);
    }
}
