# External Integrations — User Panel

**Project:** Supermemory Document Manager — v1.9 User Panel
**Researched:** 2026-06-08
**Confidence:** HIGH (verified against existing backend services + Context7 docs)

---

## Integration Map

```
┌─────────────────────────────────────────────────────────────────────┐
│                        USER PANEL (Vue 3 SPA)                       │
│                                                                       │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │   Auth   │  │   Files  │  │  Preview │  │      Chat        │   │
│  │  Login/  │  │  Upload/ │  │  PDF/    │  │  Document Q&A    │   │
│  │  Register│  │  Manage  │  │  Image   │  │                  │   │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └───────┬──────────┘   │
│       │               │              │                │               │
└───────┼───────────────┼──────────────┼────────────────┼──────────────┘
        │               │              │                │
        ▼               ▼              ▼                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     LARAVEL 12 API (Sanctum)                         │
│                                                                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │ AuthController│  │  Ingestion   │  │    SearchController      │  │
│  │ /api/auth/*  │  │  Controller  │  │  /api/search/documents   │  │
│  │              │  │ /api/documents│  │  /api/search/memories    │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────────┘  │
│         │                  │                      │                   │
│         │          ┌───────┴───────┐              │                   │
│         │          │               │              │                   │
│         ▼          ▼               ▼              ▼                   │
│  ┌──────────┐ ┌──────────┐ ┌──────────────┐ ┌──────────────────┐   │
│  │ Sanctum  │ │  Local   │ │ Supermemory  │ │  GeminiService   │   │
│  │  Auth    │ │ Storage  │ │   Service    │ │  (OCR + AI Q&A)  │   │
│  │          │ │   + S3   │ │              │ │                  │   │
│  └──────────┘ └──────────┘ └──────┬───────┘ └───────┬──────────┘   │
│                                    │                  │               │
└────────────────────────────────────┼──────────────────┼──────────────┘
                                     │                  │
                                     ▼                  ▼
                          ┌──────────────────┐  ┌──────────────────┐
                          │  SuperMemory API  │  │  Gemini API      │
                          │  api.supermemory  │  │  generativelang  │
                          │     .ai           │  │  uage.googleapi  │
                          │                  │  │  s.com           │
                          └──────────────────┘  └──────────────────┘
                                     │
                                     ▼
                          ┌──────────────────┐
                          │  Firestore       │
                          │  (sync mirror)   │
                          └──────────────────┘
```

---

## 1. Authentication — Laravel Sanctum (EXISTING)

### Current State

| Aspect | Detail |
|--------|--------|
| **Service** | Laravel Sanctum 4.3 |
| **Endpoints** | `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/logout`, `PATCH /api/auth/profile` |
| **Token delivery** | Bearer token in response body (created for Flutter mobile app) |
| **Token storage (frontend)** | `useCookie('accessToken')` — already wired in `useApi.ts` |
| **Auth middleware** | `auth:sanctum` on all protected routes |
| **User model** | `App\Models\User` with `HasApiTokens`, fields: `id`, `name`, `email`, `preferred_language`, `is_admin` |

### What the Frontend Needs to Build

**Auth Store** (`resources/ts/stores/auth.ts`):
```typescript
// Pinia store managing:
// - user: User | null
// - token: string | null (persisted to cookie)
// - subscription: Subscription info
// - usage: { files: {...}, ai_questions: {...} }
// - login(email, password) → POST /api/auth/login
// - register(email, password) → POST /api/auth/register
// - logout() → POST /api/auth/logout
// - fetchUser() → GET /api/auth/me
// - isAuthenticated: computed
// - isAdmin: computed
```

**Login Page** — Extend existing `resources/ts/pages/login.vue`:
- Currently a static Vuexy template with no API integration
- Wire form submission to `authStore.login()`
- Add error handling (401 → show "Invalid credentials")
- Add loading state on submit button
- Redirect to dashboard on success

**Registration Page** — Create `resources/ts/pages/register.vue`:
- Email + password + confirm password fields
- Wire to `authStore.register()`
- Redirect to dashboard on success

**Auth Guard** — Router middleware:
- Check `authStore.isAuthenticated` before accessing protected routes
- Redirect to `/login` if not authenticated
- The existing `useApi.ts` already reads token from cookie — just ensure login sets the cookie

### Token Flow

```
Login/Register → API returns { token: "..." }
    → Frontend stores token in cookie (useCookie('accessToken'))
    → useApi.ts reads cookie and adds Authorization header
    → All subsequent API calls are authenticated
    → Logout → DELETE token via API → clear cookie
```

