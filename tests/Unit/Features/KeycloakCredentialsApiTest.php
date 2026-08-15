<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\KeycloakCredentialsApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;

final class KeycloakCredentialsApiTest extends TestCase
{
    #[Test]
    public function parses_credentials_flags_second_factors_and_reads_created_date(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            ['id' => 'c1', 'type' => 'password'],
            ['id' => 'c2', 'type' => 'otp', 'userLabel' => 'Authenticator', 'createdDate' => 1700000000000],
        ])]);

        $credentials = (new KeycloakCredentialsApiImplementation($mock->transport))->get(new KeycloakUserId('u-1'))->all();

        self::assertCount(2, $credentials);
        self::assertFalse($credentials[0]->isSecondFactor());
        self::assertTrue($credentials[1]->isSecondFactor());
        self::assertSame('Authenticator', $credentials[1]->userLabel);
        self::assertSame(1700000000, $credentials[1]->createdAt?->getTimestamp());
        self::assertStringEndsWith('/users/u-1/credentials', $mock->lastUri());
    }

    #[Test]
    public function tolerates_a_credential_without_an_id_so_the_view_never_crashes(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([['type' => 'otp', 'userLabel' => 'Authenticator']])]);

        $credentials = (new KeycloakCredentialsApiImplementation($mock->transport))->get(new KeycloakUserId('u-1'))->all();

        self::assertCount(1, $credentials);
        self::assertNull($credentials[0]->id);
        self::assertTrue($credentials[0]->isSecondFactor());
    }

    #[Test]
    public function sends_execute_actions_email_as_a_json_array_body_not_an_object(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakCredentialsApiImplementation($mock->transport))
            ->executeActionsEmail(new KeycloakUserId('u-42'), ['UPDATE_PASSWORD'], 43200, 'account', 'https://app.test/done');

        self::assertSame('PUT', $mock->lastMethod());
        // The body MUST be a bare JSON array — an object-cast (`{"0":"UPDATE_PASSWORD"}`) is the bug guarded against.
        self::assertSame('["UPDATE_PASSWORD"]', $mock->lastBody());

        $uri = $mock->lastUri();
        self::assertStringContainsString('/users/u-42/execute-actions-email?', $uri);
        self::assertStringContainsString('lifespan=43200', $uri);
        self::assertStringContainsString('client_id=account', $uri);
        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fapp.test%2Fdone', $uri);
    }

    #[Test]
    public function omits_unset_execute_actions_email_query_params(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakCredentialsApiImplementation($mock->transport))
            ->executeActionsEmail(new KeycloakUserId('u-1'), ['UPDATE_PASSWORD']);

        self::assertStringEndsWith('/users/u-1/execute-actions-email', $mock->lastUri());
        self::assertStringNotContainsString('?', $mock->lastUri());
    }

    #[Test]
    public function removes_a_credential_via_delete_to_the_credential_endpoint(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakCredentialsApiImplementation($mock->transport))->delete(new KeycloakUserId('u-42'), 'cred-7');

        self::assertSame('DELETE', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42/credentials/cred-7', $mock->lastUri());
    }

    #[Test]
    public function maps_a_403_from_execute_actions_email_to_the_catchable_auth_exception(): void
    {
        $mock = new MockKeycloak([new Response(403, [], 'Forbidden')]);

        $this->expectException(UnexpectedKeycloakResponseException::class);

        (new KeycloakCredentialsApiImplementation($mock->transport))
            ->executeActionsEmail(new KeycloakUserId('u-1'), ['UPDATE_PASSWORD']);
    }
}
