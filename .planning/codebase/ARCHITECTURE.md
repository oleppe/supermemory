# Architecture

Date: 2026-04-21

## High-Level Shape

This is a brownfield monolith with two user-facing surfaces:

- A Laravel JSON API for mobile and system integrations
- A Vue SPA served by Laravel for web/admin experiences

The backend owns business logic and upstream API access. The frontend is a separately bundled client mounted into a single Blade shell.

## Request Flow

### API Flow

1. Request enters through `routes/api.php`
2. Laravel middleware handles auth and throttling
3. Controllers validate input and translate request intent
4. Domain/services perform business logic and upstream calls
5. Eloquent models persist local state where needed
6. JSON responses are shaped for the Flutter/web clients

Common controller traits:

- Thin controllers with constructor-injected services
- Request validation delegated to FormRequest classes or inline validation
- User context resolved through the shared base controller helpers

### Web Frontend Flow

1. Any non-API route falls through `routes/web.php`
2. Laravel returns `resources/views/application.blade.php`
3. `resources/ts/main.ts` mounts the Vue app
4. Client-side routing and UI composition take over

## Backend Layers

### Transport Layer

- Controllers in `app/Http/Controllers`
- Form requests in `app/Http/Requests`
- Middleware aliases configured in `bootstrap/app.php`

### Domain / Service Layer

Service classes in `app/Services` encapsulate most non-trivial behavior:

- `SupermemoryService` for upstream document/memory/search traffic
- `GeminiService` for LLM answer generation and OCR extraction
- `UsageLimitService` for quota enforcement and consumption accounting
- `SubscriptionService` for plan lifecycle and serialization
- `FirestoreSyncService` for secondary-state synchronization
- `PdfPageCounter` for PDF-specific enforcement support

### Persistence Layer

Primary entities in `app/Models` include:

- `User`
- `Plan`
- `Subscription`
- `UsageCounter`
- `SupermemoryIngestion`

This indicates a hybrid model:

- SQL database for core business state
- Firestore for mirrored ingestion status documents
- Upstream Supermemory as the primary memory/document search engine

## Key Domain Workflows

### Authentication And Access

- `app/Http/Controllers/AuthController.php` handles register/login/me/logout
- Sanctum tokens are created per user session
- Admin-only routes are protected with the `admin` middleware alias from `bootstrap/app.php`

### Ingestion

- `app/Http/Controllers/IngestionController.php`
- Accepts either memory text or file uploads
- Assigns user-scoped metadata and custom IDs
- Stores local ingestion rows in `supermemory_ingestions`
- Mirrors local tracking rows into Firestore
- Charges file usage after successful document handoff

### Search And Answering

- `app/Http/Controllers/SearchController.php`
- Runs Supermemory search first
- Extracts normalized context chunks
- Uses Gemini only for answer synthesis on document results
- Returns answer-only payloads plus metadata and file references

### OCR

- `app/Http/Controllers/OcrController.php`
- Accepts document/image uploads
- Uses Gemini structured JSON output for extraction

### Subscription And Limits

- `app/Services/SubscriptionService.php`
- `app/Services/UsageLimitService.php`
- Limits are enforced before upstream calls and consumed after successful operations

## Architectural Style

Observed style is pragmatic Laravel service-oriented MVC:

- Framework-owned routing, DI, and persistence
- Business logic pushed out of controllers into services
- External provider adapters isolated behind service classes
- Request/response contracts optimized for mobile app consumption

## Legacy/Transitional Footprint

There is evidence of an earlier Cognee/Cognify architecture still present in controllers, requests, docs, cached PHPUnit metadata, and logs. The active API surface in `routes/api.php` points to a newer Supermemory-centered design, so the codebase should be treated as partially migrated rather than clean-room greenfield.