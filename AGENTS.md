# AGENTS.md — Nextcloud Talk (spreed)

Guidance for AI coding agents working in this repository. The PHP backend lives in
`lib/`, integration tests in `tests/integration/` (Behat), unit tests in `tests/php/`.
The Vue 3 frontend lives in `src/`, with frontend tests colocated as `*.spec.js`/`*.spec.ts`.

## Nextcloud Contribution Policy

All contributions generated or assisted by this agent must fully comply with:

- **[AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md)** - the primary reference for AI-specific rules, covering disclosure, author accountability, communication, security, licensing, code quality, and autonomous agent behavior.
- **[Contribution Guidelines](https://github.com/nextcloud/.github/blob/master/CONTRIBUTING.md)** - covering testing requirements, the Developer Certificate of Origin (DCO), license headers, conventional commits, and translations. These apply in full to all contributions regardless of how they were produced.

### What this agent must always do

- Add an `Assisted-by: AGENT_NAME:MODEL_VERSION` git trailer to every commit containing AI-assisted content.
- Ensure every pull request includes a disclosure of AI tool use in the PR description.
- Produce focused, scoped pull requests that address exactly one concern. Do not touch unrelated files or introduce incidental refactors.
- Verify all dependencies against actual package registries before suggesting them. Do not use hallucinated or unverified package names.
- Explicitly inform the contributor when any action they are about to take, or have taken, would violate the AI Contribution Policy or the Contribution Guidelines. Do not silently proceed. State which rule is at risk and what the contributor should do instead.
- Warn the contributor if a pull request is growing too large. A PR approaching several thousand lines of changed code is a signal that it should be split into smaller, focused PRs. Suggest a logical split before the PR is opened, not after.
- Recommend opening a ticket for discussion before starting implementation whenever a feature or change is sufficiently complex - for example when it touches multiple subsystems, requires architectural decisions, or the right approach is not yet clear. A ticket allows maintainers and the contributor to align on direction before code is written, avoiding wasted effort on a PR that may be rejected or require fundamental rework.

### What this agent must never do

- Open issues, submit pull requests, post review comments, or send security reports autonomously. Every contribution must be reviewed and submitted by a human.
- Add `Signed-off-by` tags to commits. Only the human contributor can certify the Developer Certificate of Origin.
- Generate or submit security reports without independent human verification. Report verified vulnerabilities via [HackerOne](https://hackerone.com/nextcloud), not as GitHub issues.
- Write PR descriptions, review comments, or issue reports on behalf of the contributor. These must be in the contributor's own words.
- Fully automate the resolution of issues labeled [`good first issue`](https://github.com/issues?q=org%3Anextcloud+label%3A%22good+first+issue%22) or similar beginner-friendly labels.
- Submit code that has not been reviewed and cleaned up by the contributor. Dead code, redundant logic, excessive comments, and unrelated changes must be removed before submission.

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

## Frontend baseline & tooling

- Vue **3.5** + TypeScript, built with **rspack** (`rspack.config.js`), no Vite. State:
  **Pinia** (`src/stores/`, target) and legacy **Vuex 4** (`src/store/`, being phased
  out). UI components from `@nextcloud/vue` 9 — import via
  `@nextcloud/vue/components/NcXxx` only, never `@nextcloud/vue/dist/...`.
- Checks: `npm run lint` (eslint flat config), `npm run stylelint`,
  `npm run ts:check` (vue-tsc), `npm run test` (vitest + @vue/test-utils v2; no jest).
- OCS/API types are generated: `npm run ts:generate` (openapi-typescript) into
  `src/types/openapi/` — regenerate instead of hand-editing after API changes.
- No compat mode, no mixins, no `mapGetters`/`$set`/`::v-deep` — the Vue 2 → 3
  migration removed these; do not reintroduce them.

## Frontend conventions to follow (and the drift to avoid)

These rules come from a 2026-06 technical-debt analysis of `src/`. Where the codebase
has two patterns, **new code must use the modern one** listed first.

### Components
- New/rewritten SFCs use `<script setup lang="ts">` with `defineProps<T>()` and
  `defineEmits<T>()`. ~141 of 198 SFCs are still Options API — don't add more, and
  don't create the hybrid Options-API-plus-`setup()` style (`App.vue:81-244`,
  `ChatView.vue:91-243` are debt, not templates).
- Lifecycle/reactivity via imported `nextTick`/`watch`/`computed`, router via
  `useRoute()`/`useRouter()` — already 100% migrated, keep it that way.

### State management
- New state goes in a **Pinia setup-style TS store** (`defineStore('x', () => {...})`,
  see `src/stores/actor.ts`, `token.ts`). Don't add options-style stores and don't
  add new JS stores (`integrations.js`, `reactions.js`, `sounds.js`, `talkHash.js`
  are stragglers awaiting conversion).
- **Never add state, getters, mutations, or actions to the Vuex modules**
  (`src/store/conversationsStore.js`, `messagesStore.js`, `participantsStore.js`) —
  they are migration targets. If a change forces you into them, keep it minimal and
  flag it in the PR.
- Don't add new Pinia ↔ Vuex coupling. Existing bridges (`stores/reactions.js`
  committing Vuex mutations, `stores/chat.ts:97` reading raw Vuex state,
  `stores/session.ts`/`breakoutRooms.ts` dispatching into Vuex, and `conversationsStore.js`
  importing ten Pinia stores) are debt that each new link makes harder to unwind.
- Instantiate stores lazily inside actions/setup (`useXStore()`), not at module level
  with the pinia instance (`conversationsStore.js:82`, `participantsStore.js:47` are
  the anti-pattern).
- Stores synchronize through reactivity, not the EventBus.

### Services & composables
- New services and composables are TypeScript with **named function exports**
  (8 of 38 services and 7 of 31 composables are still JS — convert when touching,
  don't add more).
- Error-handling split: **services throw** (bare axios calls, no try/catch, no UI);
  **stores/composables catch** and surface with `showError(t('spreed', ...))` from
  `@nextcloud/dialogs`. Don't show toasts from services
  (`participantsService.js:37-50` is drift) or swallow errors with `console.debug`.
- Use the typed OCS helpers from `src/types/index.ts` instead of new manual
  `response.data.ocs.data` unwrapping.
- URLs via `generateOcsUrl()` with `{token}` placeholders — never string
  concatenation, never `OC.linkTo()` (last use: `src/collections.js:12`).
- Translations: `t`/`n` imported from `@nextcloud/l10n`, variables interpolated via
  placeholder objects, never concatenated. Date/time formatting goes through
  `src/utils/formattedTime.ts` (Intl-based) — no moment.js.

### EventBus
- `src/services/EventBus.ts` (typed mitt) is for bridging the non-Vue
  signaling/call layer into Vue. Don't use it for component-to-component UI
  coordination (use props/provide-inject/store state) or store-to-store sync.
  Register every new event in the `Events` type. `@nextcloud/event-bus`
  (`subscribe`/`unsubscribe`) is only for cross-app events from the server.

### Dialogs
- Declarative `<NcDialog>` in the owning component's template is the target.
  `spawnDialog()` only where no template context exists. `NcModal` for dialogs is
  legacy (see the `FIXME: Align NcModal header with NcDialog` in `App.vue`).

### Icons & loading states
- Icons: `vue-material-design-icons` components, not legacy `icon-*` CSS classes
  (~11 files remain, e.g. `PublicShareAuthSidebar.vue:15`).
- Loading: `NcLoadingIcon`, not `icon-loading`/`icon-loading-small` divs.

### Styling
- Scoped styles with Nextcloud CSS variables (`var(--color-*)`); hardcoded colors
  only for brand exceptions. Avoid new `:deep()` overrides of `@nextcloud/vue`
  internals — the existing ~174 are a breakage hotspot on library bumps; prefer
  component props/slots or an upstream issue.

### Call layer (WebRTC/signaling)
- `src/utils/webrtc/simplewebrtc/`, `src/utils/webrtc/models/`,
  `src/utils/signaling.js` and `src/utils/EmitterMixin.js` are pre-Vue legacy
  (function constructors, `util.inherits`, WildEmitter, `.bind(this)` handler
  chains, promise chains). **Do not copy these patterns** — for new code in this
  area use ES6 classes and async/await; `src/utils/media/pipeline/` is the style
  template.
- Don't add new manual model `.on()`/`.off()` subscriptions in components
  (`CallView.vue:535/546` style); prefer a composable wrapper that handles cleanup.

## Known frontend technical debt (2026-06 analysis)

Prioritized backlog; larger items (1, 3) need a discussion ticket and a PR series.

1. **Vuex → Pinia migration** — 3 modules, ~4,200 lines
   (`conversationsStore.js` 1,377 / `messagesStore.js` 1,485 /
   `participantsStore.js` 1,306) with bidirectional Pinia coupling and duplicated
   reactions state (`messagesStore.js` mutations vs `stores/reactions.js`).
   Finishing this also removes the non-atomic multi-store fan-out in
   `deleteConversation` (`conversationsStore.js:404`) and the last `useStore()`
   components (~23).
2. **Deprecated WebRTC APIs** — `pc.addStream()` at
   `src/utils/webrtc/simplewebrtc/peer.js:125`, `addstream`/`removestream` event
   listeners at `peer.js:55-57` and `src/utils/e2ee/encryption.js:646`; removed from
   the spec, migrate to `addTrack()`/`ontrack`.
3. **Legacy call layer modernization** (~4,800 lines) — ES6-classify
   `simplewebrtc/` (2,009 lines) and `webrtc/models/` (1,170 lines), replace
   `EmitterMixin`/WildEmitter with `EventTarget`, convert `signaling.js`
   (1,617 lines, 26 promise chains, zero async/await). Drops the `wildemitter`,
   `util` and `mockconsole` dependencies as a side effect.
4. **`crypto-js` → Web Crypto** — 8 files use it only for SHA hashing
   (`prepareTemporaryMessage.ts`, `messagesService.ts`, `stores/session.ts`,
   `store/participantsStore.js`, `AdminSettings/TurnServer.vue`, …);
   `crypto.subtle.digest` is async, so call sites need small refactors.
5. **`hark` (unmaintained)** — `media/pipeline/SpeakingMonitor.js`,
   `composables/useDevices.js`; replace with native AnalyserNode/AudioWorklet.
6. **Options API components** — 141 SFCs; migrate directory-by-directory, pulling
   `defineProps<T>`/`defineEmits<T>` along. Self-contained starts: AdminSettings
   (15/16 Options API), BreakoutRoomsEditor (4/4), Dashboard (3/3).
7. **JS stragglers** — 8 services (`signalingService.js`, `participantsService.js`,
   `BrowserStorage.js`, …), 7 composables (`useDevices.js`, `useIsInCall.js`, …),
   4 Pinia stores; convert to TS when touched.
8. **EventBus overreach** — UI-coordination events (`focus-message`,
   `scroll-chat-to-bottom`) and store-sync listeners (`token.ts`/`session.ts` on
   `signaling-join-room`, `upload.ts` lifecycle emits) should move to
   reactivity/provide-inject; signaling fan-out stays.
9. **`:deep()` overrides** — ~174 overrides of `@nextcloud/vue` internals; audit on
   each library bump, upstream what's generally useful.
10. **`@matrix-org/olm`** — deprecated upstream in favor of vodozemac; track for the
    e2ee code (`src/utils/e2ee/`).
11. **Misc** — legacy `icon-*` CSS classes (~11 files), `OC.linkTo()` in
    `src/collections.js:12`, `cropperjs` v1 (+`vue-cropperjs`), `base64-js` in
    `e2ee/encryption.js` (native `TextEncoder`/`Uint8Array` suffices),
    `vue-material-design-icons` (421 imports — consider `@mdi/js` +
    `NcIconSvgWrapper` for bundle size, low priority).
