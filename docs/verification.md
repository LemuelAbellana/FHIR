# Acceptance evidence

Verified 19 October 2026 (Asia/Manila).

- Laravel Framework 13.37.0; Livewire 4.4.7; PHP 8.4.10.
- `composer validate --strict`: pass.
- `php artisan --version`, both Composer version checks, `php artisan about`,
  and `php artisan route:list`: pass. Runtime database configuration is MySQL.
- `composer test`: pass, including Pint, PHPStan level 7, and all tests.
- `php artisan test`: 57 passed, 290 assertions, no skips or risky tests.
- Same full suite against a disposable MySQL 9.5 instance: 57 passed,
  290 assertions. UPDATE/DELETE audit triggers were exercised successfully.
- `npm run build`: pass. Composer/npm audits: no reported vulnerabilities.
- `composer run dev`: verified twice against disposable MySQL. Preparation,
  Laravel HTTP server and Vite all started. Local login returned HTTP 200.
  Restart preserved both synthetic patients and audit events without duplicates.
- Browser: seeded login, authenticated navigation, dynamic JSON preview,
  synthetic patient creation, read-only audit display, desktop and 390px mobile
  layouts verified. No console warnings/errors or page-level horizontal overflow.
  Light UI remains readable with an operating-system dark preference.
- One real HTTPS smoke transmission used only the seeded synthetic patient:
  HAPI returned HTTP 201 and remote Patient ID `158358`. Separate attempt and
  success rows were appended; the audit UI displayed the metadata.
- Encryption checked in raw MySQL storage and automated tests. Download content
  matches the shared serializer and reflects database changes.
- Independent security/privacy and final acceptance reviews found no required
  corrections remaining after static-analysis fixes.

The user's real MySQL connection was not used. `.env` database/username/password
values remain blank for the user to configure; APP_KEY was generated locally
from an initially empty value. `.env`, dependencies, logs and generated artifacts
are ignored by Git. No commit or push was made. Temporary verification services
were stopped after checks.

The host's npm 12 emits a compatibility warning with Node 22.15; installation,
build, audit and the development processes nevertheless completed successfully.
