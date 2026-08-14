<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\KeycloakUsersApiImplementation;

/**
 * Proves the user read path against a real Keycloak: the service-account client_credentials grant
 * works, the roles suffice, and live 26.5.3 payloads parse into the DTOs. Seeded by realm-import.json
 * (user "jane" in group "/staff").
 */
#[Group('integration')]
final class KeycloakUsersE2ETest extends IntegrationTestCase
{
    #[Test]
    public function lists_and_counts_the_seeded_user(): void
    {
        $users = new KeycloakUsersApiImplementation($this->transport);

        $found = $users->list('jane', 0, 10, null);
        self::assertFalse($found->isEmpty());
        self::assertGreaterThanOrEqual(1, $users->count('jane', null));

        $jane = $found->all()[0];
        self::assertSame('jane', $jane->username);
        self::assertSame('Jane Doe', $jane->fullName());
    }

    #[Test]
    public function reads_a_single_user_as_a_full_representation(): void
    {
        $users = new KeycloakUsersApiImplementation($this->transport);

        $jane = $users->getById($this->seededUserId('jane'));

        self::assertSame('jane', $jane->username);
        self::assertSame('jane@example.test', $jane->email);
        self::assertTrue($jane->emailVerified);
    }

    #[Test]
    public function the_enabled_filter_is_honoured_by_the_server(): void
    {
        $users = new KeycloakUsersApiImplementation($this->transport);

        // jane is enabled → present when filtering enabled=true, absent when filtering enabled=false.
        self::assertGreaterThanOrEqual(1, $users->count('jane', true));
        self::assertSame(0, $users->count('jane', false));
    }
}
