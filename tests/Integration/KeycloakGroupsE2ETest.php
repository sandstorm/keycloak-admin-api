<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto\KeycloakGroup;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\KeycloakGroupsApiImplementation;

/**
 * Proves group reads and the membership write verbs (body-less PUT to add, DELETE to remove) against
 * a real Keycloak. Seeded groups: /staff (jane is a member), /admins.
 */
#[Group('integration')]
final class KeycloakGroupsE2ETest extends IntegrationTestCase
{
    #[Test]
    public function reads_the_seeded_membership_and_the_realm_group_list(): void
    {
        $groups = new KeycloakGroupsApiImplementation($this->transport);

        $membership = $this->pathsOf($groups->getUserGroups($this->seededUserId('jane')));
        self::assertContains('/staff', $membership);

        $realm = $this->pathsOf($groups->listRealmGroups());
        self::assertContains('/staff', $realm);
        self::assertContains('/admins', $realm);
    }

    #[Test]
    public function adds_and_removes_a_membership_and_the_change_is_visible_server_side(): void
    {
        $groups = new KeycloakGroupsApiImplementation($this->transport);
        $janeId = $this->seededUserId('jane');
        $adminsId = $this->groupIdByName('admins');

        // Not a member to start with (only seeded into /staff).
        self::assertNotContains('/admins', $this->pathsOf($groups->getUserGroups($janeId)));

        $groups->addUserToGroup($janeId, $adminsId);
        self::assertContains('/admins', $this->pathsOf($groups->getUserGroups($janeId)));

        $groups->removeUserFromGroup($janeId, $adminsId);
        self::assertNotContains('/admins', $this->pathsOf($groups->getUserGroups($janeId)));
    }

    private function groupIdByName(string $name): string
    {
        foreach ((new KeycloakGroupsApiImplementation($this->transport))->listRealmGroups($name) as $group) {
            if ($group->name === $name) {
                return $group->id;
            }
        }

        self::fail("seed group \"$name\" is missing from the imported realm");
    }

    /**
     * @param  iterable<KeycloakGroup>  $groups
     *
     * @return list<string>
     */
    private function pathsOf(iterable $groups): array
    {
        $paths = [];
        foreach ($groups as $group) {
            $paths[] = $group->path ?? $group->name;
        }

        return $paths;
    }
}
