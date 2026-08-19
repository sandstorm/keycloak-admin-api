<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto;

use function is_bool;

/**
 * The caller-relative capability map Keycloak attaches to a user representation as `access`. Every flag
 * is computed **by Keycloak for the calling identity** — under act-as-user (`sso`) auth that is the
 * logged-in admin's effective Fine-Grained-Admin-Permissions on *this* user. So the UI can reflect what
 * the authority itself will allow (enable/hide edit controls up front) instead of only reacting to a 403.
 *
 * This is not client-side permission logic: Keycloak stays the authority; this DTO merely carries its
 * answer. A write can still 403 if grants changed since the read — authorize-by-attempt remains the
 * backstop.
 *
 * Parsing is tolerant: any absent or mistyped flag defaults to **false** (the safe gate — show nothing
 * editable rather than falsely enable it). A representation without an `access` object → {@see empty()}.
 */
final readonly class KeycloakUserAccess
{
    public function __construct(
        public bool $manage = false,
        public bool $view = false,
        public bool $manageGroupMembership = false,
        public bool $mapRoles = false,
        public bool $impersonate = false,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw  the value of the representation's `access` key
     */
    public static function fromRawAccess(array $raw): self
    {
        return new self(
            self::flag($raw, 'manage'),
            self::flag($raw, 'view'),
            self::flag($raw, 'manageGroupMembership'),
            self::flag($raw, 'mapRoles'),
            self::flag($raw, 'impersonate'),
        );
    }

    /**
     * The all-false capability map — used when a representation carries no `access` object (e.g. a brief
     * listing row). Nothing is shown as editable.
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param  array<int|string, mixed>  $raw
     */
    private static function flag(array $raw, string $key): bool
    {
        return is_bool($raw[$key] ?? null) ? $raw[$key] : false;
    }
}
