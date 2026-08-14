# Standalone Keycloak Admin API package — concept

**Date:** 2026-08-14 · **Supersedes** the "shared adapter" part of
`2026-08-12-keycloak-filament-extension-initial-plan.md` §3.1.

Extract `shared/KeycloakAdmin` into its **own composer library**:
`broodfonds/keycloak-admin-api`. Framework-agnostic, PSR-only external deps, tested end-to-end against a **real
Keycloak**. The Filament v4 plugin stays a separate package that depends on this one.

---

## 1. Why extract, and what changes

The code already lives at `shared/KeycloakAdmin` (namespace `Shared\KeycloakAdmin`)
and is autoloaded via `Shared\ => ../shared` in both `api/` and `admin/`. That is a *monorepo path*, not a package
boundary — no `composer.json`, no declared deps, no independent test suite, no version. Extraction gives it:

- **Clear external dependencies** (§4) instead of implicit "whatever the app has".
- **Its own test suite** including **E2E against real Keycloak** (§6) — the library owns the Keycloak wire contract, so
  the contract test belongs here, not in the app.
- A published/versioned artifact the plugin, `api/`, and future consumers pin.

Two structural changes ride along with the move:

1. **Type-first → feature-first layout.** Today the tree splits by *type*
   (`Contracts/`, `Impl/`, `Dto/`, `Auth/`) — the reader must open three folders to understand one feature. We split by
   *domain* first (`Features/KeycloakEventsApi`,
   `Features/KeycloakUsersApi`, …), keeping an interface, its implementation, and its DTOs **next to each other**
   (proximity/locality). Shared vocabulary lives in one
   `SharedModel/` folder.
