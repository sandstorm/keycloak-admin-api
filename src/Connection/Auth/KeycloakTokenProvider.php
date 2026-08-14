<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Connection\Auth;

/**
 * The one auth contract this library exposes: it hands the transport a bearer token per request. The
 * active implementation is chosen once by the consumer (bound from config, no runtime selection) —
 * the transport never picks a provider and there is no cross-mode fallback.
 *
 * This library ships only the service-account implementation ({@see ServiceAccountTokenProvider}).
 * Providers that need a web session (e.g. act-as-user SSO) live in the consuming app/plugin, since
 * they would couple the library to a framework.
 */
interface KeycloakTokenProvider
{
    /**
     * Return a bearer token valid right now, refreshing it if needed.
     *
     * @throws \RuntimeException when no usable token can be produced (surfaced loudly — never a
     *                           silent switch to another identity)
     */
    public function currentBearer(): string;
}
