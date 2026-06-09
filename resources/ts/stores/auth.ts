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
  const isInitialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    isLoading.value = true
    try {
      const response = await $api('/auth/me')
      user.value = response.user
    }
    catch {
      user.value = null
    }
    finally {
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
    }
    finally {
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
    }
    finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await $api('/auth/logout', { method: 'POST' })
    }
    finally {
      user.value = null
    }
  }

  async function initAuth() {
    try {
      await $api('/sanctum/csrf-cookie', { method: 'GET', baseURL: '' })
      await fetchUser()
    }
    catch {
      // User will be redirected by router guard if not authenticated
    }
    finally {
      isInitialized.value = true
    }
  }

  return {
    user,
    isLoading,
    isInitialized,
    isAuthenticated,
    initAuth,
    fetchUser,
    login,
    register,
    logout,
  }
})
