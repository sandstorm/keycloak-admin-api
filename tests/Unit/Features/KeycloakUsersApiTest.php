<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
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
    public function update_puts_the_full_representation_to_the_user_endpoint(): void
    {
        // getById first (full representation), then a 204 for the PUT.
        $mock = new MockKeycloak([
            MockKeycloak::json([
                'id' => 'u-42',
                'username' => 'jane',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'enabled' => true,
                'emailVerified' => false,
                'createdTimestamp' => 1700000000000,
            ]),
            new Response(204, [], ''),
        ]);

        $api = new KeycloakUsersApiImplementation($mock->transport);
        $user = $api->getById(new KeycloakUserId('u-42'));

        $api->update($user->withFirstName('Janet')->withEnabled(false)->withEmailVerified(true));

        self::assertSame('PUT', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42', $mock->lastUri());

        $body = json_decode($mock->lastBody(), true);
        self::assertIsArray($body);
        self::assertSame('Janet', $body['firstName']);
        self::assertFalse($body['enabled']);
        self::assertTrue($body['emailVerified']);
        // Unmodelled field from the fetched representation is preserved on the way back.
        self::assertSame(1700000000000, $body['createdTimestamp']);
    }

    #[Test]
    public function update_surfaces_a_403_as_the_response_exception_carrying_status_403(): void
    {
        // The FGAP denial path: a caller without manage on the target gets a real 403, never a no-op.
        $mock = new MockKeycloak([
            MockKeycloak::json(['id' => 'u-42', 'username' => 'jane']),
            new Response(403, [], '{"error":"insufficient_scope"}'),
        ]);

        $api = new KeycloakUsersApiImplementation($mock->transport);
        $user = $api->getById(new KeycloakUserId('u-42'));

        try {
            $api->update($user->withFirstName('Janet'));
            self::fail('expected an UnexpectedKeycloakResponseException');
        } catch (UnexpectedKeycloakResponseException $exception) {
            self::assertSame(403, $exception->statusCode);
        }
    }

    #[Test]
    public function a_403_surfaces_as_the_response_exception_carrying_status_403(): void
    {
        $mock = new MockKeycloak([new Response(403, [], 'Forbidden')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected an UnexpectedKeycloakResponseException');
        } catch (UnexpectedKeycloakResponseException $exception) {
            // The denied case is not a separate type — the caller (a UI) reacts on the status code.
            self::assertSame(403, $exception->statusCode);
        }
    }

    #[Test]
    public function a_401_surfaces_as_the_response_exception_carrying_status_401(): void
    {
        $mock = new MockKeycloak([new Response(401, [], 'Unauthorized')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected an UnexpectedKeycloakResponseException');
        } catch (UnexpectedKeycloakResponseException $exception) {
            self::assertSame(401, $exception->statusCode);
        }
    }

    #[Test]
    public function carries_keycloaks_upstream_error_body_on_the_exception(): void
    {
        $mock = new MockKeycloak([new Response(403, [], '{"error":"Forbidden: requires view-users"}')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected an UnexpectedKeycloakResponseException');
        } catch (UnexpectedKeycloakResponseException $exception) {
            self::assertSame(403, $exception->statusCode);
            self::assertSame('{"error":"Forbidden: requires view-users"}', $exception->responseBody);
            self::assertStringContainsString('HTTP 403', $exception->getMessage());
            self::assertStringContainsString('Forbidden: requires view-users', $exception->getMessage());
        }
    }

    #[Test]
    public function a_5xx_surfaces_as_the_response_exception_carrying_status_503(): void
    {
        $mock = new MockKeycloak([new Response(503, [], 'Service Unavailable')]);

        try {
            (new KeycloakUsersApiImplementation($mock->transport))->list(null, 0, 10, null);
            self::fail('expected an UnexpectedKeycloakResponseException');
        } catch (UnexpectedKeycloakResponseException $exception) {
            // Same type as a 403 — the status is what tells an outage (retryable) from a denial.
            self::assertSame(503, $exception->statusCode);
        }
    }
}