### No Changes Needed to Backend

The existing auth endpoints return everything the frontend needs:
- `user` object with id, email, name, preferred_language, is_admin
- `subscription` object with plan details
- `usage` object with file and AI question limits/remaining

---

## 2. File Storage — Local + S3 Migration

### Current State

| Aspect | Detail |
|--------|--------|
| **Default disk** | `local` → `storage/app/private` (not web-accessible) |
| **S3 disk** | Configured in `config/filesystems.php` but `league/flysystem-aws-s3-v3` NOT installed |
| **File uploads** | Currently sent directly to SuperMemory API (not stored locally) |
| **File serving** | No local file serving — files live in SuperMemory only |

### What Needs to Change

The current architecture sends uploaded files directly to SuperMemory for processing. The user panel needs **local file storage** so users can browse, preview, and manage their files without relying solely on SuperMemory's API.

**New flow:**
```
User uploads file
    → Laravel stores file locally (storage/app/private/users/{user_id}/{uuid}.{ext})
    → Laravel sends file to SuperMemory for processing (existing flow)
    → Laravel creates DB record linking local file path ↔ SuperMemory document ID
    → User can preview file from local storage
    → User can view SuperMemory metadata (summary, extracted text, entities)
```

### New Database Migration Needed

```php
// database/migrations/create_user_files_table.php
Schema::create('user_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('original_name');
    $table->string('mime_type');
    $table->unsignedBigInteger('size'); // bytes
    $table->string('storage_path');     // relative path on disk
    $table->string('storage_disk')->default('local'); // 'local' or 's3'
    $table->string('supermemory_id')->nullable(); // link to SuperMemory document
    $table->string('supermemory_status')->default('queued');
    $table->json('metadata')->nullable(); // extracted text, summary, entities
    $table->timestamps();
    $table->index(['user_id', 'created_at']);
});
```

### Local Storage Implementation

**New Controller** (`App\Http\Controllers\FileController`):
```php
// POST /api/files/upload — store file locally + send to SuperMemory
// GET  /api/files — list user's files (paginated)
// GET  /api/files/{id} — get file metadata
// GET  /api/files/{id}/download — serve file (signed/temporary URL)
// DELETE /api/files/{id} — soft delete
```

**File serving strategy:**
- Local disk: Use Laravel's `Storage::download()` or temporary signed URLs
- S3 disk: Use `Storage::temporaryUrl()` with 5-minute expiry
- Both: Serve through a controller route (not direct URL) to enforce auth

**Key pattern from existing codebase:**
```php
// From config/filesystems.php — temporaryUrl works for both local and S3
$url = Storage::temporaryUrl($path, now()->plusMinutes(5));
```

### S3 Migration Path

**Phase 1 (Local only):**
- Store files in `storage/app/private/users/{user_id}/`
- Serve through authenticated controller route
- S3 disk config exists but is not used

**Phase 2 (S3 migration):**
1. Install `league/flysystem-aws-s3-v3`
2. Set `FILESYSTEM_DISK=s3` in `.env`
3. Configure `AWS_*` environment variables
4. Files uploaded after switch go to S3 automatically
5. Optional: Write artisan command to migrate existing local files to S3

**Why this works:** Laravel's Flysystem abstraction means the application code doesn't change when switching disks. `Storage::put()`, `Storage::get()`, `Storage::temporaryUrl()` work identically on local and S3.

### S3-Compatible Alternatives

The existing S3 config supports any S3-compatible service by setting `AWS_ENDPOINT`:

| Service | Cost | Notes |
|---------|------|-------|
| AWS S3 | ~$0.023/GB/month | Default, most mature |
| Cloudflare R2 | $0.015/GB/month, no egress fees | Best for read-heavy workloads |
| DigitalOcean Spaces | $5/month for 250GB | Simple pricing |
| MinIO (self-hosted) | Free | For development/testing |

**Recommendation:** Start local, migrate to Cloudflare R2 if egress costs matter, AWS S3 otherwise.

---

## 3. SuperMemory — Document Scanning (EXISTING)

### Current State

| Aspect | Detail |
|--------|--------|
| **Service** | `App\Services\SupermemoryService` |
| **API Base** | `https://api.supermemory.ai` (configurable via `SUPERMEMORY_BASE_URL`) |
| **Auth** | API key via `SUPERMEMORY_API_KEY` |
| **Endpoints used** | `/v3/documents/file` (upload), `/v3/documents/list` (list), `/v3/documents/{id}` (get), `/v4/search` (search), `/v4/memories` (add memory) |
| **Container tags** | Per-user isolation via `supermemoryContainerTag($user)` |
| **Ingestion tracking** | `SupermemoryIngestion` model tracks upload status |
| **Firestore sync** | `FirestoreSyncService` mirrors ingestion records to Firestore |

