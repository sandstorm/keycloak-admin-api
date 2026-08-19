<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto;

use function in_array;
use function is_array;
use function is_string;

/**
 * Who may view and who may edit a User-Profile attribute, as declared in the realm's User-Profile
 * config (`GET /users/profile`). Keycloak models each as a list of roles drawn from a fixed vocabulary
 * — `admin` (a realm admin acting through the Admin API / console) and `user` (the account owner via
 * the account console) — e.g. `{"view":["admin","user"],"edit":["admin"]}` for an attribute an admin
 * may change but the user may only see.
 *
 * The plugin's admin form is the **`admin`** context, so it drives every field off {@see adminCanEdit()}
 * / {@see adminCanView()}: an attribute the admin may not edit renders read-only (or hidden), never a
 * writable field whose PUT Keycloak would reject.
 *
 * Parsing is tolerant and defaults to **closed**: an absent or mistyped `view`/`edit` list yields an
 * empty role list, so the safe answer (may not) is returned rather than a falsely-open field — the same
 * all-false-safe stance as {@see \Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUserAccess}.
 */
final readonly class KeycloakUserProfileAttributePermissions
{
    /**
     * @param  list<string>  $view  roles allowed to view (subset of `admin`, `user`)
     * @param  list<string>  $edit  roles allowed to edit (subset of `admin`, `user`)
     */
    public function __construct(
        public array $view = [],
        public array $edit = [],
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw  the value of an attribute's `permissions` key
     */
    public static function fromRawPermissions(array $raw): self
    {
        return new self(
            self::roles($raw['view'] ?? null),
            self::roles($raw['edit'] ?? null),
        );
    }

    /**
     * The closed permissions — no one may view or edit — used when an attribute carries no
     * `permissions` object.
     */
    public static function closed(): self
    {
        return new self();
    }

    public function adminCanView(): bool
    {
        return in_array('admin', $this->view, true);
    }

    public function adminCanEdit(): bool
    {
        return in_array('admin', $this->edit, true);
    }

    public function userCanView(): bool
    {
        return in_array('user', $this->view, true);
    }

    public function userCanEdit(): bool
    {
        return in_array('user', $this->edit, true);
    }

    /**
     * @return list<string>
     */
    private static function roles(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $roles = [];
        foreach ($raw as $role) {
            if (is_string($role) && $role !== '') {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
