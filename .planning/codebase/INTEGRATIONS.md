# Integrations

Date: 2026-04-21

## Overview

The application is primarily an integration layer. Laravel sits between a Flutter client and upstream AI/document services, while also syncing ingestion state into Firestore for client-facing status tracking.

## Supermemory

Primary service wrapper: `app/Services/SupermemoryService.php`

Configuration:

- `config/supermemory.php`
- env vars: `SUPERMEMORY_BASE_URL`, `SUPERMEMORY_API_KEY`, `SUPERMEMORY_TIMEOUT`

Observed upstream endpoints:

- `POST /v4/memories` for memory ingestion
- `POST /v3/documents/file` for document upload
- `GET /v3/documents/{id}` for document lookup
- `POST /v3/documents/list` for listing documents
- `POST /v4/search` for both memory and document search

Usage in app:

- `app/Http/Controllers/IngestionController.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Controllers/SystemController.php`
- `app/Http/Controllers/AuthController.php` exposes configuration state only

Integration pattern:

- Bearer token auth via Laravel HTTP client
- Per-user scoping via deterministic `containerTag` values such as `user-{id}`
- Errors normalized into `App\Exceptions\SupermemoryApiException`

## Gemini

Primary service wrapper: `app/Services/GeminiService.php`

Configuration:

- `config/services.php` under `gemini`
- env vars: `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_BASE_URL`, `GEMINI_TIMEOUT`, `GEMINI_TEMPERATURE`, `GEMINI_MAX_OUTPUT_TOKENS`

Observed upstream usage:

- `POST /models/{model}:generateContent`

Application responsibilities:

- Generate answer-only responses for document search results in `app/Http/Controllers/SearchController.php`
- OCR-style structured extraction from uploaded images/PDFs in `app/Http/Controllers/OcrController.php`

Integration pattern:

- API key sent as `x-goog-api-key`
- JSON-only answer handling with validation and malformed-response protection
- OCR requests can include inline base64 document/image content

## Firebase / Firestore

Primary service wrapper: `app/Services/FirestoreSyncService.php`

Configuration:

- `config/services.php` under `firebase`
- env vars: `FIREBASE_PROJECT_ID`, `FIREBASE_SERVICE_ACCOUNT_JSON`, `FIREBASE_SERVICE_ACCOUNT_PATH`, `FIREBASE_INGESTIONS_COLLECTION`

Responsibilities:

- Mirror `supermemory_ingestions` rows into Firestore at `user_ingestions/{userId}/items/{trackingId}`
- Keep mobile clients informed of ingestion state independently of primary SQL reads

Integration behavior:

- Gracefully skips when Firebase is not configured
- Accepts either JSON credentials or a file path
- Logs warnings rather than breaking request flow on Firestore failure

## Flutter Client Contract

Primary documentation:

- `docs/flutter-api-integration.md`
- `docs/flutter-gemini-ocr-api.md`

Backend API role:

- Flutter authenticates only with Laravel Sanctum tokens
- Flutter never receives upstream provider credentials
- Laravel shapes responses with subscription state, usage snapshots, and file-reference metadata

## Local Development Services

Defined in `compose.yaml`:

- `laravel.test` application container
- `mysql`
- `redis`
- `selenium`
- `mailpit`

## Notable Drift

- `docs/flutter-api-integration.md` and `README.md` still mention older `cognify` flows.
- Legacy Cognify/Cognee classes remain in the codebase, but the current `routes/api.php` no longer exposes a cognify endpoint.