# Architecture: User Panel Integration

**Project:** Supermemory Document Manager — v1.9 User Panel
**Researched:** 2026-06-08
**Confidence:** HIGH (based on full codebase analysis)

---

## Current System Overview

### Backend (Existing — Laravel 12)

```
┌─────────────────────────────────────────────────────────────┐
│                     routes/api.php                          │
│  Prefix: /api  │  Auth: auth:sanctum (Bearer token)        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  AuthController ────── register / login / me / logout       │
│  IngestionController ─ storeMemory / storeDocuments         │
│  DocumentController ─── index / show                        │
│  OcrController ──────── analyze                             │
│  SearchController ───── searchMemories / searchDocuments    │
│  SubscriptionController (billing, plans, usage)             │
│                                                             │
├─────────────────────── Services ────────────────────────────┤
│                                                             │
│  SupermemoryService ──→ Supermemory API (document store)    │
│  GeminiService ───────→ Gemini API (OCR + AI Q&A)          │
│  UsageLimitService ───→ Enforces plan quotas                │
│  FirestoreSyncService → Mirrors ingestion status            │
│  SubscriptionService ─→ Stripe billing + plan resolution    │
│                                                             │
├─────────────────────── Models ──────────────────────────────┤
│  User │ SupermemoryIngestion │ Plan │ Subscription │ Usage  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Key existing patterns:**
- **Auth:** Sanctum personal access tokens (Bearer), created with `$user->createToken('flutter')`. Token returned in login/register response.
- **Container tags:** Each user gets `user-{id}` as their Supermemory container tag. All document operations are scoped to this tag.
- **File flow:** Files uploaded via multipart → forwarded to Supermemory API → SupermemoryIngestion record stored locally → synced to Firestore.
- **Usage enforcement:** `ensureFileUploadsAllowed()` / `ensureAiQuestionAllowed()` called before operations; `consume*()` called after.
- **Controller base:** `Controller.php` provides `authenticatedUser($request)` and `supermemoryContainerTag($user)` helpers.

### Frontend (Existing — Vue 3 + Vuetify 3 SPA)

```
resources/ts/
├── main.ts                    ← App entry, registers plugins
├── App.vue                    ← Root: VApp + RouterView
├── pages/                     ← File-based routing (unplugin-vue-router)
│   ├── index.vue              ← Dashboard (empty template)
│   ├── login.vue              ← Login page (UI only, no logic)
│   ├── second-page.vue        ← Demo page
│   └── [...error].vue         ← 404 handler
├── composables/
│   └── useApi.ts              ← createFetch wrapper with Bearer token injection
├── plugins/
│   ├── 1.router/index.ts      ← Vue Router (base: /admin/)
│   ├── 2.pinia.ts             ← Pinia store
│   └── vuetify/               ← Vuetify 3 config
├── @core/
│   ├── components/            ← DropZone, AppTextField, etc.
│   ├── composable/            ← useCookie, useSkins, etc.
│   └── stores/config.ts       ← App config store (theme, layout)
├── layouts/
│   ├── default.vue            ← Main layout with navigation
│   ├── blank.vue              ← Auth pages layout (no nav)
│   └── components/            ← UserProfile, NavbarShortcuts, etc.
└── navigation/vertical/index.ts ← Nav items (Home, Second page)
```

**Key existing patterns:**
- **Routing:** File-based via `unplugin-vue-router`. Pages in `resources/ts/pages/` auto-generate routes. Layouts assigned via `definePage({ meta: { layout: 'blank' } })`.
- **API calls:** `useApi` composable wraps `@vueuse/core`'s `createFetch`. Reads token from `useCookie('accessToken')`, injects as `Authorization: Bearer {token}`.
- **State:** Pinia installed but only `config` store exists. No auth store, no domain stores.
- **Auto-imports:** Vue, Vue Router, @vueuse/core, @vueuse/math, vue-i18n, Pinia — all auto-imported. Composables in `resources/ts/composables/` also auto-imported.
- **Router base:** `/admin/` — the SPA is served from `routes/web.php` catch-all `Route::get('/admin/{any?}')`.

---

## Recommended Architecture

### High-Level Integration Map

```
┌──────────────────────────────────────────────────────────────────────┐
│                        Vue 3 SPA (/admin/)                          │
│                                                                      │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────┐  ┌───────────┐  │
│  │  Auth Pages  │  │ File Manager │  │ Doc Scanner│  │ AI Chat   │  │
│  │  (blank)     │  │ (default)    │  │ (default)  │  │ (default) │  │
│  └──────┬───────┘  └──────┬───────┘  └─────┬──────┘  └─────┬─────┘  │
│         │                  │                 │               │        │
│  ┌──────┴──────────────────┴─────────────────┴───────────────┴─────┐ │
│  │                    Pinia Stores                                 │ │
│  │  useAuthStore │ useFileStore │ useChatStore │ useScanStore     │ │
│  └──────────────────────────┬──────────────────────────────────────┘ │
│                             │                                        │
│  ┌──────────────────────────┴──────────────────────────────────────┐ │
│  │              API Layer (composables)                            │ │
│  │  useAuth() │ useFiles() │ useDocuments() │ useChat() │ useOcr()│ │
│  └──────────────────────────┬──────────────────────────────────────┘ │
│                             │                                        │
└─────────────────────────────┼────────────────────────────────────────┘
                              │ HTTP (Bearer token)
                              ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    Laravel API (/api/)                                │
