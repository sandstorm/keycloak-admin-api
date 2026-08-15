<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features;

use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto\KeycloakAdminEvents;
use Sandstorm\KeycloakAdminApi\Features\KeycloakEventsApi\Dto\KeycloakUserEvents;
use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakUserId;

/**
 * The event-history slice of the Keycloak Admin port (login events + admin events).
 *
 * Both sources are three-state and callers must not conflate them: rows returned, an empty page
 * (nothing in the retention window — not an error), or forbidden (403 →
 * {@see UnexpectedKeycloakResponseException} with ->statusCode 403). Empty-vs-forbidden is decided by
 * the call outcome, never by treating an empty collection as "disabled".
 *
 * Keycloak's events endpoints have no total-count, so callers detect a next page by requesting one
 * extra row (`max = perPage + 1`) rather than reading a total.
 */
interface KeycloakEventsApi
{
    public function getUserEvents(KeycloakUserId $userId, int $first, int $max): KeycloakUserEvents;

    public function getAdminEventsForUser(KeycloakUserId $userId, int $first, int $max): KeycloakAdminEvents;
}