### API Endpoints Available to Frontend

| Frontend Action | Backend Endpoint | What Happens |
|----------------|-----------------|--------------|
| Upload file for scanning | `POST /api/documents` | File → SuperMemory → returns document ID + status |
| Upload text/memory | `POST /api/memories` | Text → SuperMemory memory → returns memory ID |
| List documents | `GET /api/documents` | SuperMemory list → normalized response |
| Get document details | `GET /api/documents/{id}` | SuperMemory get → normalized response with content, summary, metadata |
| Search documents | `POST /api/search/documents` | SuperMemory search → Gemini AI answer |
| Search memories | `POST /api/search/memories` | SuperMemory search → memory results |

### Document Processing Pipeline

```
File uploaded to POST /api/documents
    │
    ├──→ SupermemoryService::uploadFile()
    │        POST /v3/documents/file (multipart)
    │        Returns: { id, status: "queued" }
    │
    ├──→ SupermemoryIngestion record created (status: "queued")
    │
    ├──→ FirestoreSyncService::upsertIngestion() (async mirror)
    │
    └──→ SuperMemory processes asynchronously:
             1. OCR text extraction
             2. Document classification
             3. Summarization
             4. Entity extraction
             5. Chunking for semantic search
             Status transitions: queued → processing → processed (or failed)
```

### Status Polling Strategy (Phase 1)

After upload, the frontend needs to track processing status:

```typescript
// In useFileUpload composable or documents store:
const pollDocumentStatus = async (documentId: string) => {
  const maxAttempts = 20  // 20 × 3s = 60s max
  let attempts = 0

  const poll = async () => {
    const { data } = await useApi(`/documents/${documentId}`).get().json()
    if (data.value?.document?.status === 'processed') {
      return data.value.document
    }
    if (++attempts < maxAttempts) {
      await new Promise(r => setTimeout(r, 3000))
      return poll()
    }
    throw new Error('Document processing timed out')
  }
  return poll()
}
```

### Usage Limits

The existing `UsageLimitService` enforces per-plan limits:
- **File uploads:** `monthly_file_limit` + `daily_file_limit`
- **AI questions:** `monthly_question_limit` + `daily_question_limit`
- **PDF pages:** `max_pdf_pages` per document

The frontend should display usage info from the `/api/auth/me` response:
```json
{
  "usage": {
    "files": { "limit": 100, "used": 23, "remaining": 77 },
    "ai_questions": { "limit": 500, "used": 142, "remaining": 358 }
  }
}
```

### What the Frontend Needs to Build

**Document List Page** (`resources/ts/pages/documents/index.vue`):
- Paginated table/grid of user's documents
- Filter by type (image, PDF), status (queued, processed, failed)
- Sort by date, name
- Search by name

**Document Detail Page** (`resources/ts/pages/documents/[id].vue`):
- File preview (PDF viewer or image display)
- Extracted text display
- Summary display
- Entities display (Organization, Date, Currency, Address)
- Action items list
- "Ask a question" button → opens chat

**Upload Flow** (`resources/ts/pages/documents/upload.vue` or modal):
- Drag-and-drop zone (extend existing DropZone.vue)
- File type validation (jpg, jpeg, png, webp, pdf)
- File size validation
- Upload progress bars
- Post-upload: show processing status with polling

---

## 4. Gemini AI — OCR + Document Q&A (EXISTING)

### Current State

| Aspect | Detail |
|--------|--------|
| **Service** | `App\Services\GeminiService` |
| **API Base** | `https://generativelanguage.googleapis.com/v1beta` |
| **Model** | `gemini-2.5-flash` (configurable via `GEMINI_MODEL`) |
| **Auth** | API key via `GEMINI_API_KEY` (header: `x-goog-api-key`) |
| **OCR endpoint** | `POST /api/ocr/analyze` — accepts page images, returns structured JSON |
| **Q&A endpoint** | `POST /api/search/documents` — searches SuperMemory, generates answer with context |

### OCR Analysis (`POST /api/ocr/analyze`)

**Request:**
```
POST /api/ocr/analyze
Content-Type: multipart/form-data
Authorization: Bearer {token}

pages[]: (file) image or PDF page
pages[]: (file) image or PDF page
allowed_categories[]: invoice
allowed_categories[]: receipt
```

