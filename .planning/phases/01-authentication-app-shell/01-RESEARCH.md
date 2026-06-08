# Phase 1: Authentication & App Shell - Research

**Researched:** 2026-06-08
**Domain:** Laravel Sanctum SPA authentication + Vue 3 app shell (Vuexy template)
**Confidence:** HIGH

## Summary

Phase 1 wires the existing Laravel Sanctum backend auth to the Vue 3 frontend (Vuexy template) using Sanctum's SPA cookie authentication mode. The backend already has auth endpoints (`/api/auth/login`, `/register`, `/me`, `/logout`) that currently issue Bearer tokens for the Flutter mobile app. The web SPA will use httpOnly session cookies instead — the same `auth:sanctum` middleware supports both modes simultaneously.

The critical backend change is applying `EnsureFrontendRequestsAreStateful` middleware to API routes and modifying `AuthController` to establish Laravel sessions (via `Auth::attempt()`) alongside the existing PAT token flow. The frontend needs a Pinia auth store, router guard, modified API client with `withCredentials: true`, and restructured login page with tab toggle.

**Primary recommendation:** Modify `AuthController` to use `Auth::attempt()` for login and `Auth::login()` for register (creating sessions), while still returning PAT tokens for Flutter compatibility. Apply `EnsureFrontendRequestsAreStateful` to API routes in `bootstrap/app.php`. On the frontend, switch `api.ts` from Bearer token to `withCredentials: true` cookie mode.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Auth token stored in httpOnly cookie (Sanctum SPA mode). XSS-proof, browser-managed. Requires `sanctum.stateful` domain config and CSRF setup.
- **D-02:** Pinia auth store checks authentication on app mount by fetching `/api/user`. If 401, redirect to `/login`. No per-route-guard auth checks — single mount-time check.
- **D-03:** Logout calls `POST /api/logout` to invalidate server-side token, then redirects to `/login`.
- **D-04:** Top navigation bar using Vuexy's built-in horizontal nav layout (`DefaultLayoutWithHorizontalNav`). Configure via `AppContentLayoutNav.Horizontal`.
- **D-05:** Top bar contains: app logo + title (left), user menu dropdown with logout (right). No navigation section links in the top bar for this phase.
- **D-06:** Post-login landing page is a welcome dashboard at `/` with app name and quick-action placeholders (Upload, View Documents). Actual document list comes in Phase 2.
- **D-07:** Single page at `/login` with tab toggle between Login and Register forms. Not separate pages.
- **D-08:** Centered card layout on clean background. Modify existing `resources/ts/pages/login.vue` (currently split illustration+card) to centered card using `blank` layout.
- **D-09:** Post-login redirect uses query param (`/login?redirect=/intended-path`). Route guard captures intended URL before redirecting to login. Falls back to welcome dashboard (`/`) if no redirect param.
- **D-10:** Registration auto-logs in the user (per AUTH-02 requirement). After successful register, same redirect flow as login.

### Agent's Discretion
- Form validation patterns (inline errors, field rules) — agent picks idiomatic Vuetify approach
- Loading state UX during auth requests — agent picks based on existing patterns
- CSRF cookie initialization timing — agent determines optimal point in auth flow
- Welcome dashboard content/layout — agent designs simple placeholder

### Deferred Ideas (OUT OF SCOPE)
- Error/validation UX patterns (toast vs inline) — not discussed, agent decides based on codebase conventions
- Password reset flow — mentioned in existing login.vue ("Forgot Password?" link) but out of scope for Phase 1
- OAuth/social login providers — existing `AuthProvider.vue` component in template, but backend only supports email/password
- Navigation links in top bar (Documents, Scan, Chat) — deferred to Phase 2/3 when those features exist

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| AUTH-01 | User can log in with email/password via login page | Backend `POST /api/auth/login` exists; needs `Auth::attempt()` for session + frontend form + Pinia store |
| AUTH-02 | User can register a new account via registration page | Backend `POST /api/auth/register` exists; needs `Auth::login()` after create + frontend register tab |
| AUTH-03 | User session persists across browser refresh (token in cookie) | Sanctum SPA session cookie is httpOnly, auto-sent by browser; needs `withCredentials: true` on API client |
| AUTH-04 | User can log out and be redirected to login page | Backend `POST /api/auth/logout` exists; needs session invalidation + frontend redirect |
| AUTH-05 | Unauthenticated users are redirected to login from protected pages | Router guard in `1.router/index.ts` captures intended URL, redirects to `/login?redirect=` |
</phase_requirements>

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Login/Register form UI | Browser (Vue) | — | Form rendering, validation, tab toggle |
| Credential validation (server) | API / Backend | — | `Auth::attempt()`, password hashing, rate limiting |
| Session creation | API / Backend | — | Laravel session driver, httpOnly cookie |
| CSRF protection | API / Backend | Browser | Sanctum CSRF cookie, `ValidateCsrfToken` middleware |
| Session persistence (cookie) | Browser | — | httpOnly cookie managed by browser, `withCredentials: true` |
| Auth state check on mount | Browser (Pinia) | API | `GET /api/auth/me` call, 401 → redirect |
| Route guard (redirect to login) | Browser (Vue Router) | — | `beforeEach` guard, captures intended URL |
| Logout (server-side) | API / Backend | — | Session invalidation, PAT deletion |
| Logout (client redirect) | Browser (Vue) | — | Redirect to `/login` after server confirms |
| App shell layout | Browser (Vue) | — | Horizontal nav, user dropdown |

