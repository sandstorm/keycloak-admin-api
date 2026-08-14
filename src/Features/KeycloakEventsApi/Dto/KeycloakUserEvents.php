<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable collection of {@see KeycloakUserEvent} — one page of a user's event history.
 *
 * @extends KeycloakCollection<KeycloakUserEvent>
 */
final class KeycloakUserEvents extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $events = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $events[] = KeycloakUserEvent::fromRawResponse($row);
            }
        }

        return new self($events);
    }
}
