# Supermemory Document Manager

## What This Is

A document management platform with AI-powered analysis. Users upload images and PDFs via web or mobile, the system extracts text via OCR, classifies documents, and enables conversational Q&A about their files. Built on Laravel 12 backend with Vue 3 + Vuetify frontend.

## Core Value

Users can upload documents and get instant AI-powered insights — extracted text, smart summaries, and the ability to ask questions about their files in natural language.

## Requirements

### Validated

- ✓ User registration and login via email/password — existing (Sanctum auth)
- ✓ File ingestion via API — existing (IngestionController)
- ✓ Document OCR and extraction — existing (GeminiService)
- ✓ AI-powered document Q&A — existing (SearchController + GeminiService)
- ✓ SuperMemory integration for document storage/search — existing (SupermemoryService)
- ✓ Subscription and usage tracking — existing (SubscriptionService, UsageLimitService)

### Active

- [ ] User panel frontend with login/registration UI
- [ ] File management interface (upload, view, organize)
- [ ] Document scanning with SuperMemory (extract text, classify, summarize)
- [ ] AI chat interface for document Q&A
- [ ] Local file storage with S3 migration option

### Out of Scope

- Admin panel — separate concern, not part of user panel
- Mobile app development — already exists, this is web frontend
- Advanced file organization (folders, tags) — defer to future milestone
- Multi-language UI — defer to future milestone

## Context

**Existing backend:**
- Laravel 12 with Sanctum authentication
- GeminiService handles OCR and document analysis
- SupermemoryService manages document storage and search
- UsageLimitService enforces quotas per subscription plan
- FirestoreSyncService mirrors ingestion status

**Existing frontend:**
- Vue 3 + Vuetify 3 SPA in resources/ts
- Empty Vuexy template (no user-facing pages yet)
- Pinia for state management
- vue-router with file-based routing
- ofetch for API calls

**Key integrations:**
- Gemini API for OCR and AI responses
- SuperMemory for document storage and semantic search
- Firestore for secondary state sync

## Constraints

- **Tech stack**: Laravel 12 + Vue 3 + Vuetify 3 — must use existing template
- **Auth**: Sanctum token-based — reuse existing auth endpoints
- **File types**: Images (jpg, png, webp) and PDF only
- **Storage**: Local by default, S3 as configurable option
- **API compatibility**: Must work with existing Flutter mobile app

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Use existing Sanctum auth | Already implemented, proven working | ✓ Good |
| Vue 3 + Vuetify 3 frontend | Existing template, consistent with codebase | ✓ Good |
| Local storage default | Simpler deployment, lower cost | — Pending |
| S3 as optional migration | Flexibility for scale without complexity upfront | — Pending |
| Include AI chat in v1 | Core value proposition, not optional | ✓ Good |

## Current Milestone: v1.9 User Panel

**Goal:** Create a web-based user panel for file management with AI-powered document analysis.

**Target features:**
- User login & registration UI (using existing Sanctum auth)
- File management interface (upload, view, organize images and PDFs)
- Local storage with S3 migration option
- SuperMemory scanning (extract text, classify, summarize, identify entities)
- AI chat interface (ask questions about uploaded documents)

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-08 after milestone v1.9 initialization*
