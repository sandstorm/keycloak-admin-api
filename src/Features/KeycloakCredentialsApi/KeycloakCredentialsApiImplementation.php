<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto\KeycloakCredentials;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function array_filter;
use function http_build_query;
use function rawurlencode;

/**
 * @internal access via the {@link KeycloakCredentialsApi} contract.
 */
final readonly class KeycloakCredentialsApiImplementation implements KeycloakCredentialsApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function get(KeycloakUserId $userId): KeycloakCredentials
    {
        return KeycloakCredentials::fromRawResponse(
            $this->transport->getJson('users/' . rawurlencode($userId->value) . '/credentials'),
        );
    }

    public function executeActionsEmail(
        KeycloakUserId $userId,
        array $actions,
        ?int $lifespan = null,
        ?string $clientId = null,
        ?string $redirectUri = null,
    ): void {
        // array_filter drops null/0/'' so an unset optional never becomes an empty query param (and a
        // 0-second lifespan, which is meaningless, is dropped too).
        $query = http_build_query(array_filter([
            'lifespan' => $lifespan,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]));

        $path = 'users/' . rawurlencode($userId->value) . '/execute-actions-email';
        if ($query !== '') {
            $path .= '?' . $query;
        }

        // putList (not putJson): the actions MUST serialize as a JSON array (`["UPDATE_PASSWORD"]`).
        // putJson object-casts it to `{"0":…}`, which Keycloak rejects.
        $this->transport->putList($path, $actions);
    }

    public function delete(KeycloakUserId $userId, string $credentialId): void
    {
        $this->transport->delete('users/' . rawurlencode($userId->value) . '/credentials/' . rawurlencode($credentialId));
    }
}
