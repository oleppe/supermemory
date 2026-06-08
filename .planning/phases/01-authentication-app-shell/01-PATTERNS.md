# Phase 1: Authentication & App Shell - Pattern Map

**Mapped:** 2026-06-08
**Files analyzed:** 14
**Analogs found:** 14 / 14

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `app/Http/Controllers/AuthController.php` | controller | request-response | itself (existing) | exact (modify) |
| `bootstrap/app.php` | config | request-response | itself (existing) | exact (modify) |
| `config/sanctum.php` | config | — | itself (existing) | exact (verify) |
| `.env` | config | — | itself (existing) | exact (modify) |
| `resources/ts/pages/login.vue` | component (page) | request-response | itself (existing) | exact (modify) |
| `resources/ts/pages/index.vue` | component (page) | request-response | itself (existing) | exact (modify) |
| `resources/ts/stores/auth.ts` | store | request-response | `resources/ts/@core/stores/config.ts` | role-match |
| `resources/ts/utils/api.ts` | utility | request-response | itself (existing) | exact (modify) |
| `resources/ts/plugins/1.router/index.ts` | config (plugin) | request-response | itself (existing) | exact (modify) |
| `resources/ts/layouts/components/UserProfile.vue` | component | request-response | itself (existing) | exact (modify) |
| `themeConfig.ts` | config | — | itself (existing) | exact (modify) |
| `resources/ts/navigation/horizontal/index.ts` | config | — | itself (existing) | exact (modify) |
| `resources/ts/App.vue` | component | request-response | itself (existing) | exact (modify) |
| `tests/Feature/SpaAuthTest.php` | test | request-response | `tests/Feature/AuthControllerTest.php` | role-match |

## Pattern Assignments

### `app/Http/Controllers/AuthController.php` (controller, request-response) — MODIFY

**Analog:** itself (lines 1-140)

**Current imports pattern** (lines 1-12):
```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\SupermemoryService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
```

**Add import for Auth facade:**
```php
use Illuminate\Support\Facades\Auth;
```

**Current login pattern** (lines 50-72) — replace `Hash::check` with `Auth::attempt` + session regeneration:
```php
public function login(Request $request): JsonResponse
{
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    // CHANGE: Use Auth::attempt() instead of manual Hash::check
    if (! Auth::attempt($validated)) {
        return response()->json([
            'message' => 'Invalid credentials',
        ], 401);
    }

    $user = User::where('email', $validated['email'])->first();

    $token = $user->createToken('flutter')->plainTextToken;
    $subscription = $this->subscriptionService->resolveActiveSubscription($user);

    // NEW: Regenerate session to prevent session fixation
    $request->session()->regenerate();

    return response()->json([
        ...$this->authPayload($user, $subscription),
        'token' => $token,
    ]);
}
```

**Current register pattern** (lines 22-48) — add `Auth::login()` + session regeneration after user creation:
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

    $subscription = $this->subscriptionService->createDefaultSubscription(
        $user,
        note: 'Assigned on registration.',
    );

    // NEW: Log the user in (creates session for SPA)
    Auth::login($user);
    $request->session()->regenerate();

    $token = $user->createToken('flutter')->plainTextToken;

    return response()->json([
        ...$this->authPayload($user, $subscription),
        'token' => $token,
    ], 201);
}
```

**Current logout pattern** (lines 105-114) — add session invalidation alongside PAT deletion:
```php
public function logout(Request $request): JsonResponse
{
    $user = $this->authenticatedUser($request);
    $token = $user->currentAccessToken();

    // Revoke PAT if present (Flutter flow — existing behavior)
    if ($token instanceof PersonalAccessToken) {
        $token->delete();
    }

    // NEW: Invalidate session (SPA flow)
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->json(['message' => 'Logged out']);
}
```

**Error handling pattern** (line 59-62): Returns 401 with `{'message': 'Invalid credentials'}` — keep this pattern.

**Key constraint:** Do NOT remove PAT creation (`$user->createToken('flutter')`) — Flutter mobile app still needs it (RESEARCH.md Pitfall 6).

---

### `bootstrap/app.php` (config, request-response) — MODIFY

**Analog:** itself (lines 1-24)

**Current pattern** (lines 16-21):
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'admin' => EnsureUserIsAdmin::class,
        'paid_subscription' => EnsureUserHasPaidSubscription::class,
    ]);
})
```

