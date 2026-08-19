<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto;

use function is_array;

/**
 * A realm's User-Profile configuration (`GET /users/profile`) — the declarative schema Keycloak uses
 * to render its own user form: which attributes exist, whether each is required, what validators apply,
 * and who (admin/user) may view or edit each. The plugin renders the admin user form from exactly this,
 * so the UI mirrors Keycloak rather than offering free-form attribute editing.
 *
 * Only the `attributes` list is modelled here — the piece the form is built from. The config's `groups`
 * (display grouping) and other keys are not needed by the plugin and are left unparsed.
 */
final readonly class KeycloakUserProfile
{
    public function __construct(
        public KeycloakUserProfileAttributes $attributes,
    ) {}

    /**
     * @param  array<int|string, mixed>  $raw  the decoded `GET /users/profile` body
     */
    public static function fromRawResponse(array $raw): self
    {
        $rawAttributes = $raw['attributes'] ?? [];

        return new self(
            KeycloakUserProfileAttributes::fromRawResponse(is_array($rawAttributes) ? $rawAttributes : []),
        );
    }
}
