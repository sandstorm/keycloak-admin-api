<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable collection of {@see KeycloakSession} — a user's currently-active sessions.
 *
 * @extends KeycloakCollection<KeycloakSession>
 */
final class KeycloakSessions extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $sessions = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $sessions[] = KeycloakSession::fromRawResponse($row);
            }
        }

        return new self($sessions);
    }
}
