<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakSessionsApi\Dto;

use DateTimeImmutable;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakTimestamp;

use function implode;
use function is_array;
use function is_string;

/**
 * A live user session from `GET /users/{id}/sessions`. Only currently-active sessions appear here —
 * the list is empty once the user has logged out or the session expired, which is a normal state, not
 * an error.
 *
 * Parsing stays tolerant: only `id` is required.
 */
final readonly class KeycloakSession
{
    /**
     * @param  list<string>  $clients  the human-readable client names this session is bound to
     */
    private function __construct(
        public string $id,
        public ?DateTimeImmutable $start = null,
        public ?DateTimeImmutable $lastAccess = null,
        public ?string $ipAddress = null,
        public array $clients = [],
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     *
     * @throws RuntimeException when the object lacks an id
     */
    public static function fromRawResponse(array $raw): self
    {
        $id = $raw['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Keycloak session representation had no id.', 1750000034);
        }

        $ipAddress = is_string($raw['ipAddress'] ?? null) && $raw['ipAddress'] !== '' ? $raw['ipAddress'] : null;

        // Keycloak returns `clients` as { clientUuid: clientName }; keep the readable names only.
        $clients = [];
        $rawClients = $raw['clients'] ?? [];
        if (is_array($rawClients)) {
            foreach ($rawClients as $clientName) {
                if (is_string($clientName)) {
                    $clients[] = $clientName;
                }
            }
        }

        return new self(
            $id,
            KeycloakTimestamp::fromEpochMillis($raw['start'] ?? null),
            KeycloakTimestamp::fromEpochMillis($raw['lastAccess'] ?? null),
            $ipAddress,
            $clients,
        );
    }

    public function formattedStart(): string
    {
        return KeycloakTimestamp::format($this->start);
    }

    public function formattedLastAccess(): string
    {
        return KeycloakTimestamp::format($this->lastAccess);
    }

    public function clientsLabel(): string
    {
        return $this->clients === [] ? '—' : implode(', ', $this->clients);
    }
}
