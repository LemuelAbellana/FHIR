# FHIR Patient Demo

Small local assessment app: MySQL patient record, encrypted Eloquent attributes,
FHIR R4 Patient JSON, HTTPS transmission, and append-only audit events.

**SYNTHETIC / TEST DATA ONLY. Never enter real patient information.** The default
destination is a public test server. This demo includes a public demo password;
run it locally.

## Run

Prerequisites: PHP 8.4+ with PDO MySQL/SQLite, OpenSSL, mbstring and XML;
Composer; Node.js 22.12+ with a compatible npm; and an existing MySQL database.
Dependencies are installed in this workspace. On a fresh checkout, install them
once with `composer install` and `npm ci`, and copy `.env.example` to `.env`.

Fill these values in `.env` for your existing database:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Then run:

```sh
composer run dev
```

Open <http://localhost:8000>. Login: **admin@example.com / Demo1234!**.

Startup runs `demo:prepare`, then Laravel and Vite in one terminal. Preparation
creates a missing APP_KEY, runs pending migrations, and creates missing demo
records. It preserves existing keys, credentials, changed passwords, and patient
records. It never runs `migrate:fresh`. Keep APP_KEY backed up: encrypted patient
fields cannot be recovered if that key is lost.

## Stack and flow

Laravel 13, Livewire 4, Fortify, Blade, Flux UI, Tailwind CSS, and MySQL.

The authenticated registry reads `Patient` through Eloquent. Laravel encrypted
casts protect first/middle/last names, gender, birth date, email, phone, and
address in TEXT columns. Patient number and active status remain plaintext.
The seeded record is `PT-DEMO-001`, Juan Dela Cruz, with synthetic contact data.

`FhirPatientService` maps the selected database record to an R4 Patient:
identifier, active, official family/given names, gender, birthDate, optional
telecom, and optional address. Patient Details offers **Preview FHIR JSON**,
**Download JSON**, and **Send to FHIR Server**. The download is named
`patient-PT-DEMO-001.fhir.json` for the seeded patient.

The same JSON serializer supplies preview, download and transmission. The Laravel
HTTP client POSTs to `FHIR_BASE_URL/Patient` with `application/fhir+json` headers.
The default base URL is `https://hapi.fhir.org/baseR4`. HTTPS and TLS verification
are required; redirects and automatic retries are disabled. Requests have bounded
timeouts. Set optional `FHIR_BEARER_TOKEN` in `.env` for an authenticated endpoint.
Errors show safe feedback; external unavailability does not prevent local use.

Each send appends an attempt and a separate outcome. Audit rows store only
metadata, destination, status, remote ID and SHA-256 of the exact transmitted JSON.
No payloads, response bodies, bearer tokens or patient contact details are stored
in audit logs. The audit screen is read-only. Model guards and MySQL UPDATE/DELETE
triggers reject mutations. SQLite tests use equivalent triggers. A privileged
database administrator can still remove triggers or truncate/drop tables.

## Checks

```sh
php artisan test
npm run build
composer lint:check
composer types:check
```

Tests use an isolated SQLite in-memory database and HTTP fakes. They require no
real MySQL credentials and send no data externally. Never point destructive test
fixtures at a database you need to retain. MySQL trigger behavior was also checked
against a disposable local MySQL instance during implementation.

Public registration is disabled. All patient, FHIR download and audit routes
require authentication. `.env`, application data, logs and build artifacts are
excluded from version control.
