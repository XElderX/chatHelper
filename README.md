# chatHelper

A calm writing workspace with live corrections, precise inline suggestions and previewed rewrites.

**Version:** 0.1.0 (canonical: `VERSION`). **Status:** functional local single-user MVP; see [test evidence and release limitations](docs/TESTING.md).

## Included

- Vue 3 / Tiptap rich-text editor with bold, italic, headings, lists, undo and redo.
- Debounced analysis, clickable colored underlines, accept/ignore and explanations.
- Eight writing modes and eight selection rewrite actions with accept/reject previews.
- Document creation, listing/search, rename, deletion, persistent autosave and optimistic locking.
- Responsive dashboard, writing studio, settings and dark mode.
- Official OpenAI Responses API with server-side JSON schema and anchor validation.
- Explicit mock provider for development; never silently substitutes for OpenAI.
- Loopback-only access, CSRF checks, rate limits and encrypted short-lived analysis cache.

## Stack and requirements

Laravel 13, PHP 8.3+, Composer 2, Vue 3, JavaScript, Tiptap 3, Vite, Tailwind CSS 4, MySQL 8+. Node.js 22.12+ or 24 LTS. PHP extensions: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, PDO, pdo_mysql, session, tokenizer, xml, xmlwriter; pdo_sqlite for the default test suite. `unzip` is needed if PHP zip is absent.

## Quick start

Unzip the project, then from the `chatHelper` directory:

```bash
composer install
cp .env.example .env
# Edit .env: set MySQL credentials and OPENAI_API_KEY.
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. For a keyless demonstration set `AI_PROVIDER=mock`; use **Try the writing studio**. Mock rules only correct `teh`, `recieve`, `I is`, and `She go`; they are not a real grammar engine.

See [Ubuntu installation](docs/INSTALLATION.md) for MySQL setup, optional SQLite demo and development mode.

## Development

```bash
composer dev          # Laravel + Vite (after setup)
php artisan test      # Fake HTTP, in-memory SQLite
npm test              # Real editor model tests
npm run build
npm run test:e2e      # Disposable DB, built assets, AI_PROVIDER=mock required
```

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [User guide](docs/USER_GUIDE.md)
- [Architecture](docs/ARCHITECTURE.md)
- [API](docs/API.md)
- [Development](docs/DEVELOPMENT.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Versioning](docs/VERSIONING.md)
- [Roadmap](docs/ROADMAP.md)
- [Testing and verification](docs/TESTING.md)
- [Development report](docs/DEVELOPMENT_REPORT.md)
- [Changelog](CHANGELOG.md)

## Scope and limitations

This version runs on one computer. Mobile screen layouts are supported, but direct phone/LAN access is intentionally blocked. There is no multi-user authentication, cloud sync, browser extension or keyboard integration yet. Do not expose it through a reverse proxy or disable the loopback guard to publish it.

Live analysis supports up to 12,000 Unicode characters / 200 text blocks. Rewrites support one paragraph selection up to 6,000 characters. Whole-document analysis is debounced and cached; incremental paragraph analysis is future work. Cross-paragraph replacements are deliberately rejected. The score is a simple issue-weight heuristic, not an objective assessment.

Documents are stored unencrypted in the configured local database. Encrypted analysis cache expires after five minutes by default. Live AI, manual rewrites and explanations send relevant text to OpenAI when selected. `store:false` is used; this is not a promise of zero provider retention. No document content is logged intentionally. Use a personal trusted OS account.

ChatGPT account login is not implemented. ChatGPT Plus does not include API credits; use your own official API key and separate billing. Live provider quality and availability require testing with your own credentials.
