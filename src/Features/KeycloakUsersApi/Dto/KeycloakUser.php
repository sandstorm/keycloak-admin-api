<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto;

use RuntimeException;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function array_filter;
use function implode;
use function is_array;
use function is_bool;
use function is_string;

/**
 * A user as returned by the Keycloak Admin API. Carries the identity fields the admin UI lists
 * (username, email, names, enabled, email-verified) plus the raw custom `attributes`.
 *
 * Parsing stays tolerant: only `id` and `username` are required, everything else is nullable and
 * defaulted so a brief or partial representation never throws.
 */
final readonly class KeycloakUser
{
    /**
     * @param  array<string, list<string>>  $attributes
     * @param  list<string>  $requiredActions  pending actions the user must complete at next login (e.g. CONFIGURE_TOTP)
     */
    private function __construct(
        public KeycloakUserId $id,
        public string $username,
        public array $attributes,
        public ?string $email = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public bool $enabled = true,
        public bool $emailVerified = false,
        public array $requiredActions = [],
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     *
     * @throws RuntimeException when the object lacks an id or username
     */
    public static function fromRawResponse(array $raw): self
    {
        $id = $raw['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Keycloak user representation had no id.', 1750000012);
        }

        $username = $raw['username'] ?? null;
        if (! is_string($username)) {
            throw new RuntimeException('Keycloak user representation had no username.', 1750000013);
        }

        $email = is_string($raw['email'] ?? null) ? $raw['email'] : null;
        $firstName = is_string($raw['firstName'] ?? null) ? $raw['firstName'] : null;
        $lastName = is_string($raw['lastName'] ?? null) ? $raw['lastName'] : null;
        // Keycloak omits `enabled`/`emailVerified` in brief representations; default enabled=true so a
        // partial row is not falsely shown as disabled, emailVerified=false (the safe/unverified default).
        $enabled = is_bool($raw['enabled'] ?? null) ? $raw['enabled'] : true;
        $emailVerified = is_bool($raw['emailVerified'] ?? null) ? $raw['emailVerified'] : false;

        // Pending required actions (e.g. CONFIGURE_TOTP → "2FA required, not yet set"). Absent in brief
        // representations; normalize to a clean list<string>.
        $requiredActions = [];
        $rawRequiredActions = $raw['requiredActions'] ?? [];
        if (is_array($rawRequiredActions)) {
            foreach ($rawRequiredActions as $action) {
                if (is_string($action) && $action !== '') {
                    $requiredActions[] = $action;
                }
            }
        }

        // Keycloak returns attributes as { key: [value, ...] } — every attribute is a list.
        // Normalize each value to a string and re-index the list (so the result is a clean
        // list<string> regardless of how Keycloak typed/keyed the raw JSON).
        $rawAttributes = $raw['attributes'] ?? [];
        $attributes = [];
        if (is_array($rawAttributes)) {
            foreach ($rawAttributes as $key => $values) {
                if (is_string($key) && is_array($values)) {
                    $stringValues = [];
                    foreach ($values as $value) {
                        $stringValues[] = (string) $value;
                    }
                    $attributes[$key] = $stringValues;
                }
            }
        }

        return new self(
            new KeycloakUserId($id),
            $username,
            $attributes,
            $email,
            $firstName,
            $lastName,
            $enabled,
            $emailVerified,
            $requiredActions,
        );
    }

    public function fullName(): ?string
    {
        $parts = array_filter([$this->firstName, $this->lastName], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }

    public function firstAttributeValue(string $key): ?string
    {
        return $this->attributes[$key][0] ?? null;
    }
}