## Standard Stack

### Core (Already Installed — No New Packages)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Sanctum | v4.3.1 | SPA cookie auth + token auth | Already installed; supports both SPA sessions and PAT tokens simultaneously |
| Vue | 3.5.22 | Frontend framework | Project standard |
| Vue Router | 4.5.1 (via unplugin-vue-router 0.8.8) | File-based routing, route guards | Project standard; auto-imports routes from `resources/ts/pages/` |
| Pinia | 3.0.3 | Auth state management | Project standard; `createPinia()` already configured |
| Vuetify | 3.10.8 | Form components, validation, UI | Project standard via Vuexy template |
| ofetch | 1.5.0 | HTTP client | Already configured in `api.ts`; needs `withCredentials: true` |

### Supporting (Already Installed)

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| cookie-es | 1.2.2 | Cookie parsing/serialization | Used by `useCookie` composable (client-side cookies only, NOT auth cookies) |
| @vueuse/core | 10.11.1 | Composables (`usePreferredColorScheme`, etc.) | Available for any reactive utilities |

**No new packages needed.** All required libraries are already in `package.json` and `composer.json`.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sanctum SPA cookie auth | Bearer token in localStorage | XSS-vulnerable; CONTEXT.md D-01 explicitly chose httpOnly cookies |
| ofetch | axios | ofetch already configured; switching would be unnecessary churn |
| Vuetify form validation | VeeValidate | VeeValidate is more powerful but adds ~15KB; Vuetify built-in rules suffice for 2 forms |

## Package Legitimacy Audit

> No new packages are installed in this phase. All libraries are already present in the project's `package.json` and `composer.json`. This section is N/A.

## Architecture Patterns

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Browser (Vue 3 SPA)                         │
│                                                                     │
│  ┌──────────┐    ┌──────────────┐    ┌──────────────────────────┐  │
│  │ login.vue │───▶│ Pinia Auth   │───▶│ api.ts (ofetch)          │  │
│  │ (tab:     │    │ Store        │    │ withCredentials: true    │  │
│  │  login/   │    │ - user       │    │ baseURL: /api            │  │
│  │  register)│    │ - isLoading  │    │                          │  │
│  └──────────┘    │ - login()    │    └──────────┬───────────────┘  │
│                  │ - register() │               │                  │
│  ┌──────────┐   │ - logout()   │               │                  │
│  │ router/  │   │ - fetchUser()│               │                  │
│  │ index.ts │   └──────────────┘               │                  │
│  │ beforeEach│                                  │                  │
│  │ guard    │                                   ▼                  │
│  └──────────┘                                                       │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ App Shell (DefaultLayoutWithHorizontalNav)                  │   │
│  │ ┌────────────────────┐  ┌──────────────────────────────┐   │   │
│  │ │ Logo + Title       │  │ ThemeSwitch | UserProfile ▼  │   │   │
│  │ └────────────────────┘  └──────────────────────────────┘   │   │
│  │ ┌──────────────────────────────────────────────────────┐   │   │
│  │ │ <RouterView /> — Welcome Dashboard / Future Pages    │   │   │
│  │ └──────────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────┘   │
└────────────────────────────────┬────────────────────────────────────┘
                                 │ httpOnly session cookie (auto-sent)
                                 │ + X-XSRF-TOKEN header
                                 ▼
