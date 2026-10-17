# Development report — v0.2.0

The requested dual-connection update is implemented: API-key requests retain separate API billing, while Continue with ChatGPT uses the documented official local/OSS OAuth and subscription integration. It does not use GitHub login or extract ChatGPT browser sessions.

## Changes

Added ConnectionManager, OpenAiIdentity and ChatGptAuth services; ChatGptProvider and bounded SSE ResponseDecoder; connection controller and Form Requests; encrypted ChatGptAccount model and additive migration; ConnectionSettings Vue component; OAuth/security and UI tests. Updated provider binding, settings metadata, cache identities and stale-result handling. Existing document/editor features remain.

New credentials are encrypted using APP_KEY, never returned to the browser. Sign-in validates PKCE, session-bound single-use state, nonce and signed identity claims. Refresh rotation is serialized. Account registrations remain distinct; reauthorization retains subject/client binding. Sign-out clears tokens and attempts revocation, reporting uncertain remote revocation. Subscription failures do not select the paid API provider.

## Setup and upgrade

Use INSTALLATION.md for a fresh local install and UPGRADE_0.2.md for v0.1 upgrades. Preserve .env, APP_KEY and documents. Install Composer dependencies, run config:clear and migrate, install frontend dependencies with npm ci, build, and serve on 127.0.0.1. Settings provides both connection options; ChatGPT requires loading/selecting an account-specific model after authorization.

## Evidence

36 PHP tests / 149 assertions, 19 frontend tests, the isolated real-HTTP Vue/Laravel writing workflow, production build and SQLite migrations passed. Composer successfully installed firebase/php-jwt 7.2.1 without disabling security advisory checks. See TESTING.md for exact scope.

Live OpenAI API usage, live ChatGPT authorization/inference, MySQL and actual rendered browser acceptance were not executed. The archive is a local implementation for user testing, not a production certification. No paid API requests, external deployment, GitHub changes or Git release tag were made.
