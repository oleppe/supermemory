# Feature Landscape

**Domain:** AI-powered document management platform (user panel)
**Researched:** 2026-06-08
**Confidence:** HIGH (based on full codebase analysis + ecosystem patterns)

## Existing Backend Capabilities (Already Implemented)

The backend already provides all core API endpoints. The frontend must consume these — not rebuild them.

| Backend Endpoint | Controller | What It Does | Frontend Needs |
|-----------------|------------|--------------|----------------|
| `POST /api/auth/register` | AuthController | Email/password registration, returns Sanctum token + subscription + usage | Registration form |
| `POST /api/auth/login` | AuthController | Email/password login, returns token + user + subscription + usage | Login form |
| `GET /api/auth/me` | AuthController | Current user profile + subscription + supermemory status | Auth state hydration |
| `PATCH /api/auth/profile` | AuthController | Update preferred_language | Settings page |
| `POST /api/auth/logout` | AuthController | Revoke current token | Logout handler |
| `POST /api/documents` | IngestionController | Upload files to Supermemory (multi-file, with optional summary) | File upload UI |
| `POST /api/memories` | IngestionController | Store text memory in Supermemory | Text note input (optional) |
| `GET /api/documents` | DocumentController | List documents (paginated, sortable) | Document list/grid view |
| `GET /api/documents/{id}` | DocumentController | Get single document detail | Document detail view |
| `POST /api/ocr/analyze` | OcrController | OCR analysis via Gemini (up to 12 pages, multi-image) | Scan/analyze button + results display |
| `POST /api/search/documents` | SearchController | Semantic search + AI-generated answer with conversation history | Chat interface |
| `POST /api/search/memories` | SearchController | Semantic search across text memories | Search UI (optional) |
| `GET /api/usage/counters` | SubscriptionController | Current usage vs limits | Usage dashboard widget |
| `GET /api/subscription` | SubscriptionController | Active subscription details | Subscription/billing page |

### Key Backend Constraints the Frontend Must Respect

| Constraint | Value | Source |
|-----------|-------|--------|
| Max files per upload | No explicit limit (loop) | IngestionController |
| Max file size | 50 MB per file | AddFilesRequest: `'max:51200'` |
| Max OCR pages per request | 12 pages | AnalyzeDocumentOcrRequest |
| Max OCR page file size | 10 MB per page | AnalyzeDocumentOcrRequest |
| Supported OCR mime types | jpg, jpeg, png, webp, pdf | GeminiService::SUPPORTED_INLINE_MIME_TYPES |
| Conversation history max | 12 messages, 4000 chars each | SearchRequest |
| Document types recognized | invoice, receipt, contract, letter, personal, business, other | GeminiService::DOCUMENT_TYPES |
| Entity keys extracted | Organization, Date, Currency, Address | GeminiService::ENTITY_KEYS |

---

## Table Stakes Features

Features users expect in any file management UI. Missing = product feels broken or incomplete.

### 1. Authentication Flow (Login / Registration / Logout)

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Login form | Email + password, remember-me checkbox, error display | Low |
| Registration form | Email + password + optional language preference | Low |
| Token persistence | Store Sanctum token in cookie (existing `useApi` reads `accessToken` cookie) | Low |
| Auth state management | Pinia store for user/subscription/usage, auto-hydrate on app load via `GET /api/auth/me` | Medium |
| Logout | Revoke token via API, clear cookie, redirect to login | Low |
| Route guards | Redirect unauthenticated users to login, redirect authenticated away from login | Low |
| Password visibility toggle | Already in Vuexy template | Low |

**Why table stakes:** The Vuexy template already has a static login page (`resources/ts/pages/login.vue`). It needs to be wired to the existing API. Without auth, nothing else works.

**Existing code to reuse:**
- `resources/ts/pages/login.vue` — full Vuexy auth layout (illustration, card, form)
- `resources/ts/composables/useApi.ts` — pre-configured fetch with Bearer token from cookie
- `resources/ts/views/pages/authentication/AuthProvider.vue` — auth provider component

### 2. File Upload Interface

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Drag-and-drop zone | Drop files onto upload area, visual feedback on hover | Low (DropZone.vue exists) |
| File browser button | Click to open native file picker | Low |
| Multi-file upload | Select/upload multiple files at once | Low |
| File type validation | Accept only images (jpg, png, webp) and PDFs — reject others with clear error | Low |
| File size validation | Max 50 MB per file (backend limit) | Low |
| Upload progress indicator | Per-file progress bar during upload | Medium |
| Pre-upload preview | Show thumbnails for images, filename + icon for PDFs before upload | Medium |
| Batch upload status | Overall progress when uploading multiple files | Medium |
| Error handling | Display per-file errors (too large, wrong type, quota exceeded) | Medium |