┌────────────────────────────────────────────────────────────────────┐
│                    Laravel 12 + Sanctum v4.3.1                     │
│                                                                    │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ EnsureFrontendRequestsAreStateful middleware                │  │
│  │ → EncryptCookies → AddQueuedCookies → StartSession         │  │
│  │ → ValidateCsrfToken → AuthenticateSession                  │  │
│  └──────────────────────────┬──────────────────────────────────┘  │
│                             ▼                                      │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ API Routes (routes/api.php)                                 │  │
│  │                                                             │  │
│  │ GET  /sanctum/csrf-cookie  ← Sets XSRF-TOKEN cookie        │  │
│  │ POST /api/auth/register    ← Auth::login($user) + PAT      │  │
│  │ POST /api/auth/login       ← Auth::attempt() + PAT         │  │
│  │ GET  /api/auth/me          ← auth:sanctum (session OR token)│  │
│  │ POST /api/auth/logout      ← Auth::logout() + delete PAT   │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                    │
│  ┌──────────────────────┐  ┌──────────────────────────────────┐   │
│  │ Session (database)   │  │ Personal Access Tokens (Flutter) │   │
│  │ httpOnly cookie      │  │ Bearer token                     │   │
│  └──────────────────────┘  └──────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────┘
```

### Recommended Project Structure

```
resources/ts/
├── pages/
│   ├── login.vue              # Modified: centered card + tab toggle (login/register)
│   ├── index.vue              # Modified: welcome dashboard with placeholders
│   └── [...error].vue         # Existing: error page
├── stores/
│   └── auth.ts                # NEW: Pinia auth store (user, login, register, logout, fetchUser)
├── layouts/
│   ├── default.vue            # Existing: switch to horizontal nav via themeConfig
│   ├── blank.vue              # Existing: used for login page
│   └── components/
│       ├── UserProfile.vue    # Modified: real user data, actual logout API call
│       └── DefaultLayoutWithHorizontalNav.vue  # Existing: used as app shell
├── utils/
│   └── api.ts                 # Modified: withCredentials + CSRF handling
├── plugins/
│   └── 1.router/
│       └── index.ts           # Modified: auth guard (beforeEach)
└── composables/               # NEW if needed
    └── useAuthRedirect.ts     # Optional: extract redirect logic

Backend (no new files needed):
├── app/Http/Controllers/
│   └── AuthController.php     # Modified: Auth::attempt() + Auth::login()
├── bootstrap/
│   └── app.php                # Modified: add stateful middleware to API
├── config/
│   └── sanctum.php            # Already configured; verify stateful domains
└── .env                       # Add SANCTUM_STATEFUL_DOMAINS
```

### Pattern 1: Sanctum SPA Cookie Auth Flow

**What:** The frontend uses httpOnly session cookies for authentication instead of Bearer tokens. The browser manages the cookie automatically — JavaScript never sees the session token.

**When to use:** All API requests from the Vue SPA.

**Flow:**
1. On app mount (or before first auth request): `GET /sanctum/csrf-cookie` — sets `XSRF-TOKEN` cookie
2. Login/Register: `POST /api/auth/login` with `withCredentials: true` — Laravel sets session cookie
3. Subsequent requests: browser auto-sends session cookie; ofetch sends `X-XSRF-TOKEN` header from cookie
4. `auth:sanctum` middleware authenticates via session for stateful requests, falls back to Bearer for non-stateful

**Backend middleware setup (bootstrap/app.php):**
```php
// Source: Verified in vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
    // ... existing aliases
})
```

**Frontend API client (api.ts):**
```typescript
// Source: Existing pattern in resources/ts/utils/api.ts — modified for cookie auth
import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',  // Sends cookies cross-origin (same-site in our case)
  headers: new Headers({
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',  // Required by Laravel for AJAX detection
  }),
  async onRequest({ options }) {
    // Read XSRF-TOKEN cookie and set as header for CSRF protection
    const xsrfToken = useCookie('XSRF-TOKEN').value
    if (xsrfToken)
      options.headers.set('X-XSRF-TOKEN', xsrfToken)
  },
})
```

### Pattern 2: Pinia Auth Store

**What:** Centralized auth state with login/register/logout actions and mount-time user check.

**When to use:** Every component that needs user data or auth actions.

```typescript
// Source: Pattern from existing @core/stores/config.ts + CONTEXT.md D-02
import { defineStore } from 'pinia'
import { $api } from '@/utils/api'

