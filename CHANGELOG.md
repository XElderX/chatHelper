# Changelog

All notable changes to chatHelper are documented here. This project follows Semantic Versioning and Keep a Changelog.

## [Unreleased]

### Pending verification
- Real OpenAI credentials and end-user quality evaluation.
- MySQL migration and integration verification.
- Real-browser acceptance and visual review (blocked by environment socket restrictions).

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
