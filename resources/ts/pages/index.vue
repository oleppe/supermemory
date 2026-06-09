<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { themeConfig } from '@themeConfig'

const authStore = useAuthStore()
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <h4 class="text-h4 mb-2">
          Welcome to {{ themeConfig.app.title }}
        </h4>
        <p class="text-body-1">
          Your document management workspace.
        </p>
      </VCardText>
    </VCard>

    <VRow>
      <VCol cols="12" md="6">
        <VCard title="Upload Documents" subtitle="Scan and upload new documents">
          <VCardText>
            <VBtn color="primary" disabled>
              Upload
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard title="View Documents" subtitle="Browse your document library">
          <VCardText>
            <VBtn color="primary" disabled>
              View
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