│                                                                      │
│  ┌──────────┐  ┌───────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │   Auth    │  │   Files   │  │  Search  │  │  Documents/OCR   │   │
│  │Controller │  │ Controller│  │Controller│  │  Controllers     │   │
│  └─────┬─────┘  └─────┬─────┘  └────┬─────┘  └────────┬─────────┘   │
│        │                │              │                 │            │
│  ┌─────┴────────────────┴──────────────┴─────────────────┴─────────┐ │
│  │                    Existing Services                            │ │
│  │  SupermemoryService │ GeminiService │ UsageLimitService         │ │
│  └─────────────────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌─────────────────────── NEW ──────────────────────────────────────┐│
│  │  StorageService (local → S3 abstraction)                         ││
│  │  FileController (local file CRUD, chunked uploads)               ││
│  │  ChatController (streaming AI Q&A endpoint)                      ││
│  └──────────────────────────────────────────────────────────────────┘│
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 1. Frontend Page & Component Structure

### File-Based Routing (New Pages)

```
resources/ts/pages/
├── index.vue                      ← Dashboard (redirect → /documents)
├── login.vue                      ← Login (layout: blank) — ADD LOGIC
├── register.vue                   ← NEW — Registration (layout: blank)
├── documents/
│   ├── index.vue                  ← NEW — Document list / file manager
│   ├── [id].vue                   ← NEW — Document detail / viewer
│   └── upload.vue                 ← NEW — Upload page / wizard
├── chat/
│   └── index.vue                  ← NEW — AI chat interface
├── scan/
│   ├── index.vue                  ← NEW — OCR scan entry point
│   └── [id].vue                   ← NEW — Scan results viewer
├── account/
│   ├── index.vue                  ← NEW — Profile / settings
│   └── subscription.vue           ← NEW — Subscription management
└── [...error].vue                 ← Existing 404
```

### Component Hierarchy

```
resources/ts/components/
├── auth/
│   ├── LoginForm.vue              ← Email/password form with validation
│   ├── RegisterForm.vue           ← Registration form
│   └── AuthLayout.vue             ← Shared auth page wrapper
├── documents/
│   ├── DocumentList.vue           ← Paginated document grid/list
│   ├── DocumentCard.vue           ← Single document preview card
│   ├── DocumentDetail.vue         ← Full document view with metadata
│   ├── DocumentViewer.vue         ← PDF/image viewer component
│   └── DocumentFilters.vue        ← Sort/filter/search bar
├── files/
│   ├── FileUploader.vue           ← Drag-and-drop + file picker (extends DropZone)
│   ├── FileProgressList.vue       ← Upload progress indicators
│   └── FilePreview.vue            ← Thumbnail preview for images/PDFs
├── chat/
│   ├── ChatWindow.vue             ← Main chat container
│   ├── ChatMessage.vue            ← Single message bubble (user/assistant)
│   ├── ChatInput.vue              ← Message input with send button
│   └── ChatSources.vue            ← Source document references panel
├── scan/
│   ├── ScanUploader.vue           ← File upload specifically for OCR
│   ├── ScanResults.vue            ← OCR results display
│   └── ScanEntityTable.vue        ← Extracted entities table
└── shared/
    ├── UsageMeter.vue             ← Usage limit progress bars
    ├── EmptyState.vue             ← Empty list placeholder
    └── ConfirmDialog.vue          ← Reusable confirmation dialog
```

### Layout Strategy

| Route Group | Layout | Rationale |
|-------------|--------|-----------|
| `/login`, `/register` | `blank` | No navigation chrome; focused auth experience |
| `/documents/*`, `/chat/*`, `/scan/*`, `/account/*` | `default` | Full navigation sidebar, user profile dropdown |

### Navigation Updates

```typescript
// resources/ts/navigation/vertical/index.ts
export default [
  {
    title: 'Documents',
    to: { name: 'documents' },
    icon: { icon: 'tabler-file-description' },
  },
  {
    title: 'Upload',
    to: { name: 'documents-upload' },
    icon: { icon: 'tabler-upload' },
  },
  {
    title: 'AI Chat',
    to: { name: 'chat' },
    icon: { icon: 'tabler-message-circle' },
  },
  {
    title: 'Scan Document',
    to: { name: 'scan' },
    icon: { icon: 'tabler-camera' },
  },
  {
    title: 'Account',
    children: [
      { title: 'Profile', to: { name: 'account' } },
      { title: 'Subscription', to: { name: 'account-subscription' } },
    ],
    icon: { icon: 'tabler-settings' },
  },
]
```

