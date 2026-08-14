<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\Dto\KeycloakSessions;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The active-session slice of the Keycloak Admin port.
 *
 * Every method throws {@see KeycloakAuthenticationException} on 401/403 (the one catchable, friendly
 * case); all other failures propagate to be logged.
 */
interface KeycloakSessionsApi
{
    public function getSessions(KeycloakUserId $userId): KeycloakSessions;

    /**
     * Force sign-out of every active session for the user (`POST /users/{id}/logout`). Idempotent — a
     * user with no sessions is a no-op, not an error.
     */
    public function logoutAll(KeycloakUserId $userId): void;
}
