---
phase: 01-authentication-app-shell
plan: 01
subsystem: auth
tags: [sanctum, session, spa, cookie-auth, laravel]

# Dependency graph
requires:
  - phase: none
    provides: existing Laravel backend with Sanctum PAT auth
provides:
  - Session-based SPA cookie authentication alongside existing PAT flow
  - EnsureFrontendRequestsAreStateful middleware in API stack
  - SANCTUM_STATEFUL_DOMAINS configuration
  - SpaAuthTest with 4 passing session auth tests
affects: [01-02, vue-spa-auth]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dual auth flow: session cookies for SPA + PAT tokens for Flutter"
    - "hasSession() guard for backward-compatible session operations"
    - "Stateful headers (Origin + Referer) required for Sanctum SPA cookie auth"

key-files:
  created:
    - tests/Feature/SpaAuthTest.php
  modified:
    - app/Http/Controllers/AuthController.php
    - bootstrap/app.php
    - .env.example

key-decisions:
  - "Guarded session operations with hasSession() to preserve Flutter PAT-only flow"
  - "Used Auth::attempt() instead of Hash::check() for login to enable session creation"
  - "Session cookie name is laravel-session (hyphen) per Laravel config"

patterns-established:
  - "Dual auth: session + PAT on same endpoints for SPA and Flutter compatibility"
  - "Stateful headers pattern: Origin + Referer from SANCTUM_STATEFUL_DOMAINS"

requirements-completed: [AUTH-01, AUTH-02, AUTH-03, AUTH-04]

# Metrics
duration: 13 min
completed: 2026-06-09
---

# Phase 01 Plan 01: SPA Session Auth Summary

**Session-based SPA cookie auth via Sanctum with dual PAT+session flow preserving Flutter mobile compatibility**

## Performance

- **Duration:** 13 min
- **Started:** 2026-06-09T06:26:48Z
- **Completed:** 2026-06-09T06:40:34Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- AuthController login/register/logout now create and manage sessions alongside existing PAT tokens
- EnsureFrontendRequestsAreStateful middleware prepended to API middleware stack for SPA cookie auth
- 4 new SpaAuthTest tests verify session cookie creation, session authentication, logout invalidation, and registration session
- All 13 existing AuthControllerTest tests pass with zero regression

## Task Commits

Each task was committed atomically:

1. **Task 1: Add session-based auth to AuthController + stateful middleware** - `6db7dd0` (feat)
2. **Task 2: Create SpaAuthTest for session cookie verification** - `51a1d8e` (test)

**Plan metadata:** pending (docs: complete plan)

## Files Created/Modified
- `app/Http/Controllers/AuthController.php` - Added Auth::attempt/login, session regeneration, session invalidation with hasSession() guards
- `bootstrap/app.php` - Prepended EnsureFrontendRequestsAreStateful to API middleware stack
- `.env.example` - Added SANCTUM_STATEFUL_DOMAINS default value
- `tests/Feature/SpaAuthTest.php` - 4 test methods for SPA session auth verification

## Decisions Made
- Used `hasSession()` guards around all session operations to maintain backward compatibility with Flutter PAT-only requests that don't have session middleware
- Used `Auth::attempt()` instead of `Hash::check()` for login — this automatically creates the session needed for SPA cookie auth
- Added `$this->app['auth']->forgetGuards()` in logout test to reset cached auth state between requests (Laravel test framework quirk)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Session store not set on request for non-SPA calls**
- **Found during:** Task 1 (AuthControllerTest regression)
- **Issue:** `$request->session()->regenerate()` and `$request->session()->invalidate()` threw RuntimeException "Session store not set on request" because API routes don't include StartSession middleware for non-stateful requests (Flutter)
- **Fix:** Wrapped all session operations with `$request->hasSession()` checks so they only execute for SPA requests (where EnsureFrontendRequestsAreStateful adds session middleware)
- **Files modified:** app/Http/Controllers/AuthController.php
- **Verification:** All 13 AuthControllerTest tests pass, all 4 SpaAuthTest tests pass
- **Committed in:** 6db7dd0 (Task 1 commit)

**2. [Rule 1 - Bug] Session cookie name mismatch in tests**
- **Found during:** Task 2 (SpaAuthTest creation)
- **Issue:** Plan specified `laravel_session` (underscore) but actual cookie name is `laravel-session` (hyphen) per config/session.php
- **Fix:** Used correct cookie name `laravel-session` in assertCookieNotExpired() calls
- **Files modified:** tests/Feature/SpaAuthTest.php
- **Verification:** All 4 SpaAuthTest tests pass
- **Committed in:** 51a1d8e (Task 2 commit)

**3. [Rule 1 - Bug] Auth guard caching in logout test**
- **Found during:** Task 2 (SpaAuthTest logout test)
- **Issue:** After logout, subsequent /me request returned 200 instead of 401 because Laravel test framework cached the authenticated user in the guard instance
- **Fix:** Added `$this->app['auth']->forgetGuards()` before the post-logout /me request to force fresh guard resolution
- **Files modified:** tests/Feature/SpaAuthTest.php
- **Verification:** Logout test correctly returns 401 after session invalidation
- **Committed in:** 51a1d8e (Task 2 commit)

---

**Total deviations:** 3 auto-fixed (3 Rule 1 bugs)
**Impact on plan:** All auto-fixes necessary for correctness. No scope creep. Session guards essential for Flutter compatibility.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Backend session auth complete, ready for Vue SPA login/register UI (Plan 01-02)
- SPA must send `withCredentials: true` and Origin/Referer headers from SANCTUM_STATEFUL_DOMAINS
- CSRF cookie endpoint (`/sanctum/csrf-cookie`) needed before mutations in production

---
*Phase: 01-authentication-app-shell*
*Completed: 2026-06-09*
