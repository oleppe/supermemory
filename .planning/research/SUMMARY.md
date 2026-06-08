# Project Research Summary

**Project:** Supermemory Document Manager — v1.9 User Panel
**Domain:** AI-powered document management web frontend (adding to existing Laravel API backend)
**Researched:** 2026-06-08
**Confidence:** HIGH

## Executive Summary

This project adds a Vue 3 + Vuetify 3 web frontend to an existing Laravel 12 backend that already provides all core API endpoints (auth, document ingestion, OCR, search, billing). The frontend is built on the existing Vuexy v9.5.0 template, which provides the app shell, layouts, auth page stubs, form components, and composables — meaning most foundational UI work is already done. The primary task is wiring existing static pages to live APIs, building new domain-specific components (file upload, document list, AI chat, OCR scanner), and adding minimal backend infrastructure (local file storage, chat convenience endpoint).

The recommended approach is **frontend-first with minimal backend changes**: reuse all existing API endpoints, add a `StorageService` + `StoredFile` model for local file persistence (currently files go only to Supermemory with no local copy), and build custom lightweight components rather than adopting heavy third-party libraries. The existing `useApi` composable already handles Sanctum Bearer token injection from cookies — the auth pattern is proven and should not be changed. Only 3 new packages are needed for Phase 1: `@tato30/vue-pdf` (lazy-loaded PDF preview), `marked` (markdown rendering for AI chat), and `league/flysystem-aws-s3-v3` (S3 storage driver, pre-configured but not installed).

Key risks center on **synchronous backend operations that block**: OCR analysis via Gemini takes 10-60s and currently runs inline in the request cycle, Supermemory file uploads are synchronous with no retry, and chat conversation history is not persisted (lost on refresh). These must be addressed with async job processing + polling for OCR/uploads, and at minimum client-side conversation persistence for chat. The local-to-S3 storage migration path must be designed from day one (relative paths, disk column) to prevent breakage when scaling.

## Key Findings

### Recommended Stack

The stack philosophy is **use what's already installed, build custom for domain-specific needs, reject heavy third-party libraries**. The existing codebase has a rich foundation: Vue 3.5, Vuetify 3.10, Pinia 3, VueUse 10 (includes `useDropZone`, `useFileDialog`), ofetch, vue-i18n, CASL permissions, and Tiptap editor — all already configured and working.

**New packages (Phase 1 — MVP):**
- `@tato30/vue-pdf` — PDF preview in browser; Vue 3 native, pdf.js under the hood; **must lazy-load** (~500KB gzipped)
- `marked` — Render Gemini's markdown AI responses; tiny (~15KB), fast, needs custom renderer for `app-file://` protocol links
- `league/flysystem-aws-s3-v3` ^3.0 — S3 filesystem driver; config already exists in `config/filesystems.php`, only the Composer package is missing

**New packages (Phase 2 — Enhanced, only if needed):**
- `vee-validate` + `@vee-validate/zod` + `zod` — Form validation (only if Vuetify `:rules` become insufficient)
- `laravel/reverb` + `laravel-echo` + `pusher-js` — WebSocket server for real-time (only if streaming chat or live processing status needed)

**Explicitly rejected:**
- FilePond / vue-dropzone / vue-file-agent — VueUse composables cover our needs with zero bundle weight
- vue-advanced-chat / deep-chat — Over-engineered for single-thread document Q&A; custom Vuetify components are simpler and better themed
- axios — ofetch already in codebase via `useApi`; two HTTP clients creates confusion
- markdown-it — `marked` is smaller and sufficient
- vue-pdf-embed — `@tato30/vue-pdf` is more maintained

**Bundle budget:** Main bundle must stay under 500KB gz (currently ~350KB). PDF viewer (~500KB) and chat markdown are lazy-loaded only.

### Expected Features

The backend already provides all API endpoints. The frontend consumes them — it does not rebuild them. Every endpoint in `routes/api.php` has a corresponding frontend need.