interface User {
  id: number
  email: string
  name: string
  preferred_language: string | null
  is_admin: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isLoading = ref(false)
  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      isLoading.value = true
      const response = await $api('/auth/me')
      user.value = response.user
    } catch {
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function login(email: string, password: string) {
    isLoading.value = true
    try {
      const response = await $api('/auth/login', {
        method: 'POST',
        body: { email, password },
      })
      user.value = response.user
    } finally {
      isLoading.value = false
    }
  }

  async function register(email: string, password: string) {
    isLoading.value = true
    try {
      const response = await $api('/auth/register', {
        method: 'POST',
        body: { email, password },
      })
      user.value = response.user
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await $api('/auth/logout', { method: 'POST' })
    } finally {
      user.value = null
      window.location.href = '/admin/login'
    }
  }

  return { user, isLoading, isAuthenticated, fetchUser, login, register, logout }
})
```

### Pattern 3: Router Auth Guard

**What:** Single `beforeEach` guard that checks auth state on navigation. Unauthenticated users are redirected to `/login?redirect=/intended-path`.

**When to use:** Every route navigation. Per CONTEXT.md D-02, this is a mount-time check, not per-route meta checks.

```typescript
// Source: Existing pattern in resources/ts/plugins/1.router/index.ts
// Added: beforeEach guard per CONTEXT.md D-02, D-09

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  // Public pages (login) — skip auth check
  if (to.meta.public)
    return true

  // If not authenticated, redirect to login with intended URL
  if (!authStore.isAuthenticated) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    }
  }
})
```

**Note:** The mount-time check (D-02) calls `fetchUser()` in `main.ts` or `App.vue` before the router resolves the first route. If 401, the user is redirected to login. The `beforeEach` guard handles subsequent navigations.

### Pattern 4: Login Page with Tab Toggle

**What:** Single `/login` page with VTabs to toggle between Login and Register forms. Centered card on blank layout.

**When to use:** The only auth page in the application.

```vue
<!-- Source: Existing resources/ts/pages/login.vue — restructured -->
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { emailValidator, requiredValidator } from '@core/utils/validators'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const activeTab = ref('login')
const isPasswordVisible = ref(false)

const loginForm = ref({ email: '', password: '', remember: false })
const registerForm = ref({ email: '', password: '' })

const loginFormRules = { email: [requiredValidator, emailValidator], password: [requiredValidator] }
const registerFormRules = { email: [requiredValidator, emailValidator], password: [requiredValidator] }

async function handleLogin() {
  await authStore.login(loginForm.value.email, loginForm.value.password)
  navigateAfterAuth()
}

async function handleRegister() {
  await authStore.register(registerForm.value.email, registerForm.value.password)
  navigateAfterAuth()
}

function navigateAfterAuth() {
  const redirect = (route.query.redirect as string) || '/'
  router.push(redirect)
}
</script>
```

### Pattern 5: App Mount Auth Check

**What:** On app mount, initialize CSRF cookie, then fetch user. If 401, redirect to login.

**When to use:** Once, at app startup.

```typescript
// Source: resources/ts/main.ts — add auth initialization
// Flow: CSRF cookie → fetchUser → if 401 → redirect to /login

import { $api } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

