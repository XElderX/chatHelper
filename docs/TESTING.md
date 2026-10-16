# Test evidence and release status

Verified on 2026-10-16 using Ubuntu 24.04, PHP 8.3.6, Composer 2.10.3, Node 24.19.0 and installed lockfiles. PHP was extracted into a workspace-local runtime because system installation was unavailable. This runtime is not included in the deliverable.

| Check | Result |
|---|---|
| `php artisan test` | PASS: 23 tests, 84 assertions |
| `npm test` | PASS: 15 editor tests; optional HTTP workflow skipped in this command |
| `npm run test:integration` | PASS: 1 complete Vue-to-Laravel workflow test |
| `npm run build` | PASS: 1,608 modules; JS ~489 KB / ~161 KB gzip, CSS ~58 KB / ~14 KB gzip |
| Database migrations | PASS on SQLite, including fresh isolated integration database |
| PHP syntax / Pint | Passed syntax inspection; PHP sources formatted with Pint |
| OpenAI HTTP integration | Tested with Laravel fake HTTP and validated Responses payload; no billable live call |
| MySQL | Not executed; migrations and default configuration target MySQL |
| Browser acceptance / visual layouts | Not verified: Chromium failed to create a required Unix socket (`Operation not permitted`) in this environment |

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

## Release decision

Status: **Partially Completed** against the full production-oriented brief. The core local workflow is implemented and passes the automated checks above. Full browser/visual, real-provider and MySQL acceptance remain outstanding, so no release-ready claim or Git tag has been made. VERSION remains the requested initial 0.1.0; no version increment was made for these verification limits.

Other limitations: local computer only; no authentication/remote phone access; 12,000-character analysis cap; single-paragraph rewrites; no incremental analysis, offline recovery, extensions or keyboard/desktop clients; heuristic score; no guarantee AI finds every mistake.
