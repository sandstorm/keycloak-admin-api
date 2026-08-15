<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\Dto\KeycloakSessions;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The active-session slice of the Keycloak Admin port.
 *
 * Every method throws {@see UnexpectedKeycloakResponseException} on any non-2xx or transport failure;
 * read its ->statusCode (401/403 = denied, the one a UI turns into a friendly notice).
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
