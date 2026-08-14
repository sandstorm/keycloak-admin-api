<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\KeycloakSessionsApiImplementation;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;

final class KeycloakSessionsApiTest extends TestCase
{
    #[Test]
    public function flattens_the_session_clients_map_to_readable_names(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([[
            'id' => 's1',
            'ipAddress' => '10.0.0.1',
            'start' => 1700000000000,
            'lastAccess' => 1700000100000,
            'clients' => ['uuid-a' => 'account', 'uuid-b' => 'admin-cli'],
        ]])]);

        $sessions = (new KeycloakSessionsApiImplementation($mock->transport))->getSessions(new KeycloakUserId('u-1'))->all();

        self::assertCount(1, $sessions);
        self::assertSame('10.0.0.1', $sessions[0]->ipAddress);
        self::assertSame(['account', 'admin-cli'], $sessions[0]->clients);
        self::assertStringEndsWith('/users/u-1/sessions', $mock->lastUri());
    }

    #[Test]
    public function logs_out_all_sessions_via_post_to_the_logout_endpoint(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        (new KeycloakSessionsApiImplementation($mock->transport))->logoutAll(new KeycloakUserId('u-42'));

        self::assertSame('POST', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-42/logout', $mock->lastUri());
    }

    #[Test]
    public function maps_a_403_from_logout_all_to_the_catchable_auth_exception(): void
    {
        $mock = new MockKeycloak([new Response(403, [], 'Forbidden')]);

        $this->expectException(KeycloakAuthenticationException::class);

        (new KeycloakSessionsApiImplementation($mock->transport))->logoutAll(new KeycloakUserId('u-1'));
    }
}