---

## 2. Auth Flow Integration

### Current Auth Mechanism (from codebase)

The existing system uses **Sanctum personal access tokens** (not SPA cookie auth):

1. `POST /api/auth/register` → returns `{ token, user, subscription, usage }`
2. `POST /api/auth/login` → returns `{ token, user, subscription, usage }`
3. All subsequent requests: `Authorization: Bearer {token}`
4. `POST /api/auth/logout` → deletes the token
5. `GET /api/auth/me` → returns current user + subscription + usage

The frontend's `useApi` composable already reads from `useCookie('accessToken')` and injects the Bearer header.

### Auth Store Design

```typescript
// resources/ts/stores/auth.ts
import { defineStore } from 'pinia'

interface AuthUser {
  id: number
  email: string
  name: string
  preferred_language: string | null
  is_admin: boolean
}

interface AuthState {
  user: AuthUser | null
  token: string | null
  subscription: Record<string, any> | null
  usage: Record<string, any> | null
  isLoading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: null,
    subscription: null,
    usage: null,
    isLoading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    containerTag: (state) => state.user ? `user-${state.user.id}` : null,
  },

  actions: {
    async login(email: string, password: string) { /* POST /api/auth/login */ },
    async register(email: string, password: string) { /* POST /api/auth/register */ },
    async logout() { /* POST /api/auth/logout, clear state */ },
    async fetchMe() { /* GET /api/auth/me, update state */ },
    async initAuth() {
      // On app boot: read token from cookie, if present → fetchMe()
      // If fetchMe fails → clear token, redirect to /login
    },
  },
})
```

### Token Lifecycle

```
┌──────────┐    login/register     ┌──────────────┐
│  /login  │ ──────────────────→   │  API returns │
│  /register│                      │  { token }   │
└──────────┘                       └──────┬───────┘
                                          │
                                          ▼
                                 ┌────────────────┐
                                 │ useCookie      │
                                 │ ('accessToken') │
                                 │ .value = token │
                                 └────────┬───────┘
                                          │
                    ┌─────────────────────┼─────────────────────┐
                    │                     │                     │
                    ▼                     ▼                     ▼
           ┌──────────────┐    ┌──────────────────┐   ┌──────────────┐
           │ useApi       │    │ Router guard     │   │ App boot     │
           │ beforeFetch   │    │ checks token     │   │ initAuth()   │
           │ injects Bearer│    │ exists           │   │ calls /me    │
           └──────────────┘    └──────────────────┘   └──────────────┘
```

### Router Navigation Guard

```typescript
// resources/ts/plugins/1.router/index.ts — ADD guard
router.beforeEach((to) => {
  const authStore = useAuthStore()

  // Public pages (login, register, error)
  if (to.meta.public)
    return true

  // Protected pages require auth
  if (!authStore.isAuthenticated)
    return { name: 'login', query: { redirect: to.fullPath } }

  return true
})
```

### Key Decision: Token in Cookie vs localStorage

**Use cookie** (current pattern). The `useCookie` composable from `@core/composable/useCookie` is already wired into `useApi`. Cookies persist across page refreshes naturally. The token is a Sanctum personal access token (not a session cookie), so CSRF is not a concern — the `Authorization: Bearer` header provides the auth mechanism.

**Do NOT switch to Sanctum SPA cookie auth.** The existing Flutter mobile app depends on the token-based flow. Changing to cookie-based SPA auth would require maintaining two auth strategies. Keep the Bearer token approach for both mobile and web.

---

## 3. File Upload Architecture

### Current Upload Flow (from IngestionController)

```
Client ──multipart POST /api/documents──→ IngestionController
  │                                         │
  │                                         ├──→ UsageLimitService.ensureFileUploadsAllowed()
  │                                         ├──→ SupermemoryService.uploadFile() → Supermemory API
  │                                         ├──→ SupermemoryIngestion::updateOrCreate()
  │                                         ├──→ FirestoreSyncService.upsertIngestion()
  │                                         └──→ UsageLimitService.consumeFileUploads()
```

**Current limitation:** Files are forwarded directly to Supermemory. There is no local file storage. The web panel needs local storage for file previews, downloads, and S3 migration.

### New Upload Architecture (Two-Track)

