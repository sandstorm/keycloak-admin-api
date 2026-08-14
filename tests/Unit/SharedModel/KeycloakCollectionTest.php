<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Unit\SharedModel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\Dto\KeycloakCredentials;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUser;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\Dto\KeycloakUsers;

use function count;
use function iterator_to_array;

/**
 * The collection base contract, exercised through two concrete collections — every API return type
 * is one of these, so callers rely on this behaviour uniformly.
 */
final class KeycloakCollectionTest extends TestCase
{
    #[Test]
    public function is_countable_iterable_and_convertible_to_a_list(): void
    {
        $users = KeycloakUsers::fromRawResponse([
            ['id' => 'u1', 'username' => 'alice'],
            ['id' => 'u2', 'username' => 'bob'],
        ]);

        self::assertCount(2, $users);
        self::assertFalse($users->isEmpty());

        $usernames = [];
        foreach ($users as $user) {
            self::assertInstanceOf(KeycloakUser::class, $user);
            $usernames[] = $user->username;
        }
        self::assertSame(['alice', 'bob'], $usernames);

        // all() yields a real 0-indexed list for the rare array_map/array_slice caller.
        self::assertSame([0, 1], array_keys($users->all()));
        self::assertCount(2, iterator_to_array($users));
    }

    #[Test]
    public function an_empty_response_is_an_empty_collection_not_an_error(): void
    {
        $users = KeycloakUsers::fromRawResponse([]);

        self::assertCount(0, $users);
        self::assertTrue($users->isEmpty());
    }

    #[Test]
    public function skips_non_array_rows_so_one_bad_entry_does_not_lose_the_page(): void
    {
        $users = KeycloakUsers::fromRawResponse([
            ['id' => 'u1', 'username' => 'alice'],
            'garbage',
            ['id' => 'u2', 'username' => 'bob'],
        ]);

        self::assertCount(2, $users);
    }

    #[Test]
    public function credentials_collection_answers_the_two_factor_question(): void
    {
        $withOtp = KeycloakCredentials::fromRawResponse([
            ['id' => 'c1', 'type' => 'password'],
            ['id' => 'c2', 'type' => 'otp'],
        ]);
        $passwordOnly = KeycloakCredentials::fromRawResponse([
            ['id' => 'c1', 'type' => 'password'],
        ]);

        self::assertTrue($withOtp->hasSecondFactor());
        self::assertFalse($passwordOnly->hasSecondFactor());
    }
}