**Why table stakes:** File upload is the primary user action. The existing `DropZone.vue` component only handles images — must be extended for PDF support and wired to `POST /api/documents`.

**Existing code to reuse:**
- `resources/ts/@core/components/DropZone.vue` — drag-and-drop + file dialog (currently image-only)

### 3. Document List / Library View

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Paginated document list | Fetch from `GET /api/documents` with page/limit params | Low |
| Sort controls | Sort by createdAt/updatedAt, asc/desc | Low |
| Document cards | Show title/name, type badge, date, status indicator | Medium |
| Empty state | Friendly message + upload CTA when no documents exist | Low |
| Loading skeleton | Shimmer/skeleton while documents load | Low |
| Responsive grid | Cards adapt from 1-col (mobile) to 3-4 col (desktop) | Low |

**Why table stakes:** Users need to see what they've uploaded. The backend already returns normalized document data (id, title, type, status, metadata, created_at).

### 4. Document Detail View

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Metadata display | Show title, type, date, status, custom_id | Low |
| Content/summary display | Render document summary and extracted content | Low |
| File preview | Show image preview or PDF page thumbnail | Medium |
| Extracted entities | Display Organization, Date, Currency, Address if present | Low |
| Action items | Show action items list from OCR analysis | Low |
| Back navigation | Return to document list | Low |

**Why table stakes:** After uploading, users expect to view what was extracted. The `GET /api/documents/{id}` endpoint returns all this data.

### 5. Usage / Quota Awareness

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Usage display | Show files used/remaining, AI questions used/remaining | Low |
| Quota exceeded warning | Clear message when limits reached, suggest upgrade | Low |
| Plan info | Show current plan name and billing period | Low |

**Why table stakes:** The backend enforces limits via `UsageLimitService`. Users will hit 429/402 errors if the UI doesn't show remaining quota. The `GET /api/auth/me` response already includes usage snapshots.

---

## Differentiators

Features that set the product apart. Not expected in generic file managers, but highly valued for an AI document platform.

### 1. AI Chat Interface (Document Q&A)

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Chat input | Text input with send button, Enter to send, Shift+Enter for newline | Medium |
| Message history | Display conversation as chat bubbles (user + assistant) | Medium |
| Streaming-like UX | Show typing indicator while waiting for AI response (backend is non-streaming) | Medium |
| Conversation context | Maintain and send `conversationHistory` array (max 12 messages) with each query | Medium |
| Source citations | Display `file_references` from response as clickable links | Medium |
| Markdown rendering | AI responses are markdown-formatted — render with proper formatting | Medium |
| `app-file://` link handling | Custom protocol links to local files — intercept and navigate to file viewer | High |
| Error/empty responses | Handle "no context found" gracefully with helpful suggestions | Low |
| Chat reset | Clear conversation history button | Low |
| Suggested questions | Pre-populated question starters for empty chat state | Low |

**Why differentiator:** This is the core value proposition. The backend already does semantic search → context extraction → Gemini answer generation with conversation history. The frontend chat UI makes this accessible.

**Backend flow:** `POST /api/search/documents` → Supermemory semantic search → extract context chunks → `GeminiService.generateAnswer()` → return answer + file_references + metadata.

### 2. Document Scanning Workflow (OCR + Classification)

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Scan trigger | "Analyze" button on document detail or upload completion | Low |
| Category selection | Let user pick allowed_categories before scanning (multi-select chips) | Medium |
| Multi-page support | Handle up to 12 page images per scan request | Medium |
| Scan progress | Indeterminate progress indicator during OCR (can take 10-30s) | Medium |
| Results display | Structured display of: document_type, category, summary, entities, amount, items | Medium |
| Action items checklist | Interactive checklist from `action_items` array | Low |
| Re-scan option | Allow re-scanning with different categories | Low |
| Extracted text viewer | Collapsible full-text view of OCR output | Low |

**Why differentiator:** The GeminiService does sophisticated document analysis — type classification, entity extraction, amount/tax/tip parsing, action item generation. The UI should surface this richness, not just dump raw text.

**Backend flow:** `POST /api/ocr/analyze` with pages[] + allowed_categories → `GeminiService.analyzeDocumentImages()` → structured JSON with document_type, category, extracted_text, summary, action_items, entities, amount, date, merchant, items, tax_amount, tip_amount.

### 3. Smart Document Classification Display

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Type badges | Color-coded badges for document types (invoice=green, receipt=blue, contract=purple, etc.) | Low |
| Entity cards | Structured cards showing extracted Organization, Date, Currency, Address | Low |
| Financial summary | Prominent display of amount, tax, tip for receipts/invoices | Low |
| Line items table | Render `items` array as a table for invoices/receipts | Medium |
| Category filter | Filter document list by AI-assigned category | Medium |