```
┌─────────────────────────────────────────────────────────────────┐
│                     File Upload Flow                            │
│                                                                  │
│  Client                                                         │
│    │                                                            │
│    ├─── Track A: Local Storage (NEW) ──────────────────────┐   │
│    │    POST /api/files/upload                              │   │
│    │      → FileController@store                            │   │
│    │      → StorageService::store() → local disk (or S3)    │   │
│    │      → StoredFile model created                        │   │
│    │      → Returns { file_id, url, thumbnail_url }         │   │
│    │                                                        │   │
│    └─── Track B: Supermemory Ingestion (EXISTING) ─────────┤   │
│         POST /api/documents                                 │   │
│           → IngestionController@storeDocuments              │   │
│           → SupermemoryService.uploadFile()                 │   │
│           → Returns { supermemory_id, status }              │   │
│                                                             │   │
│    Combined flow (upload + scan):                           │   │
│         POST /api/files/upload-and-scan                     │   │
│           → Stores locally (Track A)                        │   │
│           → Sends to Supermemory (Track B)                  │   │
│           → Triggers OCR if image/PDF                       │   │
│           → Returns { file, ingestion, scan_status }        │   │
└─────────────────────────────────────────────────────────────────┘
```

### Chunked Upload Strategy

For large files (especially PDFs up to 50MB), implement chunked uploads:

```
┌──────────────────────────────────────────────────────────────┐
│                   Chunked Upload Protocol                     │
│                                                               │
│  1. POST /api/files/initiate                                 │
│     { filename, size, mime_type }                            │
│     → Returns { upload_id, chunk_size, total_chunks }        │
│                                                               │
│  2. POST /api/files/chunks/{upload_id}  (× N)               │
│     multipart: chunk_index, chunk_data                       │
│     → Returns { received: true, chunk_index }                │
│                                                               │
│  3. POST /api/files/complete/{upload_id}                     │
│     → Assembles chunks → stores via StorageService           │
│     → Returns { file_id, url, stored_path }                  │
│                                                               │
│  For files < 5MB: skip chunking, use direct upload           │
│  For files ≥ 5MB: use chunked protocol (5MB chunks)          │
└──────────────────────────────────────────────────────────────┘
```

### Frontend Upload Composable

```typescript
// resources/ts/composables/useFileUpload.ts
export function useFileUpload() {
  const uploads = ref<Map<string, UploadProgress>>(new Map())

  async function uploadFile(file: File, options?: UploadOptions) {
    const CHUNK_THRESHOLD = 5 * 1024 * 1024  // 5MB

    if (file.size < CHUNK_THRESHOLD) {
      return directUpload(file, options)
    }
    return chunkedUpload(file, options)
  }

  function directUpload(file: File, options?: UploadOptions) {
    // Use XMLHttpRequest for progress tracking (fetch doesn't support upload progress)
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest()
      const formData = new FormData()
      formData.append('files[]', file)
      if (options?.customId) formData.append('custom_id', options.customId)

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          updateProgress(file.name, e.loaded / e.total)
        }
      })

      xhr.addEventListener('load', () => resolve(JSON.parse(xhr.response)))
      xhr.addEventListener('error', () => reject(new Error('Upload failed')))

      xhr.open('POST', '/api/files/upload')
      xhr.setRequestHeader('Authorization', `Bearer ${useCookie('accessToken').value}`)
      xhr.send(formData)
    })
  }

  async function chunkedUpload(file: File, options?: UploadOptions) {
    // 1. Initiate
    const { upload_id, chunk_size, total_chunks } = await initiateUpload(file)

    // 2. Upload chunks with progress
    for (let i = 0; i < total_chunks; i++) {
      const start = i * chunk_size
      const chunk = file.slice(start, start + chunk_size)
      await uploadChunk(upload_id, i, chunk)
      updateProgress(file.name, (i + 1) / total_chunks)
    }

    // 3. Complete
    return completeUpload(upload_id)
  }

  return { uploads, uploadFile }
}
```

**Why XMLHttpRequest for progress:** The Fetch API does not support upload progress events. For accurate progress tracking (essential for UX on large files), XMLHttpRequest is required. This is a well-established pattern — Vue's own `vue-upload-component` and libraries like `tus-js-client` use the same approach.

---

## 4. Storage Abstraction Layer

### Current State

The filesystem config (`config/filesystems.php`) already has `local`, `public`, and `s3` disks configured. The `FILESYSTEM_DISK` env var controls the default. However, no application code currently uses Laravel's Storage facade for user files — everything goes directly to Supermemory.

### StorageService Design

```php
// app/Services/StorageService.php
class StorageService
{
    private const USER_FILES_DISK = 'user_files';  // configurable disk

    public function store(UploadedFile $file, int $userId): StoredFile
    {
        $path = $this->buildPath($userId, $file);
        $disk = $this->disk();

        $disk->put($path, file_get_contents($file->getRealPath()));

        return StoredFile::create([
            'user_id'      => $userId,
            'original_name'=> $file->getClientOriginalName(),
            'stored_path'  => $path,
            'mime_type'    => $file->getMimeType(),
            'size'         => $file->getSize(),
            'disk'         => $this->currentDiskName(),
        ]);
    }

    public function url(StoredFile $file): string
    {
        if ($this->currentDiskName() === 's3') {
            return $this->disk()->temporaryUrl($file->stored_path, now()->addMinutes(30));
        }

        // Local: serve through a controller (not public symlink)
        return route('files.serve', ['file' => $file->id]);
    }

    public function stream(StoredFile $file): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->disk()->get($file->stored_path),
            $file->original_name,
            ['Content-Type' => $file->mime_type]
        );
    }

    public function migrateToS3(): void
    {
        // Batch migration: read from local, write to S3, update disk column
        // Run via artisan command: php artisan files:migrate-to-s3
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.user_files_disk', 'local'));
    }

    private function buildPath(int $userId, UploadedFile $file): string
    {
        $date = now()->format('Y/m/d');
        $hash = Str::random(40);
        $ext = $file->getClientOriginalExtension();
        return "users/{$userId}/{$date}/{$hash}.{$ext}";
    }
}
```

