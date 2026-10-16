# Versioning

VERSION is the canonical application version. `config/writing.php` reads it; the settings API returns it; the UI displays it. Documentation names release versions for humans; package.json deliberately has no app version field.

Initial version: 0.1.0. Follow Semantic Versioning: PATCH for compatible fixes, MINOR for features. Before 1.0, incompatible changes require a MINOR increment and an explicit migration section. After 1.0, breaking changes require MAJOR. Unfinished work belongs in Changelog Unreleased; use an explicit prerelease suffix only when deliberately preparing a preview release.

Release checklist:
1. Review scope and unresolved limitations.
2. Update VERSION and README for the prepared release; refresh cached config.
3. Add a dated Keep a Changelog entry: Added, Changed, Fixed, Security, Removed where applicable. Never invent completed work.
4. Update installation, API, user guide and architecture docs affected by changes.
5. Run PHP tests, frontend tests, frontend build and database migrations; run browser acceptance flow for editor changes.
6. Record verification environment and any unverified external dependencies in TESTING.md.
7. Commit and tag `vMAJOR.MINOR.PATCH` only when the acceptance gates are satisfied. An implementation archive is not proof of production readiness.
8. Package source, lockfiles and documentation; exclude .env, vendor, node_modules, user databases, sessions, caches and logs.

Do not silently rewrite earlier release history. Add corrections under a new release or clearly annotated erratum.