**Must have (table stakes):**
- **Authentication flow** — Wire existing Vuexy login template to `POST /api/auth/login` and `POST /api/auth/register`; Pinia auth store with token cookie persistence; route guards; auto-hydrate via `GET /api/auth/me` on app boot
- **File upload interface** — Extend existing `DropZone.vue` for PDF + images; multi-file with per-file progress bars (XMLHttpRequest for upload progress); client-side validation (50MB max, jpg/png/webp/pdf only); wire to `POST /api/documents`
- **Document list/library view** — Paginated grid from `GET /api/documents`; sort controls; document cards with type badges; empty state + loading skeletons; responsive grid
- **Document detail view** — Metadata display, content/summary, file preview (image or PDF thumbnail), extracted entities (Organization, Date, Currency, Address), action items
- **Usage/quota awareness** — Display files used/remaining, AI questions used/remaining from `GET /api/usage/counters`; quota exceeded warnings with upgrade CTA

**Should have (differentiators — core value proposition):**
- **AI chat interface** — Single-thread document Q&A using `POST /api/search/documents`; chat bubbles (user + assistant); conversation history (max 12 messages, 4000 chars each); markdown rendering of Gemini responses; `app-file://` link interception; source citation display as clickable links; typing indicator during 3-10s response wait
- **Document scanning workflow (OCR)** — "Analyze" button triggering `POST /api/ocr/analyze`; category multi-select chips; multi-page support (up to 12 pages); structured results display (document_type, category, summary, entities, amount, items, action_items); re-scan with different categories
- **Smart classification display** — Color-coded type badges (invoice=green, receipt=blue, contract=purple); entity cards; financial summary (amount/tax/tip); line items table for invoices/receipts

**Defer to future milestone:**
- Folder/tag organization — No backend support; requires DB schema changes
- Real-time streaming chat — Backend is synchronous; add SSE streaming in Phase 2
- File versioning — Supermemory documents are immutable after upload
- Collaborative sharing — Single-user per container tag; no sharing model
- Rich text editor for notes — Tiptap installed but no MVP need
- Admin panel in user SPA — Admin routes exist separately at `/admin/{any?}`
- OAuth/social login — Backend only supports email/password
- Offline mode — Requires service worker + IndexedDB + sync queue (massive scope)
- Standalone semantic search page — Chat subsumes search for MVP
- Billing/subscription management — Stripe portal exists but not critical for core flow

### Architecture Approach

The architecture follows a **thin frontend consuming a thick backend** pattern. The Vue 3 SPA (served from `/admin/`) communicates with the Laravel API (`/api/`) exclusively via HTTP with Bearer token auth. No WebSocket infrastructure for Phase 1. The frontend uses file-based routing (`unplugin-vue-router`), Pinia for state management, and a layered composable pattern (pages → components → composables → API).

**Major components:**

1. **Auth Layer** — `useAuthStore` (Pinia) manages user/token/subscription/usage state; token stored in cookie via `useCookie('accessToken')` matching existing `useApi.ts` pattern; router `beforeEach` guard redirects unauthenticated users to `/login`; **do NOT switch to Sanctum SPA cookie auth** (Flutter mobile app depends on token-based flow)

