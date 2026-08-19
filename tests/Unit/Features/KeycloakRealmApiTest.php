<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Connection\UnexpectedKeycloakResponseException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\KeycloakRealmApiImplementation;
use Sandstorm\KeycloakAdminApi\Tests\Support\MockKeycloak;

final class KeycloakRealmApiTest extends TestCase
{
    #[Test]
    public function gets_the_user_profile_config_and_parses_its_attributes(): void
    {
        $mock = new MockKeycloak([MockKeycloak::json([
            'attributes' => [
                ['name' => 'email', 'required' => ['roles' => ['user']], 'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']]],
                ['name' => 'firstName', 'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']]],
            ],
        ])]);

        $profile = (new KeycloakRealmApiImplementation($mock->transport))->getUserProfile();

        self::assertSame('GET', $mock->lastMethod());
        self::assertStringEndsWith('/admin/realms/test-realm/users/profile', $mock->lastUri());
        self::assertCount(2, $profile->attributes);
        self::assertTrue($profile->attributes->byName('email')?->required);
        self::assertTrue($profile->attributes->byName('firstName')?->permissions->adminCanEdit());
    }

    #[Test]
    public function surfaces_a_403_as_an_unexpected_response(): void
    {
        // The caller lacks realm-view under act-as-user auth — the denied path a UI turns into a notice.
        $mock = new MockKeycloak([MockKeycloak::json(['error' => 'Forbidden'], 403)]);

        $this->expectException(UnexpectedKeycloakResponseException::class);

        (new KeycloakRealmApiImplementation($mock->transport))->getUserProfile();
    }
}