**Modified pattern** — prepend `EnsureFrontendRequestsAreStateful` to API middleware:
```php
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

**Why:** Without this middleware, API requests from the SPA won't receive session cookies, and `auth:sanctum` won't authenticate via session (RESEARCH.md Pitfall 2).

---

### `config/sanctum.php` (config) — VERIFY

**Analog:** itself (lines 1-84)

**Current stateful domains** (lines 18-23):
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort(),
))),
```

**Action:** No code change needed. The defaults already include `localhost` and `127.0.0.1:8000`. Add `SANCTUM_STATEFUL_DOMAINS` to `.env` for explicit configuration.

---

### `.env` (config) — MODIFY

**Analog:** itself (lines 1-92)

**Add after line 41** (after `SESSION_DOMAIN=null`):
```
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1
```

**Why:** Explicit config covers both Vite dev server (port 5173) and Laravel server (port 8000). Per RESEARCH.md Open Question 1.

---

### `resources/ts/pages/login.vue` (component/page, request-response) — MODIFY

**Analog:** itself (lines 1-185)

**Current definePage pattern** (lines 13-18) — KEEP unchanged:
```typescript
definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})
```

**Current form pattern** (lines 20-24) — replace with dual-form + tab state:
```typescript
const activeTab = ref('login')
const isPasswordVisible = ref(false)

const loginForm = ref({ email: '', password: '', remember: false })
const registerForm = ref({ email: '', password: '' })
```

**Validation pattern** — use existing validators from `@core/utils/validators` (lines 4-22 of validators.ts):
```typescript
import { emailValidator, requiredValidator } from '@core/utils/validators'

// IMPORTANT: Do NOT use passwordValidator (requires 8+ chars with uppercase/lowercase/digit/special)
// Backend only requires min:3. Use simple required + min-length rule:
const minPasswordLength = (v: string) => !!v && v.length >= 3 || 'Password must be at least 3 characters'

const loginFormRules = {
  email: [requiredValidator, emailValidator],
  password: [requiredValidator, minPasswordLength],
}
const registerFormRules = {
  email: [requiredValidator, emailValidator],
  password: [requiredValidator, minPasswordLength],
}
```

**Auth integration pattern** — use Pinia store:
```typescript
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

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
```

**Template restructure** — from split illustration+card (lines 38-181) to centered card with VTabs:
```vue
<template>
  <div class="auth-wrapper d-flex align-center justify-center bg-surface" style="min-height: 100vh;">
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
          <VWindowItem value="login">
            <VForm @submit.prevent="handleLogin">
              <!-- login fields -->
            </VForm>
          </VWindowItem>
          <VWindowItem value="register">
            <VForm @submit.prevent="handleRegister">
              <!-- register fields -->
            </VForm>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>
```

**Remove:** `AuthProvider` import (line 2), `useGenerateImageVariant` (line 3), all illustration/mask imports (lines 4-9), split-layout template (lines 38-181).

**Style** — keep existing (lines 183-185):
```scss
<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
```

---

### `resources/ts/pages/index.vue` (component/page, request-response) — MODIFY

**Analog:** itself (lines 1-25)

**Current pattern** — Vuexy placeholder cards. Replace with welcome dashboard:
```vue
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <h4 class="text-h4 mb-2">Welcome, {{ authStore.user?.name }}!</h4>
        <p class="text-body-1">Your document management workspace.</p>
      </VCardText>
    </VCard>

    <VRow>
      <VCol cols="12" md="6">
        <VCard title="Upload Documents" subtitle="Scan and upload new documents">
          <VCardText>
            <VBtn color="primary" disabled>Upload</VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard title="View Documents" subtitle="Browse your document library">
          <VCardText>
            <VBtn color="primary" disabled>View</VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
```