2. **File Upload System** — Two-track architecture: Track A stores files locally via new `StorageService` → `StoredFile` model (enables previews, downloads, S3 migration); Track B sends to Supermemory via existing `IngestionController` (document intelligence); upload composable uses `XMLHttpRequest` for progress tracking (fetch API doesn't support upload progress); chunked upload protocol for files ≥5MB

3. **Storage Abstraction** — New `StorageService` wraps Laravel Storage facade with local-first, S3-ready design; stores relative paths + disk name per file; serves local files through authenticated controller route (not public symlink); generates temporary S3 URLs when on S3 disk; includes `php artisan files:migrate-to-s3` command for future migration

4. **AI Chat Interface** — Phase 1: reuse existing `POST /api/search/documents` endpoint (Supermemory search → Gemini answer); conversation history maintained client-side in `useChatStore` (Pinia); Phase 2: add SSE streaming via new `ChatController@stream` for token-by-token response display

5. **State Management** — Five Pinia stores: `useAuthStore` (session), `useDocumentsStore` (list/detail), `useFilesStore` (upload queue/progress), `useChatStore` (messages/history), `useScanStore` (OCR state); server state in stores, UI state in components; no WebSocket subscriptions

**New backend endpoints needed:**
- `POST /api/files/upload` — Direct upload (small files <5MB)
- `POST /api/files/initiate` + `chunks/{id}` + `complete/{id}` — Chunked upload protocol
- `GET /api/files/{file}/download` — Authenticated file serving
- `POST /api/chat/ask` — Convenience wrapper (Phase 1, can also use existing search endpoint directly)
- `GET /api/chat/stream` — SSE streaming (Phase 2)

**New backend infrastructure:**
- `StorageService` — Local/S3 abstraction
- `StoredFile` model + migration — File metadata with relative paths + disk column
- `FileController` — File CRUD endpoints
- `ChatController` — Chat convenience endpoint (Phase 1)

### Critical Pitfalls

1. **SPA Token Management** — Token loss on refresh, silent logout, or cross-tab desync. **Prevention:** Use existing cookie pattern (`useCookie('accessToken')`); build reactive `useAuthStore` with `initAuth()` on app boot that calls `GET /api/auth/me`; test multi-tab logout sync; never use localStorage (XSS vector).

2. **Upload Timeout / Silent Failure** — Large files fail silently when Supermemory upload is synchronous with 30s timeout; base64 encoding doubles payload. **Prevention:** Store locally first, then queue async Supermemory upload with retry (3 attempts, exponential backoff); validate Supermemory response contains document ID; use XMLHttpRequest for progress tracking; return 202 Accepted immediately.

3. **OCR Request Blocking** — Gemini API takes 10-60s for multi-page PDFs; synchronous processing exhausts PHP-FPM workers and hits 30s timeout. **Prevention:** Move OCR to queue job with batch ID; return 202 + batch_id immediately; frontend polls `GET /api/ocr/status/{batch_id}` every 2s; show indeterminate progress indicator.

4. **Chat Context Loss on Refresh** — Conversation history not persisted; messages stored in component state lost on navigation. **Prevention (Phase 1 minimum):** Store conversation in Pinia store (survives component unmount); persist to `sessionStorage` for cross-refresh survival; include message IDs + timestamps for ordering; send max 6 messages of history to Gemini. **Prevention (Phase 2 ideal):** Backend conversation persistence with `Conversation` + `Message` models.

5. **Local → S3 Migration Breaks Files** — Absolute paths in DB become invalid after migration. **Prevention:** Store relative paths + `storage_disk` column from day one; generate URLs dynamically via `StorageService::url()`; write idempotent migration command; keep local files 7 days after migration as rollback.

## Implications for Roadmap

Based on the dependency graph and architectural analysis, the following phase structure is recommended:

### Phase 1: Auth Foundation & App Shell
**Rationale:** Everything depends on authentication. The existing Vuexy login page is a static shell that needs wiring. Without auth, no other feature works. The app shell (navigation, layouts, route guards) must be in place before building domain pages.
**Delivers:** Working login/register/logout; authenticated app shell with navigation sidebar; auth state management; route guards; token persistence across refresh.
**Addresses:** Auth Flow (table stakes), Usage Display foundation
**Avoids:** Pitfall #1 (token management) — build reactive auth store from day one
**Backend changes:** None (all auth endpoints exist)
**Frontend deliverables:**
- Wire `resources/ts/pages/login.vue` to `POST /api/auth/login`
- Create `resources/ts/pages/register.vue`
- Create `useAuthStore` (Pinia) with login/register/logout/fetchMe/initAuth
- Add router `beforeEach` guard for protected routes
- Update navigation (`navigation/vertical/index.ts`) with app sections
- Configure blank layout for auth pages, default layout for app pages

### Phase 2: File Upload & Document Management
**Rationale:** File upload is the primary user action and the gateway to all AI features. Documents must exist before they can be scanned, viewed, or queried. This phase also introduces the local storage layer that all subsequent features depend on.
**Delivers:** Drag-and-drop file upload with progress; document list with pagination/sort; document detail view with metadata and preview; local file storage infrastructure.
**Uses:** VueUse `useDropZone`/`useFileDialog`, existing `DropZone.vue`, `useApi` composable, Pinia stores
**Implements:** StorageService, StoredFile model, FileController, useFileUpload composable
**Avoids:** Pitfall #2 (upload timeout) — store locally first, async Supermemory forwarding; Pitfall #5 (S3 migration) — relative paths + disk column from day one
**Backend changes:**
- Create `StorageService` with local disk
- Create `StoredFile` model + migration (relative paths, disk column)
- Create `FileController` with upload/download endpoints
- Install `league/flysystem-aws-s3-v3`
- Add file validation rules (MIME, size, safe filenames)
**Frontend deliverables:**
- Extend `DropZone.vue` for PDF + images
- Build `useFileUpload` composable (XMLHttpRequest for progress, Sanctum auth header)
- Build upload progress UI (per-file bars, batch status)
- Create `useDocumentsStore` (Pinia)
- Build document list page with pagination, sort, empty state
- Build document detail page with metadata, entities, preview
- Install + lazy-load `@tato30/vue-pdf` for PDF preview

### Phase 3: AI Chat & Document Scanning
**Rationale:** These are the core differentiators — the features that make this product more than a generic file manager. They depend on Phase 2 (documents must exist to scan/chat about). Building them together makes sense because they share the Supermemory + Gemini service layer and both involve long-running operations with progress feedback.
**Delivers:** AI chat interface for document Q&A; OCR document scanning with structured results; smart classification display; usage dashboard.
**Uses:** `marked` for markdown rendering, Vuetify chat components, existing `POST /api/search/documents` and `POST /api/ocr/analyze` endpoints
**Implements:** useChatStore, useScanStore, ChatWindow/ChatMessage/ChatInput components, ScanResults component
**Avoids:** Pitfall #3 (OCR blocking) — implement async polling pattern; Pitfall #4 (chat context loss) — Pinia store + sessionStorage persistence
**Backend changes:**
- Move OCR processing to queue job with batch ID + status endpoint
- Create `ChatController@ask` (or reuse existing search endpoint directly)
- Add separate rate limiters for OCR (5/min) vs fast API (120/min)
**Frontend deliverables:**
- Install `marked` for markdown rendering
- Build chat UI: ChatWindow, ChatMessage, ChatInput, ChatSources
- Build `useChatStore` with conversation history management
- Implement `app-file://` custom link handler in markdown renderer
- Build OCR scan UI: category selection, progress polling, structured results
- Build `useScanStore` with batch polling
- Build UsageMeter component + usage dashboard widget
- Build smart classification display (type badges, entity cards, financial summary)

### Phase 4: Polish & Hardening (Optional)
**Rationale:** After core features work, harden the application for production use. Address edge cases, performance, and UX polish identified during development.
**Delivers:** Production-ready error handling; performance optimization for large document lists; comprehensive loading states; security hardening.
**Addresses:** Pitfall #6 (rate limiting UX), Pitfall #7 (large file lists), Pitfall #8 (file security), Pitfall #10 (error format), Pitfall #11 (loading states)
**Potential deliverables:**
- Unified API error handler with inline validation error display
- Virtual scrolling / infinite scroll for 1000+ document lists
- `useAsyncAction` composable for consistent loading states
- File content validation (magic bytes, not just MIME type)
- Multi-tab auth sync (BroadcastChannel or storage events)
- Offline detection banner
- SSE streaming for chat (Phase 2 chat enhancement)

### Phase Ordering Rationale

- **Auth first** because every API call requires a Sanctum token. No other feature can be tested or demonstrated without working authentication. The dependency graph shows auth as the root node.
- **File management second** because it introduces the storage layer that chat and scanning depend on. Documents must exist before they can be analyzed or queried. The two-track upload architecture (local + Supermemory) is foundational infrastructure.
- **AI features third** because they are the product differentiators but depend on documents existing. Grouping chat + scanning together is efficient because both consume the same backend services (Supermemory + Gemini) and both need long-running operation UX patterns (progress indicators, polling).
- **Polish last** because it addresses quality-of-life improvements that don't block core functionality. Error handling, performance, and security hardening are important but can be layered on after the core flow works end-to-end.

### Research Flags

**Phases likely needing deeper research during planning:**
- **Phase 2 (File Upload):** The two-track upload architecture (local storage + Supermemory forwarding) is novel for this codebase. The chunked upload protocol, XMLHttpRequest progress tracking with Sanctum auth, and async Supermemory forwarding with retry all need careful implementation planning. The `StorageService` design needs to handle edge cases (partial uploads, disk full, concurrent uploads).
- **Phase 3 (AI Chat & Scanning):** The OCR async polling pattern (batch ID → status endpoint → frontend polling) requires new backend infrastructure that doesn't exist yet. The `app-file://` custom protocol handling in markdown rendering is a niche requirement. Conversation history management (client-side vs server-side persistence) needs a design decision.

**Phases with standard patterns (skip research-phase):**
- **Phase 1 (Auth):** Well-documented pattern. The Vuexy template provides the login page shell. The `useApi` composable already handles token injection. The auth store design is straightforward Pinia. The existing codebase proves the token flow works (Flutter app uses it). Standard Vue Router guards.
- **Phase 4 (Polish):** Standard performance and UX patterns. Virtual scrolling via `@vueuse/core`'s `useVirtualList`. Error handling via ofetch response interceptors. Loading states via composable wrapper. All well-documented.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Verified against installed packages in `package.json` and `composer.json`; rejected alternatives evaluated with Context7 docs; bundle size estimates based on actual dependency trees |
| Features | HIGH | Based on full codebase analysis of all controllers, services, routes, and request validators; every feature maps to an existing API endpoint; anti-features validated against PROJECT.md scope |
| Architecture | HIGH | Based on deep codebase analysis of existing patterns (auth flow, file upload flow, search flow); new components follow established conventions; storage abstraction is standard Laravel pattern |
| Pitfalls | HIGH | Each pitfall identified from actual code patterns in the codebase (e.g., synchronous OCR in `OcrController`, no local file storage, conversation history not persisted); prevention strategies tested against codebase constraints |

**Overall confidence:** HIGH

All four research areas were validated against the actual codebase (not just documentation). The existing API endpoints, service classes, frontend template, and composables were all inspected directly. The main uncertainty is in implementation details that will emerge during development (e.g., exact Supermemory API response formats, Gemini timeout behavior under load).

### Gaps to Address

- **Supermemory API response format for document listing:** The `GET /api/documents` endpoint proxies to Supermemory. The exact pagination format, field names, and error responses need validation during Phase 2 implementation. *Handle by:* Inspect actual API responses during development; build frontend to be resilient to format changes.

- **Gemini API timeout behavior:** Research indicates 10-60s for multi-page OCR, but actual timeout behavior (does it return partial results? throw?) needs validation. *Handle by:* Test with real documents of varying sizes during Phase 3; implement conservative 60s timeout with user-friendly error.

- **Chat conversation persistence scope:** Phase 1 recommends client-side only (Pinia + sessionStorage). The decision of whether to add backend `Conversation`/`Message` models in Phase 3 or defer to Phase 4 depends on user feedback. *Handle by:* Start client-side; add backend persistence if users request "chat history" feature.

- **S3 migration timing:** Research recommends local-first storage, but doesn't specify when to migrate to S3. This depends on user count and storage costs. *Handle by:* Build `StorageService` abstraction in Phase 2; migrate when storage exceeds server disk or when deploying to multiple servers.

- **File thumbnail generation:** PDF thumbnails and image resizing are mentioned but no specific library or approach is recommended. *Handle by:* Research thumbnail generation during Phase 2 planning (consider `spatie/image` for PHP or client-side canvas for images).

## Sources

### Primary (HIGH confidence)
- Full codebase analysis — `app/Http/Controllers/`, `app/Services/`, `app/Models/`, `routes/api.php`, `resources/ts/`
- Context7: Laravel 12.x filesystem docs — Storage facade, Flysystem integration, S3 configuration
- Context7: `@tato30/vue-pdf` docs — Vue 3 PDF rendering component, API, lazy loading
- Existing `useApi.ts` composable — Proven Bearer token injection from cookie pattern
- Existing `IngestionController.php` — Current file upload flow to Supermemory
- Existing `SearchController.php` — Current search + AI answer generation flow
- `config/filesystems.php` — Pre-configured local, public, and S3 disks

### Secondary (MEDIUM confidence)
- Context7: FilePond docs — Evaluated and rejected (too heavy for our needs)
- Context7: vue-advanced-chat docs — Evaluated and rejected (over-engineered for single-thread Q&A)
- Context7: deep-chat docs — Evaluated and rejected (fights Vuetify theming)
- Vuexy v9.5.0 template — Provides login page shell, layouts, form components; assumed stable

### Tertiary (LOW confidence)
- Gemini API timeout behavior under load — Estimated 10-60s based on documentation; needs real-world validation
- Supermemory API pagination format — Inferred from controller code; exact response shape needs API testing
- Bundle size estimates — Based on dependency tree analysis; actual tree-shaken sizes may vary

---
*Research completed: 2026-06-08*
*Ready for roadmap: yes*
