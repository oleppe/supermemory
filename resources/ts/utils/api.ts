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
      const { useAuthStore } = await import('@/stores/auth')
      const authStore = useAuthStore()
      authStore.user = null
    }
  },
})
