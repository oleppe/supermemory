# Phase 1: Authentication & App Shell - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-08
**Phase:** 1-Authentication & App Shell
**Areas discussed:** Token persistence, App shell layout, Login/Register UX

---

## Token Persistence

### Question 1: Token storage
| Option | Description | Selected |
|--------|-------------|----------|
| httpOnly cookie | Store Sanctum token in an httpOnly cookie. XSS-proof, browser-managed. Requires CSRF config but most secure. | ✓ |
| localStorage + Bearer header | Store token in localStorage, attach as Bearer header. Simpler client code, but vulnerable to XSS. Matches existing API pattern used by Flutter. | |
| Pinia + sessionStorage | Keep token in Pinia store, persist to sessionStorage. Survives tab refresh but not browser close. | |

**User's choice:** httpOnly cookie (Recommended)

### Question 2: Auth detection on page load
| Option | Description | Selected |
|--------|-------------|----------|
| Store checks on app mount | Pinia auth store checks cookie on app mount, fetches /api/user to validate. Redirects to /login if 401. | ✓ |
| Per-route guard check | Router guard checks auth before each navigation. More granular but adds latency per route change. | |
| You decide | Let the agent decide the best approach based on codebase patterns. | |

**User's choice:** Store checks on app mount (Recommended)

### Question 3: Logout behavior
| Option | Description | Selected |
|--------|-------------|----------|
| Server-side invalidation | Call POST /api/logout to invalidate server token, then redirect to /login. Clean server-side state. | ✓ |
| Client-side only | Just clear the cookie client-side and redirect. Simpler but server token stays valid until expiry. | |

**User's choice:** Server-side invalidation (Recommended)

---

## App Shell Layout

### Question 1: Navigation structure
| Option | Description | Selected |
|--------|-------------|----------|
| Sidebar nav | Left sidebar with navigation links. Vuexy template includes this by default. | |
| Top navigation bar | Top horizontal navigation bar. Cleaner for wide screens. | ✓ |
| You decide | Let the agent decide based on what fits the Vuexy template best. | |

**User's choice:** Top navigation bar but using vuexy template, it support top nav menu
**Notes:** User confirmed Vuexy template supports top nav natively.

### Question 2: Top bar contents
| Option | Description | Selected |
|--------|-------------|----------|
| User menu + logo | User avatar/name dropdown with logout, plus app logo and title. Minimal and focused. | ✓ |
| Nav links + user menu | Add navigation links to main sections (Documents, Scan, Chat) in the top bar. More discoverable but busier. | |
| You decide | Let the agent decide what belongs in the top bar. | |

**User's choice:** User menu + logo (Recommended)

### Question 3: Landing page after login
| Option | Description | Selected |
|--------|-------------|----------|
| Welcome dashboard | Simple welcome page with app name and quick-action buttons (Upload, View Documents). Placeholder until Phase 2. | ✓ |
| Skip to document list | Redirect straight to /documents (file list). Phase 2 will build this — would need a stub now. | |
| You decide | Let the agent decide. | |

**User's choice:** Welcome dashboard (Recommended)

---

## Login/Register UX

### Question 1: Page structure
| Option | Description | Selected |
|--------|-------------|----------|
| Single page with tabs | One page with tabs to switch between Login and Register. Fewer routes, feels modern. | ✓ |
| Separate pages | Separate /login and /register pages. More traditional, each page focused on one action. | |
| You decide | Let the agent decide. | |

**User's choice:** Single page with tabs (Recommended)

### Question 2: Visual layout
| Option | Description | Selected |
|--------|-------------|----------|
| Centered card | Centered card on a clean background. Vuexy template has auth page examples — reuse the template's auth layout. | ✓ |
| Split layout | Full-page split layout — form on one side, hero/branding on the other. More visual impact but more custom work. | |
| You decide | Let the agent decide. | |

**User's choice:** Centered card (Recommended)

### Question 3: Post-login redirect
| Option | Description | Selected |
|--------|-------------|----------|
| Query param redirect | Store the intended URL in a query param (e.g., /login?redirect=/documents). After successful auth, navigate to that URL. Falls back to welcome dashboard. | ✓ |
| Always dashboard | Always go to the welcome dashboard after login. Simpler but ignores AUTH-05's 'originally requested page' requirement. | |
| You decide | Let the agent decide. | |

**User's choice:** Query param redirect (Recommended)

---

## Agent's Discretion

No areas were deferred to agent discretion — user selected a concrete option for every question.

Agent has flexibility on: form validation patterns, loading state UX, CSRF cookie initialization timing, welcome dashboard content/layout.

## Deferred Ideas

- Error/validation UX patterns — not discussed, skipped by user selection
- Password reset flow — existing "Forgot Password?" link in template, out of scope
- OAuth/social login — existing AuthProvider.vue component, but backend only supports email/password
- Navigation links in top bar — deferred to Phase 2/3 when those features exist