2. **Drop the legacy flat facade.** The old `KeycloakAdminApiClient` interface (`ping`, `findUserByUsername`,
   `createUser`, `updateUserAttributes`,
   `setUserCredentials`) + its `…Implementation` have **no non-vendor consumers left**
   (verified: only the segregated `*Api` interfaces are injected). Fold its methods onto the right feature interface and
   **delete the old path** — no compat shim (clean evolution: refactor, don't keep the fork alive).

---

## 1a. Build vs buy — why not an off-the-shelf client

Evaluated the two maintained community clients before committing to our own. **Decision: build our own.** Neither fits
this codebase's load-bearing requirements.

### `mohammad-waleed/keycloak-admin-client` — rejected

Unmaintained (no release since 2024), Guzzle-hardwired, returns **raw arrays** (no typed DTOs), no Keycloak 26.5.3
validation. Dead end.

### `fschmtt/keycloak-rest-api-client-php` — real library, but blocked

Active (v0.42.0, Jun 2026), PHP ^8.2, MIT, typed representations. Evaluated seriously, but it misses what this design is
built on:

| Requirement (this codebase)                          | fschmtt                                                                      | Impact                                                                                                         |
|------------------------------------------------------|------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------|
| **`sso` act-as-user — inject a pre-obtained bearer** | ❌ `GrantType` only (password / client_credentials); no token-injection seam | **Decisive.** Kills the per-person-attribution design (invariants #1–3). Would require forking its auth layer. |
| User **sessions** / logout-all                       | ❌ not supported                                                             | Missing feature #10 + active-sessions panel                                                                    |
| **Login events** (`type=LOGIN`)                      | ❌ not supported (admin-events ✅)                                           | Missing "recent login events" panel                                                                            |
| **PSR-18** injected client + PII-safe app logging    | ❌ Guzzle-hardwired                                                          | Contradicts the clean-deps goal (§4)                                                                           |
| Direct `reset-password`                              | ❌ not explicit                                                              | Missing feature #8                                                                                             |
| Dependency weight                                    | symfony/serializer + property-access + lcobucci/jwt                          | Heavy for a thin transport                                                                                     |
| API stability                                        | 0.x, pre-1.0                                                                 | Breaking changes expected                                                                                      |
| DTO shape                                            | generic representations                                                      | We'd wrap them anyway for `isSecondFactor()`, three-state events, formatting                                   |

Two gaps alone sink it — **no bearer injection** (breaks `sso` mode entirely) and **no sessions/login-events**. Bearer
injection is not a config knob there; it needs a fork.

### Why own wins

The transport is thin (~300 lines, already written and working against 26.5.3). The real value — our DTOs, **dual auth
with no fallback**, PSR-18 abstraction, PII-safe logging, small fakeable feature interfaces — is exactly what neither
library provides. Adopting fschmtt means a heavy dependency **plus** a forked auth layer, to avoid code we already own.

**Reuse we will take:** crib fschmtt's representation shapes as a reference when hardening our `fromRawResponse()`
parsers. That is the only worthwhile borrow.

---

## 2. Package identity

| Field              | Value                                                             |
|--------------------|-------------------------------------------------------------------|
| Composer name      | `sandstorm/keycloak-admin-api`                                    |
| Type               | `library`                                                         |
| Root namespace     | `Sandstorm\KeycloakAdminApi\` → `src/`                            |
| PHP                | `^8.3`                                                            |
| Framework coupling | **none** — no Laravel, no `heloufir/keycloak-sso`, no app classes |
| Keycloak target    | 26.5.3 (Admin REST API)                                           |
| In-tree location   | `admin/DistributionPackages/keycloak-admin-api` (sibling of the consuming plugin) |
| Toolchain          | self-contained — own `vendor/`, own `mise.toml`, PHPUnit + PHPStan (level 6) |

Guiding principle throughout: **deep modules** (Ousterhout). A caller learns one small interface (`KeycloakUsersApi`, a
`KeycloakUserId`, a `KeycloakUser` DTO) and stays ignorant of transport, token refresh, JSON tolerance, and pagination
arithmetic behind it.

> **Status (implemented):** the package described below is built, standalone, and green — 42 unit
> tests + PHPStan level 6 pass on its own toolchain; the E2E suite (§6) runs against a real Keycloak
> in Docker and is opt-in. See `README.md` for the API-coverage table. Remaining gaps
> (`KeycloakRealmApi`, user-write DTOs) are tracked in §8.

---

## 3. Target structure

Feature-first. Each feature is a folder holding **the interface at the top**
(the general thing callers see), and everything specific — implementation + DTOs — **below it** (importance flows down
the tree).

```
keycloak-admin-api/
  composer.json
  README.md
  src/
    Connection/                          # the transport + auth "adapter" plumbing (one deep module)
      KeycloakTransport.php              # get/getJson/postJson/putJson/putList/put/delete
      KeycloakSettings.php               # connection value object (baseUrl, realm, clientId, secret)
      KeycloakSettingsProvider.php       # seam: get(): KeycloakSettings  (app supplies the impl)
      KeycloakAuthenticationException.php# the ONE caught type (401/403); extends RuntimeException
      Auth/
        KeycloakTokenProvider.php        # interface: currentBearer(): string
        ServiceAccountTokenProvider.php  # client_credentials impl (token cache lives here)
        KeycloakAccessToken.php          # token + expiry VO (isStillValidNow)

    SharedModel/                         # shared vocabulary — value objects used across features
      UserId.php                         # was KeycloakUserId
      GroupId.php                        # NEW VO — replaces bare `string $groupId`
      CredentialId.php                   # NEW VO — replaces bare `string $credentialId`
      Timestamp.php                      # was KeycloakTimestamp (epoch-ms formatting)

    Features/                            # interfaces keep the `Keycloak` prefix — redundant within the
                                         #   package, but self-describing at external call sites (§3.3)
      KeycloakUsersApi.php               # interface: list→KeycloakUsers, count, getById, findByUsername,
                                         #   create, updateAttributes, setCredentials
      KeycloakUsersApi/
        KeycloakUsersApiImplementation.php
        Dto/
          KeycloakUser.php               # id, username, email, names, enabled, requiredActions, attributes…
          KeycloakUsers.php              # immutable typed collection of KeycloakUser (§5.1)
          CreateKeycloakUserCommand.php

      KeycloakGroupsApi.php              # getUserGroups→KeycloakGroups, listRealmGroups→KeycloakGroups, add, remove
      KeycloakGroupsApi/
        KeycloakGroupsApiImplementation.php
        Dto/
          KeycloakGroup.php
          KeycloakGroups.php             # immutable typed collection

      KeycloakCredentialsApi.php         # get→KeycloakCredentials, executeActionsEmail, delete, resetPassword
      KeycloakCredentialsApi/
        KeycloakCredentialsApiImplementation.php
        Dto/
          KeycloakCredential.php
          KeycloakCredentials.php        # immutable typed collection (+ hasSecondFactor(), etc.)

      KeycloakSessionsApi.php            # getSessions→KeycloakSessions, logoutAll
      KeycloakSessionsApi/
        KeycloakSessionsApiImplementation.php
        Dto/
          KeycloakSession.php
          KeycloakSessions.php           # immutable typed collection

      KeycloakEventsApi.php              # getUserEvents→KeycloakUserEvents, getAdminEventsForUser→KeycloakAdminEvents
      KeycloakEventsApi/
        KeycloakEventsApiImplementation.php
        Dto/
          KeycloakUserEvent.php
          KeycloakUserEvents.php         # immutable typed collection
          KeycloakAdminEvent.php
          KeycloakAdminEvents.php        # immutable typed collection
  tests/
    Unit/                                # real logic only — no HTTP (§6.1)
    Integration/                         # E2E vs real Keycloak (§6.2)
    Fixtures/                            # recorded 26.5.3 JSON + realm-import.json
```

### 3.1 Why this shape

- **Proximity.** `KeycloakEventsApi.php` + `KeycloakEventsApi/KeycloakEventsApiImplementation.php` +
  `KeycloakEventsApi/Dto/KeycloakUserEvent.php` sit together. Understanding events means reading one folder, not hopping
  across `Contracts/`, `Impl/`, `Dto/`.
- **Interface at the top of its subtree.** The interface is the most-general, most-imported thing → it names the folder
  and sits at its root. The concrete impl and DTOs are deeper detail.
- **`SharedModel/` = the shared language.** VOs that more than one feature speaks (`UserId`, `Timestamp`) live in
  exactly one place, mirroring Neos
  `ContentRepository.Core/SharedModel` (`NodeAggregateId`, `NodeName`).
- **`Connection/` = one deep module for I/O.** Transport, settings, token, and the auth exception change together and
  only make sense together. Callers of a feature never touch it directly; it is pure implementation depth.
- **Plurals are real types, next to their singular.** `KeycloakUsers` sits beside `KeycloakUser` in the same `Dto/`
  folder — no method ever returns a bare `array` (§5.1), mirroring Neos `NodeAggregateIds`/`PropertyNames`.

### 3.2 Facade — deliberately omitted

The v2 plan floated a `KeycloakAdminApi` facade with `users()/groups()/…` accessors. **Skip it.** A class that only
returns already-injected sub-interfaces is *shallow* — it adds interface surface and hides nothing. Consumers already
inject the segregated
`KeycloakUsersApi`, `KeycloakEventsApi`, … directly (interface segregation via DI), which is the deeper design. Add an
aggregator later only if a real caller needs all slices at once.

### 3.3 Naming — keep the `Keycloak` prefix on the feature interfaces

The feature interfaces stay `KeycloakUsersApi`, `KeycloakGroupsApi`, `KeycloakEventsApi`, … — **not** the bare
`UsersApi`. Within this package the prefix is redundant (the namespace already says Keycloak), but these interfaces are
the package's **public seam**: consuming apps type-hint and DI-bind them inside a large codebase where a bare `UsersApi`
would be ambiguous or collide with the app's own user layer. The prefix makes an imported symbol self-describing at the
call site — worth the redundancy for a library boundary. DTOs (`KeycloakUser`, `KeycloakGroup`, …) already carry the
prefix for the same reason; **keep it** (this reverses the earlier "drop the prefix" idea in §5).

---

## 4. External dependencies (the "clear ext deps" ask)

> **Implemented state:** the package requires `guzzlehttp/guzzle` (^7.3) + `psr/http-message` + `ext-json`
> and **injects** the built client — a clear, explicit dependency set. The full PSR-18 abstraction below
> (dropping the direct Guzzle type from the transport) is the *target*, deferred and tracked in §8, so the
> in-tree move stayed low-risk. What ships today already satisfies "clear ext deps": no app classes, no
> Laravel, client injected by the consumer.

The library declares exactly what it needs and injects the rest. Today the transport speaks Guzzle types directly; the
clean-lib move is to depend on **PSR interfaces**
and let the consumer pick the concrete client.

```jsonc
// composer.json (excerpt)
"require": {
    "php": "^8.3",
    "psr/log": "^3.0",                 // LoggerInterface — injected, PII-safe info-only logging
    "psr/http-client": "^1.0",         // ClientInterface (PSR-18) — the HTTP client, injected
    "psr/http-message": "^2.0",        // RequestInterface/ResponseInterface
    "psr/http-factory": "^1.0",        // Request/Stream factories (PSR-17) to build requests
    "ext-json": "*"
},
"require-dev": {
    "guzzlehttp/guzzle": "^7.8",       // concrete PSR-18 client used in tests
    "phpunit/phpunit": "^11.0",
    "phpstan/phpstan": "^2.0"
},
"suggest": {
    "guzzlehttp/guzzle": "Any PSR-18 client works; Guzzle is the reference impl."
}
```

- **No app classes.** `LazySettings`, `LoggingGuzzleClientFactory`,
  `BroodfondsApiLogging` never appear — the consumer builds the PSR-18 client (with whatever logging middleware it
  wants) and passes it in. Preserves the PII-safe "info-only" behaviour by *convention on the consumer side*, not by
  reaching into an app factory.
- **Transport refactor:** `KeycloakTransport` takes `Psr\Http\Client\ClientInterface`
    + PSR-17 factories instead of a Guzzle `Client`. Internally it builds PSR-7 requests. Small, one-time change;
      removes the last hard Guzzle coupling.
- **Arch test** (kept from the plan): assert `src/` imports nothing from `Domain\`,
  `Illuminate\`, `Filament\`, or `heloufir\`.

---

## 5. Value objects — depth improvements to take during the move

### 5.1 Typed collections — no method returns a bare `array` (decided)

Every list-returning method returns an **immutable typed collection**, never
`array`/`list<T>`. `KeycloakUsersApi::list()` returns `KeycloakUsers`, not
`list<KeycloakUser>`. Each collection lives beside its item DTO in the same `Dto/`
folder (§3 structure). Rationale (guideline §5): a raw `array` guarantees nothing
about its contents and gives behaviour no home; a typed collection is valid by
construction and is where list-level behaviour belongs.

Shape — one small immutable base, one subclass per element type:

```php
final readonly class KeycloakUsers implements IteratorAggregate, Countable
{
    /** @var list<KeycloakUser> */
    private array $items;

    private function __construct(KeycloakUser ...$items) { $this->items = array_values($items); }

    /** @param array<int, array<string, mixed>> $raw  the decoded /users response */
    public static function fromRawResponse(array $raw): self
    {
        return new self(...array_map(KeycloakUser::fromRawResponse(...), $raw));
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->items); }
    public function count(): int               { return count($this->items); }
    public function isEmpty(): bool            { return $this->items === []; }
    // add(KeycloakUser): self → returns a NEW collection (never mutates)
}
```

- **Immutable** like the DTOs: `add()`/`filter()` return a new instance.
- **A home for list behaviour** the plugin needs: `KeycloakCredentials::hasSecondFactor()`,
  `KeycloakCredentials::removable()` (whitelist §5.4.3), `KeycloakSessions::mostRecent()`.
  This is where the credential-whitelist and three-state logic naturally sit — on the
  collection, not spread across the UI.
- **Parsing moves onto the collection.** `KeycloakUsers::fromRawResponse()` owns the
  "decode a JSON list" tolerance; the impl just calls it — the transport stays a proxy.
- **`count()` endpoint stays separate.** `KeycloakUsersApi::count()` still hits
  `/users/count` (server-side total for pagination) — the collection's `count()` is
  the size of *this page*, a different number. Don't conflate them.

### 5.2 More value objects to introduce during the move

- **`GroupId`, `CredentialId`.** Signatures today pass bare strings (`addUserToGroup(UserId, string $groupId)`,
  `delete(UserId, string $credentialId)`). A bare string is valid nowhere and names nothing. Introduce VOs in
  `SharedModel/`; they read as vocabulary and make wrong-argument-order bugs impossible.
- **Keep the `Keycloak` prefix** on feature interfaces and DTOs (`KeycloakUsersApi`, `KeycloakUser`, …) — see §3.3. It
  is redundant inside the package but self-describing at external call sites, which is what a library boundary wants.

Everything else already follows the guidelines: `final readonly` DTOs, private-ctor +
`fromRawResponse()` named constructors, behaviour on the DTO (`fullName()`,
`isSecondFactor()`, `formattedStart()`), tolerant parsing.

---

## 5a. Documentation & comment conventions

Comments in this package are **why-comments, not how-comments** (antirez / Ousterhout §3). The code
already says *what* it does; a comment earns its place only by capturing something the code cannot —
a constraint, a tradeoff, a non-obvious reason. The rules the implementation follows:

- **No comment that restates the signature.** `fullName(): ?string` needs no "returns the full name"
  docblock; `get(KeycloakUserId): KeycloakCredentials` needs no prose. If the method name and types
  already say it, write **nothing**.
- **Class-level docblock carries the rationale.** Each class either states *why it exists / what
  invariant it guards / what it deliberately hides*, or is self-evident. Examples in this package:
  the array-body `putList` (why not `putJson`), the body-less `put` (a JSON body 500s Keycloak), the
  three-state event contract, the credential `id` nullability.
- **Consolidate repeated `@throws` at the interface level.** The 401/403 →
  `KeycloakAuthenticationException` taxonomy is stated **once** in each feature interface's class
  docblock, not re-pasted onto every method. Per-method `@throws` only appears where a method deviates.
- **Keep annotations that add type information.** `@param array<string, list<string>>`,
  `@extends KeycloakCollection<KeycloakUser>`, `@param-out` — these feed the type checker (level 6)
  and are not prose, so they stay even when the sentence around them is deleted.
- **Inline comments mark the non-obvious only.** `briefRepresentation` (why the list omits
  attributes), the `resourcePath` trailing-`*` (exact-vs-prefix match), the bearer resolved *before*
  the try-block (so a token failure is not mis-wrapped). Everything a reader can see is left uncommented.

The litmus test applied to every comment: *if deleting it loses no information a competent reader
couldn't recover from the code, delete it.*

## 6. Testing — two tiers, real Keycloak for the contract

**Framework: plain PHPUnit** (the library is framework-agnostic — no Pest, no Laravel test harness). The Filament plugin
is a separate package and keeps its own tooling.

**Principle (unchanged): test where logic lives; don't test the passthrough.** The transport is a thin proxy — but the
*wire contract* (does KC 26.5.3 actually accept this URL/verb/body and return this shape?) is exactly what unit tests
**cannot** prove. That gap is what the E2E tier closes.

### 6.1 Tier 1 — Unit (fast, no I/O)

Only the bits with branching/transformation:

- **DTO `fromRawResponse()` tolerance** — missing/extra fields, attribute-list normalization (the `api/` import depends
  on this being forgiving).
- **Token cache** — hit / near-expiry refresh / safety margin (`KeycloakAccessToken`).
- **Array-body encoding** — `executeActionsEmail` emits `["UPDATE_PASSWORD"]`, not
  `{"0":"UPDATE_PASSWORD"}` (the `putList` path).
- **Query building** — `list()`/`count()` map `first/max/enabled/search` correctly.
- **Value objects** — `UserId`/`GroupId`/`CredentialId` construction + equality.

### 6.2 Tier 2 — E2E against a real Keycloak (the contract net)

Yes — **run against a real Keycloak**, containerized, in CI. This is the library's headline safety net and the reason
the tests belong *here* rather than in the app.

- **How:** a `docker compose` service (or Testcontainers-PHP) boots
  `quay.io/keycloak/keycloak:26.5.3` in dev mode with `--import-realm`, importing a fixture
  `tests/Fixtures/realm-import.json` that pre-provisions:
    - a realm with events + admin-events enabled, `editUsernameAllowed` set,
    - a **service-account client** with the `realm-management` roles,
    - a couple of seed users, a group, and a user with an OTP credential.
- **What it exercises** (the things unit tests can't):
    - every verb/path we send actually 2xx's (DELETE from-group + delete-credential, array-body `execute-actions-email`,
      read-modify-write PUT),
    - pagination params (`first`/`max`) and `/users/count` line up,
    - `ServiceAccountTokenProvider` gets a real `client_credentials` token and the 401→single-refresh path works,
    - 403 → `KeycloakAuthenticationException` (point a low-privilege client at a protected endpoint),
    - `fromRawResponse()` parses **real** 26.5.3 payloads, not hand-written fixtures that can drift from the server.
- **Cadence:** PHPUnit `#[Group('integration')]`, **opt-in locally**
  (`KEYCLOAK_E2E_BASE_URL` present → run; absent → skip), **required in CI** via a service container. No live *shared*
  KC — each run boots a throwaway realm, so tests are isolated and repeatable.
- **`sso` mode is out of scope for the library's E2E.** The library only ships the
  `ServiceAccountTokenProvider`; the SSO provider is Laravel/heloufir-coupled and lives in the plugin, tested there with
  fakes (per the existing plan §7 Tier 3).

The **Filament plugin keeps its own** fake-adapter smoke tests (plan §7 Tier 3) — binding in-memory fakes of these
feature interfaces. Small, stable interfaces make that easy; the E2E tier here guarantees the real impls behind those
interfaces actually match Keycloak.

---

## 7. Migration path (monorepo → package, no big-bang)

Each step is independently green; keep every public signature working until the final sweep so consumers never break
mid-flight.

1. **Scaffold the package** at `packages/keycloak-admin-api/` (or a sibling repo) with
   `composer.json`, PSR-4 `Broodfonds\KeycloakAdminApi\`, PHPUnit, phpstan. Add it to the root as a Composer `path`
   repository so `admin/` resolves it locally while it still lives in-tree.
2. **Move + restructure** `shared/KeycloakAdmin` → `src/` in the new feature-first layout (§3); rename namespace
   `Shared\KeycloakAdmin` → `Broodfonds\KeycloakAdminApi`. Mechanical; a scripted find/replace plus the folder moves.
3. **PSR-18 transport refactor** (§4) — inject `ClientInterface` + PSR-17 factories, drop the Guzzle type from the
   signature.
4. **Delete the legacy flat facade** (§1.2); fold its methods onto `KeycloakUsersApi`. Introduce `GroupId`/`CredentialId`
   /dropped-prefix renames (§5).
5. **Point consumers at the package.** `admin/`'s `KeycloakFilamentAdminServiceProvider`
   already binds each `*Api` interface — swap the imports to the new namespace and bind the PSR-18 client +
   config-backed `KeycloakSettingsProvider`. Run the plugin's Pest suite green.
6. **Stand up E2E** (§6.2): realm-import fixture + CI service container.
7. **Later:** publish to a private Packagist / VCS repo and pin a version, replacing the `path` repository. `api/`
   adopts it if/when it needs Keycloak writes.

---

## 8. Open questions

**Resolved:** collections-not-arrays (§5.1, decided), keep-`Keycloak`-prefix (§3.3, decided).

### Load-bearing

1. **PII-safe logging across the PSR-18 seam.** The current transport guarantees credentials never reach logs
   (info-only middleware — invariant #8). Moving logging to "consumer convention" (§4) *surrenders* that guarantee: a
   naive consumer PSR-18 logger would log `reset-password` bodies. **Recommend** the library keeps a redaction seam of
   its own (own the request-logging + a credential-field denylist internally) rather than delegating a security
   invariant. Decide before the transport refactor.
2. **401/403 → `KeycloakAuthenticationException` under PSR-18.** PSR-18 does **not** throw on non-2xx (Guzzle's
   `http_errors=true` did). The transport must explicitly inspect status → throw, carry the upstream body in the
   message, and map `NetworkExceptionInterface` → `RuntimeException`. Re-implemented by hand in the refactor; easy to
   get subtly wrong — needs its own unit tests.
3. **Is the legacy user-write surface dead entirely?** Verified: no non-vendor consumer of
   `createUser`/`updateUserAttributes`/`setUserCredentials`/`CreateKeycloakUserCommand`. Concept only deletes the flat
   *facade* and folds these onto `KeycloakUsersApi`. If nobody calls them, **delete the methods too** — don't carry a
   create/import API no one uses. Confirm the `api/` user-import is really gone vs a not-yet-wired future need.
4. **SSO mode has zero library-level test coverage.** The library ships only `ServiceAccountTokenProvider`; E2E (§6.2)
   only exercises service_account. The `sso` act-as-user path — the design centerpiece — is tested nowhere here (only in
   the plugin, with fakes). Accept, or add an opt-in E2E that injects a real user-obtained bearer?

### Shape

5. **`KeycloakRealmApi` feature is missing from §3.** The plugin needs `getConfig` (editUsernameAllowed, events flags),
   `getUserProfile` (form rendering §5.5), and `ping`. Add `Features/KeycloakRealmApi` (also gives `ping` its home —
   don't resurrect a fat facade for it).
6. **Pagination result shape.** `list()`→`KeycloakUsers` + separate `count()` = two calls; Filament `records()` needs
   both. Return a page object (`items` + server `total`) instead, or keep the two calls? (§5.1 keeps them separate for
   now.)

### Ops

7. **Repo now or later?** In-tree Composer `path` package until the interface settles, then extract to its own repo once
   E2E is green (recommended) — vs own repo immediately.
8. **E2E runner** — `docker compose` service vs Testcontainers-PHP? (Compose simpler in CI; Testcontainers gives
   per-test lifecycle. Lean compose + `--import-realm`.)
9. **Private distribution + CI auth.** How does CI pull the package once extracted — private Packagist token vs VCS
   repo? Plus `realm-import.json` fixture drift on KC upgrades — assign an owner for the version bump.
