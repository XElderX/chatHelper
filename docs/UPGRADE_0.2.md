# Upgrade v0.1 → v0.2

This is a local single-user implementation update. Existing documents and writing preferences are retained. Live account eligibility, provider calls, MySQL and real-browser acceptance are still external verification steps; see TESTING.md.

1. Stop chatHelper and back up the existing database, `.env` and `APP_KEY`. Keep backups private. For SQLite, stop the server before copying its database files; for MySQL use your normal consistent database backup.
2. Extract v0.2 into a new directory. Copy the existing `.env` and restore/configure the same database location. Preserve the exact APP_KEY. If DB_DATABASE is an absolute SQLite path, verify it still points to the intended database. Do not overwrite user data with an empty database.
3. From the new chatHelper directory run:

```bash
composer install
php artisan config:clear
php artisan migrate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Do **not** run `key:generate` or `migrate:fresh` on an existing installation. `composer install` is required because v0.2 adds firebase/php-jwt.

4. Open `http://127.0.0.1:8000`. Confirm existing documents and preferences appear. The initial provider remains the previous AI_PROVIDER value until explicitly changed in Settings.
5. Choose **Use API key** for separately billed API usage, or **Continue with ChatGPT** for eligible subscription usage. Complete official authorization, load available models and select one. OPENAI_API_KEY may be blank for subscription mode.

The new migration creates encrypted account credential storage and adds provider/account/host selection fields. It does not alter document content. APP_KEY is now also required to decrypt ChatGPT tokens. Sign-out retains registration mapping for the next authorization and does not select another billing provider.

When moving an already-connected v0.2 installation, avoid running two copies with the same refresh credentials. Prefer signing out on the old computer and reauthorizing on the new one. Keep one active local installation per copied database/host identity.

To undo an upgrade, stop the app and restore the pre-upgrade code, database and matching .env backup together. Do not run destructive rollback commands against unbacked-up data; the migration's down operation drops account credentials.
