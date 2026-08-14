<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakAuthenticationException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto\KeycloakCredentials;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The stored-credentials / 2FA slice of the Keycloak Admin port.
 *
 * Every method throws {@see KeycloakAuthenticationException} on 401/403 (the one catchable, friendly
 * case); all other failures propagate as RuntimeException/UnexpectedValueException to be logged.
 */
interface KeycloakCredentialsApi
{
    public function get(KeycloakUserId $userId): KeycloakCredentials;

    /**
     * Mail the user a link to perform the given required actions (`PUT /users/{id}/execute-actions-email`).
     *
     * The preferred password-reset path: the admin never sees or sets the password. `lifespan` (seconds)
     * bounds the link validity; `clientId`/`redirectUri` steer where the user lands afterwards.
     *
     * @param  list<string>  $actions  Keycloak required-action ids, e.g. `['UPDATE_PASSWORD']`
     */
    public function executeActionsEmail(
        KeycloakUserId $userId,
        array $actions,
        ?int $lifespan = null,
        ?string $clientId = null,
        ?string $redirectUri = null,
    ): void;

    /**
     * Remove a single stored credential by id (`DELETE /users/{id}/credentials/{credentialId}`).
     *
     * Deletes exactly what it is told; the whitelist (never the `password` credential) is enforced at
     * the UI/policy layer, not here.
     */
    public function delete(KeycloakUserId $userId, string $credentialId): void;
}
