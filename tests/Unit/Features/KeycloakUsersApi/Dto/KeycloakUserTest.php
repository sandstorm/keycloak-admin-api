<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\Features\KeycloakUsersApi\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUser;

final class KeycloakUserTest extends TestCase
{
    #[Test]
    public function parses_a_full_user_representation(): void
    {
        $user = KeycloakUser::fromRawResponse([
            'id' => 'abc-123',
            'username' => 'jane',
            'email' => 'jane@example.test',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'enabled' => false,
            'emailVerified' => true,
            'attributes' => ['fm_id_deelnemer' => ['42']],
        ]);

        self::assertSame('abc-123', $user->id->value);
        self::assertSame('jane', $user->username);
        self::assertSame('jane@example.test', $user->email);
        self::assertSame('Jane Doe', $user->fullName());
        self::assertFalse($user->enabled);
        self::assertTrue($user->emailVerified);
        self::assertSame('42', $user->firstAttributeValue('fm_id_deelnemer'));
    }

    #[Test]
    public function defaults_missing_identity_fields_tolerantly_for_a_brief_representation(): void
    {
        $user = KeycloakUser::fromRawResponse(['id' => 'abc-123', 'username' => 'jane']);

        self::assertNull($user->email);
        self::assertNull($user->firstName);
        self::assertNull($user->fullName());
        // enabled defaults true (a partial row is not falsely shown as disabled), emailVerified false.
        self::assertTrue($user->enabled);
        self::assertFalse($user->emailVerified);
        self::assertSame([], $user->attributes);
    }

    #[Test]
    public function builds_a_full_name_from_whichever_name_parts_are_present(): void
    {
        $firstOnly = KeycloakUser::fromRawResponse(['id' => 'a', 'username' => 'u', 'firstName' => 'Jane']);
        $lastOnly = KeycloakUser::fromRawResponse(['id' => 'b', 'username' => 'u', 'lastName' => 'Doe']);

        self::assertSame('Jane', $firstOnly->fullName());
        self::assertSame('Doe', $lastOnly->fullName());
    }

    #[Test]
    public function parses_pending_required_actions_and_defaults_them_to_an_empty_list(): void
    {
        $pending = KeycloakUser::fromRawResponse([
            'id' => 'a',
            'username' => 'u',
            'requiredActions' => ['CONFIGURE_TOTP', 'UPDATE_PASSWORD'],
        ]);
        $none = KeycloakUser::fromRawResponse(['id' => 'b', 'username' => 'u']);

        self::assertSame(['CONFIGURE_TOTP', 'UPDATE_PASSWORD'], $pending->requiredActions);
        self::assertSame([], $none->requiredActions);
    }

    #[Test]
    public function rejects_a_representation_without_an_id(): void
    {
        $this->expectException(RuntimeException::class);

        KeycloakUser::fromRawResponse(['username' => 'jane']);
    }
}
