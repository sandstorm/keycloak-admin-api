<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable collection of {@see KeycloakAdminEvent} — one page of admin actions targeting a user.
 *
 * @extends KeycloakCollection<KeycloakAdminEvent>
 */
final class KeycloakAdminEvents extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $events = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $events[] = KeycloakAdminEvent::fromRawResponse($row);
            }
        }

        return new self($events);
    }
}
