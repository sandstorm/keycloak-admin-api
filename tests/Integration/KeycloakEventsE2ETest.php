<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\KeycloakEventsApiImplementation;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\KeycloakGroupsApiImplementation;

use function str_contains;

/**
 * Proves both event feeds against a real Keycloak. Login events are made deterministic by a
 * direct-grant login; admin events by performing a membership change and reading it back. Both need
 * the realm's events enabled (set in realm-import.json) and the service account's `view-events` role.
 */
#[Group('integration')]
final class KeycloakEventsE2ETest extends IntegrationTestCase
{
    #[Test]
    public function a_login_shows_up_in_the_user_event_history(): void
    {
        $events = new KeycloakEventsApiImplementation($this->transport);
        $userId = $this->seededUserId('login-user');

        $this->loginAsUser('login-user');

        $types = [];
        foreach ($events->getUserEvents($userId, 0, 50) as $event) {
            $types[] = $event->type;
        }
        self::assertContains('LOGIN', $types, 'the direct-grant login should be recorded as a LOGIN event');
    }

    #[Test]
    public function an_admin_action_on_a_user_shows_up_in_that_users_admin_history(): void
    {
        $events = new KeycloakEventsApiImplementation($this->transport);
        $groups = new KeycloakGroupsApiImplementation($this->transport);
        $userId = $this->seededUserId('login-user');
        $adminsId = $this->adminsGroupId();

        // Generate an admin event targeting this user (GROUP_MEMBERSHIP on users/{id}/groups/…).
        $groups->addUserToGroup($userId, $adminsId);

        try {
            $touchedThisUser = false;
            foreach ($events->getAdminEventsForUser($userId, 0, 50) as $event) {
                if ($event->resourcePath !== null && str_contains($event->resourcePath, $userId->value)) {
                    $touchedThisUser = true;
                    break;
                }
            }
            self::assertTrue($touchedThisUser, 'the membership change should appear in the admin history for this user');
        } finally {
            $groups->removeUserFromGroup($userId, $adminsId);
        }
    }

    private function adminsGroupId(): string
    {
        foreach ((new KeycloakGroupsApiImplementation($this->transport))->listRealmGroups('admins') as $group) {
            if ($group->name === 'admins') {
                return $group->id;
            }
        }

        self::fail('seed group "admins" is missing from the imported realm');
    }
}
