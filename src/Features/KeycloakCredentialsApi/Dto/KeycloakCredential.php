<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto;

use DateTimeImmutable;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakTimestamp;

use function in_array;
use function is_string;

/**
 * A single stored credential from `GET /users/{id}/credentials`. The `type` distinguishes the factor:
 * `password`, `otp`, `webauthn`, `webauthn-passwordless`, `recovery-authn-codes`.
 *
 * Parsing stays tolerant: only `type` is required. `id` is nullable because Keycloak omits it for some
 * credential entries and it is only needed to remove a credential — a null-id credential is
 * display-only and must never crash the read view.
 */
final readonly class KeycloakCredential
{
    private const SECOND_FACTOR_TYPES = ['otp', 'webauthn', 'webauthn-passwordless'];

    private function __construct(
        public ?string $id,
        public string $type,
        public ?string $userLabel = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     *
     * @throws RuntimeException when the object lacks a type
     */
    public static function fromRawResponse(array $raw): self
    {
        $id = is_string($raw['id'] ?? null) && $raw['id'] !== '' ? $raw['id'] : null;

        $type = $raw['type'] ?? null;
        if (! is_string($type) || $type === '') {
            throw new RuntimeException('Keycloak credential representation had no type.', 1750000033);
        }

        $userLabel = is_string($raw['userLabel'] ?? null) && $raw['userLabel'] !== '' ? $raw['userLabel'] : null;

        return new self($id, $type, $userLabel, KeycloakTimestamp::fromEpochMillis($raw['createdDate'] ?? null));
    }

    public function isSecondFactor(): bool
    {
        return in_array($this->type, self::SECOND_FACTOR_TYPES, true);
    }

    public function formattedCreatedAt(): string
    {
        return KeycloakTimestamp::format($this->createdAt);
    }
}
