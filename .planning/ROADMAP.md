# Roadmap: Supermemory Document Manager — v1.9 User Panel

## Overview

Build a web-based user panel for the existing Laravel 12 + Vue 3 + Vuetify 3 application. The backend already provides all API endpoints (auth, document ingestion, OCR, search). This milestone delivers the frontend that wires those endpoints into a coherent user experience: authentication, file upload with local storage, document management, AI-powered document scanning, and conversational Q&A about uploaded documents. Three phases deliver the full feature set — auth foundation first (everything depends on tokens), file management second (documents must exist before AI features), and AI features third (the product differentiators).

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Authentication & App Shell** - Wire existing Sanctum auth to Vue frontend with login, registration, session persistence, and route guards
- [ ] **Phase 2: File Management & Storage** - Drag-and-drop file upload with progress, document list/detail views, PDF preview, and local-first storage infrastructure
- [ ] **Phase 3: AI Chat & Document Scanning** - Conversational document Q&A with markdown rendering and OCR scanning with async progress and structured results

## Phase Details

### Phase 1: Authentication & App Shell

**Goal**: Users can securely access the application through login and registration with persistent sessions
**Depends on**: Nothing (first phase)
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05
**Success Criteria** (what must be TRUE):

  1. User can log in with email/password on the login page and reach the app dashboard
  2. User can register a new account and be automatically logged in
  3. User stays logged in after browser refresh (session token persists in cookie)
  4. User can log out from any page and is redirected to the login screen
  5. Unauthenticated users visiting protected pages are redirected to login, then to the originally requested page after logging in

**Plans:** 3 plansPlans:
**Wave 1**

- [ ] 01-01-PLAN.md — Backend SPA auth: session-based login/register/logout + stateful middleware + tests
- [ ] 01-02-PLAN.md — Frontend auth infra: cookie API client, Pinia auth store, router guard, app shell config

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 01-03-PLAN.md — Auth UI: login/register page with tab toggle, welcome dashboard, user menu with logout

### Phase 2: File Management & Storage

**Goal**: Users can upload documents and manage their collection through an intuitive interface backed by local-first storage
**Depends on**: Phase 1
**Requirements**: FILE-01, FILE-02, FILE-03, FILE-04, FILE-05, FILE-06, FILE-07, STOR-01, STOR-02, STOR-03
**Success Criteria** (what must be TRUE):

  1. User can upload images (jpg, png, webp) and PDFs via drag-and-drop or file picker
  2. User sees per-file upload progress bars during file upload
  3. User can browse a paginated list of uploaded documents with file type badges
  4. User can view document detail page showing metadata, summary, and file preview
  5. User can preview PDFs directly in the browser without downloading
  6. User can delete documents from their collection
  7. Files are stored locally with relative paths and disk identifier, ready for future S3 migration

**Plans**: TBD
**UI hint**: yes

### Phase 3: AI Chat & Document Scanning

**Goal**: Users can extract AI insights from documents through OCR scanning and ask questions about their files via conversational chat
**Depends on**: Phase 2
**Requirements**: SCAN-01, SCAN-02, SCAN-03, SCAN-04, SCAN-05, CHAT-01, CHAT-02, CHAT-03, CHAT-04, CHAT-05
**Success Criteria** (what must be TRUE):

  1. User can trigger an OCR scan on any uploaded document and see progress while processing completes
  2. User can view structured scan results: extracted text, summary, document type, entities, and action items
  3. User sees color-coded document classification badges (invoice, receipt, contract, etc.)
  4. User can ask questions about their documents through a chat interface and receive markdown-formatted AI responses
  5. Chat conversation history survives page refresh via client-side persistence
  6. Chat responses include clickable source document references that link back to the relevant files

**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Authentication & App Shell | 0/3 | Planning complete | - |
| 2. File Management & Storage | 0/TBD | Not started | - |
| 3. AI Chat & Document Scanning | 0/TBD | Not started | - |
