<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\KeycloakSessionsApiImplementation;

/**
 * Proves session read + logout-all against a real Keycloak. A direct-grant login for "login-user"
 * creates a genuine session first, so the read has something to return and logout has something to
 * revoke.
 */
#[Group('integration')]
final class KeycloakSessionsE2ETest extends IntegrationTestCase
{
    #[Test]
    public function reads_an_active_session_then_logs_it_out(): void
    {
        $sessions = new KeycloakSessionsApiImplementation($this->transport);
        $userId = $this->seededUserId('login-user');

        $this->loginAsUser('login-user');

        $active = $sessions->getSessions($userId);
        self::assertFalse($active->isEmpty(), 'a direct-grant login should have produced an active session');

        $sessions->logoutAll($userId);

        self::assertTrue($sessions->getSessions($userId)->isEmpty(), 'logoutAll should have revoked every session');
    }

    #[Test]
    public function reading_sessions_of_a_user_without_any_is_an_empty_collection_not_an_error(): void
    {
        // jane never logs in during the suite → no sessions; this is the empty (not forbidden) state.
        self::assertTrue(
            (new KeycloakSessionsApiImplementation($this->transport))->getSessions($this->seededUserId('jane'))->isEmpty(),
        );
    }
}