**Response:**
```json
{
  "data": {
    "document_type": "invoice",
    "category": "invoice",
    "extracted_text": "Full OCR text...",
    "summary": "Invoice from Acme Corp for $1,234.00 dated 2026-06-01",
    "action_items": ["Review invoice details", "Schedule payment"],
    "entities": {
      "Organization": "Acme Corp",
      "Date": "2026-06-01",
      "Currency": "USD",
      "Address": "123 Main St"
    },
    "amount": 1234.00,
    "date": "2026-06-01",
    "merchant": "Acme Corp",
    "payment_method": "N/A",
    "items": ["Widget A", "Widget B"],
    "tax_amount": "123.40",
    "tip_amount": null
  },
  "meta": {
    "page_count": 2,
    "model": "gemini-2.5-flash"
  }
}
```

**Supported file types for OCR:** jpg, jpeg, png, webp, pdf (inline base64 to Gemini API)

### Document Q&A (`POST /api/search/documents`)

**Request:**
```json
{
  "query": "What is the total amount on the invoice?",
  "limit": 10,
  "conversationHistory": [
    { "role": "user", "content": "What documents do I have?" },
    { "role": "assistant", "content": "You have 3 invoices and 2 receipts." }
  ]
}
```

**Response:**
```json
{
  "answer": "The total amount on the Acme Corp invoice is **$1,234.00**, dated June 1, 2026. [View document](app-file://invoice_acme.pdf)",
  "meta": {
    "model": "gemini-2.5-flash",
    "context_items": 4,
    "file_references": [
      { "original_name": "invoice_acme.pdf", "link": "app-file://invoice_acme.pdf" }
    ],
    "no_context": false
  }
}
```

### Chat Interface Requirements

**Conversation history management:**
- Frontend maintains `conversationHistory` array
- Each message: `{ role: 'user' | 'assistant', content: string }`
- Backend truncates to last 6 messages (see `GeminiService::formatConversationHistory`)
- Frontend should display full history but only send last 6 for efficiency

**Markdown rendering:**
- Gemini returns markdown-formatted answers
- Includes `app-file://` protocol links → must be intercepted and converted to navigation
- Code blocks, bold, lists, tables all need rendering
- Use `marked` library with custom renderer for `app-file://` links

**Streaming (Phase 2):**
- Current implementation is request/response (non-streaming)
- Gemini API supports streaming via `streamGenerateContent`
- Would require backend changes (new endpoint or SSE)
- Frontend: append tokens to message bubble as they arrive

### What the Frontend Needs to Build

**Chat Window** (accessible from document detail page or standalone):
- Message list with user/assistant bubbles
- Input field with send button
- Markdown rendering for assistant messages
- `app-file://` link handling → navigate to document viewer
- Loading indicator while waiting for response
- Error handling (usage limit exceeded, API error)
- Conversation history in Pinia store (survives navigation)

**Usage limit display:**
- Show remaining AI questions in chat header
- When limit reached: show upgrade prompt (link to subscription page)

---

## 5. Firestore — Secondary State Sync (EXISTING, Background)

### Current State

| Aspect | Detail |
|--------|--------|
| **Service** | `App\Services\FirestoreSyncService` |
| **Client** | `google/cloud-firestore` PHP SDK |
| **Auth** | Service account JSON via `FIREBASE_SERVICE_ACCOUNT_PATH` |
| **Collection** | `user_ingestions/{user_id}/items/{ingestion_id}` |
| **Purpose** | Mirror ingestion records for Flutter mobile app consumption |

### Relevance to User Panel

**The web frontend does NOT need to interact with Firestore directly.**

Firestore is a secondary sync target for the Flutter mobile app. The web frontend uses the Laravel API exclusively. The `FirestoreSyncService` runs server-side after each ingestion.

**No frontend work needed** for this integration.

---

## 6. Stripe — Subscription & Billing (EXISTING)

### Current State

| Aspect | Detail |
|--------|--------|
| **Service** | `App\Services\StripeBillingService` + `SubscriptionController` |
| **Endpoints** | `GET /api/subscription`, `POST /api/subscription/payment-sheet`, `POST /api/subscription/portal-session`, `POST /api/subscription/cancel`, `POST /api/subscription/resume` |
| **Plans** | `GET /api/plans` — returns available subscription tiers |
| **Usage** | `GET /api/usage`, `GET /api/usage/counters` |

### Relevance to User Panel

The user panel needs a **Subscription/Settings page** where users can:
- View current plan and usage
- Upgrade/downgrade plan (Stripe Checkout)
- Manage billing (Stripe Customer Portal)
- View usage counters (files uploaded, AI questions asked)

