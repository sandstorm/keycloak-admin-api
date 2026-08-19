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

    #[Test]
    public function round_trips_unmodelled_fields_losslessly_through_to_representation(): void
    {
        $raw = [
            'id' => 'abc-123',
            'username' => 'jane',
            'email' => 'jane@example.test',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'enabled' => true,
            'emailVerified' => true,
            'attributes' => ['fm_id_deelnemer' => ['42']],
            // Fields the DTO does not model — must survive an update untouched.
            'createdTimestamp' => 1700000000000,
            'disableableCredentialTypes' => ['otp'],
            'requiredActions' => [],
            'notBefore' => 0,
            'access' => ['manageGroupMembership' => true, 'view' => true, 'manage' => false],
        ];

        $representation = KeycloakUser::fromRawResponse($raw)->toRepresentation();

        self::assertSame(1700000000000, $representation['createdTimestamp']);
        self::assertSame(['otp'], $representation['disableableCredentialTypes']);
        self::assertSame(0, $representation['notBefore']);
        self::assertSame(['manageGroupMembership' => true, 'view' => true, 'manage' => false], $representation['access']);
        // Modelled identity fields are present and unchanged.
        self::assertSame('jane', $representation['username']);
        self::assertSame('jane@example.test', $representation['email']);
        self::assertSame(['fm_id_deelnemer' => ['42']], $representation['attributes']);
    }

    #[Test]
    public function overlays_only_the_edited_fields_and_preserves_everything_else(): void
    {
        $raw = [
            'id' => 'abc-123',
            'username' => 'jane',
            'email' => 'jane@example.test',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'enabled' => true,
            'emailVerified' => false,
            'attributes' => ['fm_id_deelnemer' => ['42']],
            'createdTimestamp' => 1700000000000,
        ];

        $edited = KeycloakUser::fromRawResponse($raw)
            ->withFirstName('Janet')
            ->withEnabled(false)
            ->withEmailVerified(true)
            ->withAttribute('nickname', ['J']);

        $representation = $edited->toRepresentation();

        // Edited.
        self::assertSame('Janet', $representation['firstName']);
        self::assertFalse($representation['enabled']);
        self::assertTrue($representation['emailVerified']);
        self::assertSame(['fm_id_deelnemer' => ['42'], 'nickname' => ['J']], $representation['attributes']);
        // Untouched — both modelled and unmodelled.
        self::assertSame('Doe', $representation['lastName']);
        self::assertSame('jane@example.test', $representation['email']);
        self::assertSame(1700000000000, $representation['createdTimestamp']);

        // The mutators are immutable — the original is unchanged.
        self::assertSame('Jane', KeycloakUser::fromRawResponse($raw)->firstName);
    }

    #[Test]
    public function parses_the_caller_relative_access_capability_map(): void
    {
        $user = KeycloakUser::fromRawResponse([
            'id' => 'a',
            'username' => 'u',
            'access' => [
                'manage' => true,
                'view' => true,
                'manageGroupMembership' => true,
                'mapRoles' => false,
                'impersonate' => true,
            ],
        ]);

        self::assertTrue($user->access->manage);
        self::assertTrue($user->access->view);
        self::assertTrue($user->access->manageGroupMembership);
        self::assertFalse($user->access->mapRoles);
        self::assertTrue($user->access->impersonate);
    }

    #[Test]
    public function defaults_access_to_all_false_when_absent(): void
    {
        $user = KeycloakUser::fromRawResponse(['id' => 'a', 'username' => 'u']);

        self::assertFalse($user->access->manage);
        self::assertFalse($user->access->view);
        self::assertFalse($user->access->manageGroupMembership);
        self::assertFalse($user->access->mapRoles);
        self::assertFalse($user->access->impersonate);
    }

    #[Test]
    public function tolerates_a_partial_or_mistyped_access_object(): void
    {
        // Only `manage` present, `view` mistyped as a string, the rest absent → every non-true-bool false.
        $user = KeycloakUser::fromRawResponse([
            'id' => 'a',
            'username' => 'u',
            'access' => ['manage' => true, 'view' => 'yes'],
        ]);

        self::assertTrue($user->access->manage);
        self::assertFalse($user->access->view);
        self::assertFalse($user->access->impersonate);
    }

    #[Test]
    public function does_not_invent_null_identity_fields_absent_from_the_source(): void
    {
        // A source that never carried email/firstName/lastName must not gain them as null on write.
        $representation = KeycloakUser::fromRawResponse(['id' => 'a', 'username' => 'u'])->toRepresentation();

        self::assertArrayNotHasKey('email', $representation);
        self::assertArrayNotHasKey('firstName', $representation);
        self::assertArrayNotHasKey('lastName', $representation);
        self::assertSame('u', $representation['username']);
    }
}
