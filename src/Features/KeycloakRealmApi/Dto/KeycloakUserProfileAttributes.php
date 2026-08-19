<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * The ordered list of {@see KeycloakUserProfileAttribute} declared by a realm's User-Profile config,
 * in Keycloak's own declaration order (which the console renders top-to-bottom). Adds lookup by name
 * and the admin-editable projection the plugin form needs.
 *
 * @extends KeycloakCollection<KeycloakUserProfileAttribute>
 */
final class KeycloakUserProfileAttributes extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList  the config's `attributes` array
     */
    public static function fromRawResponse(array $rawList): self
    {
        $attributes = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $attributes[] = KeycloakUserProfileAttribute::fromRawResponse($row);
            }
        }

        return new self($attributes);
    }

    public function byName(string $name): ?KeycloakUserProfileAttribute
    {
        foreach ($this->items as $attribute) {
            if ($attribute->name === $name) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Only the attributes this admin may edit — the ones the plugin renders as writable form fields.
     * Order is preserved.
     */
    public function editableByAdmin(): self
    {
        $editable = [];
        foreach ($this->items as $attribute) {
            if ($attribute->permissions->adminCanEdit()) {
                $editable[] = $attribute;
            }
        }

        return new self($editable);
    }
}