---

### `resources/ts/stores/auth.ts` (store, request-response) — NEW

**Analog:** `resources/ts/@core/stores/config.ts` (lines 1-82)

**Pinia store pattern** from analog (lines 1-7):
```typescript
// Analog: @core/stores/config.ts line 1-7
import { storeToRefs } from 'pinia'
import { useTheme } from 'vuetify'
import { cookieRef, useLayoutConfigStore } from '@layouts/stores/config'
import { themeConfig } from '@themeConfig'

export const useConfigStore = defineStore('config', () => {
```

**New auth store** follows same composition API style with `defineStore` + setup function:
```typescript
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
    }
  }

  return { user, isLoading, isAuthenticated, fetchUser, login, register, logout }
})
```

**Key patterns from analog to follow:**
- Composition API style (setup function, not options)
- `defineStore` is auto-imported (see `auto-imports.d.ts` line 48)
- `ref` and `computed` are auto-imported (Vue 3 reactivity)
- `useRoute` and `useRouter` are auto-imported (Vue Router)
- Two-space indentation, no semicolons (project convention from CONTEXT.md)

---

### `resources/ts/utils/api.ts` (utility, request-response) — MODIFY

**Analog:** itself (lines 1-10)

**Current pattern** (lines 1-10) — Bearer token via client-side cookie:
```typescript
import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken)
      options.headers.append('Authorization', `Bearer ${accessToken}`)
  },
})
```

**New pattern** — replace with Sanctum SPA cookie auth:
```typescript
import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',
  async onRequest({ options }) {
    const xsrfToken = useCookie('XSRF-TOKEN').value
    if (xsrfToken)
      options.headers.set('X-XSRF-TOKEN', xsrfToken)
    options.headers.set('Accept', 'application/json')
    options.headers.set('X-Requested-With', 'XMLHttpRequest')
  },
  async onResponseError({ response }) {
    if (response.status === 401) {
      // Lazy import to avoid circular dependency at module init
      const { useAuthStore } = await import('@/stores/auth')
      const authStore = useAuthStore()
      authStore.user = null
    }
  },
})
```

**Key changes:**
- Remove `useCookie('accessToken')` Bearer pattern entirely
- Add `credentials: 'include'` for httpOnly cookie auto-send
- Add `X-XSRF-TOKEN` header from `XSRF-TOKEN` cookie (CSRF protection)
- Add `Accept: application/json` and `X-Requested-With: XMLHttpRequest` (Laravel AJAX detection)
- Add 401 response interceptor to clear auth state

**Note:** `useCookie` is auto-imported from `@vueuse/core` (via Nuxt-style auto-imports).

---

### `resources/ts/plugins/1.router/index.ts` (config/plugin, request-response) — MODIFY

**Analog:** itself (lines 1-36)

**Current pattern** (lines 19-36) — router creation with no guards:
```typescript
const router = createRouter({
  history: createWebHistory('/admin/'),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

export { router }

export default function (app: App) {
  app.use(router)
}
```

**Add beforeEach guard** after router creation (before `export`):
```typescript
import { useAuthStore } from '@/stores/auth'

// ... existing router creation ...

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

export { router }
```

**Important:** The `beforeEach` guard relies on `fetchUser()` having been called at app mount (in `App.vue`). The guard itself does NOT call `fetchUser()` — it only checks the already-populated `isAuthenticated` state.

---

### `resources/ts/layouts/components/UserProfile.vue` (component, request-response) — MODIFY

**Analog:** itself (lines 1-130)

**Current pattern** (lines 1-3) — static import, hardcoded data:
```vue
<script setup lang="ts">
import avatar1 from '@images/avatars/avatar-1.png'
</script>
```

