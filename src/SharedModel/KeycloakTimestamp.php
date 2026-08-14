<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\SharedModel;

use DateTimeImmutable;
use DateTimeZone;

use function intdiv;
use function is_int;

/**
 * Converts Keycloak's epoch-millisecond timestamps to/from `DateTimeImmutable`. Plain PHP (no
 * Carbon/Laravel) so DTOs can hold real date objects and render them. UTC throughout — admin auditing
 * is clearest in one fixed zone; the UI does not re-localize.
 */
final class KeycloakTimestamp
{
    /**
     * Parse a raw epoch-millisecond value (as Keycloak returns it) into a UTC `DateTimeImmutable`, or
     * null when absent/not an integer. DTOs call this in their `fromRawResponse`.
     */
    public static function fromEpochMillis(mixed $epochMilliseconds): ?DateTimeImmutable
    {
        if (! is_int($epochMilliseconds)) {
            return null;
        }

        return (new DateTimeImmutable('@' . intdiv($epochMilliseconds, 1000)))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Render a date as `Y-m-d H:i:s UTC`, or an em dash when absent.
     */
    public static function format(?DateTimeImmutable $moment): string
    {
        return $moment?->format('Y-m-d H:i:s \U\T\C') ?? '—';
    }
}