### New Database Model

```php
// app/Models/StoredFile.php
// Migration: create_stored_files_table
class StoredFile extends Model
{
    protected $fillable = [
        'user_id', 'original_name', 'stored_path',
        'mime_type', 'size', 'disk', 'thumbnail_path',
    ];

    public function user(): BelongsTo { ... }
}
```

### Local → S3 Migration Path

```
Phase 1 (Now): FILESYSTEM_DISK=local
  └─ Files stored in storage/app/private/users/{id}/{date}/{hash}.ext
  └─ Served via authenticated controller route (not public symlink)
  └─ StoredFile.disk = 'local'

Phase 2 (When needed): FILESYSTEM_DISK=s3
  └─ New files go to S3
  └─ StoredFile.disk = 's3' for new files
  └─ Old files still on local, served via local disk

Phase 3 (Background migration):
  └─ php artisan files:migrate-to-s3 --batch=100
  └─ Reads local files, uploads to S3, updates StoredFile.disk + stored_path
  └─ Idempotent: skips already-migrated files
```

**Why not just start with S3:** Local storage is simpler for development, testing, and initial deployment. No AWS credentials needed. The `StorageService` abstraction means switching is a config change for new files + a batch command for existing files.

---

## 5. Real-Time Communication for AI Chat

### Analysis of Options

| Approach | Complexity | Latency | Browser Support | Fit for This App |
|----------|-----------|---------|-----------------|------------------|
| **SSE (Server-Sent Events)** | Low | Low | All modern browsers | **✓ Best fit** |
| WebSocket (Laravel Reverb) | High | Lowest | All modern browsers | Overkill for Q&A |
| Long Polling | Medium | Medium | Universal | Unnecessary complexity |
| Simple POST (current) | Lowest | High (waits for full response) | Universal | ✓ Acceptable for v1 |

### Recommendation: SSE for Streaming, POST for Non-Streaming

**Use SSE (Server-Sent Events)** for the AI chat streaming response. Here's why:

1. **Gemini API returns complete answers** — The current `GeminiService::generateAnswer()` is synchronous and returns the full answer. However, streaming is the expected UX for AI chat (users see text appear incrementally).

2. **SSE is simpler than WebSocket** — No persistent connection management, no Laravel Reverb/Soketi infrastructure, no channel auth. The browser's native `EventSource` API handles reconnection automatically.

3. **One-directional data flow** — Chat is fundamentally request-response (user sends question → server streams answer). SSE is designed for exactly this pattern. WebSocket's bidirectional capability is wasted.

4. **Works with existing auth** — SSE connections can include the Bearer token via query params or initial headers (via `fetch` + `ReadableStream` instead of `EventSource` if header auth is needed).

