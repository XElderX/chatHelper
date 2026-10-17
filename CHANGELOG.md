# Changelog

All notable changes to chatHelper are documented here. This project follows Semantic Versioning and Keep a Changelog.

## [Unreleased]

### Pending verification
- Live API-key calls, ChatGPT OAuth/account eligibility and end-user quality evaluation.
- MySQL migration and integration verification.
- Real-browser acceptance and visual review (blocked by environment socket restrictions).

## [0.2.2] - 2026-10-17

### Fixed
- Subscription streaming now collects `response.output_text.delta` events before decoding, covering Responses streams whose final event contains no nested output items.
- Structured output decoding handles top-level output text, UTF-8 BOMs, JSON fences and JSON-string wrappers.

## [0.2.1] - 2026-10-17

### Fixed
- ChatGPT subscription mode automatically selects and saves the fastest available model when an account has no saved model.
- Structured Responses output parsing now accepts the top-level output text and harmless JSON code fences/wrappers.

## [0.2.0] - 2026-10-17

Local implementation update; no production-readiness claim or Git release tag. Live account acceptance remains unverified.

### Added
- Two explicit billing options: OpenAI API key and Continue with ChatGPT through the official local/OSS integration.
- OAuth authorization code with PKCE, dynamic app registration, stable host identity, account selection and account-specific model catalog.
- Encrypted local credentials, serialized refresh-token rotation, revocation attempts and clear local sign-out feedback.
- Bounded subscription streaming with completed-response validation and late usage-limit handling.
- Connection UI, migrations, upgrade guide and security/regression tests.

### Changed
- Provider choice persists in Settings; AI_PROVIDER is the initial/default selection.
- Analysis caches separate provider/account/model/authorization sessions. Stale connection results are rejected.
- API key remains server-only and optional for ChatGPT mode. Subscription failures never fall back to paid API usage.

### Security
- Validate state/session/nonce, JWT signature, issuer, audience, expiry and subject. Retain issued registration for reauthorization.
- Tokens remain encrypted server-side; no browser-cookie/session extraction. Preserve local-only and CSRF guards.
- Added firebase/php-jwt 7.x; lockfile installs 7.2.1. The older 6.x line was rejected by Composer's advisory gate.

### Upgrade
- Run composer install, config:clear, migrate, npm ci and npm run build. Preserve database, .env and APP_KEY; see docs/UPGRADE_0.2.md.

## [0.1.0] - 2026-10-16

Initial local single-user implementation. This is not declared production-ready or tagged as a verified release; see docs/TESTING.md.

### Added
- Laravel 13 backend, Vue 3 / JavaScript frontend, Vite and Tailwind CSS.
- Tiptap editor with formatting, undo/redo, counts and ProseMirror suggestion decorations.
- OpenAI Responses integration with strict structured output, safe errors and a separate labeled mock provider.
- Debounced live analysis with browser cancellation, revision checks and encrypted result caching.
- Exact-quote occurrence anchors validated on the server and client, including UTF-16/Unicode and repeated phrases.
- Accept/ignore suggestion controls, click-open correction panel and explanation requests.
- Eight writing modes and eight selected-text rewrite actions with preview/accept/reject.
- Document CRUD, title search, serialized autosave, version-based conflicts and reopen flow.
- Responsive dashboard/editor/settings styles, dark preference and canonical version display.
- MySQL-compatible document/settings migrations, executed locally against SQLite.
- Backend feature tests, editor unit tests and an isolated Vue-to-Laravel integration harness.
- Browser acceptance test specification (included but not verified here).
- Setup, configuration, user, API, architecture, development, troubleshooting, versioning and roadmap documentation.

### Changed
- None; initial implementation.

### Fixed
- None; initial implementation.

### Security
- Server-side credentials, loopback peer and Host restrictions, same-origin requests and CSRF protection.
- AI/general request rate limits, bounded inputs and rich-text JSON allowlist.
- Provider response validation and plain-text replacements; no model-generated HTML insertion.
- Optimistic locking on document updates/deletes, hashed cache identifiers and encrypted cached results.
