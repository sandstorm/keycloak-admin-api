<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto;

use DateTimeImmutable;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakTimestamp;

use function is_array;
use function is_string;

/**
 * One user event from `GET /events?user={id}` (LOGIN, LOGIN_ERROR, UPDATE_PASSWORD, …). This is
 * history that survives logout, gated on the realm storing login events and its retention window —
 * never present it as an authoritative "last login".
 *
 * Every field is optional: Keycloak events are sparse and the parser must never throw on a thin record.
 */
final readonly class KeycloakUserEvent
{
    /**
     * @param  array<string, string>  $details  the event's `details` map (e.g. username, auth_method), stringified
     */
    private function __construct(
        public ?DateTimeImmutable $at = null,
        public ?string $type = null,
        public ?string $clientId = null,
        public ?string $ipAddress = null,
        public ?string $error = null,
        public array $details = [],
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     */
    public static function fromRawResponse(array $raw): self
    {
        $details = [];
        $rawDetails = $raw['details'] ?? [];
        if (is_array($rawDetails)) {
            foreach ($rawDetails as $key => $value) {
                if (is_string($key)) {
                    $details[$key] = (string) $value;
                }
            }
        }

        return new self(
            KeycloakTimestamp::fromEpochMillis($raw['time'] ?? null),
            is_string($raw['type'] ?? null) && $raw['type'] !== '' ? $raw['type'] : null,
            is_string($raw['clientId'] ?? null) && $raw['clientId'] !== '' ? $raw['clientId'] : null,
            is_string($raw['ipAddress'] ?? null) && $raw['ipAddress'] !== '' ? $raw['ipAddress'] : null,
            is_string($raw['error'] ?? null) && $raw['error'] !== '' ? $raw['error'] : null,
            $details,
        );
    }

    /**
     * The event type with any error appended in brackets, e.g. `LOGIN_ERROR (invalid_user_credentials)`.
     */
    public function label(): string
    {
        $label = $this->type ?? 'UNKNOWN';
        if ($this->error !== null) {
            $label .= ' (' . $this->error . ')';
        }

        return $label;
    }

    public function formattedTime(): string
    {
        return KeycloakTimestamp::format($this->at);
    }
}
