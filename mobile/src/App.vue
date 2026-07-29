<template>
  <div class="mobile-app-shell">
    <MobileTopNav :is-dark-mode="isDarkMode" :notification-count="3" @toggle-theme="toggleTheme" />

    <main class="mobile-app-content">
      <router-view />
      <AppFooter />
    </main>

    <MobileBottomNav />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import MobileTopNav from '@/components/MobileTopNav.vue'
import MobileBottomNav from '@/components/MobileBottomNav.vue'
import AppFooter from '@/components/AppFooter.vue'

const isDarkMode = ref(false)

function applyTheme(theme) {
  document.documentElement.setAttribute('data-bs-theme', theme)
  document.documentElement.setAttribute('data-theme', theme)

  localStorage.setItem('phoenixTheme', theme)
  isDarkMode.value = theme === 'dark'
}

function toggleTheme() {
  applyTheme(isDarkMode.value ? 'light' : 'dark')
}

onMounted(() => {
  const theme = localStorage.getItem('phoenixTheme') || 'light'
  applyTheme(theme)
})
</script>

<style scoped>
.mobile-app-shell {
  min-height: 100vh;
  background: var(--phoenix-body-bg, #f5f7fb);
  color: var(--phoenix-body-color, #1f2937);
}

.mobile-app-content {
  min-height: 100vh;
  padding-top: 72px;
  padding-bottom: 82px;
  overflow-x: hidden;
}
</style>
