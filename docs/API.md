# API reference

Base URL: `http://127.0.0.1:8000/api`. JSON requests must send `Accept: application/json` and `Content-Type: application/json`.

## Access and CSRF

All endpoints require a loopback peer and Host (`localhost`, `127.0.0.1`, `[::1]`). Non-local callers are rejected with 403. There is no bearer token or remote-access mode. The browser first loads `/` to establish the Laravel session and read the `csrf-token` meta element; mutations send that value in `X-CSRF-TOKEN` with the same session cookie. Cross-origin requests are denied. Invalid CSRF returns 419. Do not disable these checks for a new client; design authentication first.

Global API quota: 180 requests/minute. Analyze/rewrite/explain share an additional 30/minute quota (`AI_RATE_LIMIT`). Responses on throttling: 429 with standard Laravel retry headers. Quotas are shared by the single local workspace.

## Endpoints

| Method | Path | Parameters / response |
|---|---|---|
| GET | `/writing/modes` | No body. Array of `{id,label}`; eight modes |
| GET | `/writing/settings` | No body. Settings and non-secret connection metadata |
| PUT | `/writing/settings` | Any of mode, live_analysis, dark_mode; returns settings wrapper |
| POST | `/writing/analyze` | blocks, mode, revision; returns revision and suggestions |
| POST | `/writing/rewrite` | text, mode, action, revision; returns replacement, explanation, revision |
| POST | `/writing/explain` | original, replacement; returns explanation |
| GET | `/documents?page=1` | Paginated metadata, 50/page, updated descending |
| POST | `/documents` | title, content; returns document, HTTP 201 |
| GET | `/documents/{id}` | Full document or 404 |
| PUT / PATCH | `/documents/{id}` | title, content, expected version; returns updated document |
| DELETE | `/documents/{id}` | expected version; returns HTTP 204 |

`GET /` serves the Vue shell and CSRF meta tag; it is not a JSON endpoint. Version comes from settings, not a separate endpoint.

### Modes and settings

```json
[{"id":"grammar","label":"Grammar Only"},{"id":"natural","label":"Natural"}]
```

Mode IDs: `grammar`, `natural`, `professional`, `casual`, `friendly`, `romantic`, `concise`, `fluent`.

PUT example:
```json
{"mode":"natural","live_analysis":false,"dark_mode":true}
```
GET/PUT response example (timestamps omitted):
```json
{"settings":{"id":1,"mode":"natural","live_analysis":false,"dark_mode":true},"version":"0.2.0","provider":"openai","configured":true,"debounce_ms":700}
```
`configured` checks presence, not key validity. No secret is returned.

### Analyze

```json
{"blocks":[{"id":"0","text":"😊 teh draft"}],"mode":"grammar","revision":4}
```

Constraints: revision nonnegative integer; at most 200 blocks, unique string IDs (50 characters), total at most 12,000 Unicode characters. Empty blocks or an empty block array return no suggestions. Text whitespace is preserved.

```json
{"revision":4,"suggestions":[{"id":"server-generated-sha256","block_id":"0","original":"teh","replacement":"the","occurrence":0,"start":3,"end":6,"explanation":"Correct the spelling.","category":"spelling","severity":"medium","revision":4}]}
```

`start`/`end` are zero-based UTF-16 offsets inside the block; end is exclusive. `occurrence` is the zero-based non-overlapping quote occurrence. Empty replacement is a deletion; original is never empty. Categories: spelling, grammar, clarity, style. Severities: low, medium, high. Maximum 40 suggestions. IDs are opaque. Invalid/overlapping anchors are discarded. Always verify revision and current text before applying.

### Rewrite

```json
{"text":"I is ready.","mode":"natural","action":"natural","revision":8}
```

```json
{"replacement":"I am ready.","explanation":"Use am with I.","revision":8}
```

Text: 1–6,000 characters. Actions: `grammar`, `improve`, `natural`, `professional`, `friendly`, `romantic`, `shorten`, `expand`. Output: non-empty replacement up to 12,000 characters and explanation up to 3,000. Selection range is maintained on the client; the server never edits a document during an AI request.

### Explain

```json
{"original":"teh","replacement":"the"}
```

```json
{"explanation":"The letters were transposed; the is the correct spelling."}
```

Original up to 6,000 characters; replacement may be empty and is limited to 12,000. The backend may give a provider-specific explanation; the example is illustrative, not a fixed output.

### Documents

Create request:
```json
{"title":"My draft","content":{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Hello."}]}]}}
```
Response:
```json
{"id":1,"title":"My draft","content":{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Hello."}]}]},"version":1,"created_at":"2026-10-16T00:00:00.000000Z","updated_at":"2026-10-16T00:00:00.000000Z"}
```

PUT and PATCH both require the complete title/content plus `"version":1`; the returned version becomes 2. DELETE sends `{"version":2}`. A stale update/delete returns 409. Title is required, at most 160 characters. JSON content max 250,000 serialized bytes, 4,000 nodes, depth 12. Supported nodes: doc, paragraph, heading (levels 1–3), blockquote, bulletList, orderedList, listItem, text, hardBreak, horizontalRule. Marks: bold, italic, strike, code, underline. Unsafe/unsupported attributes or node structures are rejected.

List response is Laravel's paginator object: `data` contains `{id,title,version,updated_at}`; use `next_page_url` or increment page until null. Content is excluded from list responses.

### Errors

```json
{"message":"The selected mode is invalid.","errors":{"mode":["The selected mode is invalid."]}}
```

Validation: 422 with per-field errors. Unknown document: 404. Stale document: 409. Non-local/cross-origin: 403. CSRF/session expiry: 419. Rate limit or upstream quota: 429. Missing/rejected credentials: 503. Provider timeout/network failure: 504. Upstream failure, incomplete response, invalid JSON/schema: 502. Provider refusal: 422 with message only. Provider bodies/secrets are not relayed. Never retry automatically with an unknown document version.

## AI connections (v0.2)

These routes use the same local-only/session/CSRF protections and an additional 30 requests/minute connection limit.

| Method | Path | Contract |
|---|---|---|
| GET | `/connections` | provider, configured, api_key_configured, accounts, active_account_id |
| PUT | `/connections` | `{provider: openai/chatgpt/mock, account_id?: integer}`; explicit selection |
| POST | `/connections/chatgpt/start` | Optional account_id for reauthorization; returns `{url}` for full-page navigation to official OpenAI authorization |
| GET | `/connections/chatgpt/models?account_id=1` | Returns `{models:[{id,label}]}` from the authorized account's catalog |
| PUT | `/connections/chatgpt/model` | `{account_id,model}`; checks the current catalog and saves selection |
| POST | `/connections/chatgpt/disconnect` | `{account_id}`; returns revocation_confirmed and a safe status message |

`GET /auth/chatgpt/callback` is outside the /api prefix, limited to 20/minute. It validates single-use state, browser session, PKCE exchange and signed identity, then redirects to `/?connection=return`. A notice is available once from writing/settings. Do not call this route manually. Only HTTP 127.0.0.1 callbacks are generated, with the running app's port.

Account metadata includes id, label, issued client_id, connected, plan_authorized and selected model. It never includes access/refresh/ID tokens or the identity subject. `configured` means local connection prerequisites exist, not that live service access has been verified. No raw API key is accepted by these routes.

Subscription failures: 401 reconnect needed; 403 missing permission; 422 invalid selection/sign-in; 409 concurrent connection change; 429 plan usage unavailable/limited; 502 malformed/incomplete stream; 503 sign-in/model service unavailable; 504 timeout/interruption. Error bodies contain a safe message. No automatic retry or billing fallback occurs.
