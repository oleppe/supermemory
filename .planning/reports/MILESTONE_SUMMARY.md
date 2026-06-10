# Milestone Summary — Project Overview

**Generated:** 2026-06-08
**Purpose:** Team onboarding and project review
**Note:** This is a minimal summary. No formal milestone cycle (STATE.md, ROADMAP.md, REQUIREMENTS.md, phased build) has been executed. Content is derived from codebase analysis artifacts (`planning/codebase/`) and quick task summaries.

---

## 1. Project Overview

This is a brownfield Laravel application that serves as the backend for **foobar.net** — a document/memory search platform built on top of Google's Supermemory API with Gemini-powered answer generation and OCR capabilities.

**Architecture:** Laravel JSON API (mobile/system integrations) + Vue SPA admin frontend mounted via Blade.

**Target users:**
- Flutter mobile app consumers (primary consumer of the API)
- Web/admin operators using the Vue SPA
- System integrations consuming the JSON API

**Core value proposition:** Users upload documents or memory text, the system ingests them into Google Supermemory for searchable knowledge retrieval, and returns AI-generated answers grounded in those documents via Gemini LLM synthesis.

**Known context gap:** The README is still a generic Vue template. Some docs reference deprecated Cognee/Cognify flows. Legacy controller/request files for Cognify are present but inactive.

---

## 2. Architecture & Technical Decisions

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2+, Laravel 12, Sanctum auth |
| Frontend | Vue 3.5 + Vuetify 3, TypeScript, Pinia, Vite 7 |
| Database | MySQL (primary), Redis (cache/session) |
| External APIs | Google Supermemory, Gemini, Firebase/Firestore |
| Dev/Infra | Docker Compose, Laravel Sail, PHPUnit 11 |

### Key Technical Decisions

- **Service class pattern over controllers** — Business logic and provider calls are delegated to dedicated service classes (SupermemoryService, GeminiService, UsageLimitService, SubscriptionService, FirestoreSyncService)
- **Per-user scoping via container tags** — Supermemory state is scoped using deterministic `user-{id}` tags; ingestion rows are mirrored into Firestore at `user_ingestions/{userId}/items/{trackingId}`
- **Optional Firestore mirroring** — Sync degrades gracefully to warnings when Firebase is misconfigured, protecting API availability at the cost of potential client-side state drift
- **Usage limit enforcement with transactional counters** — `UsageLimitService` uses DB transactions and `lockForUpdate()` for race-safe quota tracking; controllers must enforce limits before upstream calls and consume after success
- **Hybrid authentication** — Flutter/Auth uses Sanctum tokens; admin routes protected by custom `admin` middleware

### Request Flow (API)
1. `routes/api.php` → Laravel middleware (auth, throttling)
2. Controller validates input via FormRequest classes
3. Service layer handles business logic and upstream API calls
4. Eloquent models persist local state (optional Firestore mirroring)
5. JSON response returned for Flutter/web clients

### Request Flow (Web/SPA)
1. Non-API route falls through to `routes/web.php`
2. Blade view returns SPA shell (`resources/views/application.blade.php`)
3. Vue app mounts at `resources/ts/main.ts`, handles all client-side routing and UI

---

## 3. Phases Delivered

No formal phased build cycle exists for this project. However, two **quick tasks** were completed:

| # | Task | Status | Description |
|---|------|--------|-------------|
| 1 | `260422-cs0` | complete | Replaced Stripe Checkout with mobile-first PaymentSheet flow (PaymentSheet bootstrap controller, PaymentSheet-aware billing service, subscription management tests) |
| 2 | `20260427-translation-endpoint` | complete | Added `/api/translate` endpoint using Google Cloud Translate for multi-text payloads (TranslationController, route registration) |

There are **no phase directories** under `.planning/phases/`. The codebase analysis (`planning/codebase/`) documents represent the structural baseline rather than phase-delivered work.

---

## 4. Requirements Coverage

No formal REQUIREMENTS.md exists for this project. Based on the architecture and active endpoints, the implied requirements are:

- ✅ User registration, login, session management (Sanctum)
- ✅ Document/memory ingestion into Supermemory with Firestore mirroring
- ✅ Search queries against Supermemory (memory + document search)
- ✅ Gemini-powered answer synthesis over search results
- ✅ OCR extraction from uploaded images/PDFs via Gemini structured output
- ✅ Subscription and payment processing via Stripe PaymentSheet
- ✅ Translation endpoint (`/api/translate`) for multi-text translations
- ⚠️ Usage limit enforcement — present but depends on correct controller call ordering; no automated enforcement guardrails found
- ❌ No formal requirements baseline document to validate against
- ❌ No frontend test suite identified

