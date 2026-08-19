<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\KeycloakUsersApiImplementation;

/**
 * Proves the caller-relative `access` capability map (`KeycloakUser::$access`) against a real Keycloak
 * in **both** authorization modes — the whole point of the map is that Keycloak computes it *for the
 * calling identity*, so only an E2E against a live server can prove it reflects real grants:
 *
 * - `test-realm` (Admin Permissions OFF, classic roles): a caller holding the realm-management roles
 *   (here the service account) sees `access.manage = true` for any user.
 * - `test-realm-fgap` (Admin Permissions ON): `sarah` is scoped by the group policy to *view all* but
 *   *manage only /endusers*. So her `access.manage` is **true** for `emma` (an enduser) and **false**
 *   for `jane` (a staff peer) — same caller, per-user outcome — while `access.view` stays true for both.
 *   This is exactly the FGAP authority the write E2E enforces, surfaced up front so the UI can gate
 *   controls without first attempting a 403-ing write.
 *
 * Opt-in: skips unless `KEYCLOAK_E2E_BASE_URL` is set (see docker-compose.yml).
 */
#[Group('integration')]
final class KeycloakUserAccessE2ETest extends IntegrationTestCase
{
    #[Test]
    public function a_role_holder_can_manage_everyone_when_fgap_is_off(): void
    {
        // Default transport = the service account, which holds the realm-management roles in test-realm.
        $users = new KeycloakUsersApiImplementation($this->transport);

        $jane = $users->getById($this->seededUserId('jane'));

        self::assertTrue($jane->access->manage, 'a role-holder should be able to manage any user when FGAP is off');
        self::assertTrue($jane->access->view);
    }

    #[Test]
    public function a_scoped_admin_sees_manage_only_for_permitted_users_when_fgap_is_on(): void
    {
        $realm = 'test-realm-fgap';

        // sarah: FGAP-scoped — view all, manage only /endusers. No realm-management roles.
        $staff = new KeycloakUsersApiImplementation($this->transportActingAs($realm, 'sarah'));

        $emmaId = $staff->list('emma', 0, 1, null)->all()[0]->id;
        $janeId = $staff->list('jane', 0, 1, null)->all()[0]->id;

        $emma = $staff->getById($emmaId);
        $jane = $staff->getById($janeId);

        // Enduser: manageable → access.manage true.
        self::assertTrue($emma->access->manage, 'a scoped staff admin should be able to manage an enduser');
        self::assertTrue($emma->access->view);

        // Staff peer: viewable but not manageable → access.manage false, still viewable.
        self::assertFalse($jane->access->manage, 'a scoped staff admin must NOT be able to manage a staff peer');
        self::assertTrue($jane->access->view, 'a scoped staff admin can still view a staff peer');
    }
}
