<?php

declare(strict_types=1);

namespace Sandstorm\KeycloakAdminApi\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Sandstorm\KeycloakAdminApi\Features\KeycloakCredentialsApi\KeycloakCredentialsApiImplementation;

/**
 * Proves the credential read, the array-body `execute-actions-email` (against the MailPit SMTP the
 * realm points at), and per-credential delete against a real Keycloak. Seeded: jane has a password
 * credential.
 */
#[Group('integration')]
final class KeycloakCredentialsE2ETest extends IntegrationTestCase
{
    #[Test]
    public function reads_the_seeded_password_credential(): void
    {
        $credentials = (new KeycloakCredentialsApiImplementation($this->transport))->get($this->seededUserId('jane'));

        $types = [];
        foreach ($credentials as $credential) {
            $types[] = $credential->type;
        }
        self::assertContains('password', $types);
    }

    #[Test]
    public function sends_an_execute_actions_email_via_the_realm_smtp(): void
    {
        // Succeeds only because the realm's smtpServer points at MailPit — this is the array-body PUT
        // (`["UPDATE_PASSWORD"]`) reaching a real Keycloak. No exception = accepted.
        (new KeycloakCredentialsApiImplementation($this->transport))
            ->executeActionsEmail($this->seededUserId('jane'), ['UPDATE_PASSWORD']);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function deletes_a_stored_credential_by_id(): void
    {
        $credentialsApi = new KeycloakCredentialsApiImplementation($this->transport);
        $janeId = $this->seededUserId('jane');

        $target = null;
        foreach ($credentialsApi->get($janeId) as $credential) {
            if ($credential->id !== null) {
                $target = $credential->id;
                break;
            }
        }
        self::assertNotNull($target, 'expected at least one deletable (id-bearing) credential');

        $credentialsApi->delete($janeId, $target);

        $remainingIds = [];
        foreach ($credentialsApi->get($janeId) as $credential) {
            $remainingIds[] = $credential->id;
        }
        self::assertNotContains($target, $remainingIds);
    }
}
