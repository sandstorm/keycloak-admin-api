<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\KeycloakUsersApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;
use UnexpectedValueException;

final class KeycloakUsersApiTest extends TestCase
{
    #[Test]
    public function maps_page_offset_limit_and_search_enabled_into_the_query_and_parses_rows(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            ['id' => 'u1', 'username' => 'alice', 'email' => 'alice@example.test', 'enabled' => true],
            ['id' => 'u2', 'username' => 'bob', 'enabled' => false],
        ])]);

        $users = (new KeycloakUsersApiImplementation($mock->transport))->list('ali', 20, 10, true);

        self::assertCount(2, $users);
        $list = $users->all();
        self::assertSame('alice', $list[0]->username);
        self::assertFalse($list[1]->enabled);

        $uri = $mock->lastUri();
        self::assertStringContainsString('/admin/realms/test-realm/users?', $uri);
        self::assertStringContainsString('search=ali', $uri);
        self::assertStringContainsString('first=20', $uri);
        self::assertStringContainsString('max=10', $uri);
        self::assertStringContainsString('enabled=true', $uri);
        self::assertStringContainsString('briefRepresentation=true', $uri);
    }

    #[Test]
    public function omits_search_and_enabled_from_the_query_when_not_given(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([])]);

        (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 25, null);

        $uri = $mock->lastUri();
        self::assertStringNotContainsString('search=', $uri);
        self::assertStringNotContainsString('enabled=', $uri);
        self::assertStringContainsString('first=0', $uri);
        self::assertStringContainsString('max=25', $uri);
    }

    #[Test]
    public function parses_the_bare_integer_body_from_users_count(): void
    {
        $mock = new MockKeycloak([new Response(200, [], '42')]);

        $count = (new KeycloakUsersApiImplementation($mock->transport))->count('ali', true);

        self::assertSame(42, $count);
        self::assertStringContainsString('/users/count?', $mock->lastUri());
        self::assertStringContainsString('search=ali', $mock->lastUri());
    }

    #[Test]
    public function requests_users_count_without_a_query_string_when_unfiltered(): void
    {
        $mock = new MockKeycloak([new Response(200, [], '7')]);

        self::assertSame(7, (new KeycloakUsersApiImplementation($mock->transport))->count(null, null));
        self::assertStringEndsWith('/users/count', $mock->lastUri());
    }

    #[Test]
    public function rejects_a_non_numeric_count_body_as_a_broken_contract(): void
    {
        $mock = new MockKeycloak([new Response(200, [], '"not-a-number"')]);

        $this->expectException(UnexpectedValueException::class);

        (new KeycloakUsersApiImplementation($mock->transport))->count(null, null);
    }

    #[Test]
    public function fetches_a_single_user_by_id_as_a_full_non_brief_representation(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            'id' => 'u-42',
            'username' => 'alice',
            'firstName' => 'Alice',
            'lastName' => 'Doe',
            'emailVerified' => true,
            'attributes' => ['fm_id_deelnemer' => ['123']],
        ])]);

        $user = (new KeycloakUsersApiImplementation($mock->transport))->getById(new KeycloakUserId('u-42'));

        self::assertSame('u-42', $user->id->value);
        self::assertSame('Alice Doe', $user->fullName());
        self::assertSame('123', $user->firstAttributeValue('fm_id_deelnemer'));
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42', $mock->lastUri());
        self::assertStringNotContainsString('briefRepresentation', $mock->lastUri());
    }

    #[Test]
    public function a_403_becomes_a_catchable_authentication_exception(): void
    {
        $mock = new MockKeycloak([new Response(403, [], 'Forbidden')]);

        $this->expectException(KeycloakAuthenticationException::class);

        (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
    }

    #[Test]
    public function a_401_becomes_a_catchable_authentication_exception(): void
    {
        $mock = new MockKeycloak([new Response(401, [], 'Unauthorized')]);

        $this->expectException(KeycloakAuthenticationException::class);

        (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
    }

    #[Test]
    public function carries_keycloaks_upstream_error_body_in_the_exception_message(): void
    {
        $mock = new MockKeycloak([new Response(403, [], '{"error":"Forbidden: requires view-users"}')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected a KeycloakAuthenticationException');
        } catch (KeycloakAuthenticationException $exception) {
            self::assertStringContainsString('HTTP 403', $exception->getMessage());
            self::assertStringContainsString('Forbidden: requires view-users', $exception->getMessage());
        }
    }

    #[Test]
    public function a_5xx_propagates_as_a_plain_runtime_exception_not_the_catchable_auth_case(): void
    {
        $mock = new MockKeycloak([new Response(503, [], 'Service Unavailable')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected an exception');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(KeycloakAuthenticationException::class, $exception);
        }
    }
}
