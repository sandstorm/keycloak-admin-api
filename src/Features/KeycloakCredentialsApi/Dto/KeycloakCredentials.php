<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto;

use Sandstorm\KeycloakAdminApi\SharedModel\KeycloakCollection;

use function is_array;

/**
 * An immutable collection of {@see KeycloakCredential}. Owns the list-level question the UI actually
 * asks — "does this user have 2FA?" — so the answer lives with the data, not spread across callers.
 *
 * @extends KeycloakCollection<KeycloakCredential>
 */
final class KeycloakCredentials extends KeycloakCollection
{
    /**
     * @param  array<int|string, mixed>  $rawList
     */
    public static function fromRawResponse(array $rawList): self
    {
        $credentials = [];
        foreach ($rawList as $row) {
            if (is_array($row)) {
                $credentials[] = KeycloakCredential::fromRawResponse($row);
            }
        }

        return new self($credentials);
    }

    public function hasSecondFactor(): bool
    {
        foreach ($this->items as $credential) {
            if ($credential->isSecondFactor()) {
                return true;
            }
        }

        return false;
    }
}
