<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\SharedModel;

/**
 * The Keycloak-assigned id (UUID) of a user. Shared vocabulary across every feature that addresses a
 * single user (read, groups, credentials, sessions, events).
 */
final readonly class KeycloakUserId
{
    public function __construct(
        public string $value,
    ) {}
}
