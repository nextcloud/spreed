# AGENTS.md — Nextcloud Talk (spreed)

Guidance for AI coding agents working in this repository. The PHP backend lives in
`lib/`, integration tests in `tests/integration/` (Behat), unit tests in `tests/php/`.

## Baseline & tooling

- PHP **8.2+**, Nextcloud server **35** (`appinfo/info.xml`). Code must stay portable
  across MariaDB, MySQL, PostgreSQL, SQLite, and Oracle — migrations, query-builder
  usage, and tests alike.
- `lib/Vendor/` is third-party code bundled via Mozart (`cuyz/valinor`,
  `firebase/php-jwt`). **Never edit or analyze it as project code.**
- Checks: `composer cs:fix` (php-cs-fixer, Nextcloud coding standard),
  `composer psalm` (level 4, baseline in `tests/psalm-baseline.xml`),
  `composer rector:check`, `composer lint`.
- OpenAPI: controller docblocks/psalm types in `lib/ResponseDefinitions.php` feed
  `composer openapi`; regenerate after changing API signatures or responses.

## Conventions to follow (and the drift to avoid)

These rules come from a 2026-06 technical-debt analysis. Where the codebase has two
patterns, **new code must use the modern one** listed first.

### Dependency injection
- Constructor injection with property promotion everywhere. Do **not** add new
  `\OCP\Server::get()` calls; the existing ones are debt:
  - `lib/Room.php:231-332` — four `// TODO use DI` service-locator blocks (the domain
    model pulls `ParticipantService`/`RoomService`/`Manager` from the container;
    `getName()` even writes to the DB). Do not extend this pattern.
  - Controllers contain ~42 `Server::get()` calls to instantiate federation proxy
    controllers (`ChatController` alone has 13) — tolerated pattern, don't add more
    variants of it.

### Data access
- New persisted entities use `QBMapper` + `SnowflakeAwareEntity` in `lib/Model/` (see `ConversationTag`,
  `ScheduledMessage` as templates).
- `Room` and `Participant` are **not** Entities: they are hydrated by hand in
  `Manager::createRoomObject()` (`lib/Manager.php:127`) from columns aliased in
  `lib/Model/SelectHelper.php`. Adding a Room/Participant column means touching the
  migration, `SelectHelper`, `Manager` hydration, and the constructor **in lockstep**.
- Query builder only; no raw SQL outside migrations.

### Services & responsibility split
- Reads/lookups: `lib/Manager.php`. Writes/mutations + event dispatch:
  `lib/Service/RoomService.php` / `ParticipantService.php`. Keep that split.
- New service classes go in `lib/Service/` with a `*Service` name. The lib-root
  `GuestManager`, `MatterbridgeManager` and `lib/Chat/ChatManager` are legacy naming —
  don't copy that layout.
- Use the newer modules (`lib/Federation/`, `lib/Service/RecordingService.php`,
  `lib/Service/BotService.php`, `lib/RoomPresets/`) as style templates, not the
  2016-era core.

### Error signaling
- Lookups throw domain exceptions (`RoomNotFoundException`,
  `ParticipantNotFoundException` — `lib/Exceptions/`).
- Idempotent setters may return `bool` (false = no-op/invalid), e.g.
  `RoomService::setPermissions()`. Don't return `null`/`false` for "not found".

### Events
- Typed events only (`IEventDispatcher::dispatchTyped()`), extending the `A`-prefixed
  abstract bases in `lib/Events/`, with `Before*`/`*` pairs for mutations. Listeners
  are registered in `lib/AppInfo/Application.php`. No string-based events or hooks.

### Controllers & API
- OCS controllers needing room/participant context extend
  `AEnvironmentAwareOCSController` (populated by `InjectionMiddleware` via the
  `#[RequireRoom]`-style attributes in `lib/Middleware/Attribute/`); others extend
  `OCSController` directly.
- PHP attributes only (`#[NoAdminRequired]`, `#[PublicPage]`,
  `#[BruteForceProtection]`, `#[ApiRoute]`) — never docblock annotations.
- Responses are `DataResponse` with psalm type shapes from `ResponseDefinitions.php`.