### Chat Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                         Chat Flow                                │
│                                                                   │
│  User types question                                              │
│       │                                                           │
│       ▼                                                           │
│  POST /api/chat/ask                                               │
│  { query, conversationHistory, documentIds? }                     │
│       │                                                           │
│       ▼                                                           │
│  ChatController@ask                                               │
│       │                                                           │
│       ├──→ UsageLimitService.ensureAiQuestionAllowed()            │
│       ├──→ SupermemoryService.searchDocuments(query, containerTag)│
│       ├──→ GeminiService.generateAnswer(query, context, history)  │
│       │                                                           │
│       ▼                                                           │
│  Response: { answer, meta: { sources, file_references } }        │
│                                                                   │
│  Phase 2 (streaming):                                             │
│  GET /api/chat/stream?query=...&history=...                       │
│  → Returns text/event-stream                                      │
│  → Events: token, sources, done, error                            │
└──────────────────────────────────────────────────────────────────┘
```

### Phase 1: Non-Streaming (Start Here)

Use the existing `POST /api/search/documents` endpoint. It already:
1. Searches Supermemory for relevant document chunks
2. Passes context to GeminiService for answer generation
3. Returns the full answer + file references + metadata

The frontend chat interface calls this endpoint and displays the response. No new backend endpoint needed for v1.

### Phase 2: Streaming (Enhancement)

```php
// app/Http/Controllers/ChatController.php
public function stream(Request $request): StreamedResponse
{
    return response()->stream(function () use ($request) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        // ... search + generate ...

        // Stream answer tokens as SSE events
        foreach ($tokens as $token) {
            echo "data: " . json_encode(['type' => 'token', 'content' => $token]) . "\n\n";
            ob_flush();
            flush();
        }

        echo "data: " . json_encode(['type' => 'done', 'sources' => $sources]) . "\n\n";
    });
}
```

**Note:** Gemini's `streamGenerateContent` endpoint would be needed for true token-by-token streaming. The current `GeminiService` uses `generateContent` (non-streaming). Adding streaming requires a new method in GeminiService that uses `streamGenerateContent` and yields chunks.

---

## 6. State Management Patterns

### Pinia Store Architecture

```
resources/ts/stores/
├── auth.ts          ← User, token, subscription, usage
├── documents.ts     ← Document list, filters, pagination
├── files.ts         ← Upload queue, progress tracking
├── chat.ts          ← Conversation history, current response
└── scan.ts          ← OCR scan state, results
```

### Store Designs

#### Auth Store (see Section 2 above)

#### Documents Store

```typescript
// resources/ts/stores/documents.ts
export const useDocumentsStore = defineStore('documents', {
  state: () => ({
    documents: [] as Document[],
    pagination: { page: 1, limit: 20, total: 0 },
    filters: { sort: 'createdAt', order: 'desc', search: '' },
    isLoading: false,
    currentDocument: null as Document | null,
  }),

  actions: {
    async fetchDocuments(page = 1) {
      // GET /api/documents?page={page}&limit={limit}&sort={sort}&order={order}
      // Uses existing DocumentController@index
    },
    async fetchDocument(id: string) {
      // GET /api/documents/{id}
      // Uses existing DocumentController@show
    },
  },
})
```

#### Files Upload Store

```typescript
// resources/ts/stores/files.ts
interface UploadItem {
  id: string
  file: File
  progress: number      // 0-100
  status: 'pending' | 'uploading' | 'processing' | 'done' | 'error'
  result?: any
  error?: string
}

export const useFilesStore = defineStore('files', {
  state: () => ({
    uploads: [] as UploadItem[],
    isUploading: computed(() => this.uploads.some(u => u.status === 'uploading')),
  }),

  actions: {
    addFiles(files: FileList) { /* Queue files for upload */ },
    async startUpload(id: string) { /* Begin upload with progress tracking */ },
    async startAll() { /* Process queue sequentially */ },
    removeUpload(id: string) { /* Remove from queue */ },
  },
})
```

#### Chat Store

```typescript
// resources/ts/stores/chat.ts
interface ChatMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
  sources?: FileReference[]
  timestamp: Date
}

