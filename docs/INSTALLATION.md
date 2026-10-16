# Installation — Ubuntu 24.04

## 1. System requirements

PHP 8.3+ and Composer 2; Node.js 22.12+ or 24 LTS; MySQL 8+; Git and unzip. Ubuntu 24.04's standard PHP 8.3 packages are sufficient.

```bash
sudo apt update
sudo apt install php8.3-cli php8.3-common php8.3-curl php8.3-mbstring \
  php8.3-xml php8.3-mysql php8.3-sqlite3 php8.3-zip unzip git composer mysql-server
php -v
composer --version
node --version
npm --version
```

Install a supported Node LTS from https://nodejs.org/en/download if missing. Use the official distribution or a trusted version manager. Ubuntu's older Node package may not meet Vite requirements. Do not use `--ignore-platform-reqs` to work around PHP errors.

## 2. Database

```bash
sudo systemctl enable --now mysql
sudo mysql
```

At the MySQL prompt (choose a strong unique password):

```sql
CREATE DATABASE chathelper CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chathelper'@'127.0.0.1' IDENTIFIED BY 'replace-with-your-password';
GRANT ALL PRIVILEGES ON chathelper.* TO 'chathelper'@'127.0.0.1';
EXIT;
```

Use the same host and password in `.env`. The application must not use the MySQL root account.

## 3. Project

```bash
unzip chatHelper-0.1.0.zip
cd chatHelper
composer install
cp .env.example .env
```

Edit `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chathelper
DB_USERNAME=chathelper
DB_PASSWORD="replace-with-your-password"
AI_PROVIDER=openai
OPENAI_API_KEY="your-own-key"
OPENAI_MODEL=gpt-4.1-mini
```

Alternatively set `AI_PROVIDER=mock` for offline demonstration. A missing real key produces a useful configuration error, never a fake success.

```bash
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000. The PHP development server is for local use. The built frontend requires no separate Vite process.

## 4. Development

From the installed project:

```bash
composer dev
```

Or run `php artisan serve --host=127.0.0.1` and `npm run dev` in separate terminals. Both must remain running. Never use `--host=0.0.0.0`. Do not tunnel or reverse proxy the MVP.

## 5. Optional SQLite demo

MySQL is the target database. For a quick isolated demo, use SQLite:

```bash
touch database/database.sqlite
```

Set `DB_CONNECTION=sqlite` and `DB_DATABASE=/absolute/path/to/chatHelper/database/database.sqlite`, then run `php artisan config:clear` and `php artisan migrate`. Tests use their own in-memory SQLite database, not this file.

## 6. Verify

```bash
php artisan test
npm test
npm run build
php artisan migrate:status
```

For browser integration tests, use a disposable database, set `AI_PROVIDER=mock`, run migrations and build assets, then `npx playwright install chromium` and `npm run test:e2e`. They create documents and modify settings.

## Common setup failures

- `could not find driver`: install the matching `php8.3-mysql` / `php8.3-sqlite3`; confirm `php -m`.
- MySQL access denied: match username, password and `127.0.0.1` host in SQL and `.env`.
- Missing application key: run `php artisan key:generate` once. Preserve this key thereafter.
- Write permissions: the account running PHP needs write access to `storage` and `bootstrap/cache`; do not use world-writable permissions.
- Vite missing manifest: `npm ci && npm run build`; stop stale Vite processes and remove a stale `public/hot` only if Vite is stopped.
- Port in use: use another loopback port and update `APP_URL`.

Detailed diagnostics: [Troubleshooting](TROUBLESHOOTING.md).
