<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable, ordered collection of {@see KeycloakGroup} — a user's memberships or the realm's
 * group picker source.
 *
 * @extends KeycloakCollection<KeycloakGroup>
 */
final class KeycloakGroups extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $groups = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $groups[] = KeycloakGroup::fromRawResponse($row);
            }
        }

        return new self($groups);
    }
}
