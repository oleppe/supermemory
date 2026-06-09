<script setup lang="ts">
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import { initConfigStore, useConfigStore } from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { $api } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()

const authStore = useAuthStore()

async function initAuth() {
  try {
    await $api('/sanctum/csrf-cookie', { method: 'GET', baseURL: '' })
    await authStore.fetchUser()
  }
  catch {
    // User will be redirected by router guard if not authenticated
  }
}

initAuth()
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
