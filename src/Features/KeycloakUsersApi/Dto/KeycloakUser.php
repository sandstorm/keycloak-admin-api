<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto;

use RuntimeException;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function array_filter;
use function array_key_exists;
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
 *
 * Writes are **read-modify-write**: fetch with {@see KeycloakUsersApi::getById()} (a full
 * representation), apply edits with the `with*()` mutators, then hand the result to
 * {@see KeycloakUsersApi::update()}. The original response is retained verbatim in `$raw`, and
 * {@see toRepresentation()} overlays only the modelled fields back onto it — so any Keycloak field this
 * DTO does not model (createdTimestamp, access, disableableCredentialTypes, federation links, …) round
 * trips **losslessly** and is never dropped by an update.
 */
final readonly class KeycloakUser
{
    /**
     * @param  array<string, list<string>>  $attributes
     * @param  list<string>  $requiredActions  pending actions the user must complete at next login (e.g. CONFIGURE_TOTP)
     * @param  array<int|string, mixed>  $raw  the untouched source representation, preserved for lossless round-trips
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
        public KeycloakUserAccess $access = new KeycloakUserAccess(),
        private array $raw = [],
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

        // Caller-relative capability map (what THIS caller may do to THIS user). Absent in brief
        // representations → all-false (nothing shown as editable).
        $rawAccess = $raw['access'] ?? null;
        $access = is_array($rawAccess) ? KeycloakUserAccess::fromRawAccess($rawAccess) : KeycloakUserAccess::empty();

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
            $access,
            $raw,
        );
    }

    public function withFirstName(?string $firstName): self
    {
        return $this->cloneWith(firstName: $firstName);
    }

    public function withLastName(?string $lastName): self
    {
        return $this->cloneWith(lastName: $lastName);
    }

    public function withEmail(?string $email): self
    {
        return $this->cloneWith(email: $email);
    }

    public function withEnabled(bool $enabled): self
    {
        return $this->cloneWith(enabled: $enabled);
    }

    public function withEmailVerified(bool $emailVerified): self
    {
        return $this->cloneWith(emailVerified: $emailVerified);
    }

    /**
     * Replace the whole custom-attribute map. Keycloak's shape is `{ key: [value, ...] }`; each value
     * list is stored as `list<string>`.
     *
     * @param  array<string, list<string>>  $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return $this->cloneWith(attributes: $attributes);
    }

    /**
     * Set (or overwrite) a single custom attribute, leaving the others untouched.
     *
     * @param  list<string>  $values
     */
    public function withAttribute(string $key, array $values): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $values;

        return $this->cloneWith(attributes: $attributes);
    }

    /**
     * The representation to PUT back to Keycloak: the untouched source `$raw` with only the modelled
     * fields overlaid. Unmodelled keys survive verbatim (lossless round-trip).
     *
     * A nullable identity field (email/firstName/lastName) is only written when it is non-null, or when
     * the source already carried that key — so a brief/partial source never gains a spurious `null` and
     * an update never silently clears a field this DTO simply chose not to model as set.
     *
     * @return array<int|string, mixed>
     */
    public function toRepresentation(): array
    {
        $representation = $this->raw;

        $representation['id'] = $this->id->value;
        $representation['username'] = $this->username;
        $representation['enabled'] = $this->enabled;
        $representation['emailVerified'] = $this->emailVerified;
        $representation['attributes'] = $this->attributes;

        foreach (['email' => $this->email, 'firstName' => $this->firstName, 'lastName' => $this->lastName] as $key => $value) {
            if ($value !== null) {
                $representation[$key] = $value;
            } elseif (array_key_exists($key, $this->raw)) {
                $representation[$key] = $this->raw[$key];
            }
        }

        return $representation;
    }

    /**
     * Immutable copy with selected modelled fields replaced. Only non-null arguments take effect, so the
     * `with*()` mutators set values; clearing an identity field back to null is intentionally not offered
     * (no edit feature needs it, and Keycloak treats an absent field as "leave unchanged" anyway).
     *
     * @param  array<string, list<string>>|null  $attributes
     */
    private function cloneWith(
        ?string $email = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?bool $enabled = null,
        ?bool $emailVerified = null,
        ?array $attributes = null,
    ): self {
        return new self(
            $this->id,
            $this->username,
            $attributes ?? $this->attributes,
            $email ?? $this->email,
            $firstName ?? $this->firstName,
            $lastName ?? $this->lastName,
            $enabled ?? $this->enabled,
            $emailVerified ?? $this->emailVerified,
            $this->requiredActions,
            $this->access,
            $this->raw,
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
