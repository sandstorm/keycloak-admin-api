<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto\KeycloakAdminEvent;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\KeycloakEventsApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;

use function json_encode;

final class KeycloakEventsApiTest extends TestCase
{
    #[Test]
    public function queries_all_user_events_without_a_type_filter_and_parses_type_error_and_details(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            ['time' => 1700000000000, 'type' => 'LOGIN', 'ipAddress' => '10.0.0.1', 'clientId' => 'account', 'details' => ['username' => 'jane', 'auth_method' => 'openid-connect']],
            ['time' => 1700000100000, 'type' => 'LOGIN_ERROR', 'error' => 'invalid_user_credentials'],
        ])]);

        $events = (new KeycloakEventsApiImplementation($mock->transport))->getUserEvents(new KeycloakUserId('u-42'), 0, 26)->all();

        self::assertCount(2, $events);
        self::assertSame('LOGIN', $events[0]->type);
        self::assertSame(['username' => 'jane', 'auth_method' => 'openid-connect'], $events[0]->details);
        self::assertSame('LOGIN_ERROR', $events[1]->type);
        self::assertSame('invalid_user_credentials', $events[1]->error);

        $uri = $mock->lastUri();
        self::assertStringContainsString('/admin/realms/test-realm/events?', $uri);
        self::assertStringNotContainsString('type=', $uri);
        self::assertStringContainsString('user=u-42', $uri);
        self::assertStringContainsString('max=26', $uri);
    }

    #[Test]
    public function queries_admin_events_by_resource_path_prefix_and_flattens_the_acting_admin(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([[
            'time' => 1700000000000,
            'operationType' => 'UPDATE',
            'resourcePath' => 'users/u-42',
            'authDetails' => ['userId' => 'admin-1'],
        ]])]);

        $events = (new KeycloakEventsApiImplementation($mock->transport))->getAdminEventsForUser(new KeycloakUserId('u-42'), 0, 26)->all();

        self::assertCount(1, $events);
        self::assertSame('UPDATE', $events[0]->operationType);
        self::assertSame('admin-1', $events[0]->authUser);

        // Trailing wildcard → prefix match, so sub-resource actions (role-mappings, reset-password, …) count.
        self::assertStringContainsString('resourcePath=users%2Fu-42%2A', $mock->lastUri());
    }

    #[Test]
    public function an_empty_event_page_is_an_empty_collection_not_an_error(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([])]);

        $events = (new KeycloakEventsApiImplementation($mock->transport))->getUserEvents(new KeycloakUserId('u-1'), 0, 20);

        self::assertTrue($events->isEmpty());
    }

    #[Test]
    public function a_403_on_a_detail_slice_is_the_catchable_forbidden_state(): void
    {
        $mock = new MockKeycloak([new Response(403, [], 'Forbidden')]);

        $this->expectException(KeycloakAuthenticationException::class);

        (new KeycloakEventsApiImplementation($mock->transport))->getUserEvents(new KeycloakUserId('u-1'), 0, 20);
    }

    #[Test]
    public function labels_an_admin_resource_from_the_logged_representation_not_the_uuid_path(): void
    {
        $groupMembership = KeycloakAdminEvent::fromRawResponse([
            'operationType' => 'CREATE',
            'resourceType' => 'GROUP_MEMBERSHIP',
            'resourcePath' => 'users/u-1/groups/1d5f7121-4124-423c-b519-99dd5eec8c23',
            'representation' => json_encode(['id' => '1d5f7121', 'name' => 'Automatiseerders', 'path' => '/Automatiseerders']),
        ]);
        self::assertSame('/Automatiseerders', $groupMembership->resourceLabel());

        $userCreate = KeycloakAdminEvent::fromRawResponse([
            'operationType' => 'CREATE',
            'resourceType' => 'USER',
            'resourcePath' => 'users/u-1',
            'representation' => json_encode(['username' => 'jane', 'firstName' => 'Jane', 'lastName' => 'Doe', 'email' => 'jane@example.test']),
        ]);
        self::assertSame('jane', $userCreate->resourceLabel());

        $delete = KeycloakAdminEvent::fromRawResponse([
            'operationType' => 'DELETE',
            'resourcePath' => 'users/u-1/groups/1d5f7121',
        ]);
        self::assertSame('users/u-1/groups/1d5f7121', $delete->resourceLabel());
    }
}
