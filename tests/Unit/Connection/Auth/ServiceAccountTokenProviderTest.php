<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Connection\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Sandstorm\KeycloakAdminApi\Connection\Auth\ServiceAccountTokenProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettings;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;

use function json_encode;

final class ServiceAccountTokenProviderTest extends TestCase
{
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];

    #[Test]
    public function caches_a_valid_token_and_hits_the_token_endpoint_only_once(): void
    {
        $provider = $this->providerReturning([
            new Response(200, [], (string) json_encode(['access_token' => 'tok-1', 'expires_in' => 300])),
            new Response(200, [], (string) json_encode(['access_token' => 'tok-2', 'expires_in' => 300])),
        ]);

        self::assertSame('tok-1', $provider->currentBearer());
        self::assertSame('tok-1', $provider->currentBearer());

        // Second call served from cache — no second token request, and the token endpoint was hit.
        self::assertCount(1, $this->history);
        self::assertStringEndsWith('/realms/test-realm/protocol/openid-connect/token', (string) $this->history[0]['request']->getUri());
    }

    #[Test]
    public function refreshes_the_token_once_it_is_within_the_expiry_safety_margin(): void
    {
        // expires_in below the 30s safety margin → the token is never considered valid → every call refreshes.
        $provider = $this->providerReturning([
            new Response(200, [], (string) json_encode(['access_token' => 'tok-1', 'expires_in' => 5])),
            new Response(200, [], (string) json_encode(['access_token' => 'tok-2', 'expires_in' => 5])),
        ]);

        self::assertSame('tok-1', $provider->currentBearer());
        self::assertSame('tok-2', $provider->currentBearer());
        self::assertCount(2, $this->history);
    }

    /**
     * @param  list<Response>  $responses
     */
    private function providerReturning(array $responses): ServiceAccountTokenProvider
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new ServiceAccountTokenProvider(
            new readonly class implements KeycloakSettingsProvider {
                public function get(): KeycloakSettings
                {
                    return new KeycloakSettings('https://kc.test', 'test-realm', 'client', 'secret');
                }
            },
            new Client(['handler' => $stack]),
            new HttpFactory(),
            new HttpFactory(),
        );
    }
}
