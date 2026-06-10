---
phase: 01-authentication-app-shell
reviewed: 2026-06-09T00:00:00Z
depth: deep
files_reviewed: 13
files_reviewed_list:
  - app/Http/Controllers/AuthController.php
  - bootstrap/app.php
  - .env
  - tests/Feature/SpaAuthTest.php
  - resources/ts/utils/api.ts
  - resources/ts/stores/auth.ts
  - resources/ts/plugins/1.router/index.ts
  - resources/ts/App.vue
  - themeConfig.ts
  - resources/ts/navigation/horizontal/index.ts
  - resources/ts/pages/login.vue
  - resources/ts/pages/index.vue
  - resources/ts/layouts/components/UserProfile.vue
findings:
  critical: 1
  warning: 3
  info: 3
  total: 7
status: issues_found
---

# Phase 1: Code Review Report

**Reviewed:** 2026-06-09
**Depth:** deep
**Files Reviewed:** 13
**Status:** issues_found

## Summary

Phase 1 wires Sanctum SPA cookie authentication into the Vue frontend, adds a router auth guard, and restructures the login page with a tabbed login/register form. The backend changes (session login/logout alongside existing PAT flow) are well-structured and the test coverage is solid. However, there is a **critical race condition** in the frontend auth initialization that will cause every authenticated page refresh to redirect to `/login`. Several warnings around error handling and token accumulation also need attention.

## Critical Issues

### CR-01: Router guard races against async auth init — all page refreshes redirect to /login

**File:** `resources/ts/plugins/1.router/index.ts:33-45` + `resources/ts/App.vue:20-30`
**Issue:** `App.vue` calls `initAuth()` (fire-and-forget) during component setup. This starts an async sequence: fetch CSRF cookie → fetch `/auth/me` → set `user`. The router's `beforeEach` guard checks `authStore.isAuthenticated` synchronously. On any page refresh or direct navigation to a protected route, the guard executes **before** `fetchUser()` resolves, sees `user === null`, and redirects to `/login` — even for fully authenticated users with valid sessions.

Execution timeline:
1. `registerPlugins(app)` installs router (no navigation yet)
2. `app.mount('#app')` → `App.vue` setup → `initAuth()` starts (async, not awaited)
3. `<RouterView>` renders → triggers initial navigation → `beforeEach` fires
4. `authStore.isAuthenticated` is `false` (fetch still in-flight) → guard redirects to `/login`

This makes the app completely unusable for authenticated users — every refresh or direct URL entry kicks them to the login page.

**Fix:** Expose a readiness promise from the auth store and await it in the guard before making auth decisions:

```ts
// resources/ts/stores/auth.ts
const authReady = ref(false)

async function fetchUser() {
  isLoading.value = true
  try {
    const response = await $api('/auth/me')
    user.value = response.user
  } catch {
    user.value = null
  } finally {
    isLoading.value = false
    authReady.value = true
  }
}

// ... return authReady alongside other exports
```

```ts
// resources/ts/plugins/1.router/index.ts
router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (to.meta.public)
    return true

  // Wait for initial auth check to complete
  if (!authStore.authReady) {
    // Poll until ready (or use a promise-based approach)
    while (!authStore.authReady)
      await new Promise(resolve => setTimeout(resolve, 25))
  }

  if (!authStore.isAuthenticated) {
    return {
      path: '/login',
      query: { redirect: to.fullPath },
    }
  }
})
```

A cleaner alternative is to store the `initAuth()` promise and `await` it directly in the guard.

## Warnings

### WR-01: 401 response handler clears user but never redirects to /login

**File:** `resources/ts/utils/api.ts:14-20`
**Issue:** When any API call returns 401 (e.g., session expired while the tab is open), `onResponseError` sets `authStore.user = null` but does not navigate to `/login`. The user remains on the current page in a broken state — the UI may show empty data, and subsequent API calls will also fail. The router guard only fires on navigation, so the user won't be redirected until they click a link.

**Fix:**
```ts
async onResponseError({ response }) {
  if (response.status === 401) {
    const { useAuthStore } = await import('@/stores/auth')
    const authStore = useAuthStore()
    authStore.user = null
    // Redirect to login if not already there
    const { default: router } = await import('@/plugins/1.router')
    // Note: router is exported as named export, adjust import accordingly
  }
}
```
Alternatively, watch `authStore.isAuthenticated` in `App.vue` and redirect when it transitions from `true` to `false`.

### WR-02: handleLogout() silently fails to redirect if API call throws

**File:** `resources/ts/layouts/components/UserProfile.vue:8-11`
**Issue:** `handleLogout()` awaits `authStore.logout()` then calls `router.push('/login')`. If the `$api('/auth/logout')` call throws (network error, server error), the error propagates out of `authStore.logout()` (its `finally` block runs, clearing `user`, but the error is not caught). The `router.push('/login')` line is never reached. The user is logged out client-side but stuck on the current page with no feedback.

**Fix:**
```ts
async function handleLogout() {
  try {
    await authStore.logout()
  } catch {
    // Server-side logout failed, but client-side user is already cleared
  }
  router.push('/login')
}
```

### WR-03: Every SPA login/register creates an orphaned Sanctum PAT

**File:** `app/Http/Controllers/AuthController.php:49,76`
**Issue:** Both `login()` (line 76) and `register()` (line 49) unconditionally call `$user->createToken('flutter')`, creating a new `personal_access_tokens` row on every authentication. The SPA uses cookie/session auth and never uses these tokens. Over time this accumulates orphaned tokens in the database with no expiration (`sanctum.expiration` is `null`). The token is returned in the JSON response but the frontend ignores it.

**Fix:** Only create the PAT when explicitly requested (e.g., a query parameter or separate endpoint for mobile clients):
```php
// In login():
if ($request->boolean('create_token')) {
    $token = $user->createToken('flutter')->plainTextToken;
}

return response()->json([
    ...$this->authPayload($user, $subscription),
    'token' => $token ?? null,
]);
```

## Info

### IN-01: Invalid `autocomplete` attribute value on password fields

**File:** `resources/ts/pages/login.vue:118,166`
**Issue:** Both password fields use `autocomplete="password"`, which is not a valid value per the HTML spec. Browsers may ignore it, defeating password manager integration.

**Fix:** Use `autocomplete="current-password"` for the login form and `autocomplete="new-password"` for the registration form.

### IN-02: minPasswordLength validator relies on implicit operator precedence

**File:** `resources/ts/pages/login.vue:25`
**Issue:** The expression `!!v && v.length >= 3 || 'Password must be at least 3 characters'` works correctly due to `&&` binding tighter than `||`, but the lack of parentheses makes it easy to misread or introduce bugs during future edits.

**Fix:**
```ts
const minPasswordLength = (v: string) => (!!v && v.length >= 3) || 'Password must be at least 3 characters'
```

### IN-03: .env file contains hardcoded secrets (not a phase change)

**File:** `.env:3,17-19,35,81,87`
**Issue:** The `.env` file contains real credentials: `APP_KEY`, `DB_PASSWORD`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `SUPERMEMORY_API_KEY`, `GEMINI_API_KEY`. While `.env` is correctly listed in `.gitignore` and is not tracked by git, this file was listed in the review scope. If these are production or shared credentials, they should be rotated and stored in a secrets manager. The `.env.example` file should be used for documentation with placeholder values.

**Fix:** Ensure `.env` is never committed. Rotate any credentials that may have been exposed. Use `.env.example` with placeholder values for documentation.

---

_Reviewed: 2026-06-09_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_
