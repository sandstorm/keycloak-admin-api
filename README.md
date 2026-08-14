# sandstorm/keycloak-admin-api

A framework-agnostic PHP client for the **Keycloak Admin REST API** (target: Keycloak 26.5.3).

It exposes the admin API as small, cohesive **feature interfaces** returning **immutable typed DTOs and collections** —
never bare arrays. It has no framework coupling: you inject a Guzzle client, a settings provider, and a token provider,
and nothing else reaches into your app.

- **Deep, segregated interfaces** — depend only on the slice you use (`KeycloakUsersApi`,
  `KeycloakGroupsApi`, …).
- **Immutable value objects** — `KeycloakUser`, `KeycloakCredential`, … and typed collections (`KeycloakUsers`,
  `KeycloakCredentials`) that own list-level behaviour (`hasSecondFactor()`).
- **One catchable failure** — `KeycloakAuthenticationException` on 401/403; everything else propagates to be logged.
- **Pluggable auth, no fallback** — the token provider is chosen once by the consumer.

## Requirements

- PHP 8.3+
- A PSR-18-capable Guzzle 7 client (injected)

## Implemented APIs & coverage

| Feature interface            | Endpoint(s)                                                                                                             | Operations                                                                  | Level                                                              |
|------------------------------|-------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------|--------------------------------------------------------------------|
| **`KeycloakUsersApi`**       | `GET /users`, `GET /users/count`, `GET /users/{id}`                                                                     | `list` → `KeycloakUsers`, `count`, `getById`                                | **Read** (search + server-side pagination)                         |
| **`KeycloakGroupsApi`**      | `GET /users/{id}/groups`, `GET /groups`, `PUT`/`DELETE /users/{id}/groups/{groupId}`                                    | `getUserGroups`, `listRealmGroups`, `addUserToGroup`, `removeUserFromGroup` | **Read + membership writes** (single-op, partial-failure friendly) |
| **`KeycloakCredentialsApi`** | `GET /users/{id}/credentials`, `PUT /users/{id}/execute-actions-email`, `DELETE /users/{id}/credentials/{credentialId}` | `get`, `executeActionsEmail`, `delete`                                      | **Read + reset-email + remove**                                    |
| **`KeycloakSessionsApi`**    | `GET /users/{id}/sessions`, `POST /users/{id}/logout`                                                                   | `getSessions`, `logoutAll`                                                  | **Read + logout-all**                                              |
| **`KeycloakEventsApi`**      | `GET /events?user=…`, `GET /admin-events?resourcePath=…`                                                                | `getUserEvents`, `getAdminEventsForUser`                                    | **Read** (login history + admin history)                           |

**Authentication:** `ServiceAccountTokenProvider` implements the OAuth2 `client_credentials` grant (token cached
in-memory, refreshed with a safety margin). Act-as-user **SSO** providers are the consumer's responsibility — implement
`KeycloakTokenProvider` in your app (a web session is framework-coupled and deliberately out of scope here).

### Not yet implemented

- User **create/update** writes (`POST`/`PUT /users`) — the read model is complete; write DTOs land with a later slice.
- **`KeycloakRealmApi`** — realm config (`editUsernameAllowed`, events flags) and health/`ping` (**Realms Admin** group).
- **User Profile** (`GET /users/profile[/metadata]`) — attribute schema for proactive form rendering. A **Users**-group concern, **not** realm.
- Direct **`reset-password`** (admin-set password) — `execute-actions-email` is the preferred path.

### Keycloak REST API coverage — complete, by resource group

This is **honest and complete**: it lists **every** resource group of the official
[Keycloak Admin REST API](https://www.keycloak.org/docs-api/latest/rest-api/index.html) (all 22, per the
reference), so nothing is hidden — the vast majority is **not** implemented, because this is a focused
**user-administration** client, not a full realm-management SDK.

**Our feature slices are not 1:1 with KC's resource groups.** KC files user reads, credentials,
sessions, *and* user↔group membership all under one large **Users** group; we deliberately split that
into small cohesive slices (interface segregation). So the mapping is many-of-ours → one-of-theirs, and
one of ours (`KeycloakGroupsApi`) spans two KC groups.

Legend: ✅ implemented · 🟡 partial · ❌ not implemented (candidate) · ⬜ out of scope for a user-admin client.

