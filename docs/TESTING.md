# Test evidence — v0.2.2

Verified on 2026-10-17 using Ubuntu 24.04, PHP 8.3.6, Composer 2.10.3, Node 24.19.0 and installed lockfiles. The workspace-local PHP runtime is not included in the archive.

| Check | Result |
|---|---|
| `php artisan test` | PASS: 38 tests, 154 assertions |
| `npm test` | PASS: 19 tests (15 editor + 4 connection UI); optional HTTP workflow skipped in this command |
| `npm run test:integration` | PASS: 1 complete Vue-to-Laravel HTTP workflow |
| `npm run build` | PASS: 1,611 modules; JS ~494 KB / ~163 KB gzip; CSS ~59 KB / ~14 KB gzip |
| Database migrations | PASS: fresh temporary SQLite database and v0.1 local database upgrade |
| PHP formatting | Pint applied |
| OAuth and subscription provider | Fake HTTP with real RSA-signed JWT fixtures; no live account authorization |
| API-key provider | Fake HTTP payload/error tests; no paid live call |
| MySQL | Not executed; migration is written using Laravel schema APIs |
| Real browser / visual acceptance | Not verified; Chromium was previously blocked by the runtime's Unix-socket restrictions |

The model-selection test verifies that a missing model chooses the fastest available catalog entry. The decoder tests also cover valid wrapped structured output. New tests cover PKCE and issued registration exchange, encrypted storage and metadata secrecy, nonce/issuer/audience/expiry/signature rejection, browser binding, replay prevention, HTTP callback/session continuity, reauthorization subject binding, rotating refresh credentials, catalog filtering, streaming request shape, interrupted and late-failure streams, missing plan scope, sign-out without billing fallback, explicit provider selection and cache isolation across accounts. UI tests cover explicit API selection, model loading/selection, sign-out messaging and failed sign-in. These fixtures validate implementation behavior, not OpenAI account eligibility or live API compatibility.

## What the integration check actually does

It creates a temporary SQLite database, migrates it, starts the real Laravel HTTP server, obtains a session and CSRF token, and mounts the real Vue application in jsdom. It verifies that missing CSRF tokens are rejected with 419; creates a demo document; obtains automatic suggestions; clicks an underline; accepts a correction; waits for autosave; renames the document; changes to Natural mode; selects text via the real ProseMirror API; requests and accepts a rewrite preview; navigates away; reopens the saved document; and persists dark-mode settings. This uses the mock AI provider, not a live model.

jsdom verifies DOM/application behavior but is not a rendering engine. It cannot establish visual quality, actual pointer/selection mechanics, real mobile behavior or browser layout. The included Playwright suite remains a required external acceptance check.

## Reproduce

After installation and a frontend build:

```bash
php artisan test
npm test
npm run test:integration
npm run build
```

The integration script uses a disposable DB and mock provider automatically; it does not use your document database. Keep Laravel configuration uncached for this test because cached configuration would override environment variables. Run `php artisan config:clear` first if needed.

For browser tests, configure a disposable migrated database with AI_PROVIDER=mock, build assets, install Chromium with `npx playwright install chromium`, and run `npm run test:e2e`. Browser tests currently create sample documents and change settings. Do not point them at valuable data. An optional `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` can select an already-installed test browser.

## Verification limits

The v0.2 local implementation is complete for the requested dual-connection scope and is packaged for user testing. A live ChatGPT authorization, eligible subscription inference, live API-key inference, MySQL and rendered browser/mobile acceptance remain unverified. No Git tag or production-ready claim is made. Official integration availability, account permissions and model support can vary.

For live acceptance, run on http://127.0.0.1, connect an eligible account, load/select a model and analyze a short non-sensitive sentence. Verify reconnect and sign-out in ChatGPT Settings. Test API-key mode separately only when you intend separately billed usage. Do not infer live success from the fake-HTTP suite.

The app remains local-only; no remote login/phone access, incremental analysis or offline recovery was added. Existing text-size and single-paragraph rewrite limits remain.
