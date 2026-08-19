<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto;

use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;

/**
 * One attribute declared in the realm's User-Profile schema (`GET /users/profile`). This is the
 * *definition* of a field — its name, label, whether it is required, its per-role view/edit
 * {@see KeycloakUserProfileAttributePermissions}, and its Keycloak validators — not a user's value.
 * The plugin renders the admin user form from these definitions (which fields exist, which are
 * editable, what validation applies), mirroring the Keycloak console instead of offering free-form
 * key/value editing.
 *
 * The built-in identity fields (`username`, `email`, `firstName`, `lastName`) are themselves
 * User-Profile attributes and appear here alongside any realm-specific custom attributes.
 *
 * `validations` is kept as Keycloak's raw validator map (`{"length":{"min":3,"max":255},"email":{}}`)
 * so nothing is lost; the typed helpers ({@see maxLength()}, {@see minLength()}, {@see pattern()},
 * {@see requiresEmailFormat()}) expose the few validators the UI maps to Filament rules, and are
 * tolerant — a malformed validator config simply yields `null`/`false` rather than throwing.
 *
 * Parsing is tolerant: only `name` is required; everything else defaults so a sparse attribute never
 * throws.
 */
final readonly class KeycloakUserProfileAttribute
{
    /**
     * @param  array<string, array<int|string, mixed>>  $validations  raw validator-name → config map
     */
    private function __construct(
        public string $name,
        public ?string $displayName,
        public bool $required,
        public bool $multivalued,
        public ?string $group,
        public KeycloakUserProfileAttributePermissions $permissions,
        public array $validations,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw  one entry of the config's `attributes` list
     *
     * @throws RuntimeException when the attribute has no name
     */
    public static function fromRawResponse(array $raw): self
    {
        $name = $raw['name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Keycloak user-profile attribute had no name.', 1750000040);
        }

        $displayName = is_string($raw['displayName'] ?? null) ? $raw['displayName'] : null;

        // `required` is an object (`{"roles":["user"]}`) when the attribute is required, and absent
        // otherwise — so its mere presence is the signal. An admin form treats "required for anyone" as
        // required; the per-role nuance does not change what the field must contain to save.
        $required = array_key_exists('required', $raw) && is_array($raw['required']);

        $multivalued = is_bool($raw['multivalued'] ?? null) ? $raw['multivalued'] : false;

        $group = is_string($raw['group'] ?? null) && $raw['group'] !== '' ? $raw['group'] : null;

        $rawPermissions = $raw['permissions'] ?? null;
        $permissions = is_array($rawPermissions)
            ? KeycloakUserProfileAttributePermissions::fromRawPermissions($rawPermissions)
            : KeycloakUserProfileAttributePermissions::closed();

        $validations = [];
        $rawValidations = $raw['validations'] ?? null;
        if (is_array($rawValidations)) {
            foreach ($rawValidations as $validator => $config) {
                if (is_string($validator) && is_array($config)) {
                    $validations[$validator] = $config;
                }
            }
        }

        return new self($name, $displayName, $required, $multivalued, $group, $permissions, $validations);
    }

    public function requiresEmailFormat(): bool
    {
        return array_key_exists('email', $this->validations);
    }

    public function maxLength(): ?int
    {
        return $this->lengthBound('max');
    }

    public function minLength(): ?int
    {
        return $this->lengthBound('min');
    }

    /**
     * The regex from a `pattern` validator, if any (Keycloak stores it under the validator's `pattern`
     * key). Null when the attribute has no pattern validator.
     */
    public function pattern(): ?string
    {
        $pattern = $this->validations['pattern']['pattern'] ?? null;

        return is_string($pattern) && $pattern !== '' ? $pattern : null;
    }

    private function lengthBound(string $bound): ?int
    {
        $value = $this->validations['length'][$bound] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
