<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Sandstorm\KeycloakAdminApi\Connection\Auth\KeycloakTokenProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettings;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;

use function array_key_last;
use function json_encode;

/**
 * Builds a {@see KeycloakTransport} wired to a Guzzle mock so tests can assert both the outgoing
 * request (URL/verb/body) and the parsing of a canned response — no real Keycloak, no network. The
 * captured request history lets a test inspect exactly what the client sent.
 */
final class MockKeycloak
{
    /** @var list<array{request: RequestInterface}> */
    public array $history = [];

    /**
     * @param  list<Response>  $responses  one canned response per request the code under test makes
     */
    public function __construct(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $this->transport = new KeycloakTransport(
            new readonly class implements KeycloakSettingsProvider {
                public function get(): KeycloakSettings
                {
                    return new KeycloakSettings('https://kc.test', 'test-realm', 'client', 'secret');
                }
            },
            new Client(['handler' => $stack]),
            new HttpFactory(),
            new HttpFactory(),
            new readonly class implements KeycloakTokenProvider {
                public function currentBearer(): string
                {
                    return 'fake-token';
                }
            },
        );
    }

    public readonly KeycloakTransport $transport;

    /**
     * @param  array<int|string, mixed>  $json
     */
    public static function json(array $json, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($json));
    }

    public function lastUri(): string
    {
        return (string) $this->history[array_key_last($this->history)]['request']->getUri();
    }

    public function lastMethod(): string
    {
        return $this->history[array_key_last($this->history)]['request']->getMethod();
    }

    public function lastBody(): string
    {
        return (string) $this->history[array_key_last($this->history)]['request']->getBody();
    }

    public function lastContentType(): string
    {
        return $this->history[array_key_last($this->history)]['request']->getHeaderLine('Content-Type');
    }
}