**Why differentiator:** Most document managers show file lists. This product understands document *content* — the UI should reflect that intelligence.

### 4. Semantic Search with AI Summaries

| Aspect | Detail | Complexity |
|--------|--------|------------|
| Natural language search | Search bar that accepts questions, not just keywords | Medium |
| AI-generated answer | Display the Gemini-generated answer above search results | Medium |
| Result relevance scores | Show confidence/similarity scores on results | Low |
| Result source linking | Link results back to source documents | Medium |
| Search within conversation | Maintain search context across queries in a session | Medium |

**Why differentiator:** The backend already supports hybrid semantic search with reranking and threshold filtering. This is not keyword search — it's question-answering over documents.

---

## Anti-Features

Features to explicitly NOT build in this milestone.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Folder/tag organization | PROJECT.md explicitly defers to future milestone; adds DB schema complexity | Use Supermemory `containerTags` (already per-user) for flat organization |
| Real-time streaming chat | Backend uses synchronous Gemini API (non-streaming); adding SSE/WebSocket is major scope | Use typing indicators + loading states to simulate responsiveness |
| File versioning | No backend support; Supermemory documents are immutable after upload | Show upload date, allow re-upload as new document |
| Collaborative sharing | Single-user per container tag; no sharing model exists | Keep documents private per user |
| Rich text editor for notes | Tiptap is in dependencies but no text note UI is needed for MVP | Defer to future milestone if user feedback demands it |
| Admin panel in user SPA | Admin routes exist separately (`/admin/{any?}`) | Keep admin as separate route group, don't mix into user panel nav |
| OAuth/social login | Backend only supports email/password; no OAuth providers configured | Show email/password only; AuthProvider.vue component exists but has no providers |
| Client-side OCR | All OCR goes through Gemini API server-side | Don't attempt browser-based OCR (Tesseract.js etc.) |
| Offline mode | Requires service worker + IndexedDB + sync queue — massive scope | Require online connection; show offline banner if disconnected |

---

## Feature Dependencies

```
Auth Flow → Everything (all API calls require Sanctum token)
Auth Flow → Usage Display (usage data comes from auth payload)
File Upload → Document List (uploaded docs appear in list)
File Upload → Document Detail (navigate to detail after upload)
File Upload → Document Scanning (scan uploaded files)
Document List → Document Detail (click card to view detail)
Document Detail → Document Scanning (scan from detail view)
Document Detail → AI Chat (reference document in chat context)
Document Scanning → Smart Classification Display (show scan results)
AI Chat → Semantic Search (chat uses search endpoint internally)
Usage Display → File Upload (show remaining quota before/after upload)
```

### Dependency Graph

```
┌─────────────┐
│  Auth Flow  │
└──────┬──────┘
       │
       ├──────────────────────┬──────────────────┐
       ▼                      ▼                  ▼
┌─────────────┐    ┌──────────────────┐  ┌──────────────┐
│ Usage Display│    │  File Upload     │  │ AI Chat      │
└─────────────┘    └───────┬──────────┘  └──────┬───────┘
                           │                     │
                    ┌──────┴──────┐              │
                    ▼             ▼              │
            ┌────────────┐ ┌──────────────┐     │
            │ Doc List   │ │ Doc Scanning │     │
            └─────┬──────┘ └──────┬───────┘     │
                  │               │              │
                  ▼               ▼              │
            ┌────────────┐ ┌──────────────────┐  │
            │ Doc Detail │ │  Classification  │  │
            └─────┬──────┘ │  Display         │  │
                  │         └──────────────────┘  │
                  └───────────────┬───────────────┘
                                  ▼
                          ┌──────────────┐
                          │ Semantic     │
                          │ Search       │
                          └──────────────┘
```

---

## UX Patterns for Key Workflows

### Upload → Scan → Chat Flow (Primary User Journey)

```
1. User lands on dashboard → sees empty state or recent documents
2. User drags files onto upload zone → previews appear → "Upload" button
3. Upload progresses → per-file status (uploading → processing → done)
4. On completion → auto-navigate to document detail OR show success toast
5. Document detail shows extracted metadata → "Ask a question" CTA
6. Chat opens with document context → user asks question → AI answers
```

### Progress Indicators for Long-Running Operations

| Operation | Typical Duration | UX Pattern |
|-----------|-----------------|------------|
| File upload (single) | 1-10s | Linear progress bar with percentage |
| File upload (batch) | 5-60s | Overall progress + per-file status list |
| Supermemory processing | 5-30s | Indeterminate spinner + "Processing document..." text |
| OCR analysis | 10-30s | Indeterminate spinner + "Analyzing document with AI..." + page count |
| AI chat response | 3-10s | Typing indicator (three dots) in chat bubble |
| Semantic search | 2-5s | Skeleton loader in results area |

