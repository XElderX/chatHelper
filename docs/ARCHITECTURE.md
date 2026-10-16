# Architecture

## Request flow

Vue SPA → same-origin Laravel web routes under `/api` → loopback guard + CSRF + rate limiting → Form Requests → controllers → injected domain services → provider / Eloquent → validated JSON → revision-checked editor decorations.

Laravel's web middleware is intentional: sessions and CSRF protect mutations even though URLs start with `/api`. This version has a clearly restricted local single-user mode. The global LocalOnly middleware checks the actual connection address and allowlisted Host and compares Origin when supplied. No forwarded headers are trusted. Loopback exposure is not multi-user authentication: never deploy behind a proxy. A future public API needs accounts, ownership policies, authentication, CORS and per-user quotas.

## Backend

- `OpenAiProvider`: HTTPS Responses calls, timeout/error mapping, strict JSON schema request and JSON decoding. Fixed official endpoint prevents user-controlled upstream URLs.
- `MockProvider`: explicit deterministic examples; not a substitute for real AI quality.
- `PromptBuilder`: task-specific instructions, mode definitions and strict object schemas. Document JSON is untrusted data.
- `SuggestionValidator`: validates fields, exact quotes, occurrences, categories and overlapping ranges. Invalid anchors are dropped; structurally invalid responses fail safely.
- `WritingAnalysisService`: hashed input cache key, encrypted cache result and current revision binding.
- `WritingRewriteService`: validates rewrite/explanation output, strips unexpected top-level fields.
- `DocumentController`: CRUD and version-conditional atomic saves/deletes.
- `RichText`: bounded node tree with allowed types/marks and child structure. No links, images, raw HTML or model-rendered HTML.

## Frontend

`App.vue` owns UI and request lifecycle. `lib/api.js` handles same-origin JSON and CSRF. `lib/anchors.js` is the tested text-position layer. `lib/suggestions.js` is a ProseMirror plugin using DecorationSet. No direct editor DOM/HTML replacement is used.

The editor serializes each text block independently, including nested list paragraphs. Blocks have IDs derived from their ProseMirror position, which are valid only for one revision. Hard breaks serialize as one newline code unit. Formatting marks do not change text positions.

Models return an exact quote and a zero-based non-overlapping occurrence index, avoiding reliance on unreliable model-generated numerical offsets. PHP locates that quote, converts the prefix and quote lengths to UTF-16 code units and rejects overlaps. JavaScript verifies the same quote again before deriving editor positions. ProseMirror and JS strings use UTF-16; emoji and combining-character tests cover the distinction from Unicode code points.

Any document update immediately clears decorations, cancels pending browser requests and increments revision. Request generation tokens prevent cross-document responses. Analysis request counters also reject responses after live analysis is disabled or another analysis starts. Cached content is rebound to the current revision. Ignore is scoped to a revision and mode. All replacements use `tr.insertText`, never `insertContent` with model HTML.

Rewriting captures range, text and revision from a single text block. It previews output and rechecks the snapshot before insertion. Rejecting the preview does nothing. Editing while a rewrite is pending aborts and invalidates it.

## Autosave

Changes debounce for 900 ms. A single in-flight save promise serializes writes; edits arriving during a save trigger another save with the returned DB version. Navigation awaits saves. Atomic `WHERE version = expected` updates return HTTP 409 rather than silently losing another tab's edits. A beforeunload handler warns when dirty. No background offline persistence is provided.

## Database

- `documents`: id, title (160), JSON content, integer version, created_at, updated_at.
- `writing_settings`: singleton id=1, mode, live_analysis, dark_mode and timestamps.
- Standard Laravel skeleton tables remain available (users, password resets, sessions, cache/locks and jobs); no user accounts are exposed in this MVP.

No fabricated user/document relationship is needed for one local workspace. Future accounts should add user ownership to documents/settings with policies and migrations before exposing external access.

## Limits, performance and privacy

Whole-document analysis is bounded, debounced and cached for five minutes. Paragraph-incremental analysis is deferred. AI calls have no automatic billable retries. AbortController stops client waits, but an already-started provider call may still finish and incur cost.

Document DB content is local and unencrypted. Cached suggestions are encrypted with APP_KEY. Hashes are used for cache keys. Provider request data is not intentionally logged; expected provider errors are excluded from exception reporting. API keys stay server-side. All UI data is escaped by Vue. Native deletion confirmation is the only destructive action prompt.

Future browser/keyboard/desktop clients can reuse the provider and validation services, but need an authenticated external transport and ownership model first.
