<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

use RuntimeException;

/**
 * Keycloak did not accept the caller: no usable identity (e.g. the admin is not logged in via SSO),
 * invalid credentials, or lacking the permission for the request (HTTP 401/403).
 *
 * This is the ONE Keycloak failure a UI is meant to catch and turn into a friendly notice. It is an
 * expected, per-caller outcome, not an operational or developer problem. Every other failure
 * (Keycloak unreachable/5xx, missing configuration, a malformed response) is thrown as a plain
 * {@see RuntimeException}/{@see \UnexpectedValueException} and left to propagate and be logged.
 */
final class KeycloakAuthenticationException extends RuntimeException {}