### Error States to Handle

| Error | Source | User-Facing Message | Recovery |
|-------|--------|-------------------|----------|
| 401 Unauthorized | Token expired/revoked | "Session expired. Please log in again." | Redirect to login |
| 413 File too large | File > 50 MB | "File exceeds 50 MB limit." | Remove file from upload queue |
| 422 Validation error | Wrong file type, etc. | Show field-level errors | Fix and retry |
| 429 Rate limited | Throttle middleware | "Too many requests. Please wait a moment." | Auto-retry with backoff |
| 402 Usage limit exceeded | UsageLimitService | "You've reached your [plan] limit for [metric]." | Show upgrade CTA |
| 502 Gemini API error | GeminiService failure | "AI analysis temporarily unavailable. Please try again." | Retry button |
| 502 Supermemory error | SupermemoryService failure | "Document storage temporarily unavailable." | Retry button |
| Network error | Offline/server down | "Connection lost. Check your internet connection." | Retry on reconnect |

---

## MVP Recommendation

### Phase 1: Auth + Foundation (build first, everything depends on it)
1. **Login/Registration UI** — Wire existing Vuexy login template to API
2. **Auth state management** — Pinia store, token cookie, route guards
3. **App shell** — Navigation sidebar, user menu, layout with auth checks

### Phase 2: File Management (core user action)
4. **File upload interface** — Extend DropZone for PDFs, wire to `POST /api/documents`
5. **Document list view** — Paginated grid with sort, empty state, loading states
6. **Document detail view** — Metadata, content preview, entity display

### Phase 3: AI Features (core value proposition)
7. **Document scanning UI** — Category selection, progress, structured results
8. **AI chat interface** — Chat bubbles, conversation history, markdown rendering
9. **Usage dashboard** — Quota display, plan info, upgrade prompts

### Defer to Future Milestone
- **Folder/tag organization** — Requires new DB schema, not supported by current backend
- **Semantic search standalone page** — Chat subsumes search for MVP
- **Billing/subscription management** — Stripe portal exists but not critical for core flow
- **Document text editor / notes** — Tiptap available but no clear user need yet
- **Notification system** — No backend support; defer until async processing exists

---

## Component Inventory (What to Build)

### Pinia Stores (State Management)
| Store | Purpose | Key State |
|-------|---------|-----------|
| `useAuthStore` | User session, token, profile | user, token, subscription, usage, isAuthenticated |
| `useDocumentStore` | Document list and detail | documents[], currentDocument, pagination, loading |
| `useChatStore` | Chat conversation state | messages[], conversationHistory, isWaiting, fileReferences |
| `useUploadStore` | Upload queue and progress | queue[], activeUploads, overallProgress |

### Vue Pages (File-Based Routes)
| Route | Page | Layout |
|-------|------|--------|
| `/login` | Login page (exists, needs wiring) | blank |
| `/register` | Registration page | blank |
| `/` | Dashboard (recent docs + usage + quick upload) | default |
| `/documents` | Document list/library | default |
| `/documents/:id` | Document detail + scan results | default |
| `/chat` | AI chat interface | default |
| `/settings` | User profile + preferences | default |

### Reusable Components
| Component | Purpose | Based On |
|-----------|---------|----------|
| `DocumentUploadZone` | Extended DropZone for images + PDFs | Existing `DropZone.vue` |
| `DocumentCard` | Card display for document list items | New (Vuetify VCard) |
| `DocumentTypeBadge` | Color-coded type indicator | New |
| `EntityDisplay` | Structured entity key-value cards | New |
| `ChatMessage` | Single chat bubble (user or assistant) | New |
| `ChatInput` | Text input with send button | New |
| `UsageMeter` | Circular or linear usage indicator | New |
| `ScanResultsPanel` | Structured OCR results display | New |
| `ProgressOverlay` | Full-area progress for long operations | New |
| `EmptyState` | Friendly empty state with CTA | New |

---

## Sources

- Full codebase analysis: `app/Http/Controllers/`, `app/Services/`, `app/Models/`, `routes/api.php`
- Frontend template: `resources/ts/` (Vuexy v9.5.0 with Vue 3.5 + Vuetify 3.10)
- Backend constraints: `app/Http/Requests/` validation rules
- Data model: `database/migrations/` (users, supermemory_ingestions, usage_counters, plans, subscriptions)
- Project scope: `.planning/PROJECT.md` (validated requirements, active tasks, out-of-scope items)
