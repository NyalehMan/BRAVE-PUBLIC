<template>
  <template v-if="isAuthPage">
    <router-view />
  </template>

  <template v-else>
    <div class="app-shell">
      <AppNavbar />

      <div class="app-body">
        <AppSidebar
          :collapsed="sidebarCollapsed"
          @toggle="toggleSidebar"
        />

        <main
          class="app-content"
          :class="{ expanded: sidebarCollapsed }"
        >
          <router-view />
          <AppFooter />
        </main>
      </div>
    </div>
  </template>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import AppNavbar from './components/AppNavbar.vue'
import AppSidebar from './components/AppSidebar.vue'
import AppFooter from './components/AppFooter.vue'

const route = useRoute()

// Sidebar is collapsed by default
const sidebarCollapsed = ref(true)

const isAuthPage = computed(() => {
  return ['login', 'verify-otp'].includes(route.name)
})

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value

  localStorage.setItem(
    'cw_sidebar_collapsed',
    sidebarCollapsed.value ? '1' : '0'
  )
}

onMounted(() => {
  // Always begin collapsed when the website is opened/refreshed
  sidebarCollapsed.value = true
  localStorage.setItem('cw_sidebar_collapsed', '1')

  const theme = localStorage.getItem('phoenixTheme') || 'light'

  document.documentElement.setAttribute(
    'data-bs-theme',
    theme
  )

  document.documentElement.setAttribute(
    'data-theme',
    theme
  )
})
</script>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--phoenix-body-bg, #f5f7fa);
}

.app-body {
  display: flex;
  width: 100%;
  min-height: calc(100vh - 72px);
}

.app-content {
  flex: 1;
  min-width: 0;
  width: 100%;
  padding: 1rem 1rem 0;
  overflow-x: hidden;

  display: flex;
  flex-direction: column;

  transition:
    padding 0.22s ease,
    width 0.22s ease;
}

.app-content.expanded {
  min-width: 0;
  width: calc(100% - 72px);
}

@media (max-width: 992px) {
  .app-content,
  .app-content.expanded {
    width: 100%;
  }
}

@media (max-width: 390px) {
  .app-content,
  .app-content.expanded {
    width: 100%;
    padding: 0.75rem 0.5rem 0;
  }
}
</style>