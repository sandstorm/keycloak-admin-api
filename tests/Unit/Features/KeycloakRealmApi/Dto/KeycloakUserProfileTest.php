<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features\KeycloakRealmApi\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakRealmApi\Dto\KeycloakUserProfile;

final class KeycloakUserProfileTest extends TestCase
{
    /**
     * A representative slice of a real `GET /users/profile` body: the built-in identity attributes
     * (admin+user editable, with validators) plus one realm-specific custom attribute an admin may
     * edit but the user may only view.
     *
     * @return array<int|string, mixed>
     */
    private static function realisticConfig(): array
    {
        return [
            'attributes' => [
                [
                    'name' => 'username',
                    'displayName' => '${username}',
                    'validations' => [
                        'length' => ['min' => 3, 'max' => 255],
                        'username-prohibited-characters' => [],
                    ],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                    'multivalued' => false,
                ],
                [
                    'name' => 'email',
                    'displayName' => '${email}',
                    'validations' => ['email' => [], 'length' => ['max' => 255]],
                    'required' => ['roles' => ['user']],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                ],
                [
                    'name' => 'firstName',
                    'displayName' => '${firstName}',
                    'validations' => ['length' => ['max' => 255]],
                    'required' => ['roles' => ['user']],
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']],
                ],
                [
                    'name' => 'department',
                    'displayName' => 'Department',
                    'group' => 'user-metadata',
                    'multivalued' => true,
                    'validations' => ['pattern' => ['pattern' => '^[A-Z]+$']],
                    // Admin may manage it; the user may only see it.
                    'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin']],
                ],
            ],
        ];
    }

    #[Test]
    public function parses_every_attribute_in_declaration_order(): void
    {
        $profile = KeycloakUserProfile::fromRawResponse(self::realisticConfig());

        $names = [];
        foreach ($profile->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        self::assertSame(['username', 'email', 'firstName', 'department'], $names);
    }

    #[Test]
    public function marks_email_required_and_username_optional_from_the_required_key_presence(): void
    {
        $profile = KeycloakUserProfile::fromRawResponse(self::realisticConfig());

        self::assertTrue($profile->attributes->byName('email')?->required);
        self::assertFalse($profile->attributes->byName('username')?->required);
    }

    #[Test]
    public function reflects_admin_only_edit_permission_on_a_custom_attribute(): void
    {
        $department = KeycloakUserProfile::fromRawResponse(self::realisticConfig())
            ->attributes->byName('department');

        self::assertNotNull($department);
        self::assertTrue($department->permissions->adminCanView());
        self::assertTrue($department->permissions->adminCanEdit());
        self::assertTrue($department->permissions->userCanView());
        self::assertFalse($department->permissions->userCanEdit(), 'user must not be able to edit an admin-only attribute');
    }

    #[Test]
    public function projects_only_admin_editable_attributes(): void
    {
        $profile = KeycloakUserProfile::fromRawResponse([
            'attributes' => [
                ['name' => 'email', 'permissions' => ['view' => ['admin', 'user'], 'edit' => ['admin', 'user']]],
                // View-only for the admin (e.g. a managed/federated attribute): excluded from the form.
                ['name' => 'locale', 'permissions' => ['view' => ['admin'], 'edit' => ['user']]],
            ],
        ]);

        $editableNames = [];
        foreach ($profile->attributes->editableByAdmin() as $attribute) {
            $editableNames[] = $attribute->name;
        }

        self::assertSame(['email'], $editableNames);
    }

    #[Test]
    public function exposes_the_validators_the_ui_maps_to_field_rules(): void
    {
        $profile = KeycloakUserProfile::fromRawResponse(self::realisticConfig());

        $username = $profile->attributes->byName('username');
        self::assertSame(3, $username?->minLength());
        self::assertSame(255, $username?->maxLength());
        self::assertFalse($username->requiresEmailFormat());

        $email = $profile->attributes->byName('email');
        self::assertTrue($email?->requiresEmailFormat());
        self::assertSame(255, $email->maxLength());
        self::assertNull($email->minLength());

        $department = $profile->attributes->byName('department');
        self::assertSame('^[A-Z]+$', $department?->pattern());
        self::assertTrue($department->multivalued);
        self::assertSame('user-metadata', $department->group);
    }

    #[Test]
    public function defaults_to_closed_permissions_when_an_attribute_declares_none(): void
    {
        // A malformed/sparse attribute must never be treated as admin-editable.
        $attribute = KeycloakUserProfile::fromRawResponse(['attributes' => [['name' => 'mystery']]])
            ->attributes->byName('mystery');

        self::assertNotNull($attribute);
        self::assertFalse($attribute->permissions->adminCanEdit());
        self::assertFalse($attribute->permissions->adminCanView());
        self::assertFalse($attribute->required);
        self::assertNull($attribute->maxLength());
    }

    #[Test]
    public function tolerates_a_body_without_an_attributes_key(): void
    {
        $profile = KeycloakUserProfile::fromRawResponse([]);

        self::assertTrue($profile->attributes->isEmpty());
    }

    #[Test]
    public function rejects_an_attribute_without_a_name(): void
    {
        $this->expectException(RuntimeException::class);

        KeycloakUserProfile::fromRawResponse(['attributes' => [['displayName' => 'no name here']]]);
    }
}
