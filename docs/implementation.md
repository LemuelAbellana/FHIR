# Implementation record

Authoritative scope: `FHIR_Laravel13_Livewire4_Codex_Goal.md`.

Use the official Laravel 13 / Livewire 4 starter kit, Fortify, Blade, Flux,
Tailwind, and MySQL. Keep one application, two domain models, one FHIR
service, five Livewire page components, and a safe preparation command.

## Ownership and checkpoints

- Root: scaffold, dependency/config integration, authenticated routes,
  JSON download, startup, README, acceptance and browser verification.
- A: encrypted Patient, append-only AuditLog, migrations/triggers,
  factories, idempotent seeders, demo:prepare, focused data tests.
- B: FHIR mapping/serialization, HTTPS transport, safe outcomes and
  audit metadata, focused fake-HTTP tests.
- C: Livewire pages, starter layout/login, accessible responsive UI,
  focused UI tests. Installed UI UX Pro Max guides design.
- D: independent QA, auth/download/integration coverage and review.

Shared FHIR contract: `payload(Patient): array`, `json(Patient): string`,
`send(Patient, ?int $userId): array` returning `success`, `http_status`,
`remote_resource_id`, `timestamp`, `message`.

## Engineering loop

- [x] Inspect empty workspace and complete specification.
- [x] Architecture checkpoint and disjoint file ownership.
- [x] Parallel implementation with focused automated checks.
- [x] Integrate and run full PHP tests, formatter and asset build.
- [x] Review security/privacy and fix findings with regression tests.
- [x] Verify versions, Composer validation, Artisan about/routes.
- [x] Verify composer run dev and local HTTP/browser behavior.
- [x] Record actual verification and credential-dependent limits.

No commits or pushes. Preserve all existing keys and database credentials.
Use only synthetic data. Test transport with HTTP fakes; never store payloads
or response bodies in audit logs. Disable redirects and require HTTPS.
SQLite tests are separate from runtime MySQL; MySQL triggers need a dedicated
test connection or isolated test server. Normal startup runs pending migrations
and idempotent seeders, never migrate:fresh.
