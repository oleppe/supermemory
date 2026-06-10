---
phase: 1
slug: authentication-app-shell
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-08
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.3 (backend only) |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter=AuthControllerTest` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=AuthControllerTest`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01 | 1 | AUTH-01 | T-1-01 | Login returns session cookie + PAT | feature | `php artisan test --filter=AuthControllerTest::test_login_returns_token` | ✅ W0 | ⬜ pending |
| 01-01-02 | 01 | 1 | AUTH-02 | T-1-02 | Register creates user + session | feature | `php artisan test --filter=AuthControllerTest::test_register_creates_user_and_returns_token` | ✅ W0 | ⬜ pending |
| 01-02-01 | 01 | 1 | AUTH-03 | T-1-03 | Session cookie set after login | feature | `php artisan test --filter=SpaAuthTest::test_login_sets_session_cookie` | ❌ W0 | ⬜ pending |
| 01-02-02 | 01 | 1 | AUTH-03 | — | Session persists across requests | feature | `php artisan test --filter=SpaAuthTest::test_session_persists_across_requests` | ❌ W0 | ⬜ pending |
| 01-03-01 | 01 | 2 | AUTH-04 | T-1-04 | Logout invalidates session | feature | `php artisan test --filter=SpaAuthTest::test_logout_invalidates_session` | ❌ W0 | ⬜ pending |
| 01-03-02 | 01 | 2 | AUTH-04 | — | Logout clears client state | manual | N/A — frontend redirect | ❌ | ⬜ pending |
| 01-04-01 | 01 | 2 | AUTH-05 | — | Unauthenticated redirect to login | manual | N/A — router guard is frontend-only | ❌ | ⬜ pending |
| 01-05-01 | 01 | 2 | AUTH-01 | — | Login form submits correctly | manual | N/A — no frontend test infra | ❌ | ⬜ pending |
| 01-05-02 | 01 | 2 | AUTH-02 | — | Register tab works, auto-login | manual | N/A — no frontend test infra | ❌ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/SpaAuthTest.php` — covers AUTH-03 (session cookie after login), AUTH-04 (session invalidation on logout), and stateful middleware behavior
- [ ] New test: verify `EnsureFrontendRequestsAreStateful` is applied to API routes
- [ ] New test: verify login with stateful request sets session cookie
- [ ] New test: verify `/api/auth/me` authenticates via session cookie

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Login form submits correctly | AUTH-01 | No frontend test infra | Open /login, enter credentials, verify redirect to dashboard |
| Register tab works, auto-login | AUTH-02 | No frontend test infra | Switch to Register tab, create account, verify auto-login |
| Logout redirects to login | AUTH-04 | Frontend redirect behavior | Click logout in user menu, verify redirect to /login |
| Unauthenticated redirect to login | AUTH-05 | Router guard is frontend-only | Visit / while logged out, verify redirect to /login?redirect=/ |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
