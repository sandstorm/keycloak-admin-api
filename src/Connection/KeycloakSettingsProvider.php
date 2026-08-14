<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection;

/**
 * Framework-agnostic seam that supplies the Keycloak connection settings to the transport.
 *
 * This library does not own a source of settings and must not depend on any app-specific settings
 * type. Each consumer provides its own implementation (e.g. one reading Laravel/Filament config).
 * The transport only ever calls {@see self::get()}.
 */
interface KeycloakSettingsProvider
{
    public function get(): KeycloakSettings;
}
