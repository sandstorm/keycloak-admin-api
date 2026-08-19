<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto\KeycloakUserProfile;

/**
 * @internal access via the {@link KeycloakRealmApi} contract.
 */
final readonly class KeycloakRealmApiImplementation implements KeycloakRealmApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function getUserProfile(): KeycloakUserProfile
    {
        // The transport prefixes {baseUrl}/admin/realms/{realm}/ → GET .../users/profile, the admin
        // endpoint that returns the realm's User-Profile config (UPConfig).
        return KeycloakUserProfile::fromRawResponse($this->transport->getJson('users/profile'));
    }
}