### What the Frontend Needs to Build

**Settings/Subscription Page** (`resources/ts/pages/settings/subscription.vue`):
- Current plan display
- Usage meters (files: X/Y remaining, questions: X/Y remaining)
- "Upgrade" button → `POST /api/subscription/payment-sheet` → Stripe Checkout
- "Manage Billing" button → `POST /api/subscription/portal-session` → Stripe Portal redirect
- Cancel/Resume subscription buttons

**Stripe integration on frontend:**
- For Checkout: redirect to Stripe-hosted page (no Stripe.js needed for redirect mode)
- For Portal: redirect to Stripe-hosted portal
- No need for `@stripe/stripe-js` package unless implementing embedded payment elements

---

## Integration Dependency Matrix

| Feature | Auth | File Storage | SuperMemory | Gemini | Stripe | Firestore |
|---------|------|-------------|-------------|--------|--------|-----------|
| Login/Register | ✅ | — | — | — | — | — |
| File Upload | ✅ | ✅ | ✅ | — | — | — |
| File Preview | ✅ | ✅ | — | — | — | — |
| Document List | ✅ | ✅ | ✅ | — | — | — |
| OCR Analysis | ✅ | ✅ | — | ✅ | — | — |
| AI Chat | ✅ | — | ✅ | ✅ | — | — |
| Subscription | ✅ | — | — | — | ✅ | — |
| Usage Limits | ✅ | — | — | — | ✅ | — |

---

## Environment Variables Required

### Already Configured (`.env.example`)

```ini
# Auth
SANCTUM_STATEFUL_DOMAINS=     # Add frontend domain

# SuperMemory
SUPERMEMORY_BASE_URL=https://api.supermemory.ai
SUPERMEMORY_API_KEY=

# Gemini
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash

# Storage
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=            # For S3 migration
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# Stripe
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=

# Frontend
VITE_API_BASE_URL=            # API base URL for frontend
```

### New Variables Needed

```ini
# Frontend
VITE_APP_NAME=                # Display name in UI
VITE_MAX_UPLOAD_SIZE=         # Client-side file size limit (e.g., "10485760" for 10MB)
VITE_ALLOWED_FILE_TYPES=      # Comma-separated: "image/jpeg,image/png,image/webp,application/pdf"
```

---

## API Endpoint Summary for Frontend

### Auth (existing)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/auth/register` | Create account |
| POST | `/api/auth/login` | Get auth token |
| GET | `/api/auth/me` | Get user + subscription + usage |
| PATCH | `/api/auth/profile` | Update preferences |
| POST | `/api/auth/logout` | Revoke token |

### Files (new — needs backend work)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/files/upload` | Upload file (store locally + send to SuperMemory) |
| GET | `/api/files` | List user's files (paginated) |
| GET | `/api/files/{id}` | Get file metadata |
| GET | `/api/files/{id}/download` | Download/preview file |
| DELETE | `/api/files/{id}` | Delete file |

### Documents/SuperMemory (existing)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/documents` | Upload to SuperMemory (existing, may be replaced by /api/files) |
| GET | `/api/documents` | List SuperMemory documents |
| GET | `/api/documents/{id}` | Get document with extracted content |

### Search/Chat (existing)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/search/documents` | AI Q&A — search docs + generate answer |
| POST | `/api/search/memories` | Search memories |

### OCR (existing)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/ocr/analyze` | Analyze document images with Gemini |

### Subscription (existing)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/plans` | List available plans |
| GET | `/api/subscription` | Current subscription details |
| GET | `/api/usage` | Usage overview |
| POST | `/api/subscription/payment-sheet` | Create Stripe Checkout session |
| POST | `/api/subscription/portal-session` | Create Stripe Portal session |
| POST | `/api/subscription/cancel` | Cancel subscription |
| POST | `/api/subscription/resume` | Resume subscription |

---

## Sources

- Existing codebase: AuthController, IngestionController, SearchController, OcrController (HIGH)
- Existing codebase: SupermemoryService, GeminiService, FirestoreSyncService (HIGH)
- Existing codebase: config/filesystems.php, .env.example (HIGH)
- Context7: Laravel 12.x filesystem docs — S3, temporaryUrl (HIGH)
- Context7: Laravel 12.x broadcasting docs — Reverb, Echo (HIGH)
- Context7: vue-advanced-chat — evaluated for chat UI (MEDIUM)
- Context7: deep-chat — evaluated for AI chat UI (MEDIUM)
