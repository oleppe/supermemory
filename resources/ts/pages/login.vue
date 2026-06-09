<script setup lang="ts">
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'
import { emailValidator, requiredValidator } from '@core/utils/validators'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

const activeTab = ref('login')
const isPasswordVisible = ref(false)

const loginForm = ref({ email: '', password: '', remember: false })
const registerForm = ref({ email: '', password: '' })

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

// Password validation matches backend (min:3) — do NOT use passwordValidator from @core/utils/validators
const minPasswordLength = (v: string) => !!v && v.length >= 3 || 'Password must be at least 3 characters'

const loginFormRules = {
  email: [requiredValidator, emailValidator],
  password: [requiredValidator, minPasswordLength],
}

const registerFormRules = {
  email: [requiredValidator, emailValidator],
  password: [requiredValidator, minPasswordLength],
}

const loginError = ref('')
const registerError = ref('')

function navigateAfterAuth() {
  const redirect = (route.query.redirect as string) || '/'
  router.push(redirect)
}

async function handleLogin() {
  loginError.value = ''
  try {
    await authStore.login(loginForm.value.email, loginForm.value.password)
    navigateAfterAuth()
  }
  catch (e: any) {
    loginError.value = e.response?._data?.message || 'Login failed'
  }
}

async function handleRegister() {
  registerError.value = ''
  try {
    await authStore.register(registerForm.value.email, registerForm.value.password)
    navigateAfterAuth()
  }
  catch (e: any) {
    registerError.value = e.response?._data?.message || 'Registration failed'
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center bg-surface" style="min-height: 100vh;">
    <VCard max-width="450" class="pa-6">
      <!-- Logo + Title -->
      <div class="d-flex align-center justify-center gap-x-3 mb-4">
        <VNodeRenderer :nodes="themeConfig.app.logo" />
        <h1 class="text-h3 font-weight-bold">
          {{ themeConfig.app.title }}
        </h1>
      </div>

      <!-- Heading -->
      <div class="text-center mb-4">
        <h4 class="text-h4 mb-1">
          Welcome to {{ themeConfig.app.title }}! 👋🏻
        </h4>
        <p class="text-body-1 mb-0">
          Please sign-in to your account and start the adventure
        </p>
      </div>

      <!-- Tabs -->
      <VTabs v-model="activeTab" grow>
        <VTab value="login">Login</VTab>
        <VTab value="register">Register</VTab>
      </VTabs>

      <VCardText>
        <VWindow v-model="activeTab">
          <!-- Login Tab -->
          <VWindowItem value="login">
            <VForm @submit.prevent="handleLogin">
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="loginForm.email"
                    autofocus
                    label="Email"
                    type="email"
                    placeholder="johndoe@email.com"
                    :rules="loginFormRules.email"
                  />
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="loginForm.password"
                    label="Password"
                    placeholder="············"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    autocomplete="password"
                    :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                    :rules="loginFormRules.password"
                  />
                </VCol>

                <VCol cols="12">
                  <div class="d-flex align-center flex-wrap justify-space-between">
                    <VCheckbox v-model="loginForm.remember" label="Remember me" />
                    <a class="text-primary" href="javascript:void(0)">Forgot Password?</a>
                  </div>
                </VCol>

                <VCol v-if="loginError" cols="12">
                  <VAlert type="error" variant="tonal">{{ loginError }}</VAlert>
                </VCol>

                <VCol cols="12">
                  <VBtn block type="submit" :loading="authStore.isLoading" class="mt-4">
                    Login
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VWindowItem>

          <!-- Register Tab -->
          <VWindowItem value="register">
            <VForm @submit.prevent="handleRegister">
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="registerForm.email"
                    autofocus
                    label="Email"
                    type="email"
                    placeholder="johndoe@email.com"
                    :rules="registerFormRules.email"
                  />
                </VCol>

                <VCol cols="12">
                  <AppTextField
                    v-model="registerForm.password"
                    label="Password"
                    placeholder="············"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    autocomplete="password"
                    :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                    :rules="registerFormRules.password"
                  />
                </VCol>

                <VCol v-if="registerError" cols="12">
                  <VAlert type="error" variant="tonal">{{ registerError }}</VAlert>
                </VCol>

                <VCol cols="12">
                  <VBtn block type="submit" :loading="authStore.isLoading" class="mt-4">
                    Register
                  </VBtn>
                </VCol>
              </VRow>
            </VForm>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
