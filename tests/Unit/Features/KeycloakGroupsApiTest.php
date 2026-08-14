<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\KeycloakGroupsApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;

final class KeycloakGroupsApiTest extends TestCase
{
    #[Test]
    public function lists_a_users_groups_and_hits_the_groups_endpoint(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            ['id' => 'g1', 'name' => 'admins', 'path' => '/admins'],
            ['id' => 'g2', 'name' => 'staff'],
        ])]);

        $groups = (new KeycloakGroupsApiImplementation($mock->transport))->getUserGroups(new KeycloakUserId('u-1'))->all();

        self::assertCount(2, $groups);
        self::assertSame('/admins', $groups[0]->path);
        self::assertNull($groups[1]->path);
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-1/groups', $mock->lastUri());
    }

    #[Test]
    public function lists_realm_groups_with_brief_representation_and_an_optional_search_filter(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([['id' => 'g1', 'name' => 'admins']])]);

        (new KeycloakGroupsApiImplementation($mock->transport))->listRealmGroups('adm');

        $uri = $mock->lastUri();
        self::assertStringContainsString('/admin/realms/test-realm/groups?', $uri);
        self::assertStringContainsString('briefRepresentation=true', $uri);
        self::assertStringContainsString('search=adm', $uri);
    }

    #[Test]
    public function adds_a_user_to_a_group_via_body_less_put_because_a_json_body_500s_keycloak(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakGroupsApiImplementation($mock->transport))->addUserToGroup(new KeycloakUserId('u-42'), 'g-9');

        self::assertSame('PUT', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42/groups/g-9', $mock->lastUri());
        self::assertSame('', $mock->lastBody());
        self::assertSame('', $mock->lastContentType());
    }

    #[Test]
    public function removes_a_user_from_a_group_via_delete(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakGroupsApiImplementation($mock->transport))->removeUserFromGroup(new KeycloakUserId('u-42'), 'g-9');

        self::assertSame('DELETE', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42/groups/g-9', $mock->lastUri());
    }
}
