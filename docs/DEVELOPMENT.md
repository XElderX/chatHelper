# Development

Install according to INSTALLATION.md. Run `composer dev` for local Laravel + Vite. Node's package version is intentionally omitted so VERSION is canonical; Laravel's configuration reads it, and the UI receives it through settings.

## Layout

- `app/Services/AI`: provider abstraction, prompts, output validation, analysis/rewrite services.
- `app/Http/Requests`: API input contracts.
- `app/Http/Controllers`: thin HTTP adapters and CRUD.
- `app/Http/Middleware/LocalOnly.php`: loopback, Host, Origin restrictions.
- `app/Rules/RichText.php`: rich JSON allowlist and bounds.
- `config/writing.php`: version, provider controls, modes.
- `resources/js/App.vue`: dashboard/editor/settings and request lifecycle.
- `resources/js/lib`: API transport, anchor mapping, decoration plugin.
- `resources/css/app.css`: Tailwind entry plus responsive component styling.
- `database/migrations`: documents/settings and standard Laravel tables.
- `tests/Feature`, `tests/frontend`, `tests/browser`: backend, editor, browser tests.

## Conventions

Follow PSR-12 in PHP (format with `vendor/bin/pint`), keep validation separate, inject service interfaces and return safe errors. JavaScript uses ES modules and Vue Composition API. No model text may be inserted as HTML. Any document/schema extension must update the allowlist, editor configuration, tests, API and user guide together.

To add a mode, extend `config/writing.php` with a stable ID, label and instruction; the API and frontend populate automatically. Add a behavioral test of the prompt and update docs.

To add a provider, implement `AiProviderInterface::generate(task,input,mode)` and bind it in AppServiceProvider. Match the schema and keep validation in shared services. Add fake-HTTP tests and explicit configuration. Never silently fall back to a mock provider.

## Tests

```bash
php artisan test
npm test
npm run build
npm run test:integration  # Isolated temporary SQLite DB; mock provider
```

Backend tests use in-memory SQLite and `Http::fake`; stray HTTP is forbidden. Use a disposable MySQL database to run the same feature suite against MySQL, overriding phpunit environment DB configuration explicitly in a dedicated test config (never point tests at valuable data). Browser tests require migrated disposable DB, built assets and `AI_PROVIDER=mock`; they create documents and change settings:

```bash
npx playwright install chromium
npm run test:e2e
```

No live API key is necessary for automated tests. A live smoke test should be deliberate, with non-sensitive sample text and a billing budget.

## Contribution / release workflow

Read AGENTS.md. Keep commits scoped. Write regression tests for state/range/persistence changes. Update CHANGELOG, API and user docs as needed. Run required gates, document exact results and limitations. Update VERSION only for a prepared release. Do not tag an unverified release; see VERSIONING.md.
