# Troubleshooting

| Symptom | Check / action |
|---|---|
| API key required | Set OPENAI_API_KEY in `.env`, or connect ChatGPT in Settings, or choose the local demo. Clear config and restart |
| OpenAI credentials rejected | Verify the project key, model access and billing; do not paste keys into browser code |
| OpenAI rate limit / quota | Check project usage and billing; wait before retrying, increase debounce or disable live analysis |
| Timeout | Retry once, use a shorter passage; check connectivity and OPENAI_TIMEOUT |
| Unsupported model/options | Verify Responses structured-output support; clear optional temperature/reasoning values |
| Invalid AI data | Shorten the passage and retry; output validation intentionally rejects malformed results |
| No underlines | Check Live analysis, connection status, selected mode and limits. Use Analyze now. Mock recognizes only four demo patterns |
| Suggestion disappears while typing | Expected: old ranges are cleared to prevent stale replacements |
| Rewrite disabled | Select text in one paragraph; no selection or a cross-paragraph selection is unsupported |
| Rewrite preview disappears | The document was edited, invalidating the captured selection |
| Save failed / 409 | Another tab changed the document. Copy unsaved content before reloading. Do not force an overwrite |
| Save fails on rich text | Unsupported structure, >250 KB, too many nodes or excessive depth; simplify formatting / split document |
| Database access denied | Confirm MySQL service, DB user grant host, credentials and database existence |
| Migration fails | Check database connectivity and PHP PDO extensions. Use `php artisan migrate:status`; never use migrate:fresh on valuable data |
| PHP extension missing | `php -m`; install matching Ubuntu PHP packages and confirm CLI version |
| npm dependency errors | Use supported Node, `npm ci` with included lockfile; do not delete lockfile just to bypass incompatibilities |
| Vite error / blank page | `npm run build`; check manifest and browser console; if Vite is stopped remove stale public/hot |
| Session expired / 419 | Copy unsaved content, then reload; the CSRF token/session must match |
| 403 from phone / LAN | Expected in local-only MVP. Do not disable the guard; authenticated remote access is future work |
| Settings change in .env ignored | Provider and writing preferences persist. Change them in Settings; AI_PROVIDER is only the default before a provider is selected |
| Cache decrypt error after key change | Preserve original APP_KEY where possible, or `php artisan cache:clear` |

Server errors: `storage/logs/laravel.log`. Do not enable request body logging or share document content/API keys when reporting problems. Expected AI exceptions are intentionally not logged. Include version, PHP/Node versions, endpoint/status and reproducible non-sensitive sample text.

## ChatGPT connection

- “Open at 127.0.0.1”: use `http://127.0.0.1:<port>`, not localhost, for the official local callback. Keep the server running on that port.
- State expired/different browser: start again from Settings in the same browser. Do not copy callback URLs between browsers. Ensure the file cache/session directories are writable.
- No subscription permission: reconnect and authorize plan usage. Account eligibility and organization policy are determined by OpenAI. API credentials do not grant subscription access.
- No model selected: load available models for the selected account and choose one. If the list is empty, check account authorization. Models are never guessed from OPENAI_MODEL in subscription mode.
- Usage unavailable/limited: review ChatGPT Settings → Usage; wait or explicitly choose API-key billing. No automatic paid fallback occurs.
- Interrupted stream: retry with shorter text. Partial responses are discarded and do not modify documents.
- Remote revocation unconfirmed: local credentials were removed; also disconnect the app in ChatGPT Settings. Stable registration records remaining locally are intentional.
- Credential decryption error after upgrade: restore the original APP_KEY. Do not generate a new key on an existing installation.
- Login page refuses registration: this release follows the official local/OSS integration, whose availability and eligibility may vary. Do not use unofficial session-cookie workarounds.

Do not share OAuth callback query strings, authorization codes, token responses or credential/database files when reporting a problem.