### Config
- Talk settings go through the `lib/Config.php` facade; `lib/ConfigLexicon.php`
  (currently `Strictness::IGNORE`, only 2 user-preference entries) is the emerging
  registry — register new app-config keys there as well.

### Caching
- Cache prefixes belong in `lib/CachePrefix.php`. Known drift: `hpb_servers` lacks the
  `talk/` prefix and `Capabilities.php` uses a raw `'talk::'` string — don't add more
  ad-hoc prefixes.

### PHP style
- Strict comparisons, `?Type` nullables, `match` over `switch`, `str_contains`/
  `str_starts_with` over `strpos`/`substr` checks, `readonly` where applicable,
  arrow functions, `JSON_THROW_ON_ERROR` on new `json_encode`/`json_decode` calls.
- Only two native enums exist (`lib/RoomAttributes.php`,
  `lib/RoomPresets/Parameter.php`); the domain otherwise uses int constant groups
  (`Room::TYPE_*`, `Participant::OWNER/...`, `Attendee::ACTOR_*`, …). Prefer native
  backed enums for new value sets; bitflags (`Attendee::PERMISSIONS_*`,
  `Participant::FLAG_*`) stay int constants.

## Known technical debt (2026-06 analysis)

Prioritized backlog; safe to pick up as standalone PRs.

1. **`\OC_Util::tearDownFS()/setupFS()`** at `lib/Chat/Parser/SystemMessage.php:990-992`
   — last private-server-API usage; the only real forward-compat risk. Replace with a
   public API for per-user filesystem context.
2. **`Room.php` service locators** (`lib/Room.php:231-332`) — move 1:1 name
   resolution, display-name and last-message loading out of the model into
   `Manager`/formatter code; removes the `Room ↔ RoomService` cycle and a
   getter-with-DB-write.
3. **Enum migration** — convert the constant groups above to native backed enums,
   group by group, keeping `->value` at OCS/DB boundaries.
4. **Room/Participant hydration** — introduce a mapper that owns the
   `SelectHelper` ⇄ constructor column mapping currently spread over three files.
5. **Stale "temporary" code** — 15× `FIXME Temporary solution for the Talk6 release`
   in `lib/Manager.php` (Talk 6 shipped 2019): decide permanent vs repair-step and
   delete the comments. Likewise `Room::OBJECT_TYPE_PHONE_LEGACY` (`@deprecated`) is
   still consumed in 5 places (`Notification/Notifier.php:1095`,
   `Service/AvatarService.php:265`, `Controller/RoomController.php:767`,
   `Service/RoomService.php:192`, `Listener/RestrictStartingCalls.php:57`).
6. **God classes** — `Controller/RoomController.php` (~3.3k lines, 63 endpoints),
   `Controller/ChatController.php` (~2.6k), `Service/ParticipantService.php` (~2.4k;
   a `SessionService` would split out naturally), `Service/RoomService.php`,
   `Manager.php`. Don't grow them; extract when touching related code.
7. **Copy-paste `BackendNotifier`s** — `Signaling/`, `Recording/`, `Federation/`
   share the `doRequest()` + retry + `PHPUNIT_RUN` boilerplate; extract a base class.
8. **`MatterbridgeManager`** — 16-branch elseif chain in `generateConfig()` (~line
   337), remaining `is_null()` calls and `strpos`/`substr` clusters; oldest-style file
   in the repo.
9. **Tests** — pyramid is inverted (~61 unit-test files vs huge Behat suite); core
   domain is only covered end-to-end. Unit tests still use `@dataProvider`
   annotations — migrate to `#[DataProvider]` attributes before the next PHPUnit bump.
10. **Static analysis headroom** — psalm level 4 with `findUnusedCode="false"` and a
    ~200-line baseline; tightening would surface hidden debt.
11. **`json_encode`/`json_decode`** — ~164 of 193 calls lack `JSON_THROW_ON_ERROR`
    (e.g. the 1:1 name trick at `Room.php:248`); fix opportunistically.
12. **Loose comparison** at `BackgroundJob/CheckCertificates.php:90` (`== null`) and
    `Command/Signaling/VerifyKeys.php:50` (`!=`).
