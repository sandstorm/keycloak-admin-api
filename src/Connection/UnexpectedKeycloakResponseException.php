<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

use RuntimeException;
use Throwable;

/**
 * The single failure type for a Keycloak call that did not return a usable 2xx: Keycloak was
 * unreachable (connection refused, timeout, DNS), or it answered with a non-2xx status — including the
 * denied case (401/403), an outage (5xx), an unexpected 400/404/409, or a rejected service-account
 * `client_credentials` grant.
 *
 * There is deliberately no separate "authentication" subtype: callers branch on {@see $statusCode}
 * instead. A UI turns 401/403 into a friendly "not permitted" notice; a 5xx may be retried. Distinct
 * from {@see \UnexpectedValueException}, which is about a 2xx body whose *shape* is wrong (not a JSON
 * array/object).
 *
 * It carries the raw HTTP outcome so callers can react: {@see $statusCode} is the response status, or
 * `null` when the request never got a response at all (a transport failure), and {@see $responseBody}
 * is Keycloak's own error body (empty string when none).
 */
final class UnexpectedKeycloakResponseException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code,
        public readonly ?int $statusCode,
        public readonly string $responseBody = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
