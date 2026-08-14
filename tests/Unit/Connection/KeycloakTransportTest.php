<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Connection;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Connection\Auth\KeycloakTokenProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettings;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;
use UnexpectedValueException;

final class KeycloakTransportTest extends TestCase
{
    #[Test]
    public function delete_reaches_the_exact_admin_url_with_the_delete_verb(): void
    {
        $mock = new MockKeycloak([new Response(204)]);

        $mock->transport->delete('users/u-9/credentials/cred-1');

        self::assertSame('DELETE', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/u-9/credentials/cred-1', $mock->lastUri());
    }

    #[Test]
    public function getJson_rejects_a_non_array_body_as_a_broken_contract(): void
    {
        $mock = new MockKeycloak([new Response(200, [], '"a string, not an array"')]);

        $this->expectException(UnexpectedValueException::class);

        $mock->transport->getJson('users');
    }

    #[Test]
    public function a_token_provision_failure_propagates_and_is_not_mis_wrapped_as_a_request_failure(): void
    {
        // The bearer is resolved BEFORE the Guzzle try-block, so a broken token provider (bad secret,
        // missing SSO session) surfaces as its own error rather than a request-level auth/unavailable one.
        $transport = new KeycloakTransport(
            new readonly class implements KeycloakSettingsProvider {
                public function get(): KeycloakSettings
                {
                    return new KeycloakSettings('https://kc.test', 'test-realm', 'client', 'secret');
                }
            },
            new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], '[]')]))]),
            new readonly class implements KeycloakTokenProvider {
                public function currentBearer(): string
                {
                    throw new RuntimeException('no service-account secret configured');
                }
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no service-account secret configured');

        $transport->get('users');
    }
}