// In App.vue or main.ts after mount:
async function initAuth() {
  // Step 1: Get CSRF cookie
  await $api('/sanctum/csrf-cookie', { method: 'GET', baseURL: '' })

  // Step 2: Fetch authenticated user
  const authStore = useAuthStore()
  await authStore.fetchUser()
}
```

**CSRF cookie timing:** The optimal point is BEFORE the first authenticated API call. Calling it once at app mount (before `fetchUser()`) is the standard Sanctum SPA pattern. The `XSRF-TOKEN` cookie is then available for all subsequent requests.

### Anti-Patterns to Avoid

- **Storing auth token in localStorage:** XSS-vulnerable. CONTEXT.md D-01 explicitly chose httpOnly cookies.
- **Per-route auth meta checks:** CONTEXT.md D-02 specifies a single mount-time check, not `meta: { requiresAuth: true }` on every route.
- **Separate login and register pages:** CONTEXT.md D-07 requires a single page with tab toggle.
- **Client-side password validation matching backend rules:** Backend requires `min:3` (AuthController line 26). The existing `passwordValidator` in `@core/utils/validators.ts` requires uppercase+lowercase+digit+special+8chars — this is MUCH stricter than the backend. Use a simple `required` + `min:3` rule to match backend, or the form will reject valid passwords.
- **Hardcoding nav items for future features:** CONTEXT.md D-05 says no nav links in top bar for this phase. The horizontal nav items array should be empty or minimal.
- **Using `window.location.href` for SPA navigation after logout:** Use `router.push('/login')` instead. However, for full page reload after logout (to clear all client state), `window.location.href` is acceptable.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSRF protection | Custom CSRF token generation | Sanctum's `/sanctum/csrf-cookie` + `ValidateCsrfToken` middleware | Battle-tested, integrated with Laravel session |
| Session management | Custom cookie read/write for auth | Laravel session driver (database) + httpOnly cookies | httpOnly = XSS-proof; database driver = no filesystem issues |
| Form validation | Custom validation logic | Vuetify `:rules` prop with existing `@core/utils/validators` | Already integrated, consistent UX |
| HTTP client | Custom fetch wrapper | ofetch (already configured in `api.ts`) | Already set up, supports interceptors |
| State management | Custom reactive state | Pinia store | Already configured, devtools support |
| Route guarding | Manual route checking in components | Vue Router `beforeEach` guard | Centralized, runs before component mount |

**Key insight:** The existing `api.ts` uses `useCookie('accessToken')` to read a client-side cookie and attach it as Bearer. For Sanctum SPA mode, this entire pattern is replaced by `credentials: 'include'` — the browser handles the httpOnly session cookie automatically. Do NOT try to read or set the session cookie from JavaScript.

## Common Pitfalls

### Pitfall 1: CSRF Token Mismatch on First Request

**What goes wrong:** First POST request after page load returns 419 CSRF token mismatch.
**Why it happens:** The `XSRF-TOKEN` cookie hasn't been fetched yet, or the `X-XSRF-TOKEN` header isn't being sent.
**How to avoid:** Always call `GET /sanctum/csrf-cookie` before any mutating request. In practice, call it once at app mount before `fetchUser()`.
**Warning signs:** 419 status on login/register POST.

### Pitfall 2: `EnsureFrontendRequestsAreStateful` Not Applied

**What goes wrong:** API requests from the SPA don't get session cookies; `auth:sanctum` returns 401 even after login.
**Why it happens:** The middleware isn't in the API middleware stack. Currently NOT applied in `bootstrap/app.php`.
**How to avoid:** Add `EnsureFrontendRequestsAreStateful::class` to API middleware in `bootstrap/app.php`.
**Warning signs:** Login succeeds (200) but subsequent `/api/auth/me` returns 401.

### Pitfall 3: Stateful Domain Mismatch

**What goes wrong:** `EnsureFrontendRequestsAreStateful::fromFrontend()` returns false; request treated as third-party.
**Why it happens:** The request's `Referer` or `Origin` header doesn't match any domain in `sanctum.stateful` config.
**How to avoid:** Configure `SANCTUM_STATEFUL_DOMAINS` in `.env` to include the SPA's domain (e.g., `localhost:5173` for Vite dev, production domain for prod).
**Warning signs:** Session cookie not set in response; `auth:sanctum` falls back to Bearer token check.

### Pitfall 4: Password Validator Mismatch

**What goes wrong:** Frontend rejects valid passwords that backend accepts.
**Why it happens:** Existing `passwordValidator` in `@core/utils/validators.ts` requires 8+ chars with uppercase, lowercase, digit, special char. Backend `AuthController` only requires `min:3`.
**How to avoid:** Use simple `requiredValidator` + custom `min:3` rule for the auth forms. Do NOT use the existing `passwordValidator`.
**Warning signs:** User can register via API but frontend form shows validation error.

### Pitfall 5: Router Base Path Confusion

**What goes wrong:** Redirect after login goes to wrong URL or causes infinite loop.
**Why it happens:** Router uses `createWebHistory('/admin/')` — all routes are under `/admin/`. The `redirect` query param must preserve this prefix.
**How to avoid:** Use `to.fullPath` (which includes the base) for the redirect param. After auth, `router.push(redirect)` handles the base automatically.
**Warning signs:** Redirect loop between `/admin/login` and `/admin/`.

### Pitfall 6: `auth:sanctum` Guard Behavior with Session + Token

**What goes wrong:** After switching to cookie auth, Flutter mobile breaks or vice versa.
**Why it happens:** Both auth modes must coexist. The `auth:sanctum` guard checks session first (for stateful requests), then Bearer token.
**How to avoid:** Don't remove PAT creation from login/register — Flutter still needs the token. The session is created alongside it for SPA requests.
**Warning signs:** Flutter app stops working after web auth changes.

### Pitfall 7: Vite Dev Server CORS with Sanctum

**What goes wrong:** Cookies not sent/received between Vite dev server (port 5173) and Laravel (port 8000).
**Why it happens:** Different ports = different origins. Cookies require same-site or proper CORS + `withCredentials`.
**How to avoid:** In development, the SPA is served by Laravel via `laravel-vite-plugin` (same origin). The Vite dev server proxies through Laravel. If using separate servers, add `localhost:5173` to `SANCTUM_STATEFUL_DOMAINS` and configure CORS.
**Warning signs:** Cookies not set in dev tools; 401 on all API requests during development.

## Code Examples

### Backend: Modified AuthController Login (Session + Token)

```php
// Source: Existing app/Http/Controllers/AuthController.php — modified
use Illuminate\Support\Facades\Auth;

