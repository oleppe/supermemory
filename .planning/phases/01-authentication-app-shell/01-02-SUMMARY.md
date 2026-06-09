---
phase: 01-authentication-app-shell
plan: 02
subsystem: auth
tags: [sanctum, cookie-auth, csrf, pinia, vue-router, ofetch]

# Dependency graph
requires:
  - phase: 01-authentication-app-shell
    plan: 01
    provides: Sanctum SPA cookie auth backend endpoints
provides:
  - Cookie-based API client with CSRF handling ($api)
  - Pinia auth store with user state and auth actions (useAuthStore)
  - Router auth guard via beforeEach
  - Mount-time auth initialization in App.vue
  - Horizontal nav layout configuration
affects: [01-03, 01-04, 01-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cookie-based auth with credentials:include instead of Bearer tokens"
    - "Lazy import of useAuthStore in api.ts 401 handler to avoid circular dependency"
    - "Mount-time CSRF cookie fetch before user authentication check"

key-files:
  created:
    - resources/ts/stores/auth.ts
  modified:
    - resources/ts/utils/api.ts
    - resources/ts/plugins/1.router/index.ts
    - resources/ts/App.vue
    - themeConfig.ts
    - resources/ts/navigation/horizontal/index.ts

key-decisions:
  - "Lazy import pattern for useAuthStore in api.ts to avoid circular dependency at module init"
  - "baseURL override to empty string for /sanctum/csrf-cookie call (endpoint is at root, not /api)"
  - "Router guard relies on mount-time App.vue init rather than calling fetchUser itself"

patterns-established:
  - "CSRF flow: cookie fetch at mount → XSRF-TOKEN header on every request"
  - "401 interceptor clears auth state via lazy store import"

requirements-completed: [AUTH-03, AUTH-05]

# Metrics
duration: 2 min
completed: 2026-06-09
---

# Phase 01 Plan 02: Frontend Auth Infrastructure Summary

**Cookie-based API client with CSRF handling, Pinia auth store, router guard, and horizontal nav layout**

## Performance

- **Duration:** 2 min
- **Started:** 2026-06-09T06:44:05Z
- **Completed:** 2026-06-09T06:46:49Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Replaced Bearer token auth in api.ts with Sanctum SPA cookie auth (credentials:include, CSRF header, 401 interceptor)
- Created Pinia auth store with User interface, fetchUser, login, register, logout actions
- Added router beforeEach guard redirecting unauthenticated users to /login?redirect=
- Added mount-time auth initialization in App.vue (CSRF cookie → fetchUser)
- Switched layout to horizontal nav and cleared placeholder nav items

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace api.ts Bearer auth with cookie auth + create Pinia auth store** - `e2610a7` (feat)
2. **Task 2: Add router auth guard + App.vue auth init + horizontal nav config** - `9ac4171` (feat)

## Files Created/Modified
- `resources/ts/utils/api.ts` - Cookie-based API client with CSRF token handling and 401 interceptor
- `resources/ts/stores/auth.ts` - Pinia auth store with User interface and auth actions
- `resources/ts/plugins/1.router/index.ts` - Router beforeEach auth guard
- `resources/ts/App.vue` - Mount-time CSRF cookie fetch and user initialization
- `themeConfig.ts` - Switched from vertical to horizontal nav layout
- `resources/ts/navigation/horizontal/index.ts` - Cleared placeholder nav items

## Decisions Made
- Used lazy import (`await import('@/stores/auth')`) in api.ts 401 handler to avoid circular dependency — api.ts is imported by auth.ts, so auth.ts cannot be imported at the top of api.ts
- Override `baseURL: ''` on the CSRF cookie fetch call because `/sanctum/csrf-cookie` lives at the root, not under `/api`
- Router guard does NOT call `fetchUser()` — it relies on App.vue's mount-time `initAuth()` having already populated the auth store

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Frontend auth infrastructure complete, ready for login/registration UI (Plan 03)
- API client, auth store, router guard, and mount-time init all wired together
- Horizontal nav layout configured for app shell

---
*Phase: 01-authentication-app-shell*
*Completed: 2026-06-09*

## Self-Check: PASSED

All 6 key files found on disk. All 3 commits (e2610a7, 9ac4171, bf0518c) verified in git log.
