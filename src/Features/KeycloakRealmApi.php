<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto\KeycloakUserProfile;

/**
 * The realm-configuration read slice of the Keycloak Admin port. Where {@see KeycloakUsersApi} reads and
 * writes individual users, this reads the realm-level settings that shape those users — today just the
 * User-Profile schema that declares which attributes a user has and who may edit them.
 *
 * Every method throws {@see UnexpectedKeycloakResponseException} on any non-2xx or transport failure;
 * read its ->statusCode (401/403 = denied — e.g. the caller lacks realm-view). A malformed 2xx body
 * surfaces as an UnexpectedValueException.
 */
interface KeycloakRealmApi
{
    /**
     * The realm's declarative User-Profile config (`GET /users/profile`): the attribute schema —
     * names, required-ness, validators, and per-role (admin/user) view/edit permissions — the admin
     * user form is rendered from, so the plugin edits exactly the attributes Keycloak itself exposes.
     */
    public function getUserProfile(): KeycloakUserProfile;
}