**New pattern** — dynamic user data from auth store + real logout:
```vue
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import avatar1 from '@images/avatars/avatar-1.png'

const authStore = useAuthStore()
const router = useRouter()

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>
```

**Template changes:**
- Replace hardcoded "John Doe" (line 51) with `{{ authStore.user?.name }}`
- Replace hardcoded "Admin" (line 53) with `{{ authStore.user?.email }}`
- Replace logout `<VListItem to="/login">` (line 114) with `@click="handleLogout"`:
```vue
<VListItem @click="handleLogout">
  <template #prepend>
    <VIcon class="me-2" icon="tabler-logout" size="22" />
  </template>
  <VListItemTitle>Logout</VListItemTitle>
</VListItem>
```

**Remove:** Profile, Settings, Pricing, FAQ menu items (lines 58-108) — not needed for Phase 1.

---

### `themeConfig.ts` (config) — MODIFY

**Analog:** itself (lines 1-68)

**Change line 13** from `Vertical` to `Horizontal`:
```typescript
// BEFORE (line 13):
contentLayoutNav: AppContentLayoutNav.Vertical,

// AFTER:
contentLayoutNav: AppContentLayoutNav.Horizontal,
```

This single change switches the entire app shell from vertical sidebar nav to horizontal top nav, per CONTEXT.md D-04.

---

### `resources/ts/navigation/horizontal/index.ts` (config) — MODIFY

**Analog:** itself (lines 1-12)

**Current pattern** (lines 1-12):
```typescript
export default [
  {
    title: 'Home',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Second page',
    to: { name: 'second-page' },
    icon: { icon: 'tabler-file' },
  },
]
```

**New pattern** — empty array (no nav links in Phase 1 per CONTEXT.md D-05):
```typescript
export default []
```

---

### `resources/ts/App.vue` (component, request-response) — MODIFY

**Analog:** itself (lines 1-26)

**Current pattern** (lines 1-15):
```vue
<script setup lang="ts">
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'

const { global } = useTheme()

initCore()
initConfigStore()

const configStore = useConfigStore()
</script>
```

**Add auth initialization** after existing init calls:
```vue
<script setup lang="ts">
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { $api } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const { global } = useTheme()

initCore()
initConfigStore()

const configStore = useConfigStore()
const authStore = useAuthStore()

// Auth initialization: CSRF cookie → fetch user
async function initAuth() {
  try {
    await $api('/sanctum/csrf-cookie', { method: 'GET', baseURL: '' })
    await authStore.fetchUser()
  } catch {
    // CSRF or fetch failed — user will be redirected by router guard
  }
}

initAuth()
</script>
```

**Why App.vue and not main.ts:** The auth store uses Pinia, which is registered via `registerPlugins(app)` in `main.ts` (line 14). The store can only be used AFTER plugins are registered. `App.vue`'s `<script setup>` runs after plugin registration, making it the correct place for auth initialization.

---

### `tests/Feature/SpaAuthTest.php` (test, request-response) — NEW

**Analog:** `tests/Feature/AuthControllerTest.php` (lines 1-226)

**Test structure pattern** from analog (lines 1-14):
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class SpaAuthTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;
```

**Test methods to create** (following analog's assertion patterns):

```php
// AUTH-03: Session cookie set after login
public function test_spa_login_sets_session_cookie(): void
{
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response = $this->withHeader('Origin', 'http://localhost')
        ->withHeader('Referer', 'http://localhost/')
        ->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

    $response->assertOk()
        ->assertCookieNotExpired('laravel_session');
}

// AUTH-03: Session authenticates /api/auth/me
public function test_spa_session_authenticates_me_endpoint(): void
{
    $user = User::factory()->create();

    // Login first to establish session
    $this->withHeader('Origin', 'http://localhost')
        ->withHeader('Referer', 'http://localhost/')
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    // Then access protected endpoint with session cookie
    $response = $this->withHeader('Origin', 'http://localhost')
        ->withHeader('Referer', 'http://localhost/')
        ->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.email', $user->email);
}

