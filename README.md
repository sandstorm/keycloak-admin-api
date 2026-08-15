# sandstorm/keycloak-admin-api

A framework-agnostic PHP client for the **Keycloak Admin REST API** (target: Keycloak 26.5 or newer).

The whole package is work in progress, and is extended as needed.

> Thanks to [BroodfondsMakers](https://www.broodfonds.nl/) for sponsoring the development of this package,
> and for agreeing to Open Source it!

It exposes the admin API in modern PHP, using **immutable typed DTOs and collections**. It has no framework coupling.


<!-- TOC -->

* [sandstorm/keycloak-admin-api](#sandstormkeycloak-admin-api)
    * [Requirements](#requirements)
    * [Usage](#usage)
    * [Keycloak REST API coverage](#keycloak-rest-api-coverage)
        * [#21 - Users (paths under `/admin/realms/{realm}`)](#21---users-paths-under-adminrealmsrealm)
        * [#11 - Groups (paths under `/admin/realms/{realm}`)](#11---groups-paths-under-adminrealmsrealm)
        * [#16 - Realms Admin (paths under `/admin/realms`)](#16---realms-admin-paths-under-adminrealms)
    * [Development Ideas](#development-ideas)
        * [Package layout (feature-first)](#package-layout-feature-first)
    * [Unit and Integration Tests](#unit-and-integration-tests)
    * [License](#license)

<!-- TOC -->

## Requirements

- PHP 8.3+
- A PSR-18 HTTP client, like Guzzle

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

// Inject a client that never body-logs - admin responses carry user PII.
$client = new Client(['http_errors' => true]);
$transport = new KeycloakTransport($settings, $client, new ServiceAccountTokenProvider($settings, $client));

$users = new KeycloakUsersApiImplementation($transport);
foreach ($users->list(search: 'jane', first: 0, max: 20, enabled: true) as $user) {
    echo $user->username, ' ', $user->fullName() ?? '', PHP_EOL;
}
```

## Keycloak REST API coverage

This is based on the [Keycloak Admin REST API](https://www.keycloak.org/docs-api/latest/rest-api/index.html):
The vast majority is **not** implemented, because so far this is focused upon **user-administration**.

Legend: ✅ implemented · 🟡 partial · ❌ not implemented (candidate)

|  # | KC resource group            | Status | What we cover / our slice                                                                                                      |
|---:|------------------------------|:------:|--------------------------------------------------------------------------------------------------------------------------------|
|  1 | Attack Detection             |   ❌   | brute-force status/clear - none                                                                                                |
|  2 | Authentication Management    |   ❌   | realm auth-flow config                                                                                                         |
|  3 | Client Attribute Certificate |   ❌   | client keystores                                                                                                               |
|  4 | Client Initial Access        |   ❌   | dynamic client registration tokens                                                                                             |
|  5 | Client Registration Policy   |   ❌   | -                                                                                                                              |
|  6 | Client Role Mappings         |   ❌   | a user's client-role grants - candidate                                                                                        |
|  7 | Client Scopes                |   ❌   | -                                                                                                                              |
|  8 | Clients                      |   ❌   | client CRUD                                                                                                                    |
|  9 | Component                    |   ❌   | user-federation / key providers                                                                                                |
| 10 | default (realm root)         |   ❌   | `GET/PUT /admin/realms/{realm}` - planned `KeycloakRealmApi`                                                                   |
| 11 | **Groups**                   |   🟡   | `GET /groups` (`KeycloakGroupsApi::listRealmGroups`); group CRUD, `/groups/{id}/members`, children - ❌                        |
| 12 | Identity Providers           |   ❌   | -                                                                                                                              |
| 13 | Key                          |   ❌   | realm keys                                                                                                                     |
| 14 | Organizations                |   ❌   | -                                                                                                                              |
| 15 | Protocol Mappers             |   ❌   | -                                                                                                                              |
| 16 | **Realms Admin**             |   🟡   | `GET /events`, `GET /admin-events` (`KeycloakEventsApi`); realm config + `/health` - ❌ (planned `KeycloakRealmApi`)           |
| 17 | Role Mapper                  |   ❌   | a user's realm-role grants - candidate                                                                                         |
| 18 | Roles                        |   ❌   | realm/client role definitions                                                                                                  |
| 19 | Roles (by ID)                |   ❌   | -                                                                                                                              |
| 20 | Scope Mappings               |   ❌   | -                                                                                                                              |
| 21 | **Users**                    |   🟡   | read + credentials + sessions + membership (see detail below); create/update/delete/reset-password and many sub-resources - ❌ |
| 22 | Workflows                    |   ❌   | -                                                                                                                              |
|  - | OIDC token endpoint          |   ✅   | `POST /realms/{realm}/protocol/openid-connect/token` - `ServiceAccountTokenProvider`                                           |

### #21 - Users (paths under `/admin/realms/{realm}`)

| Method & path                                                                          | Status | Notes                                                         |
|----------------------------------------------------------------------------------------|:------:|---------------------------------------------------------------|
| `GET /users`                                                                           |   ✅   | `KeycloakUsersApi::list`                                      |
| `POST /users`                                                                          |   ❌   | create                                                        |
| `GET /users/count`                                                                     |   ✅   | `KeycloakUsersApi::count`                                     |
| `GET /users/profile`                                                                   |   ❌   | user-profile **attribute schema** (Users group)               |
| `PUT /users/profile`                                                                   |   ❌   | user-profile schema authoring                                 |
| `GET /users/profile/metadata`                                                          |   ❌   | user-profile metadata (drives proactive form rendering)       |
| `GET /users/{user-id}`                                                                 |   ✅   | `KeycloakUsersApi::getById`                                   |
| `PUT /users/{user-id}`                                                                 |   ❌   | update (enable/disable, username, names)                      |
| `DELETE /users/{user-id}`                                                              |   ❌   | user deletion                                                 |
| `GET /users/{user-id}/configured-user-storage-credential-types`                        |   ❌   | -                                                             |
| `GET /users/{user-id}/consents`                                                        |   ❌   | -                                                             |
| `DELETE /users/{user-id}/consents/{client}`                                            |   ❌   | -                                                             |
| `GET /users/{user-id}/credentials`                                                     |   ✅   | `KeycloakCredentialsApi::get`                                 |
| `DELETE /users/{user-id}/credentials/{credentialId}`                                   |   ✅   | `KeycloakCredentialsApi::delete`                              |
| `POST /users/{user-id}/credentials/{credentialId}/moveAfter/{newPreviousCredentialId}` |   ❌   | reorder credential                                            |
| `POST /users/{user-id}/credentials/{credentialId}/moveToFirst`                         |   ❌   | reorder credential                                            |
| `PUT /users/{user-id}/credentials/{credentialId}/userLabel`                            |   ❌   | rename credential                                             |
| `PUT /users/{user-id}/disable-credential-types`                                        |   ❌   | -                                                             |
| `PUT /users/{user-id}/execute-actions-email`                                           |   ✅   | `KeycloakCredentialsApi::executeActionsEmail` (array body)    |
| `GET /users/{user-id}/federated-identity`                                              |   ❌   | -                                                             |
| `POST /users/{user-id}/federated-identity/{provider}`                                  |   ❌   | -                                                             |
| `DELETE /users/{user-id}/federated-identity/{provider}`                                |   ❌   | -                                                             |
| `GET /users/{user-id}/groups`                                                          |   ✅   | `KeycloakGroupsApi::getUserGroups`                            |
| `GET /users/{user-id}/groups/count`                                                    |   ❌   | -                                                             |
| `PUT /users/{user-id}/groups/{groupId}`                                                |   ✅   | `KeycloakGroupsApi::addUserToGroup` (body-less)               |
| `DELETE /users/{user-id}/groups/{groupId}`                                             |   ✅   | `KeycloakGroupsApi::removeUserFromGroup`                      |
| `POST /users/{user-id}/impersonation`                                                  |   ❌   | impersonate                                                   |
| `POST /users/{user-id}/logout`                                                         |   ✅   | `KeycloakSessionsApi::logoutAll`                              |
| `GET /users/{user-id}/offline-sessions/{clientUuid}`                                   |   ❌   | -                                                             |
| `PUT /users/{user-id}/reset-password`                                                  |   ❌   | direct admin-set password - `execute-actions-email` preferred |
| `PUT /users/{user-id}/reset-password-email`                                            |   ❌   | deprecated alias of `execute-actions-email`                   |
| `PUT /users/{user-id}/send-verify-email`                                               |   ❌   |                                                               |
| `GET /users/{user-id}/sessions`                                                        |   ✅   | `KeycloakSessionsApi::getSessions`                            |
| `GET /users/{user-id}/unmanagedAttributes`                                             |   ❌   | -                                                             |

### #11 - Groups (paths under `/admin/realms/{realm}`)

| Method & path                                   | Status | Notes                                                |
|-------------------------------------------------|:------:|------------------------------------------------------|
| `GET /groups`                                   |   ✅   | `KeycloakGroupsApi::listRealmGroups`                 |
| `POST /groups`                                  |   ❌   | group CRUD                                           |
| `GET /groups/count`                             |   ❌   | -                                                    |
| `GET /groups/{group-id}`                        |   ❌   | single group                                         |
| `PUT /groups/{group-id}`                        |   ❌   | group CRUD                                           |
| `DELETE /groups/{group-id}`                     |   ❌   | group CRUD                                           |
| `GET /groups/{group-id}/children`               |   ❌   | sub-group listing                                    |
| `POST /groups/{group-id}/children`              |   ❌   | sub-group create                                     |
| `GET /groups/{group-id}/members`                |   ❌   | list users **in** a group (group-filter data source) |
| `GET /groups/{group-id}/management/permissions` |   ❌   | FGAP admin permissions                               |
| `PUT /groups/{group-id}/management/permissions` |   ❌   | FGAP admin permissions                               |

### #16 - Realms Admin (paths under `/admin/realms`)

| Method & path                                                    | Status | Notes                                                                           |
|------------------------------------------------------------------|:------:|---------------------------------------------------------------------------------|
| `GET /`                                                          |   ❌   | list realms                                                                     |
| `POST /`                                                         |   ❌   | create realm                                                                    |
| `GET /{realm}`                                                   |   ❌   | realm config (`editUsernameAllowed`, events flags) - planned `KeycloakRealmApi` |
| `PUT /{realm}`                                                   |   ❌   | realm config authoring                                                          |
| `DELETE /{realm}`                                                |   ❌   | delete realm                                                                    |
| `GET /{realm}/admin-events`                                      |   ✅   | `KeycloakEventsApi::getAdminEventsForUser`                                      |
| `DELETE /{realm}/admin-events`                                   |   ❌   | clear admin events                                                              |
| `POST /{realm}/client-description-converter`                     |   ❌   | -                                                                               |
| `GET /{realm}/client-policies/policies`                          |   ❌   | -                                                                               |
| `PUT /{realm}/client-policies/policies`                          |   ❌   | -                                                                               |
| `GET /{realm}/client-policies/profiles`                          |   ❌   | -                                                                               |
| `PUT /{realm}/client-policies/profiles`                          |   ❌   | -                                                                               |
| `GET /{realm}/client-session-stats`                              |   ❌   | -                                                                               |
| `GET /{realm}/client-types`                                      |   ❌   | -                                                                               |
| `PUT /{realm}/client-types`                                      |   ❌   | -                                                                               |
| `GET /{realm}/credential-registrators`                           |   ❌   | -                                                                               |
| `GET /{realm}/default-default-client-scopes`                     |   ❌   | -                                                                               |
| `PUT /{realm}/default-default-client-scopes/{clientScopeId}`     |   ❌   | -                                                                               |
| `DELETE /{realm}/default-default-client-scopes/{clientScopeId}`  |   ❌   | -                                                                               |
| `GET /{realm}/default-groups`                                    |   ❌   | -                                                                               |
| `PUT /{realm}/default-groups/{groupId}`                          |   ❌   | -                                                                               |
| `DELETE /{realm}/default-groups/{groupId}`                       |   ❌   | -                                                                               |
| `GET /{realm}/default-optional-client-scopes`                    |   ❌   | -                                                                               |
| `PUT /{realm}/default-optional-client-scopes/{clientScopeId}`    |   ❌   | -                                                                               |
| `DELETE /{realm}/default-optional-client-scopes/{clientScopeId}` |   ❌   | -                                                                               |
| `GET /{realm}/events`                                            |   ✅   | `KeycloakEventsApi::getUserEvents`                                              |
| `DELETE /{realm}/events`                                         |   ❌   | clear login events                                                              |
| `GET /{realm}/events/config`                                     |   ❌   | events flags                                                                    |
| `PUT /{realm}/events/config`                                     |   ❌   | events config authoring                                                         |
| `GET /{realm}/group-by-path/{path}`                              |   ❌   | candidate                                                                       |
| `GET /{realm}/localization`                                      |   ❌   | realm i18n                                                                      |
| `GET /{realm}/localization/{locale}`                             |   ❌   | realm i18n                                                                      |
| `POST /{realm}/localization/{locale}`                            |   ❌   | realm i18n                                                                      |
| `DELETE /{realm}/localization/{locale}`                          |   ❌   | realm i18n                                                                      |
| `GET /{realm}/localization/{locale}/{key}`                       |   ❌   | realm i18n                                                                      |
| `PUT /{realm}/localization/{locale}/{key}`                       |   ❌   | realm i18n                                                                      |
| `DELETE /{realm}/localization/{locale}/{key}`                    |   ❌   | realm i18n                                                                      |
| `POST /{realm}/logout-all`                                       |   ❌   |                                                                                 |
| `POST /{realm}/partial-export`                                   |   ❌   | -                                                                               |
| `POST /{realm}/partialImport`                                    |   ❌   | -                                                                               |
| `POST /{realm}/push-revocation`                                  |   ❌   | -                                                                               |
| `DELETE /{realm}/sessions/{session}`                             |   ❌   |                                                                                 |
| `POST /{realm}/testSMTPConnection`                               |   ❌   | realm SMTP config                                                               |
| `GET /{realm}/users-management-permissions`                      |   ❌   | FGAP admin permissions                                                          |
| `PUT /{realm}/users-management-permissions`                      |   ❌   | FGAP admin permissions                                                          |

## Development Ideas

### Package layout (feature-first)

```
src/
  Connection/          transport + auth
    Auth/
  SharedModel/         KeycloakUserId, KeycloakTimestamp, KeycloakCollection (core value objects)
  Features/
    KeycloakUsersApi.php          + KeycloakUsersApi/{…Implementation, Dto/…}
    KeycloakGroupsApi.php         + KeycloakGroupsApi/{…}
    KeycloakCredentialsApi.php    + KeycloakCredentialsApi/{…}
    KeycloakSessionsApi.php       + KeycloakSessionsApi/{…}
    KeycloakEventsApi.php         + KeycloakEventsApi/{…}
```

Each feature keeps its interface, implementation, and DTOs together. Interfaces carry the `Keycloak`
prefix so an imported symbol is self-describing inside a larger host codebase.

## Unit and Integration Tests

```bash
mise run install          # composer install
mise run test             # unit suite - no I/O, no Keycloak
mise run analyse          # phpstan (level 6)
```

Two tiers:

- **Unit** (`tests/Unit`) - fast, hermetic. Real logic only (DTO/collection parsing tolerance, token cache, array-body
  encoding, query building, the 401/403-vs-propagate taxonomy) driven through a Guzzle mock.
- **Integration / E2E** (`tests/Integration`) - runs the client against a **real Keycloak 26.5.3** in Docker
  (`tests/Integration/docker-compose.yml` imports a self-contained `test-realm`). This proves the wire contract unit
  tests cannot.

```bash
mise run e2e              # boot Keycloak, run the integration suite, tear down
# or step by step:
mise run e2e:up
mise run test:integration
mise run e2e:down
```

## License

MIT
