<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUser;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUsers;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;
use UnexpectedValueException;

use function http_build_query;
use function is_numeric;
use function json_decode;
use function rawurlencode;
use function sprintf;

/**
 * @internal access via the {@link KeycloakUsersApi} contract.
 */
final readonly class KeycloakUsersApiImplementation implements KeycloakUsersApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function list(?string $search, int $first, int $max, ?bool $enabled): KeycloakUsers
    {
        // briefRepresentation keeps the payload small (no attributes/credentials) — the list only needs
        // identity fields. The view page fetches the full representation when a single user is opened.
        $query = self::buildQuery($search, $enabled) + [
            'first' => $first,
            'max' => $max,
            'briefRepresentation' => 'true',
        ];

        return KeycloakUsers::fromRawResponse($this->transport->getJson('users?' . http_build_query($query)));
    }

    public function count(?string $search, ?bool $enabled): int
    {
        $query = self::buildQuery($search, $enabled);
        $path = 'users/count';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        // The endpoint's contract: a bare JSON number, e.g. `42` — never an object or a string. Anything
        // else is a broken contract, not a routine failure, so it surfaces (propagates + gets logged).
        $body = (string) $this->transport->get($path)->getBody();
        $decoded = json_decode($body, true);

        if (! is_numeric($decoded)) {
            throw new UnexpectedValueException(sprintf('Keycloak GET /users/count must return a number; got: %s', $body), 1750000017);
        }

        return (int) $decoded;
    }

    public function getById(KeycloakUserId $id): KeycloakUser
    {
        // Full representation
        $raw = $this->transport->getJson('users/' . rawurlencode($id->value));

        return KeycloakUser::fromRawResponse($raw);
    }

    /**
     * Shared search/enabled query params for both list and count so the paginator total always
     * matches the listed rows.
     *
     * @return array<string, string>
     */
    private static function buildQuery(?string $search, ?bool $enabled): array
    {
        $query = [];

        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }

        if ($enabled !== null) {
            $query['enabled'] = $enabled ? 'true' : 'false';
        }

        return $query;
    }
}
