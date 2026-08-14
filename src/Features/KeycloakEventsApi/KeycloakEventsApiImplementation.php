<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi;

use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto\KeycloakAdminEvents;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto\KeycloakUserEvents;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

use function http_build_query;

/**
 * @internal access via the {@link KeycloakEventsApi} contract.
 */
final readonly class KeycloakEventsApiImplementation implements KeycloakEventsApi
{
    public function __construct(
        private KeycloakTransport $transport,
    ) {}

    public function getUserEvents(KeycloakUserId $userId, int $first, int $max): KeycloakUserEvents
    {
        // No `type` filter — the full user-events list (every type), matching Keycloak's own UI.
        $query = http_build_query([
            'user' => $userId->value,
            'first' => $first,
            'max' => $max,
        ]);

        return KeycloakUserEvents::fromRawResponse($this->transport->getJson('events?' . $query));
    }

    public function getAdminEventsForUser(KeycloakUserId $userId, int $first, int $max): KeycloakAdminEvents
    {
        // Keycloak's `resourcePath` filter is an EXACT match unless the value contains a wildcard. A bare
        // `users/{id}` would only match a top-level user update and MISS every sub-resource action
        // (role-mappings, reset-password, …). The trailing `*` makes it a prefix match.
        $query = http_build_query([
            'resourcePath' => 'users/' . $userId->value . '*',
            'first' => $first,
            'max' => $max,
        ]);

        return KeycloakAdminEvents::fromRawResponse($this->transport->getJson('admin-events?' . $query));
    }
}
