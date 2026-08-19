<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\KeycloakRealmApiImplementation;

/**
 * Proves `KeycloakRealmApi::getUserProfile()` against a real Keycloak: that KC 26.5.3 answers
 * `GET /admin/realms/{realm}/users/profile` with the UPConfig shape the DTOs parse. The seeded realm
 * ships no custom User-Profile config, so Keycloak serves its **default declarative profile** — the
 * built-in identity attributes (username/email/firstName/lastName) with their standard validators,
 * required-ness, and admin/user permissions. That default is exactly what the plugin form reads, so
 * proving we parse it from a live server is the point.
 *
 * The admin-only-edit permission case (`edit:["admin"]` but not `user`) is covered by the unit test —
 * the default profile ships no such built-in attribute to exercise it end-to-end.
 *
 * Opt-in: skips unless `KEYCLOAK_E2E_BASE_URL` is set (see docker-compose.yml).
 */
#[Group('integration')]
final class KeycloakUserProfileE2ETest extends IntegrationTestCase
{
    #[Test]
    public function reads_the_default_declarative_user_profile_from_a_live_keycloak(): void
    {
        $profile = (new KeycloakRealmApiImplementation($this->transport))->getUserProfile();

        // The four built-in identity attributes are always present in the default profile.
        foreach (['username', 'email', 'firstName', 'lastName'] as $name) {
            self::assertNotNull($profile->attributes->byName($name), "default profile must declare the built-in \"$name\" attribute");
        }

        // Keycloak's defaults: email + names are required, username is not.
        self::assertTrue($profile->attributes->byName('email')?->required, 'email is required in the default profile');
        self::assertTrue($profile->attributes->byName('firstName')?->required);
        self::assertTrue($profile->attributes->byName('lastName')?->required);
        self::assertFalse($profile->attributes->byName('username')?->required);
    }

    #[Test]
    public function surfaces_the_admin_edit_permissions_a_real_keycloak_computes(): void
    {
        $profile = (new KeycloakRealmApiImplementation($this->transport))->getUserProfile();

        // The built-in identity fields are admin-editable in the default profile — so they are exactly
        // the fields the plugin renders as writable.
        $editableNames = [];
        foreach ($profile->attributes->editableByAdmin() as $attribute) {
            $editableNames[] = $attribute->name;
        }

        self::assertContains('email', $editableNames);
        self::assertContains('firstName', $editableNames);
        self::assertContains('lastName', $editableNames);

        $email = $profile->attributes->byName('email');
        self::assertTrue($email?->permissions->adminCanEdit());
        self::assertTrue($email->permissions->adminCanView());
        // The default email attribute carries an email-format validator — the UI maps it to a rule.
        self::assertTrue($email->requiresEmailFormat());
    }
}
