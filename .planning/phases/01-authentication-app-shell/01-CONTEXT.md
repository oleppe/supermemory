# Phase 1: Authentication & App Shell - Context

**Gathered:** 2026-06-08
**Status:** Ready for planning

## Phase Boundary

Wire existing Sanctum auth to the Vue frontend with login, registration, session persistence, route guards, and an authenticated app shell. This phase delivers the access layer only — no document management, no AI features.

## Implementation Decisions

### Token Persistence
- **D-01:** Auth token stored in httpOnly cookie (Sanctum SPA mode). XSS-proof, browser-managed. Requires `sanctum.stateful` domain config and CSRF setup.
- **D-02:** Pinia auth store checks authentication on app mount by fetching `/api/user`. If 401, redirect to `/login`. No per-route-guard auth checks — single mount-time check.
- **D-03:** Logout calls `POST /api/logout` to invalidate server-side token, then redirects to `/login`.

### App Shell Layout
- **D-04:** Top navigation bar using Vuexy's built-in horizontal nav layout (`DefaultLayoutWithHorizontalNav`). Configure via `AppContentLayoutNav.Horizontal`.
- **D-05:** Top bar contains: app logo + title (left), user menu dropdown with logout (right). No navigation section links in the top bar for this phase.
- **D-06:** Post-login landing page is a welcome dashboard at `/` with app name and quick-action placeholders (Upload, View Documents). Actual document list comes in Phase 2.

### Login/Register UX
- **D-07:** Single page at `/login` with tab toggle between Login and Register forms. Not separate pages.
- **D-08:** Centered card layout on clean background. Modify existing `resources/ts/pages/login.vue` (currently split illustration+card) to centered card using `blank` layout.
- **D-09:** Post-login redirect uses query param (`/login?redirect=/intended-path`). Route guard captures intended URL before redirecting to login. Falls back to welcome dashboard (`/`) if no redirect param.
- **D-10:** Registration auto-logs in the user (per AUTH-02 requirement). After successful register, same redirect flow as login.

### Agent's Discretion
- Form validation patterns (inline errors, field rules) — agent picks idiomatic Vuetify approach
- Loading state UX during auth requests — agent picks based on existing patterns
- CSRF cookie initialization timing — agent determines optimal point in auth flow
- Welcome dashboard content/layout — agent designs simple placeholder

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Authentication
- `app/Http/Controllers/AuthController.php` — Existing auth endpoints (login, register, logout, user). Must be wired to frontend.
- `config/sanctum.php` — Sanctum config. Needs `stateful` domains and `spa_url` configured for cookie auth.
- `routes/api.php` — API route definitions. Auth routes live here.

### Frontend Structure
- `resources/ts/pages/login.vue` — Existing login page (Vuexy template). To be modified for centered card + tab toggle.
- `resources/ts/layouts/default.vue` — Default authenticated layout. Supports horizontal/vertical nav switch.
- `resources/ts/layouts/components/DefaultLayoutWithHorizontalNav.vue` — Horizontal nav layout component to use for app shell.
- `resources/ts/layouts/components/UserProfile.vue` — Existing user profile dropdown. Reuse for user menu in top bar.
- `resources/ts/utils/api.ts` — Shared API client. Needs `withCredentials: true` for cookie auth.
- `resources/ts/plugins/1.router/index.ts` — Router setup. Route guards and meta config live here.

### Project Context
- `.planning/REQUIREMENTS.md` — AUTH-01 through AUTH-05 requirements (5 requirements, all in this phase)
- `.planning/ROADMAP.md` — Phase 1 goal, success criteria, dependencies

## Existing Code Insights

### Reusable Assets
- `resources/ts/pages/login.vue`: Existing Vuexy login page — restructure from split layout to centered card, add register tab
- `resources/ts/layouts/components/UserProfile.vue`: Existing user dropdown — reuse for top bar user menu with logout
- `resources/ts/layouts/components/DefaultLayoutWithHorizontalNav.vue`: Horizontal nav layout — use as authenticated app shell
- `resources/ts/utils/api.ts`: Shared ofetch client — add `withCredentials: true` for Sanctum cookie mode
- `@core/composable/useGenerateImageVariant`: Theme-aware image variant composable — available for auth page if needed

### Established Patterns
- File-based routing via `unplugin-vue-router` with `definePage()` meta blocks (`layout`, `public`)
- `blank` layout for public/auth pages, `default` layout for authenticated pages
- Pinia stores for app state (`useConfigStore` pattern in core)
- SCSS-driven styling with `@use` imports and alias-based paths (`@/`, `@core/`, `@layouts/`)
- Two-space indentation, no semicolons in TypeScript/Vue files

### Integration Points
- `resources/ts/plugins/1.router/index.ts`: Add global `beforeEach` guard for auth redirect logic
- `resources/ts/main.ts`: App bootstrap — add auth store initialization and mount-time user check
- `config/sanctum.php`: Configure `stateful` domains for SPA cookie auth
- `bootstrap/app.php`: May need middleware adjustments for Sanctum SPA mode
- `routes/web.php`: Catch-all SPA route — ensure `/login?redirect=` params pass through

## Specific Ideas

No specific requirements — open to standard approaches

## Deferred Ideas

- Error/validation UX patterns (toast vs inline) — not discussed, agent decides based on codebase conventions
- Password reset flow — mentioned in existing login.vue ("Forgot Password?" link) but out of scope for Phase 1
- OAuth/social login providers — existing `AuthProvider.vue` component in template, but backend only supports email/password
- Navigation links in top bar (Documents, Scan, Chat) — deferred to Phase 2/3 when those features exist

---

*Phase: 1-Authentication & App Shell*
*Context gathered: 2026-06-08*