| # | KC resource group | Status | What we cover / our slice |
|--:|---|:--:|---|
| 1 | Attack Detection | ❌ | brute-force status/clear — none |
| 2 | Authentication Management | ⬜ | realm auth-flow config |
| 3 | Client Attribute Certificate | ⬜ | client keystores |
| 4 | Client Initial Access | ⬜ | dynamic client registration tokens |
| 5 | Client Registration Policy | ⬜ | — |
| 6 | Client Role Mappings | ❌ | a user's client-role grants — candidate |
| 7 | Client Scopes | ⬜ | — |
| 8 | Clients | ⬜ | client CRUD |
| 9 | Component | ⬜ | user-federation / key providers |
| 10 | default (realm root) | ❌ | `GET/PUT /admin/realms/{realm}` — planned `KeycloakRealmApi` |
| 11 | **Groups** | 🟡 | `GET /groups` (`KeycloakGroupsApi::listRealmGroups`); group CRUD, `/groups/{id}/members`, children — ❌ |
| 12 | Identity Providers | ⬜ | — |
| 13 | Key | ⬜ | realm keys |
| 14 | Organizations | ⬜ | — |
| 15 | Protocol Mappers | ⬜ | — |
| 16 | **Realms Admin** | 🟡 | `GET /events`, `GET /admin-events` (`KeycloakEventsApi`); realm config + `/health` — ❌ (planned `KeycloakRealmApi`) |
| 17 | Role Mapper | ❌ | a user's realm-role grants — candidate |
| 18 | Roles | ⬜ | realm/client role definitions |
| 19 | Roles (by ID) | ⬜ | — |
| 20 | Scope Mappings | ⬜ | — |
| 21 | **Users** | 🟡 | read + credentials + sessions + membership (see detail below); create/update/delete/reset-password and many sub-resources — ❌ |
| 22 | Workflows | ⬜ | — |
| — | OIDC token endpoint | ✅ | `POST /realms/{realm}/protocol/openid-connect/token` — `ServiceAccountTokenProvider` |