public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($validated)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = User::where('email', $validated['email'])->first();
    
    // Create PAT for Flutter compatibility (existing behavior)
    $token = $user->createToken('flutter')->plainTextToken;
    $subscription = $this->subscriptionService->resolveActiveSubscription($user);

    // Regenerate session to prevent session fixation
    $request->session()->regenerate();

    return response()->json([
        ...$this->authPayload($user, $subscription),
        'token' => $token,
    ]);
}
```

### Backend: Modified AuthController Register (Session + Token)

```php
public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'string', 'min:3'],
        'preferred_language' => $this->preferredLanguageRules(),
    ]);

    $user = User::create([
        'name' => explode('@', $validated['email'])[0],
        'email' => $validated['email'],
        'preferred_language' => $validated['preferred_language'] ?? null,
        'password' => $validated['password'],
    ]);

    $subscription = $this->subscriptionService->createDefaultSubscription($user, note: 'Assigned on registration.');

    // Log the user in (creates session for SPA)
    Auth::login($user);
    $request->session()->regenerate();

    $token = $user->createToken('flutter')->plainTextToken;

    return response()->json([
        ...$this->authPayload($user, $subscription),
        'token' => $token,
    ], 201);
}
```

### Backend: Modified AuthController Logout (Session + Token)

```php
public function logout(Request $request): JsonResponse
{
    $user = $this->authenticatedUser($request);
    $token = $user->currentAccessToken();

    // Revoke PAT if present (Flutter flow)
    if ($token instanceof PersonalAccessToken) {
        $token->delete();
    }

    // Invalidate session (SPA flow)
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logged out']);
}
```

### Backend: bootstrap/app.php Middleware

```php
// Source: Existing bootstrap/app.php — add stateful middleware
->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);

    $middleware->alias([
        'admin' => EnsureUserIsAdmin::class,
        'paid_subscription' => EnsureUserHasPaidSubscription::class,
    ]);
})
```

### Frontend: Modified api.ts (Cookie Auth)

```typescript
// Source: Existing resources/ts/utils/api.ts — modified for Sanctum SPA
import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',
  async onRequest({ options }) {
    const xsrfToken = useCookie('XSRF-TOKEN').value
    if (xsrfToken)
      options.headers.set('X-XSRF-TOKEN', xsrfToken)
    options.headers.set('Accept', 'application/json')
  },
  async onResponseError({ response }) {
    if (response.status === 401) {
      const authStore = useAuthStore()
      authStore.user = null
    }
  },
})
```

### Frontend: Login Page Template Structure

```vue
<!-- Source: Restructured from existing resources/ts/pages/login.vue -->
<template>
  <div class="auth-wrapper d-flex align-center justify-center bg-surface">
    <VCard max-width="450" class="pa-4">
      <VCardText class="text-center mb-4">
        <div class="d-flex align-center justify-center gap-x-3 mb-4">
          <VNodeRenderer :nodes="themeConfig.app.logo" />
          <h1 class="text-h3 font-weight-bold">{{ themeConfig.app.title }}</h1>
        </div>

        <VTabs v-model="activeTab" grow>
          <VTab value="login">Login</VTab>
          <VTab value="register">Register</VTab>
        </VTabs>
      </VCardText>

      <VCardText>
        <VWindow v-model="activeTab">
          <!-- Login Tab -->
          <VWindowItem value="login">
            <VForm @submit.prevent="handleLogin">
              <AppTextField v-model="loginForm.email" label="Email" type="email" :rules="loginFormRules.email" />
              <AppTextField v-model="loginForm.password" label="Password" :type="isPasswordVisible ? 'text' : 'password'" :rules="loginFormRules.password" :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'" @click:append-inner="isPasswordVisible = !isPasswordVisible" />
              <VBtn block type="submit" :loading="authStore.isLoading" class="mt-4">Login</VBtn>
            </VForm>
          </VWindowItem>

          <!-- Register Tab -->
          <VWindowItem value="register">
            <VForm @submit.prevent="handleRegister">
              <AppTextField v-model="registerForm.email" label="Email" type="email" :rules="registerFormRules.email" />
              <AppTextField v-model="registerForm.password" label="Password" :type="isPasswordVisible ? 'text' : 'password'" :rules="registerFormRules.password" :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'" @click:append-inner="isPasswordVisible = !isPasswordVisible" />
              <VBtn block type="submit" :loading="authStore.isLoading" class="mt-4">Register</VBtn>
            </VForm>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>