---

## 5. Key Decisions Log

### From Codebase Analysis (`planning/codebase/`)

1. **Choose Laravel over a separate Node service** — Backend owns business logic, upstream API access, and auth; mobile app never sees provider credentials directly
2. **Wrap all external providers behind service adapters** — SupermemoryService, GeminiService, FirestoreSyncService each encapsulate API calls, error normalization, and config access
3. **Hybrid storage: SQL + Firestore + Supermemory** — SQL for core business state (plans, subscriptions, counters), Firestore for mirrored ingestion status, Supermemory as primary document engine
4. **Per-user deterministic scoping** — Container tags like `user-{id}` consistently used across all layers instead of randomized session tokens

### From Quick Tasks

5. **Stripe PaymentSheet over Checkout Session** — Mobile-first bootstrap flow required a new controller action and compatibility layer (`/api/subscription/checkout-session` → PaymentSheet bootstrap)
6. **Google Cloud Translate for `/api/translate`** — Chose the official `google/cloud-translate` package with service-account-based auth

---

## 6. Tech Debt & Deferred Items

### High-Priority Issues

- **Legacy Cognee/Cognify code still present** — `CognifyController.php`, `CognifyRequest.php`, stale docs, and PHPUnit cache entries reference a deprecated architecture
- **Stale README** — Still starts as a generic Vue CLI template; does not describe the actual Laravel API + Flutter architecture
- **Mixed package manager usage** — Repo has both `pnpm-lock.yaml` and npm-based setup scripts (`composer.json` calls `npm install`). Contributors will get inconsistent environments.

### Medium-Priority Issues

- **Firestore sync can silently degrade** — Optional mirroring logs warnings on failure but doesn't alert operators; clients may consume stale status data
- **Usage limit correctness is procedural, not structural** — No framework-level guardrails prevent controllers from skipping the check-before-consume pattern
- **No frontend test suite** — Vue admin app relies on manual verification
- **Template bloat** — Large amount of generic admin-template code under `@core` and `@layouts` increases maintenance surface

### Low-Priority Issues

- Generated/cache artifacts in repo root (`.phpunit.result.cache`, `node-compile-cache/`, `tsx-0/`)
- Open TODOs in shipped framework files (`useSkins.ts`, layout components)
- README and docs still mention cognify flows that are no longer exposed via API routes

---

## 7. Getting Started

### Run the Project

```bash
# Full setup (PHP + JS deps, DB migration, asset build)
composer run setup

# Development mode (Laravel server, queue worker, logs, Vite)
composer run dev

# Run tests
composer test
# or for specific tests:
php artisan test tests/Feature/IngestionControllerTest.php
```

### Key Directories

| Directory | Purpose |
|-----------|---------|
| `app/Http/Controllers/` | API controllers (auth, ingestion, search, OCR, billing) |
| `app/Services/` | External API integration services (Supermemory, Gemini, Firestore) |
| `app/Models/` | Eloquent models (User, Plan, Subscription, UsageCounter, SupermemoryIngestion) |
| `resources/ts/` | Vue SPA frontend source |
| `config/supermemory.php` | Supermemory integration config |
| `docs/` | API integration docs (Flutter API contract, Gemini OCR API) |
| `.planning/codebase/` | Codebase analysis artifacts (architecture, stack, conventions) |

### Tests

- Coverage exists primarily for backend controllers and services
- No frontend tests identified
- Run all: `composer test` or `php artisan test`

### Where to Look First

1. **`docs/flutter-api-integration.md`** — Complete API contract for the Flutter client
2. **`config/supermemory.php`** and **`config/services.php`** — External service configuration
3. **`app/Services/SupermemoryService.php`** — Core integration pattern to follow for any new provider
4. **`tests/Feature/`** — Feature tests show the expected request/response shapes

---

## Stats

- **Timeline:** (no tag or date range could be determined)
- **Phases Delivered:** 2 quick tasks (0 formal phases)
- **Total Commits:** 3 (entire repo history)
- **Contributors:** Abdulrahman
- **Note:** Git statistics are limited. Run `/gsd-new-project` or `/gsd-new-milestone` to start a formal milestone cycle with full tracking.

---

*This is a structural overview, not a build milestone summary. For team onboarding of the actual product architecture and feature set covered by later codebase analysis work, see all files in `.planning/codebase/`.*