Below: **every** endpoint of the three resource groups we touch, from the Keycloak 26.x OpenAPI —
regenerate anytime with `mise run kc:routes` (the source of truth). `role-mappings` sub-paths (under
both users and groups) are tagged **Role Mapper (#17)** by Keycloak and listed there, not here.

Legend: ✅ implemented · ❌ not implemented (candidate) · ⬜ out of scope for a user-admin client.

#### #21 — Users (paths under `/admin/realms/{realm}`)

| Method & path | Status | Our slice / note |
|---|:--:|---|
| `GET /users` | ✅ | `KeycloakUsersApi::list` |
| `POST /users` | ❌ | create — later slice |
| `GET /users/count` | ✅ | `KeycloakUsersApi::count` |
| `GET /users/profile` | ❌ | user-profile **attribute schema** (Users group) — candidate for a UserProfile slice, **not** realm |
| `PUT /users/profile` | ⬜ | user-profile schema authoring |
| `GET /users/profile/metadata` | ❌ | user-profile metadata (drives proactive form rendering) — candidate, **not** realm |
| `GET /users/{user-id}` | ✅ | `KeycloakUsersApi::getById` |
| `PUT /users/{user-id}` | ❌ | update (enable/disable, username, names) — later slice |
| `DELETE /users/{user-id}` | ⬜ | user deletion — not exposed |
| `GET /users/{user-id}/configured-user-storage-credential-types` | ❌ | — |
| `GET /users/{user-id}/consents` | ❌ | — |
| `DELETE /users/{user-id}/consents/{client}` | ❌ | — |
| `GET /users/{user-id}/credentials` | ✅ | `KeycloakCredentialsApi::get` |
| `DELETE /users/{user-id}/credentials/{credentialId}` | ✅ | `KeycloakCredentialsApi::delete` |
| `POST /users/{user-id}/credentials/{credentialId}/moveAfter/{newPreviousCredentialId}` | ❌ | reorder credential |
| `POST /users/{user-id}/credentials/{credentialId}/moveToFirst` | ❌ | reorder credential |
| `PUT /users/{user-id}/credentials/{credentialId}/userLabel` | ❌ | rename credential |
| `PUT /users/{user-id}/disable-credential-types` | ❌ | — |
| `PUT /users/{user-id}/execute-actions-email` | ✅ | `KeycloakCredentialsApi::executeActionsEmail` (array body) |
| `GET /users/{user-id}/federated-identity` | ❌ | — |
| `POST /users/{user-id}/federated-identity/{provider}` | ❌ | — |
| `DELETE /users/{user-id}/federated-identity/{provider}` | ❌ | — |
| `GET /users/{user-id}/groups` | ✅ | `KeycloakGroupsApi::getUserGroups` |
| `GET /users/{user-id}/groups/count` | ❌ | — |
| `PUT /users/{user-id}/groups/{groupId}` | ✅ | `KeycloakGroupsApi::addUserToGroup` (body-less) |
| `DELETE /users/{user-id}/groups/{groupId}` | ✅ | `KeycloakGroupsApi::removeUserFromGroup` |
| `POST /users/{user-id}/impersonation` | ⬜ | impersonate — not exposed |
| `POST /users/{user-id}/logout` | ✅ | `KeycloakSessionsApi::logoutAll` |
| `GET /users/{user-id}/offline-sessions/{clientUuid}` | ❌ | — |
| `PUT /users/{user-id}/reset-password` | ❌ | direct admin-set password — `execute-actions-email` preferred |
| `PUT /users/{user-id}/reset-password-email` | ❌ | deprecated alias of `execute-actions-email` |
| `PUT /users/{user-id}/send-verify-email` | ❌ | candidate |
| `GET /users/{user-id}/sessions` | ✅ | `KeycloakSessionsApi::getSessions` |
| `GET /users/{user-id}/unmanagedAttributes` | ❌ | — |

#### #11 — Groups (paths under `/admin/realms/{realm}`)

| Method & path | Status | Our slice / note |
|---|:--:|---|
| `GET /groups` | ✅ | `KeycloakGroupsApi::listRealmGroups` |
| `POST /groups` | ⬜ | group CRUD |
| `GET /groups/count` | ❌ | — |
| `GET /groups/{group-id}` | ❌ | single group — candidate |
| `PUT /groups/{group-id}` | ⬜ | group CRUD |
| `DELETE /groups/{group-id}` | ⬜ | group CRUD |
| `GET /groups/{group-id}/children` | ❌ | sub-group listing |
| `POST /groups/{group-id}/children` | ⬜ | sub-group create |
| `GET /groups/{group-id}/members` | ❌ | list users **in** a group (group-filter data source) |
| `GET /groups/{group-id}/management/permissions` | ⬜ | FGAP admin permissions |
| `PUT /groups/{group-id}/management/permissions` | ⬜ | FGAP admin permissions |

#### #16 — Realms Admin (paths under `/admin/realms`)

| Method & path | Status | Our slice / note |
|---|:--:|---|
| `GET /` | ⬜ | list realms |
| `POST /` | ⬜ | create realm |
| `GET /{realm}` | ❌ | realm config (`editUsernameAllowed`, events flags) — planned `KeycloakRealmApi` |
| `PUT /{realm}` | ⬜ | realm config authoring |
| `DELETE /{realm}` | ⬜ | delete realm |
| `GET /{realm}/admin-events` | ✅ | `KeycloakEventsApi::getAdminEventsForUser` |
| `DELETE /{realm}/admin-events` | ⬜ | clear admin events |
| `POST /{realm}/client-description-converter` | ⬜ | — |
| `GET /{realm}/client-policies/policies` | ⬜ | — |
| `PUT /{realm}/client-policies/policies` | ⬜ | — |
| `GET /{realm}/client-policies/profiles` | ⬜ | — |
| `PUT /{realm}/client-policies/profiles` | ⬜ | — |
| `GET /{realm}/client-session-stats` | ⬜ | — |
| `GET /{realm}/client-types` | ⬜ | — |
| `PUT /{realm}/client-types` | ⬜ | — |
| `GET /{realm}/credential-registrators` | ⬜ | — |
| `GET /{realm}/default-default-client-scopes` | ⬜ | — |
| `PUT /{realm}/default-default-client-scopes/{clientScopeId}` | ⬜ | — |
| `DELETE /{realm}/default-default-client-scopes/{clientScopeId}` | ⬜ | — |
| `GET /{realm}/default-groups` | ⬜ | — |
| `PUT /{realm}/default-groups/{groupId}` | ⬜ | — |
| `DELETE /{realm}/default-groups/{groupId}` | ⬜ | — |
| `GET /{realm}/default-optional-client-scopes` | ⬜ | — |
| `PUT /{realm}/default-optional-client-scopes/{clientScopeId}` | ⬜ | — |
| `DELETE /{realm}/default-optional-client-scopes/{clientScopeId}` | ⬜ | — |
| `GET /{realm}/events` | ✅ | `KeycloakEventsApi::getUserEvents` |
| `DELETE /{realm}/events` | ⬜ | clear login events |
| `GET /{realm}/events/config` | ❌ | events flags — planned `KeycloakRealmApi` |
| `PUT /{realm}/events/config` | ⬜ | events config authoring |
| `GET /{realm}/group-by-path/{path}` | ❌ | candidate |
| `GET /{realm}/localization` | ⬜ | realm i18n |
| `GET /{realm}/localization/{locale}` | ⬜ | realm i18n |
| `POST /{realm}/localization/{locale}` | ⬜ | realm i18n |
| `DELETE /{realm}/localization/{locale}` | ⬜ | realm i18n |
| `GET /{realm}/localization/{locale}/{key}` | ⬜ | realm i18n |
| `PUT /{realm}/localization/{locale}/{key}` | ⬜ | realm i18n |
| `DELETE /{realm}/localization/{locale}/{key}` | ⬜ | realm i18n |
| `POST /{realm}/logout-all` | ❌ | candidate (revoke all realm sessions) |
| `POST /{realm}/partial-export` | ⬜ | — |
| `POST /{realm}/partialImport` | ⬜ | — |
| `POST /{realm}/push-revocation` | ⬜ | — |
| `DELETE /{realm}/sessions/{session}` | ❌ | candidate (revoke one session) |
| `POST /{realm}/testSMTPConnection` | ⬜ | realm SMTP config |
| `GET /{realm}/users-management-permissions` | ⬜ | FGAP admin permissions |
| `PUT /{realm}/users-management-permissions` | ⬜ | FGAP admin permissions |

> Not in the tables above because Keycloak tags them as their own groups (all ❌/⬜): **Key (#13)**,
> **Client Initial Access (#4)**, and `role-mappings` under **Role Mapper (#17)**. Reachability is
> `GET /health/ready` on the management port (9000), outside `/admin/realms` entirely.

Every ✅ row is covered by both a unit test (Guzzle-mocked request/response) and an end-to-end test
against a real Keycloak (`tests/Integration`).

## Package layout (feature-first)

```
src/
  Connection/          transport + auth + settings + the one caught exception
    Auth/
  SharedModel/         KeycloakUserId, KeycloakTimestamp, KeycloakCollection (immutable base)
  Features/
    KeycloakUsersApi.php          + KeycloakUsersApi/{…Implementation, Dto/…}
    KeycloakGroupsApi.php         + KeycloakGroupsApi/{…}
    KeycloakCredentialsApi.php    + KeycloakCredentialsApi/{…}
    KeycloakSessionsApi.php       + KeycloakSessionsApi/{…}
    KeycloakEventsApi.php         + KeycloakEventsApi/{…}
```

Each feature keeps its interface, implementation, and DTOs together. Interfaces carry the `Keycloak`
prefix so an imported symbol is self-describing inside a larger host codebase.

## Usage

```php
use GuzzleHttp\Client;
use Sandstorm\KeycloakAdminApi\Connection\Auth\ServiceAccountTokenProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettings;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakSettingsProvider;
use Sandstorm\KeycloakAdminApi\Connection\KeycloakTransport;
use Sandstorm\KeycloakAdminApi\Features\KeycloakUsersApi\KeycloakUsersApiImplementation;

$settings = new class implements KeycloakSettingsProvider {
    public function get(): KeycloakSettings {
        return new KeycloakSettings('https://keycloak.example', 'my-realm', 'admin-api', $secret);
    }
};

// Inject a client that never body-logs — admin responses carry user PII.
$client = new Client(['http_errors' => true]);
$transport = new KeycloakTransport($settings, $client, new ServiceAccountTokenProvider($settings, $client));

$users = new KeycloakUsersApiImplementation($transport);
foreach ($users->list(search: 'jane', first: 0, max: 20, enabled: true) as $user) {
    echo $user->username, ' ', $user->fullName() ?? '', PHP_EOL;
}
```

## Testing

The package is self-contained — it has its own `vendor/` and toolchain (via `mise`, from this directory):

```bash
mise run install          # composer install
mise run test             # unit suite — no I/O, no Keycloak
mise run analyse          # phpstan (level 6)
```

Two tiers:

- **Unit** (`tests/Unit`) — fast, hermetic. Real logic only (DTO/collection parsing tolerance, token cache, array-body
  encoding, query building, the 401/403-vs-propagate taxonomy) driven through a Guzzle mock.
- **Integration / E2E** (`tests/Integration`) — runs the client against a **real Keycloak 26.5.3** in Docker
  (`tests/Integration/docker-compose.yml` imports a self-contained `test-realm`). This proves the wire contract unit
  tests cannot. Opt-in: it skips unless `KEYCLOAK_E2E_BASE_URL` is set.

```bash
mise run e2e              # boot Keycloak, run the integration suite, tear down
# or step by step:
mise run e2e:up
mise run test:integration
mise run e2e:down
```

## License

MIT
