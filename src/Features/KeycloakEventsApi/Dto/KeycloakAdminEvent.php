<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto;

use DateTimeImmutable;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakTimestamp;

use function is_array;
use function is_string;
use function json_decode;
use function trim;

/**
 * One admin event from `GET /admin-events?resourcePath=users/{id}*` — an administrative action
 * performed ON this user (who changed what). Gated on the realm storing admin events + retention.
 *
 * Every field is optional. The `auth*` fields are flattened from the event's `authDetails` — the
 * acting admin's id/client/ip — so the UI can show attribution (a reason to prefer `sso` mode).
 */
final readonly class KeycloakAdminEvent
{
    private function __construct(
        public ?DateTimeImmutable $at = null,
        public ?string $operationType = null,
        public ?string $resourceType = null,
        public ?string $resourcePath = null,
        public ?string $authUser = null,
        public ?string $authClient = null,
        public ?string $authIpAddress = null,
        public ?string $error = null,
        public ?string $representation = null,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     */
    public static function fromRawResponse(array $raw): self
    {
        $authDetails = is_array($raw['authDetails'] ?? null) ? $raw['authDetails'] : [];

        return new self(
            KeycloakTimestamp::fromEpochMillis($raw['time'] ?? null),
            self::stringOrNull($raw['operationType'] ?? null),
            self::stringOrNull($raw['resourceType'] ?? null),
            self::stringOrNull($raw['resourcePath'] ?? null),
            self::stringOrNull($authDetails['userId'] ?? null),
            self::stringOrNull($authDetails['clientId'] ?? null),
            self::stringOrNull($authDetails['ipAddress'] ?? null),
            self::stringOrNull($raw['error'] ?? null),
            self::stringOrNull($raw['representation'] ?? null),
        );
    }

    public function formattedTime(): string
    {
        return KeycloakTimestamp::format($this->at);
    }

    /**
     * A human-readable label for the affected resource, taken from the representation Keycloak already
     * logs (a group-membership event carries `{"name":…,"path":…}`) — so no UUID-to-name lookup or path
     * string-surgery is needed. Falls back to the raw `resourcePath` when there is no usable
     * representation (e.g. deletes).
     */
    public function resourceLabel(): ?string
    {
        return $this->representationName() ?? $this->resourcePath;
    }

    /**
     * The affected resource's own display name, tried most-specific-first because different resource
     * types name themselves differently: a group carries `path`/`name`, a user `username`/name/`email`.
     */
    private function representationName(): ?string
    {
        if ($this->representation === null) {
            return null;
        }

        $decoded = json_decode($this->representation, true);
        if (! is_array($decoded)) {
            return null;
        }

        $fullName = trim(
            (self::stringOrNull($decoded['firstName'] ?? null) ?? '') . ' ' . (self::stringOrNull($decoded['lastName'] ?? null) ?? '')
        );

        return self::stringOrNull($decoded['path'] ?? null)
            ?? self::stringOrNull($decoded['name'] ?? null)
            ?? self::stringOrNull($decoded['username'] ?? null)
            ?? ($fullName !== '' ? $fullName : null)
            ?? self::stringOrNull($decoded['email'] ?? null);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
