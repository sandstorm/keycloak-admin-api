<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

use function rtrim;

/**
 * Connection settings for the Keycloak Admin API service-account client.
 */
final readonly class KeycloakSettings
{
    /** BaseURL - normalized WITHOUT trailing slash */
    public string $baseUrl;

    public function __construct(
        string $baseUrl,
        public string $realm,
        public string $clientId,
        public string $clientSecret,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
}
