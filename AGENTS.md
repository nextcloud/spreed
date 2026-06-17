<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# AGENTS.md — Nextcloud Talk (spreed)

Guidance for AI coding agents in this repo. PHP backend in `lib/`, Behat integration tests in `tests/integration/`, unit tests in `tests/php/`. Vue 3 + TS frontend in `src/`, tests colocated as `*.spec.js`/`*.spec.ts`.

## Contribution policy

Comply with the [AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md) (disclosure, accountability, security, licensing, code quality, autonomous behavior) and [Contribution Guidelines](https://github.com/nextcloud/.github/blob/master/CONTRIBUTING.md) (testing, DCO, license headers, conventional commits, translations).

**Always:**
- Add an `Assisted-by: AGENT_NAME:MODEL_VERSION` trailer to every AI-assisted commit.
- Disclose AI tool use in every PR description.
- Keep PRs focused on one concern; no unrelated files or incidental refactors.
- Verify dependencies exist in real package registries.
- Tell the contributor when an action would violate the policy/guidelines — name the rule and the alternative; never silently proceed.
- Warn before a PR grows too large (approaching several thousand lines) and suggest a split.
- Recommend a discussion ticket before complex changes (multiple subsystems, architectural decisions, unclear approach).

**Never:**
- Open issues, submit PRs, post review comments, or send security reports autonomously — a human submits every contribution.
- Add `Signed-off-by` tags (only the human certifies the DCO).
- Submit unverified security reports; report verified ones via [HackerOne](https://hackerone.com/nextcloud), not GitHub issues.
- Write PR descriptions, review comments, or issue reports for the contributor — these must be in their own words.
- Fully automate resolution of [`good first issue`](https://github.com/issues?q=org%3Anextcloud+label%3A%22good+first+issue%22)-style issues.
- Submit unreviewed code — remove dead code, redundant logic, excessive comments, and unrelated changes first.

## Baseline & tooling

- `l10n/` is generated from Transifex (`fix(l10n): Update translations…` commits). **Never hand-edit translation files.**

**Backend:** PHP **8.2+**, Nextcloud server **35** (`appinfo/info.xml`). Stay portable across MariaDB, MySQL, PostgreSQL, SQLite, Oracle (migrations, query builder, tests).
- Setup: `composer i`. `lib/Vendor/` is Mozart-bundled third-party code (`cuyz/valinor`, `firebase/php-jwt`) — **never edit or analyze it.**
- Checks: `composer cs:fix`, `composer psalm`, `composer rector:fix`, `composer lint`.
- OpenAPI: docblocks/psalm types + `lib/ResponseDefinitions.php` feed `composer openapi` (also regenerates TS types). Regenerate after adding/removing/renaming a route, changing a controller signature or its `@param`/`@return`/psalm-shape, changing a response shape, or adding/removing an HTTP status code. CI fails on a stale spec or `src/types/openapi/`.

**Frontend:** Vue **3.5** + TypeScript, built with **rspack** (no Vite). State: **Pinia** (`src/stores/`, target) and legacy **Vuex 4** (`src/store/`, being phased out). UI components from `@nextcloud/vue` only.
- Setup: `npm ci`. Build: `npm run build` (prod), `npm run dev`/`npm run watch`.
- Checks: `npm run lint:fix`, `npm run stylelint`, `npm run ts:check`, `npm run test` (vitest + @vue/test-utils v2; no jest).
- OCS/API types generated via `npm run ts:generate` (openapi-typescript) into `src/types/openapi/` — regenerate, don't hand-edit.
- No compat mode, mixins, `mapGetters`/`$set`/`::v-deep` — removed in the Vue 2→3 migration; don't reintroduce.

## Running tests

- PHP unit (`tests/php/`): `composer run test:unit`. Single file: `composer run test:unit -- tests/php/Service/AvatarServiceTest.php`; filter: `composer run test:unit -- --filter="testMethodName"`.
- `tests/php/bootstrap.php` requires `../../../../lib/base.php`: the suite only runs when this repo lives at `<nextcloud-server>/apps/spreed`. **If you cannot run a suite, say so — don't claim it passes.** `composer lint`/`composer psalm` run standalone.
- Integration (Behat, `tests/integration/`): `run.sh` against a local server, or `run-docker.sh`.
- Frontend: `npm run test`; single file via `npm run test -- <path>`.

## License headers

Every new file needs an SPDX header. Use `AGPL-3.0-or-later`, never `AGPL-3.0-only`:

```php
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
```

Adapt the comment style to the file type; files that can't carry a header (binary assets) go in `REUSE.toml`. CI enforces REUSE.

## Git workflow

- Do **not** commit or push unless explicitly asked. Leave changes in the working dir, summarize, and suggest a commit message.
- Never commit/push to `main` — use a `type/issue-or-noid/short-description` branch (e.g. `fix/12345/handle-mcu-disconnect`), not generated names like `agent-xxxx`.
- Conventional Commits with a component scope: `feat(chat): …`, `fix(call): …`, `fix(api): …`.
- Backports happen via a `/backport to stable-X.Y` comment on the merged PR — don't cherry-pick manually.

## Backend conventions (modern pattern first; avoid the drift)

From a 2026-06 tech-debt analysis. New code uses the modern pattern.

- **DI:** constructor injection with property promotion. Don't add `\OCP\Server::get()` calls — existing ones are debt (the service-locator blocks in `lib/Room.php` where `getName()` even writes to the DB; ~42 `Server::get()` calls instantiating federation proxy controllers, 13 in `ChatController`).
- **Data access:** new persisted entities use `QBMapper` + `SnowflakeAwareEntity` in `lib/Model/` (templates: `ConversationTag`, `ScheduledMessage`). `Room`/`Participant` are **not** Entities — hand-hydrated in `Manager::createRoomObject()` from columns aliased in `lib/Model/SelectHelper.php`; adding a column means touching the migration, `SelectHelper`, `Manager` hydration, and the constructor **in lockstep**. Query builder only (no raw SQL outside migrations). Build queries **outside** loops via `createParameter`/`setParameter`; chunk `IN ()` with `array_chunk(…, IQueryBuilder::MAX_IN_PARAMETERS)` for Oracle and `array_merge(...$results)` once after the loop (see `lib/Model/ThreadMapper.php`).
- **Services:** reads/lookups in `lib/Manager.php`; writes/mutations + event dispatch in `lib/Service/RoomService.php`/`ParticipantService.php` — keep the split. New services in `lib/Service/` named `*Service`. Lib-root `GuestManager`, `MatterbridgeManager`, `lib/Chat/ChatManager` are legacy naming. Use `lib/Federation/`, `RecordingService`, `BotService`, `lib/RoomPresets/` as templates, not 2016-era core.
- **Errors:** lookups throw domain exceptions (`RoomNotFoundException`, `ParticipantNotFoundException` in `lib/Exceptions/`). Idempotent setters may return `bool` (false = no-op), e.g. `RoomService::setPermissions()`; don't return `null`/`false` for "not found".
- **Events:** typed only (`dispatchTyped()`), extending the `A`-prefixed bases in `lib/Events/`, with `Before*`/`*` pairs for mutations; registered in `lib/AppInfo/Application.php`. No string events/hooks.
- **Controllers/API:** OCS controllers needing room/participant context extend `AEnvironmentAwareOCSController` (populated by `InjectionMiddleware` via `#[RequireRoom]`-style attributes in `lib/Middleware/Attribute/`); others extend `OCSController`. PHP attributes only (`#[NoAdminRequired]`, `#[PublicPage]`, `#[BruteForceProtection]`, `#[ApiRoute]`) — never docblock annotations. Responses are `DataResponse` with psalm shapes from `ResponseDefinitions.php`.
- **Config:** settings go through the `lib/Config.php` facade; register new app-config keys in `lib/ConfigLexicon.php` (the emerging registry).
- **Caching:** cache prefixes belong in `lib/CachePrefix.php` — don't add ad-hoc prefixes (drift: `hpb_servers` lacks `talk/`, `Capabilities.php` uses raw `'talk::'`).
- **PHP style:** strict comparisons, `?Type` nullables, `match` over `switch`, `str_contains`/`str_starts_with`, `readonly` where applicable, arrow functions, `JSON_THROW_ON_ERROR` on new json calls. Prefer native backed enums for new value sets (only `lib/RoomAttributes.php`, `lib/RoomPresets/Parameter.php` exist today); bitflags (`Attendee::PERMISSIONS_*`, `Participant::FLAG_*`) stay int constants.

## Frontend conventions (modern pattern first; avoid the drift)

- **Components:** new/rewritten SFCs use `<script setup lang="ts">` with `defineProps<T>()`/`defineEmits<T>()`.
- **State:** new state in a **Pinia setup-style TS store** (`defineStore('x', () => {…})`, see `src/stores/actor.ts`, `token.ts`). No new options-style or JS stores. **Never add to the Vuex modules** (`conversationsStore.js`, `messagesStore.js`, `participantsStore.js`) — migration targets; if forced, keep minimal and flag in the PR. Don't add new Pinia↔Vuex coupling. Instantiate stores lazily inside actions/setup (`useXStore()`), not at module level. Stores sync through reactivity, not the EventBus.
- **Services/composables:** TypeScript with **named function exports** (convert JS ones when touched). Error split: **services throw** (bare axios, no try/catch, no UI); **stores/composables catch** and surface via `showError(t('spreed', …))` from `@nextcloud/dialogs` — don't toast from services or swallow with `console.debug`. Use types from `src/types/index.ts`. URLs via `generateOcsUrl()` with `{token}` placeholders — never concatenation, never `OC.linkTo()`. Translations: `t`/`n` from `@nextcloud/l10n`, interpolate via placeholder objects; date/time via `src/utils/formattedTime.ts` (Intl) — no moment.js.
- **EventBus:** `src/services/EventBus.ts` (typed mitt) bridges the non-Vue signaling/call layer into Vue only — not for component-to-component UI coordination or store-to-store sync; register every new event in the `Events` type. `@nextcloud/event-bus` is only for cross-app server events.
- **Dialogs:** declarative `<NcDialog>` in the owning template is the target; `spawnDialog()` only without template context; `NcModal` is legacy.
- **Icons/loading:** `vue-material-design-icons` components (not `icon-*` CSS classes); `NcLoadingIcon` (not `icon-loading*` divs).
- **Styling:** scoped styles with Nextcloud CSS vars (`var(--color-*)`, defined in `apps/theming/css/default.css`); hardcoded colors only for brand exceptions. Avoid new `:deep()` overrides of `@nextcloud/vue` internals — prefer props/slots or an upstream issue. Spacing/dimensions use the standard vars (`calc(x * var(--default-grid-baseline))`), no magic numbers. Follow the [string-writing rules](https://docs.nextcloud.com/server/latest/developer_manual/design/writing.html).
- **Call layer (WebRTC/signaling):** `src/utils/webrtc/simplewebrtc/`, `webrtc/models/`, `signaling.js`, `EmitterMixin.js` are pre-Vue legacy — **don't copy these patterns**; use ES6 classes + async/await, with `src/utils/media/pipeline/` as the template. Don't add new manual model `.on()`/`.off()` subscriptions in components — prefer a composable wrapper that cleans up.

## Known technical debt (2026-06 analysis)

Roadmap, not new-code patterns. Don't extend these; extract/repair when touching nearby code.

**Backend:**
1. `\OC_Util::tearDownFS()/setupFS()` in `lib/Chat/Parser/SystemMessage.php` — last private-server-API usage; replace with a public per-user FS API.
2. `Room.php` service locators — move name resolution / display-name / last-message loading into `Manager`/formatter; removes the `Room↔RoomService` cycle and a getter-with-DB-write.
3. Enum migration — convert constant groups (`Room::TYPE_*`, `Participant::OWNER/…`, `Attendee::ACTOR_*`) to native backed enums, keeping `->value` at OCS/DB boundaries.
4. Room/Participant hydration — introduce a mapper owning the `SelectHelper`⇄constructor mapping now spread over three files.
5. Stale "temporary" code — 15× `FIXME Temporary solution for the Talk6 release` in `lib/Manager.php`; `Room::OBJECT_TYPE_PHONE_LEGACY` (`@deprecated`) still used in 5 places (`Notifier`, `AvatarService`, `RoomController`, `RoomService`, `RestrictStartingCalls`).
6. God classes — `RoomController.php` (~3.3k, 63 endpoints), `ChatController.php` (~2.6k), `ParticipantService.php` (~2.4k, a `SessionService` would split out), `RoomService.php`, `Manager.php`. Don't grow them.
7. Copy-paste `BackendNotifier`s — `Signaling/`, `Recording/`, `Federation/` share `doRequest()`+retry+`PHPUNIT_RUN` boilerplate; extract a base.
8. `MatterbridgeManager` — 16-branch elseif in `generateConfig()`, `is_null()`/`strpos`/`substr` clusters; oldest-style file.
9. Tests — inverted pyramid (~61 unit files vs huge Behat suite)
10. Static analysis — psalm level 4 with `findUnusedCode="false"` and ~200-line baseline; tightening surfaces hidden debt.
11. `json_encode`/`json_decode` — ~164 of 193 calls lack `JSON_THROW_ON_ERROR`; fix opportunistically.
12. Loose comparison in `BackgroundJob/CheckCertificates.php` (`== null`) and `Command/Signaling/VerifyKeys.php` (`!=`).

**Frontend:**
1. Vuex→Pinia — 3 modules ~4,200 lines (`conversationsStore.js`, `messagesStore.js`, `participantsStore.js`), bidirectional coupling, duplicated reactions state. Finishing removes the non-atomic fan-out in `deleteConversation` and ~23 `useStore()` components.
2. Deprecated WebRTC APIs — `pc.addStream()` and `addstream`/`removestream` listeners in `simplewebrtc/peer.js` and `e2ee/encryption.js`; migrate to `addTrack()`/`ontrack`.
3. Legacy call layer (~4,800 lines) — ES6-classify `simplewebrtc/` and `webrtc/models/`, replace `EmitterMixin`/WildEmitter with `EventTarget`, convert `signaling.js` (~26 promise chains) to async/await. Drops `wildemitter`/`util`/`mockconsole`.
4. `crypto-js`→Web Crypto — 8 files use it only for SHA (`prepareTemporaryMessage.ts`, `messagesService.ts`, `stores/session.ts`, `store/participantsStore.js`, `AdminSettings/TurnServer.vue`, …); `crypto.subtle.digest` is async.
5. `hark` (unmaintained) — `media/pipeline/SpeakingMonitor.js`, `composables/useDevices.js`; replace with native AnalyserNode/AudioWorklet.
6. Options API components — 141 SFCs; migrate directory-by-directory. Self-contained starts: AdminSettings (15/16), BreakoutRoomsEditor (4/4), Dashboard (3/3).
7. JS stragglers — 8 services, 7 composables, 4 Pinia stores; convert to TS when touched.
8. EventBus overreach — UI-coordination events (`focus-message`, `scroll-chat-to-bottom`) and store-sync listeners should move to reactivity/provide-inject; signaling fan-out stays.
9. `:deep()` overrides — ~174 of `@nextcloud/vue` internals; audit on each library bump, upstream what's useful.
10. `@matrix-org/olm` — deprecated upstream for vodozemac; track for `src/utils/e2ee/`.
11. Misc — `icon-*` CSS classes (~11 files), `OC.linkTo()` in `src/collections.js`, `cropperjs` v1, `base64-js` in `e2ee/encryption.js`, `vue-material-design-icons` (421 imports — consider `@mdi/js`+`NcIconSvgWrapper`, low priority).