export const useChatStore = defineStore('chat', {
  state: () => ({
    messages: [] as ChatMessage[],
    isWaiting: false,
    conversationHistory: [] as { role: string; content: string }[],
  }),

  actions: {
    async askQuestion(query: string) {
      // POST /api/search/documents
      // Uses existing SearchController@searchDocuments
      // Adds user message + assistant response to messages[]
      // Maintains conversationHistory for context (max 6 messages)
    },
    clearConversation() { /* Reset messages and history */ },
  },
})
```

### State Management Principles

1. **Server state in stores, UI state in components.** Document lists, auth state, and chat history live in Pinia. Form inputs, dialog visibility, and hover states stay in components.

2. **Optimistic updates for uploads.** When a file is queued, add it to the store immediately with `status: 'pending'`. Update progress as the upload proceeds. Remove only on explicit user action or after confirmed success.

3. **No WebSocket subscriptions.** All data is fetched on demand. Document lists refresh on navigation. Chat sends a POST per question. This keeps the architecture simple and avoids infrastructure complexity.

4. **Conversation history is client-side only.** The `conversationHistory` array is maintained in the chat store and sent with each request. The backend does not persist chat sessions (it's stateless). This matches the existing `SearchRequest` validation which accepts `conversationHistory` as an optional array.

---

## 7. API Endpoint Design for New Features

### New Endpoints Needed

```
┌──────────────────────────────────────────────────────────────────────┐
│                    New API Endpoints                                  │
│                                                                       │
│  FILES (local storage)                                                │
│  POST   /api/files/upload              ← Direct upload (small files) │
│  POST   /api/files/initiate            ← Start chunked upload        │
│  POST   /api/files/chunks/{id}         ← Upload chunk                │
│  POST   /api/files/complete/{id}       ← Finalize chunked upload     │
│  GET    /api/files                      ← List user's stored files    │
│  GET    /api/files/{file}               ← Get file metadata           │
│  GET    /api/files/{file}/download      ← Download / serve file       │
│  DELETE /api/files/{file}               ← Delete stored file          │
│                                                                       │
│  COMBINED OPERATIONS                                                  │
│  POST   /api/files/upload-and-scan     ← Upload + OCR + Supermemory  │
│                                                                       │
│  CHAT                                                                   │
│  POST   /api/chat/ask                  ← Ask question (Phase 1)      │
│  GET    /api/chat/stream               ← Stream answer (Phase 2)     │
│  GET    /api/chat/history              ← Get past conversations      │
│                                                                       │
│  SCAN                                                                   │
│  POST   /api/scan/analyze              ← OCR analyze (wraps existing)│
│  GET    /api/scan/{ingestion}/status    ← Check scan processing status│
│                                                                       │
│  EXISTING (reuse as-is)                                               │
│  POST   /api/auth/register             ← Registration                 │
│  POST   /api/auth/login                ← Login                        │
│  GET    /api/auth/me                    ← Current user                │
│  POST   /api/auth/logout               ← Logout                      │
│  GET    /api/documents                  ← List Supermemory docs       │
│  GET    /api/documents/{id}             ← Get Supermemory doc         │
│  POST   /api/search/documents          ← Search + AI answer          │
│  POST   /api/search/memories           ← Search memories             │
│  GET    /api/subscription               ← Subscription info           │
│  GET    /api/usage                      ← Usage overview              │
│  GET    /api/usage/counters             ← Detailed usage counters     │
└──────────────────────────────────────────────────────────────────────┘
```

### Endpoint Design Patterns (from existing codebase)

Follow the patterns established by existing controllers:

1. **Response envelope:** Use `{ data: ..., meta: { ... } }` for list endpoints, `{ resource: ... }` for single resources.

2. **Form Requests:** Validate with dedicated Form Request classes (e.g., `AddFilesRequest`, `SearchRequest`). Create `UploadFileRequest`, `ChatAskRequest`, etc.

3. **Container tag scoping:** Use `$this->supermemoryContainerTag($user)` from the base Controller for all Supermemory operations.

4. **Usage enforcement:** Call `UsageLimitService::ensure*()` before operations, `consume*()` after.

5. **Throttle groups:** Add new throttle groups in `RateLimiter`:
   - `files` — for file upload endpoints (generous: 30/min)
   - `chat` — for chat endpoints (restricted by usage limits: 10/min)

### New Controller Structure

```php
// app/Http/Controllers/FileController.php
class FileController extends Controller
{
    public function __construct(
        private readonly StorageService $storageService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    public function upload(Request $request): JsonResponse { ... }
    public function index(Request $request): JsonResponse { ... }
    public function show(Request $request, StoredFile $file): JsonResponse { ... }
    public function download(Request $request, StoredFile $file): StreamedResponse { ... }
    public function destroy(Request $request, StoredFile $file): JsonResponse { ... }
}

// app/Http/Controllers/ChatController.php
class ChatController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly SupermemoryService $supermemoryService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    public function ask(Request $request): JsonResponse { ... }
}
```

---

## 8. Data Flow Diagrams

### Complete Upload + Scan Flow

```
┌──────────┐         ┌──────────────┐         ┌──────────────────┐
│  Browser  │         │   Laravel     │         │  External APIs   │
│  (Vue)    │         │   (API)       │         │                  │
└─────┬─────┘         └──────┬───────┘         └────────┬─────────┘
      │                       │                          │
      │  POST /api/files/     │                          │
      │  upload-and-scan      │                          │
      │  { file }             │                          │
      │──────────────────────→│                          │
      │                       │                          │
      │                       │  1. Validate file        │
      │                       │  2. Check usage limits   │
      │                       │                          │
      │                       │  3. Store locally        │
      │                       │  ───────────────────────→│
      │                       │  StorageService::store() │
      │                       │                          │
      │                       │  4. Upload to Supermemory│
      │                       │  ───────────────────────→│
      │                       │  SupermemoryService      │
      │                       │  ←───────────────────────│
      │                       │  { id, status }          │
      │                       │                          │
      │                       │  5. OCR if image/PDF     │
      │                       │  ───────────────────────→│
      │                       │  GeminiService           │
      │                       │  ←───────────────────────│
      │                       │  { extracted_text, ... } │
      │                       │                          │
      │                       │  6. Create records       │
      │                       │  - StoredFile            │
      │                       │  - SupermemoryIngestion  │
      │                       │                          │
      │  { file, ingestion,   │                          │
      │    scan_results }     │                          │
      │←──────────────────────│                          │
      │                       │                          │
```

### AI Chat Flow

```
┌──────────┐         ┌──────────────┐         ┌──────────────────┐
│  Browser  │         │   Laravel     │         │  External APIs   │
│  (Vue)    │         │   (API)       │         │                  │
└─────┬─────┘         └──────┬───────┘         └────────┬─────────┘
      │                       │                          │
      │  POST /api/chat/ask   │                          │
      │  { query, history }   │                          │
      │──────────────────────→│                          │
      │                       │                          │
      │                       │  1. Check usage limits   │
      │                       │                          │
      │                       │  2. Search documents     │
      │                       │  ───────────────────────→│
      │                       │  SupermemoryService      │
      │                       │  .searchDocuments()      │
      │                       │  ←───────────────────────│
      │                       │  { results, chunks }     │
      │                       │                          │
      │                       │  3. Extract context      │
      │                       │  (existing logic from    │
      │                       │   SearchController)      │
      │                       │                          │
      │                       │  4. Generate answer      │
      │                       │  ───────────────────────→│
      │                       │  GeminiService           │
      │                       │  .generateAnswer()       │
      │                       │  ←───────────────────────│
      │                       │  { answer text }         │
      │                       │                          │
      │                       │  5. Consume usage        │
      │                       │                          │
      │  { answer, meta:      │                          │
      │    { sources, refs }} │                          │
      │←──────────────────────│                          │
      │                       │                          │