// AUTH-04: Logout invalidates session
public function test_spa_logout_invalidates_session(): void
{
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->withHeader('Origin', 'http://localhost')
        ->withHeader('Referer', 'http://localhost/')
        ->postJson('/api/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Logged out']);
}
```

**Key patterns from analog:**
- `postJson()` / `getJson()` for API requests (analog lines 17-18, 98-99)
- `assertStatus()`, `assertOk()`, `assertJson()` assertion chaining (analog lines 22-28)
- `User::factory()->create()` for test data (analog line 93-96)
- `actingAs($user)` for authenticated requests (analog line 140)
- `MocksSupermemoryService` trait for mocking (analog line 12)

---

## Shared Patterns

### Pinia Store Pattern (Composition API)
**Source:** `resources/ts/@core/stores/config.ts` lines 7-56
**Apply to:** `resources/ts/stores/auth.ts`
```typescript
// defineStore is auto-imported (auto-imports.d.ts line 48)
export const useConfigStore = defineStore('config', () => {
  const theme = cookieRef('theme', themeConfig.app.theme)
  // ... reactive state + computed + functions
  return { theme, isVerticalNavSemiDark, skin, /* ... */ }
})
```

### File-Based Routing with definePage()
**Source:** `resources/ts/pages/login.vue` lines 13-18
**Apply to:** `resources/ts/pages/index.vue`, any new page files
```typescript
definePage({
  meta: {
    layout: 'blank',    // or 'default' for authenticated pages
    public: true,       // skips auth guard
  },
})
```

### Auto-Imports Convention
**Source:** `auto-imports.d.ts` (lines 48, 415)
**Apply to:** All new TypeScript/Vue files
```typescript
// These are auto-imported — do NOT add explicit imports:
// - ref, computed, watch, onMounted, etc. (Vue reactivity)
// - defineStore (Pinia)
// - useRoute, useRouter (Vue Router)
// - useCookie, usePreferredColorScheme (@vueuse/core)
```

### Vuetify Form Components
**Source:** `resources/ts/pages/login.vue` lines 97-140
**Apply to:** Login and register forms
```vue
<AppTextField v-model="form.email" label="Email" type="email" :rules="rules.email" />
<AppTextField v-model="form.password" label="Password"
  :type="isPasswordVisible ? 'text' : 'password'"
  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
  @click:append-inner="isPasswordVisible = !isPasswordVisible"
  :rules="rules.password" />
<VBtn block type="submit" :loading="authStore.isLoading">Login</VBtn>
```

### SCSS Import Convention
**Source:** `resources/ts/pages/login.vue` lines 183-185
**Apply to:** All Vue files with scoped styles
```scss
<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
```

### Backend Controller Pattern
**Source:** `app/Http/Controllers/Controller.php` lines 8-16
**Apply to:** `AuthController.php` modifications
```php
abstract class Controller
{
    protected function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        return $user;
    }
}
```

### Laravel Validation Pattern
**Source:** `app/Http/Controllers/AuthController.php` lines 24-28
**Apply to:** All backend validation in this phase
```php
$validated = $request->validate([
    'email' => ['required', 'email', 'unique:users,email'],
    'password' => ['required', 'string', 'min:3'],
]);
```

---

## No Analog Found

Files with no close match in the codebase (planner should use RESEARCH.md patterns instead):

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| None | — | — | All files either modify existing files (self-analog) or have clear role-matches |

---

## Metadata

**Analog search scope:** `resources/ts/`, `app/Http/Controllers/`, `bootstrap/`, `config/`, `tests/Feature/`, `themeConfig.ts`
**Files scanned:** 20+ (all referenced files in CONTEXT.md and RESEARCH.md)
**Pattern extraction date:** 2026-06-08