```

### Frontend: themeConfig.ts — Switch to Horizontal Nav

```typescript
// Source: Existing themeConfig.ts — change contentLayoutNav
export const { themeConfig, layoutConfig } = defineThemeConfig({
  app: {
    // ... existing config
    contentLayoutNav: AppContentLayoutNav.Horizontal,  // Changed from Vertical
  },
  // ... rest unchanged
})
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Sanctum v3 SPA auth | Sanctum v4 SPA auth (same flow, cleaner config) | 2024 | Middleware config moved to `config/sanctum.php` middleware array |
| Laravel 10 middleware registration | Laravel 11/12 `bootstrap/app.php` fluent config | 2024 | No more `Kernel.php`; middleware applied via `Application::configure()` |
| `unplugin-vue-router` 0.7 | 0.8.8 with `getPascalCaseRouteName` | Current | Route names are auto-generated as kebab-case from PascalCase |

**Deprecated/outdated:**
- `resources/ts/pages/login.vue` current split-layout design: being replaced with centered card per D-08
- `api.ts` current Bearer token pattern: being replaced with `credentials: 'include'` per D-01
- `UserProfile.vue` hardcoded "John Doe" / "Admin": must use real user data from auth store

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | The Vite dev server serves through Laravel (same origin) so cookies work without CORS config | Architecture | Medium — if separate origins, need CORS + `SANCTUM_STATEFUL_DOMAINS` with port 5173 |
| A2 | The `sessions` database table already exists (created by Laravel's default migration) | Backend | Low — verified in `0001_01_01_000000_create_users_table.php` line 30 |
| A3 | The `auth:sanctum` guard authenticates via session for stateful requests without code changes to the guard itself | Backend | Low — this is documented Sanctum behavior, verified in middleware source |

## Open Questions

1. **Vite dev server origin vs Laravel origin**
   - What we know: `vite.config.ts` configures dev server on port 5173 with `laravel-vite-plugin`. The plugin typically proxies through Laravel's server.
   - What's unclear: Whether in the actual dev workflow, the browser accesses the SPA at `localhost:8000` (Laravel) or `localhost:5173` (Vite). This affects `SANCTUM_STATEFUL_DOMAINS`.
   - Recommendation: Configure `SANCTUM_STATEFUL_DOMAINS` to include both `localhost:8000` and `localhost:5173` to cover both cases. The existing default in `config/sanctum.php` already includes `localhost:3000` and `127.0.0.1:8000`.

2. **`/api/user` vs `/api/auth/me` endpoint**
   - What we know: CONTEXT.md D-02 says "fetching `/api/user`" but the actual endpoint is `/api/auth/me`.
   - What's unclear: Whether D-02 literally means `/api/user` (a new endpoint) or is shorthand for the existing `/api/auth/me`.
   - Recommendation: Use the existing `/api/auth/me` endpoint. It returns the user data plus subscription and usage info. No need to create a new endpoint.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Laravel backend | ✓ | 8.3.30 | — |
| Node.js | Vite + Vue build | ✓ | 22.22.2 | — |
| npm | Package management | ✓ | 10.9.7 | — |
| Composer | PHP dependencies | ✓ | 2.7.1 | — |
| SQLite | Database (dev) | ✓ | Built-in | — |
| Laravel Sanctum | SPA auth | ✓ | v4.3.1 | — |
| Laravel Session (database) | Session storage | ✓ | sessions table in migration | — |

**Missing dependencies with no fallback:** None

**Missing dependencies with fallback:** None

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.5.3 (backend only) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter=AuthControllerTest` |
| Full suite command | `php artisan test` |
| Frontend tests | None — no vitest config exists |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| AUTH-01 | Login with email/password | Feature (backend) | `php artisan test --filter=AuthControllerTest::test_login_returns_token` | ✅ Existing |
| AUTH-01 | Login form submits correctly | Manual only | N/A — no frontend test infra | ❌ |
| AUTH-02 | Register creates account + auto-login | Feature (backend) | `php artisan test --filter=AuthControllerTest::test_register_creates_user_and_returns_token` | ✅ Existing |
| AUTH-02 | Register tab works, auto-login | Manual only | N/A — no frontend test infra | ❌ |
| AUTH-03 | Session persists across refresh | Feature (backend) | New test needed: session cookie set after login | ❌ Wave 0 |
| AUTH-04 | Logout invalidates session | Feature (backend) | New test needed: session invalidated after logout | ❌ Wave 0 |
| AUTH-04 | Logout redirects to login | Manual only | N/A — no frontend test infra | ❌ |
| AUTH-05 | Unauthenticated redirect to login | Manual only | N/A — router guard is frontend-only | ❌ |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=AuthControllerTest`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/SpaAuthTest.php` — covers AUTH-03 (session cookie after login), AUTH-04 (session invalidation on logout), and stateful middleware behavior
- [ ] New test: verify `EnsureFrontendRequestsAreStateful` is applied to API routes
- [ ] New test: verify login with stateful request sets session cookie
- [ ] New test: verify `/api/auth/me` authenticates via session cookie

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes | Laravel `Auth::attempt()` + bcrypt hashing + rate limiting (`throttle:auth`) |
| V3 Session Management | yes | Laravel database session driver + httpOnly + SameSite=Lax cookies |
| V4 Access Control | yes | `auth:sanctum` middleware on protected routes |
| V5 Input Validation | yes | Laravel `$request->validate()` on all auth endpoints |
| V6 Cryptography | no | No custom crypto; Laravel handles password hashing (bcrypt) |

### Known Threat Patterns for Laravel Sanctum SPA

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| XSS stealing session cookie | Information Disclosure | httpOnly cookie (Sanctum session cookies are httpOnly by default — verified in `EnsureFrontendRequestsAreStateful::configureSecureCookieSessions()`) |
| CSRF attack on auth endpoints | Tampering | Sanctum CSRF cookie + `ValidateCsrfToken` middleware + `X-XSRF-TOKEN` header |
| Session fixation | Spoofing | `$request->session()->regenerate()` after login (must be added to AuthController) |
| Brute force login | Denial of Service | `throttle:auth` middleware already applied to login/register routes |
| Session hijacking | Information Disclosure | `SameSite=Lax` cookie attribute; HTTPS in production (`SESSION_SECURE_COOKIE=true`) |

## Sources

### Primary (HIGH confidence)
- `vendor/laravel/sanctum/src/Http/Middleware/EnsureFrontendRequestsAreStateful.php` — Verified middleware pipeline and `fromFrontend()` domain matching logic
- `vendor/laravel/sanctum/src/Http/Middleware/AuthenticateSession.php` — Verified session password hash validation
- `config/sanctum.php` — Verified stateful domains config, middleware config, guards
- `config/session.php` — Verified session driver (database), cookie settings, httpOnly (true), SameSite (lax)
- `app/Http/Controllers/AuthController.php` — Verified current auth flow (PAT-only, no session creation)
- `bootstrap/app.php` — Verified `EnsureFrontendRequestsAreStateful` is NOT currently applied
- `resources/ts/utils/api.ts` — Verified current Bearer token pattern (to be replaced)
- `resources/ts/plugins/1.router/index.ts` — Verified router setup (no guards currently)
- `resources/ts/pages/login.vue` — Verified current split-layout design (to be restructured)
- `resources/ts/layouts/components/UserProfile.vue` — Verified hardcoded user data (to be dynamic)
- `resources/ts/layouts/components/DefaultLayoutWithHorizontalNav.vue` — Verified horizontal nav layout structure
- `themeConfig.ts` — Verified `contentLayoutNav: Vertical` (to be changed to Horizontal)
- `php artisan route:list` — Verified `/sanctum/csrf-cookie` route exists, auth routes confirmed

### Secondary (MEDIUM confidence)
- `composer show laravel/sanctum` — Verified v4.3.1 installed (released 2026-02-07)
- `database/migrations/0001_01_01_000000_create_users_table.php` — Verified sessions table creation at line 30

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all packages already installed, verified versions
- Architecture: HIGH — verified all source files, middleware behavior, route configuration
- Pitfalls: HIGH — identified by reading actual source code (e.g., password validator mismatch, missing stateful middleware)

**Research date:** 2026-06-08
**Valid until:** 2026-07-08 (stable stack, no fast-moving dependencies)