```

---

## 9. Anti-Patterns to Avoid

### ❌ Don't: Create a Separate Auth System for Web

**Bad:** Adding Sanctum SPA cookie auth alongside the existing token auth.
**Why:** Two auth strategies = double the maintenance, double the edge cases. The Flutter app uses tokens. Keep tokens for web too.
**Instead:** Use the existing Bearer token flow. Store token in cookie via `useCookie('accessToken')`.

### ❌ Don't: Store Files Only in Supermemory

**Bad:** Relying solely on Supermemory for file storage and retrieval.
**Why:** Supermemory is a document intelligence API, not a file store. It doesn't provide direct file download URLs, thumbnail generation, or storage guarantees. If Supermemory goes down, users lose access to their files.
**Instead:** Store files locally (or S3) via `StorageService`. Use Supermemory for document intelligence (search, OCR, Q&A). The `StoredFile` model is the source of truth for file existence; `SupermemoryIngestion` tracks the intelligence layer.

### ❌ Don't: Build WebSocket Infrastructure for Chat

**Bad:** Adding Laravel Reverb, Soketi, or Pusher for real-time chat.
**Why:** The chat is request-response. Users ask questions, get answers. There's no multi-user chat, no presence, no live updates from other users. WebSocket adds significant infrastructure complexity for zero benefit.
**Instead:** Use POST for Phase 1, SSE for Phase 2 streaming. Both are HTTP-native, require no additional infrastructure, and work behind any reverse proxy.

### ❌ Don't: Put Business Logic in Controllers

**Bad:** Writing Supermemory calls, Gemini calls, and storage operations directly in controller methods.
**Why:** The existing codebase already separates services (GeminiService, SupermemoryService). Controllers should orchestrate, not implement.
**Instead:** Create `StorageService`, `ChatService` (if logic grows), and keep controllers thin — validate input, call services, format response.

### ❌ Don't: Duplicate Existing Endpoints

**Bad:** Creating new `/api/panel/documents` endpoints that wrap the same Supermemory calls as `/api/documents`.
**Why:** The existing `DocumentController` and `SearchController` already do what the panel needs. Duplicating them creates maintenance burden and inconsistency.
**Instead:** Reuse existing endpoints from the frontend. Only create new endpoints for genuinely new functionality (local file storage, chat convenience wrapper).

### ❌ Don't: Use Vuex (Deprecated)

**Bad:** Adding Vuex for state management because older Vuexy templates used it.
**Why:** Pinia is already installed and configured (`plugins/2.pinia.ts`). Vuex is in maintenance mode. Pinia is the official Vue 3 state management solution.
**Instead:** Use Pinia stores as designed in Section 6.

---

## 10. Scalability Considerations

| Concern | At 100 users | At 10K users | At 1M users |
|---------|--------------|--------------|-------------|
| File storage | Local disk fine | Migrate to S3 | S3 + CloudFront CDN |
| File serving | Controller-served | S3 signed URLs | CloudFront + signed URLs |
| Chat | POST (non-streaming) | SSE streaming | SSE + horizontal scaling (Redis broadcaster) |
| Document list | Direct Supermemory proxy | Add local cache (Redis) | Local DB as primary, Supermemory as search backend |
| Upload | Direct multipart | Chunked uploads | Presigned S3 URLs (client → S3 direct) |
| Auth tokens | Sanctum personal tokens | Same (tokens are cheap) | Consider JWT for stateless verification |

---

## Sources

- Codebase analysis: `app/Http/Controllers/`, `app/Services/`, `resources/ts/`, `config/`, `routes/`
- Existing auth pattern: `AuthController.php` lines 22-72 (token creation and response)
- Existing API composable: `resources/ts/composables/useApi.ts` (Bearer token injection)
- Existing file upload: `IngestionController.php` lines 66-190 (multipart → Supermemory)
- Existing search+answer: `SearchController.php` lines 60-109 (Supermemory search → Gemini answer)
- Filesystem config: `config/filesystems.php` (local, public, s3 disks pre-configured)
- Router config: `resources/ts/plugins/1.router/index.ts` (base: `/admin/`, file-based routing)
- Frontend template: Vuexy v9.5.0 with Vue 3.5.22, Vuetify 3.10.8, Pinia 3.0.3
