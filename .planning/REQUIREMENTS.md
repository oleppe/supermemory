# Requirements: Supermemory Document Manager — v1.9 User Panel

**Defined:** 2026-06-08
**Core Value:** Users can upload documents and get instant AI-powered insights — extracted text, smart summaries, and the ability to ask questions about their files in natural language.

## v1 Requirements

### Authentication

- [x] **AUTH-01**: User can log in with email/password via login page
- [x] **AUTH-02**: User can register a new account via registration page
- [x] **AUTH-03**: User session persists across browser refresh (token in cookie)
- [x] **AUTH-04**: User can log out and be redirected to login page
- [ ] **AUTH-05**: Unauthenticated users are redirected to login from protected pages

### File Management

- [ ] **FILE-01**: User can upload images (jpg, png, webp) and PDFs via drag-and-drop
- [ ] **FILE-02**: User sees upload progress for each file during upload
- [ ] **FILE-03**: User can view a paginated list of uploaded documents with type badges
- [ ] **FILE-04**: User can view document detail with metadata, summary, and file preview
- [ ] **FILE-05**: User can preview PDFs in-browser without downloading
- [ ] **FILE-06**: User can delete uploaded documents
- [ ] **FILE-07**: Uploaded files are stored locally with metadata (name, size, type, dates)

### Document Scanning

- [ ] **SCAN-01**: User can trigger OCR scan on an uploaded document
- [ ] **SCAN-02**: User can view scan results: extracted text, summary, document type, entities
- [ ] **SCAN-03**: User sees document classification (invoice, receipt, contract, etc.) with color-coded badges
- [ ] **SCAN-04**: User sees action items derived from document content
- [ ] **SCAN-05**: Scan progress is shown while OCR processing completes (async with polling)

### AI Chat

- [ ] **CHAT-01**: User can ask questions about their documents via chat interface
- [ ] **CHAT-02**: AI responses are rendered as formatted markdown
- [ ] **CHAT-03**: Chat shows loading/typing indicator while waiting for AI response
- [ ] **CHAT-04**: Conversation history survives page refresh (client-side persistence)
- [ ] **CHAT-05**: Chat responses include source document references as clickable links

### Storage

- [ ] **STOR-01**: Files stored locally with relative paths and disk identifier (S3-ready)
- [ ] **STOR-02**: User can configure S3 storage via environment variables
- [ ] **STOR-03**: Files served through authenticated route (not public symlink)

## Future Requirements

### Organization

- **ORG-01**: User can organize files into folders
- **ORG-02**: User can tag documents for custom categorization

### Enhanced Chat

- **CHAT-06**: AI chat responses stream token-by-token (SSE)
- **CHAT-07**: Chat history persisted server-side for cross-device access

### Admin

- **ADM-01**: Admin panel within user SPA
- **ADM-02**: User management interface

## Out of Scope

| Feature | Reason |
|---------|--------|
| Mobile app development | Already exists (Flutter) |
| OAuth/social login | Backend only supports email/password |
| Real-time WebSocket chat | Overkill for request-response pattern; SSE in future |
| File versioning | Supermemory documents are immutable after upload |
| Collaborative sharing | Single-user per container; no sharing model |
| Offline mode | Requires service worker + IndexedDB (massive scope) |
| Billing management | Stripe portal exists but not critical for core flow |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 1 | Complete |
| AUTH-02 | Phase 1 | Complete |
| AUTH-03 | Phase 1 | Complete |
| AUTH-04 | Phase 1 | Complete |
| AUTH-05 | Phase 1 | Pending |
| FILE-01 | Phase 2 | Pending |
| FILE-02 | Phase 2 | Pending |
| FILE-03 | Phase 2 | Pending |
| FILE-04 | Phase 2 | Pending |
| FILE-05 | Phase 2 | Pending |
| FILE-06 | Phase 2 | Pending |
| FILE-07 | Phase 2 | Pending |
| SCAN-01 | Phase 3 | Pending |
| SCAN-02 | Phase 3 | Pending |
| SCAN-03 | Phase 3 | Pending |
| SCAN-04 | Phase 3 | Pending |
| SCAN-05 | Phase 3 | Pending |
| CHAT-01 | Phase 3 | Pending |
| CHAT-02 | Phase 3 | Pending |
| CHAT-03 | Phase 3 | Pending |
| CHAT-04 | Phase 3 | Pending |
| CHAT-05 | Phase 3 | Pending |
| STOR-01 | Phase 2 | Pending |
| STOR-02 | Phase 2 | Pending |
| STOR-03 | Phase 2 | Pending |

**Coverage:**

- v1 requirements: 25 total
- Mapped to phases: 25
- Unmapped: 0 ✓

---
*Requirements defined: 2026-06-08*
*Last updated: 2026-06-08 after initial definition*
