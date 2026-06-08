# Technology Stack — User Panel Frontend

**Project:** Supermemory Document Manager — v1.9 User Panel
**Researched:** 2026-06-08
**Confidence:** HIGH (verified against existing codebase + Context7 docs)

---

## Existing Stack (Already in Codebase)

These are already installed and configured — do NOT re-add.

### Frontend Core

| Technology | Version | Purpose | Status |
|------------|---------|---------|--------|
| Vue | 3.5.22 | SPA framework | ✅ Installed |
| Vuetify | 3.10.8 | Material Design component library | ✅ Installed |
| Pinia | 3.0.3 | State management | ✅ Installed |
| Vue Router | 4.5.1 | File-based routing (unplugin-vue-router) | ✅ Installed |
| Vite | 7.1.12 | Build tool + HMR | ✅ Installed |
| TypeScript | 5.9.3 | Type safety | ✅ Installed |
| vue-i18n | 11.1.12 | Internationalization | ✅ Installed |
| @vueuse/core | 10.11.1 | Composable utilities (includes `useDropZone`, `useFileDialog`) | ✅ Installed |
| ofetch | 1.5.0 | HTTP client (via `useApi` composable) | ✅ Installed |
| @tiptap/* | 2.27.x | Rich text editor (for future note-taking) | ✅ Installed |
| @casl/ability + @casl/vue | 6.7.3 / 2.2.2 | Permission management | ✅ Installed |
| @formkit/drag-and-drop | 0.1.6 | Drag-and-drop lists (NOT file uploads) | ✅ Installed |

### Backend Core

| Technology | Version | Purpose | Status |
|------------|---------|---------|--------|
| Laravel | 12.x | API backend | ✅ Installed |
| Laravel Sanctum | 4.3 | Token-based auth | ✅ Installed |
| smalot/pdfparser | 2.12 | PDF page counting | ✅ Installed |
| stripe/stripe-php | 20.0 | Subscription billing | ✅ Installed |
| google/cloud-firestore | 1.52 | Secondary state sync | ✅ Installed |
| google/cloud-translate | 2.3 | Translation service | ✅ Installed |

### Existing Composables & Components to Reuse

| Asset | Location | What It Provides |
|-------|----------|-----------------|
| `useApi` | `resources/ts/composables/useApi.ts` | Authenticated fetch with Sanctum token from cookie |
| `DropZone.vue` | `resources/ts/@core/components/DropZone.vue` | Basic image drop zone (VueUse-based, images only) |
| `TiptapEditor.vue` | `resources/ts/@core/components/TiptapEditor.vue` | Rich text editor wrapper |
| `AppTextField` | `resources/ts/@core/components/app-form-elements/` | Styled form inputs |
| `AppStepper` | `resources/ts/@core/components/AppStepper.vue` | Multi-step form wizard |
| Vertical + Horizontal layouts | `resources/ts/layouts/` | Full app shell with nav |
| Cookie-based auth | `useApi.ts` line 14 | `useCookie('accessToken')` pattern |

---

## New Libraries to Add

### 1. File Upload — vue-file-agent (REJECT) → Custom VueUse + Vuetify (RECOMMENDED)

**Decision: Build custom upload component using existing VueUse composables.**

**Rationale:**
- `@vueuse/core` already provides `useDropZone`, `useFileDialog`, `useObjectUrl` — all we need
- The existing `DropZone.vue` proves this pattern works (it only handles images; we extend for PDFs)
- vue-file-agent, FilePond, vue-dropzone all add 50-200KB bundle weight for features we don't need
- We need tight integration with Sanctum auth headers — custom is simpler than configuring third-party uploaders
- Upload progress tracking: `XMLHttpRequest.upload.onprogress` or `fetch` with `ReadableStream`
- The existing `useApi` composable uses `ofetch` which doesn't support upload progress natively — use raw `fetch` or `XMLHttpRequest` for uploads

**What to build:**
```
resources/ts/components/file-upload/
├── FileDropZone.vue         # Extended from existing DropZone.vue — accept PDF + images
├── FileUploadList.vue       # Shows queued files with progress bars
├── FileUploadItem.vue       # Single file card: thumbnail, name, size, progress, status
└── composables/
    └── useFileUpload.ts     # Handles chunked upload, progress, retry, auth headers
```

**Key implementation detail:** The `useFileUpload` composable must:
- Read Sanctum token from `useCookie('accessToken')` (matching `useApi.ts` pattern)
- Set `Authorization: Bearer <token>` header
- POST to `POST /api/documents` (existing endpoint) as `multipart/form-data`
- Track per-file progress via `XMLHttpRequest.upload.onprogress`
- Support cancellation via `AbortController`
- Validate file type (jpg, jpeg, png, webp, pdf) and size client-side before upload

### 2. PDF Preview — @tato30/vue-pdf

| Detail | Value |
|--------|-------|
| **Package** | `@tato30/vue-pdf` |
| **Version** | Latest (1.x) |
| **Purpose** | Render PDF pages in-browser for preview |
| **Why this one** | Vue 3 native, uses pdf.js under the hood, supports text selection, annotations, all pages rendering |
| **Bundle impact** | ~500KB gzipped (pdf.js worker) — lazy-load only when PDF preview is opened |
| **Alternative rejected** | `vue-pdf-embed` — less maintained, fewer features |

**Installation:**
```bash
pnpm add @tato30/vue-pdf
```

**Usage pattern:**
```vue
<script setup lang="ts">
import { VuePDF, usePDF } from '@tato30/vue-pdf'
import '@tato30/vue-pdf/style.css'

const props = defineProps<{ url: string }>()
const { pdf, pages } = usePDF(props.url)
</script>

<template>
  <div v-for="page in pages" :key="page">
    <VuePDF :pdf="pdf" :page="page" fit-parent />
  </div>
</template>
```

**Critical:** Lazy-load this component with `defineAsyncComponent()` — never include pdf.js in the main bundle.

### 3. Image Preview — Native browser + Vuetify

**Decision: No library needed.** Use native `<img>` tags inside `v-dialog` or `v-img` with Vuetify's built-in lightbox-style patterns.

- `v-img` component already handles responsive images, lazy loading, aspect ratios
- For zoom/pan: CSS `transform: scale()` + `overflow: auto` is sufficient for document images
- For image comparison (before/after OCR): side-by-side `v-img` components

### 4. Chat Interface — Custom Vuetify-based (RECOMMENDED over third-party)

**Decision: Build custom chat UI with Vuetify components.**

**Rationale for rejecting third-party chat libraries:**

| Library | Why Rejected |
|---------|-------------|
| `vue-advanced-chat` | Designed for multi-room messaging (Slack-like). Our use case is single-thread document Q&A. Over-engineered, 200KB+ bundle. |
| `deep-chat` | Framework-agnostic web component. Works, but fights Vuetify's styling system. Theming is painful. |
| `TDesign Chat` | Tencent ecosystem, heavy dependency tree, overkill for single-thread Q&A. |

**Our chat is simple:**
- One conversation thread per document session (or global)
- User sends question → backend calls SuperMemory search + Gemini → returns answer
- Messages are text with markdown formatting (Gemini returns markdown per its prompt)
- No file sharing in chat, no reactions, no threads

**What to build:**
```
resources/ts/components/chat/
├── ChatWindow.vue           # Main container: message list + input
├── ChatMessage.vue          # Single message bubble (user or assistant)
├── ChatInput.vue            # Text input + send button
├── ChatMarkdown.vue         # Renders markdown responses (uses marked or markdown-it)
└── composables/
    └── useChat.ts           # Message state, send/receive, conversation history
```

### 5. Markdown Rendering — marked

| Detail | Value |
|--------|-------|
| **Package** | `marked` |
| **Version** | Latest (15.x) |
| **Purpose** | Render Gemini's markdown responses to HTML |
| **Why** | Tiny (~15KB), fast, no dependencies. Gemini returns markdown with `app-file://` links that need custom rendering. |
| **Alternative** | `markdown-it` — larger, more plugins than we need |

**Installation:**
```bash
pnpm add marked
```

**Custom renderer needed:** Gemini responses include `[filename](app-file://url-encoded-filename)` links. The markdown renderer must intercept `app-file://` protocol links and convert them to click handlers that navigate to the document viewer.

### 6. State Management — Pinia (already installed)

**No new library needed.** Create new stores:

```
resources/ts/stores/
├── auth.ts          # User session, token management, login/logout
├── documents.ts     # Document list, filters, pagination
├── chat.ts          # Chat messages, conversation state
└── upload.ts        # Upload queue, progress tracking
```

### 7. Form Validation — VeeValidate (RECOMMENDED)

| Detail | Value |
|--------|-------|
| **Package** | `vee-validate` + `@vee-validate/zod` |
| **Version** | Latest (4.x) |
| **Purpose** | Form validation for login, registration, file upload forms |
| **Why** | Integrates cleanly with Vuetify form components, schema-based validation with Zod |
| **Alternative rejected** | Vuelidate — less intuitive API, weaker TypeScript support |

**Installation:**
```bash
pnpm add vee-validate @vee-validate/zod zod
```

**Alternative (lighter):** Skip vee-validate and use Vuetify's built-in `:rules` prop on form inputs. This works for simple cases but gets messy for multi-field forms with cross-field validation. **Recommendation:** Start with Vuetify rules, upgrade to vee-validate only if forms become complex.

---

## Backend Libraries to Add

### 1. S3 Flysystem Driver — league/flysystem-aws-s3-v3

| Detail | Value |
|--------|-------|
| **Package** | `league/flysystem-aws-s3-v3` |
| **Version** | ^3.0 |
| **Purpose** | Enable S3 filesystem disk in Laravel (already configured in `config/filesystems.php` but driver not installed) |
| **Why** | The S3 disk config already exists in the codebase. Installing the driver enables instant S3 switching via `FILESYSTEM_DISK=s3`. |

**Installation:**
```bash
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```

**Note:** The `config/filesystems.php` already has a fully configured `s3` disk with `AWS_*` env vars. The only missing piece is the Composer package.

### 2. Laravel Reverb (WebSocket Server) — OPTIONAL, Phase-Dependent

| Detail | Value |
|--------|-------|
| **Package** | `laravel/reverb` |
| **Purpose** | WebSocket server for real-time events (document processing status, chat streaming) |
| **When to add** | Only if real-time document processing status is needed (Phase 2+) |

**For Phase 1 (MVP):** Polling is sufficient. Document processing via SuperMemory takes 5-30 seconds. Poll every 3 seconds with `setInterval` in the upload composable. This avoids the operational complexity of running a WebSocket server.

**For Phase 2+:** Add Reverb when:
- Users expect live progress updates during OCR/analysis
- Chat streaming is implemented (Gemini supports streaming)
- Multiple browser tabs need synchronized state

**Installation (Phase 2):**
```bash
php artisan install:broadcasting --reverb
pnpm add laravel-echo pusher-js
# Or for Vue-specific: pnpm add @laravel/echo-vue
```

---

## Summary: What to Install

### Phase 1 (MVP — Must Have)

```bash
# Frontend
pnpm add @tato30/vue-pdf          # PDF preview
pnpm add marked                    # Markdown rendering for chat

# Backend
composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies
```

### Phase 2 (Enhanced — Nice to Have)

```bash
# Frontend (if forms get complex)
pnpm add vee-validate @vee-validate/zod zod

# Backend (if real-time needed)
php artisan install:broadcasting --reverb
pnpm add laravel-echo pusher-js
```

### Do NOT Install

| Library | Why Not |
|---------|---------|
| FilePond / vue-dropzone / vue-file-agent | Existing VueUse composables + custom code covers our needs with less bundle weight |
| vue-advanced-chat / deep-chat | Over-engineered for single-thread Q&A; custom Vuetify components are simpler and better themed |
| vue-pdf-embed | @tato30/vue-pdf is more maintained and feature-rich |
| markdown-it | `marked` is smaller and sufficient for our markdown rendering needs |
| axios | `ofetch` is already in the codebase via `useApi` — adding axios creates two HTTP clients |

---

## Bundle Size Budget

| Category | Current Est. | After Phase 1 | Limit |
|----------|-------------|---------------|-------|
| Main bundle (JS) | ~350KB gz | ~365KB gz | <500KB |
| PDF viewer (lazy) | 0 | ~500KB gz | <600KB (lazy) |
| CSS (Vuetify) | ~80KB gz | ~85KB gz | <120KB |

**Key rule:** PDF viewer and markdown renderer are lazy-loaded. They MUST NOT be in the initial bundle.

---

## Sources

- Context7: Laravel 12.x filesystem docs (HIGH)
- Context7: @tato30/vue-pdf docs (HIGH)
- Context7: FilePond docs — evaluated and rejected (HIGH)
- Context7: vue-advanced-chat docs — evaluated and rejected (MEDIUM)
- Context7: deep-chat docs — evaluated and rejected (MEDIUM)
- Existing codebase analysis (HIGH)
