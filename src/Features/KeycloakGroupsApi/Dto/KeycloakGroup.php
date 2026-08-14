<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakGroupsApi\Dto;

use RuntimeException;

use function is_string;

/**
 * A realm group a user belongs to, as returned by `GET /users/{id}/groups`. Parsing stays tolerant:
 * only `id` and `name` are required, `path` is optional (Keycloak returns it for hierarchical groups).
 */
final readonly class KeycloakGroup
{
    private function __construct(
        public string $id,
        public string $name,
        public ?string $path = null,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw
     *
     * @throws RuntimeException when the object lacks an id or name
     */
    public static function fromRawResponse(array $raw): self
    {
        $id = $raw['id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Keycloak group representation had no id.', 1750000030);
        }

        $name = $raw['name'] ?? null;
        if (! is_string($name)) {
            throw new RuntimeException('Keycloak group representation had no name.', 1750000031);
        }

        $path = is_string($raw['path'] ?? null) ? $raw['path'] : null;

        return new self($id, $name, $path);
    }
}
