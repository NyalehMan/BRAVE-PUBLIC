<template>
  <header class="app-navbar">
    <div class="app-navbar-inner">
      <RouterLink
        to="/"
        class="brand-wrap text-decoration-none"
        aria-label="BRAVE Home"
      >
        <div class="brand-logo">
          <img
            src="/NiAT-light.png"
            alt="NIAT Logo"
            class="logo-img"
          />
        </div>
      </RouterLink>

      <div class="navbar-actions">
        <!-- Theme button -->
        <button
          type="button"
          class="icon-btn"
          :aria-label="
            isDark
              ? 'Switch to light mode'
              : 'Switch to dark mode'
          "
          :title="
            isDark
              ? 'Switch to light mode'
              : 'Switch to dark mode'
          "
          @click="toggleTheme"
        >
          <span v-if="isDark">☀️</span>
          <span v-else>🌙</span>
        </button>

        <!-- Mobile navigation -->
        <div
          ref="mobileMenuWrapper"
          class="mobile-only"
        >
          <button
            type="button"
            class="icon-btn mobile-menu-btn"
            :aria-expanded="menuOpen"
            :aria-label="
              menuOpen
                ? 'Close navigation menu'
                : 'Open navigation menu'
            "
            @click.stop="menuOpen = !menuOpen"
          >
            <FeatherIcon :icon="menuOpen ? 'x' : 'menu'" />
          </button>

          <div
            v-if="menuOpen"
            class="mobile-menu"
          >
            <div class="mobile-menu-header">
              <div>
                <h5>BRAVE Menu</h5>

                <p>
                  Emergency Monitoring Dashboard
                </p>
              </div>
            </div>

            <nav aria-label="Mobile navigation">
              <RouterLink
                to="/"
                class="mobile-menu-item"
                :class="{ active: route.path === '/' }"
                @click="menuOpen = false"
              >
                <FeatherIcon icon="home" />
                <span>Home</span>
              </RouterLink>

              <RouterLink
                to="/fire-public"
                class="mobile-menu-item"
                :class="{
                  active: route.path === '/fire-public',
                }"
                @click="menuOpen = false"
              >
                <FeatherIcon icon="alert-triangle" />
                <span>Fire Dashboard</span>
              </RouterLink>

              <RouterLink
                to="/fire-list"
                class="mobile-menu-item"
                :class="{
                  active: route.path.startsWith('/fire-list'),
                }"
                @click="menuOpen = false"
              >
                <FeatherIcon icon="list" />
                <span>Fire Incidents List</span>
              </RouterLink>

              <!-- Enable this when the Flood module is ready.

              <RouterLink
                to="/flood"
                class="mobile-menu-item"
                :class="{
                  active: route.path.startsWith('/flood'),
                }"
                @click="menuOpen = false"
              >
                <FeatherIcon icon="droplet" />
                <span>Flood Dashboard</span>
              </RouterLink>

              -->
            </nav>
          </div>
        </div>

        <!-- Notifications -->
        <div
          v-if="showNotificationButton"
          ref="notificationWrapper"
          class="navbar-notification-wrapper"
        >
          <button
            type="button"
            class="icon-btn navbar-notification-btn"
            title="Notifications"
            aria-label="Open notifications"
            :aria-expanded="showNotificationCenter"
            @click.stop="
              showNotificationCenter =
                !showNotificationCenter
            "
          >
            <FeatherIcon icon="bell" />

            <span
              v-if="
                notificationStore.unreadNotificationCount > 0
              "
              class="navbar-notification-badge"
            >
              {{
                notificationStore.unreadNotificationCount
              }}
            </span>
          </button>

          <div
            v-if="showNotificationCenter"
            class="navbar-notification-dropdown"
          >
            <div class="notification-header">
              <div>
                <h6>Notifications</h6>

                <small>
                  {{
                    notificationStore.unreadNotificationCount
                  }}
                  unread
                </small>
              </div>

              <button
                type="button"
                @click.stop="
                  notificationStore.markAllAsRead()
                "
              >
                Mark all
              </button>
            </div>

            <div class="notification-list">
              <div
                v-if="
                  notificationStore.notifications.length === 0
                "
                class="empty-notification"
              >
                No notifications yet.
              </div>

              <button
                v-for="notification in notificationStore.notifications"
                :key="notification.id"
                type="button"
                class="notification-item"
                :class="[
                  notification.severity,
                  {
                    unread: !notification.read,
                  },
                ]"
                @click.stop="
                  notificationStore.markAsRead(
                    notification.id,
                  )
                "
              >
                <div class="notification-icon">
                  <FeatherIcon :icon="notification.icon" />
                </div>

                <div class="notification-content">
                  <strong>
                    {{ notification.title }}
                  </strong>

                  <p>
                    {{ notification.message }}
                  </p>

                  <small>
                    {{ notification.time }}
                  </small>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'
import {
  RouterLink,
  useRoute,
} from 'vue-router'

import FeatherIcon from '@/components/FeatherIcon.vue'
import { useNotificationStore } from '@/stores/notificationStore'

const route = useRoute()
const notificationStore = useNotificationStore()

const menuOpen = ref(false)
const isDark = ref(false)
const showNotificationCenter = ref(false)

const mobileMenuWrapper = ref(null)
const notificationWrapper = ref(null)

const showNotificationButton = computed(() => {
  return route.meta.hideNotifications !== true
})

function applyTheme(dark) {
  const theme = dark ? 'dark' : 'light'

  isDark.value = dark
  localStorage.setItem('phoenixTheme', theme)

  document.documentElement.setAttribute(
    'data-bs-theme',
    theme,
  )

  document.documentElement.setAttribute(
    'data-theme',
    theme,
  )

  window.dispatchEvent(
    new CustomEvent('theme-changed', {
      detail: theme,
    }),
  )
}

function toggleTheme() {
  applyTheme(!isDark.value)
}

function handleClickOutside(event) {
  if (
    mobileMenuWrapper.value &&
    !mobileMenuWrapper.value.contains(event.target)
  ) {
    menuOpen.value = false
  }

  if (
    notificationWrapper.value &&
    !notificationWrapper.value.contains(event.target)
  ) {
    showNotificationCenter.value = false
  }
}

function handleEscape(event) {
  if (event.key === 'Escape') {
    menuOpen.value = false
    showNotificationCenter.value = false
  }
}

watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
    showNotificationCenter.value = false
  },
)

onMounted(() => {
  const savedTheme =
    localStorage.getItem('phoenixTheme') || 'light'

  applyTheme(savedTheme === 'dark')

  document.addEventListener(
    'click',
    handleClickOutside,
  )

  document.addEventListener(
    'keydown',
    handleEscape,
  )
})

onBeforeUnmount(() => {
  document.removeEventListener(
    'click',
    handleClickOutside,
  )

  document.removeEventListener(
    'keydown',
    handleEscape,
  )
})
</script>

<style scoped>
/* ================= NAVBAR ================= */

.app-navbar {
  position: sticky;
  top: 0;
  z-index: 1030;
  padding: 0 24px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  background: var(--brave-brand-yellow, #ffc107);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.app-navbar-inner {
  width: 100%;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

/* ================= BRAND ================= */

.brand-wrap {
  display: flex;
  align-items: center;
}

.logo-img {
  display: block;
  width: auto;
  height: 45px;
  padding-left: 20px;
}

/* ================= ACTIONS ================= */

.navbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.icon-btn {
  width: 42px;
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.38);
  color: var(--brave-brand-text, #1f2937);
  cursor: pointer;
  transition:
    transform 0.2s ease,
    background-color 0.2s ease,
    color 0.2s ease;
}

.icon-btn:hover {
  background: rgba(255, 255, 255, 0.65);
  transform: translateY(-1px);
}

.icon-btn :deep(svg) {
  width: 20px;
  height: 20px;
  stroke-width: 2.2;
}

/* ================= MOBILE MENU ================= */

.mobile-only {
  position: relative;
  display: none;
}

.mobile-menu-btn :deep(svg) {
  width: 22px;
  height: 22px;
}

.mobile-menu {
  position: fixed;
  top: 76px;
  right: 16px;
  bottom: 16px;
  z-index: 1600;
  width: min(340px, calc(100vw - 32px));
  padding: 1.25rem;
  overflow-y: auto;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  background: var(--phoenix-card-bg, #fff);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.22);
}

.mobile-menu-header {
  margin-bottom: 1.25rem;
}

.mobile-menu-header h5 {
  margin: 0;
  color: var(--phoenix-heading-color);
  font-size: 1.15rem;
  font-weight: 800;
}

.mobile-menu-header p {
  margin: 0.25rem 0 0;
  color: var(--phoenix-secondary-color);
  font-size: 0.9rem;
}

.mobile-menu-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.4rem;
  padding: 0.95rem 0.75rem;
  border-radius: 0.875rem;
  color: var(--phoenix-body-color);
  font-weight: 700;
  text-decoration: none;
  transition:
    background-color 0.2s ease,
    color 0.2s ease;
}

.mobile-menu-item :deep(svg) {
  width: 18px;
  height: 18px;
}

.mobile-menu-item:hover,
.mobile-menu-item.active {
  background: rgba(255, 193, 7, 0.2);
  color: #9a6700;
}

/* ================= NOTIFICATIONS ================= */

.navbar-notification-wrapper {
  position: relative;
}

.navbar-notification-btn {
  position: relative;
}

.navbar-notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 800;
}

.navbar-notification-dropdown {
  position: absolute;
  top: 52px;
  right: 0;
  z-index: 1700;
  width: 380px;
  max-width: calc(100vw - 2rem);
  overflow: hidden;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 18px;
  background: var(--phoenix-card-bg, #fff);
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
}

.notification-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.notification-header h6 {
  margin: 0;
  color: var(--phoenix-heading-color);
  font-weight: 800;
}

.notification-header small {
  color: var(--phoenix-secondary-color);
}

.notification-header button {
  border: 0;
  background: transparent;
  color: #2563eb;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}

.notification-list {
  max-height: 420px;
  overflow-y: auto;
  padding: 0.75rem;
}

.empty-notification {
  padding: 1rem;
  color: var(--phoenix-secondary-color);
  text-align: center;
}

.notification-item {
  width: 100%;
  display: flex;
  gap: 0.75rem;
  margin-bottom: 0.65rem;
  padding: 0.85rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 14px;
  background: var(--phoenix-card-bg, #fff);
  color: var(--phoenix-body-color);
  text-align: left;
  cursor: pointer;
}

.notification-item.unread {
  border-color: #93c5fd;
  background: var(--phoenix-body-bg, #f8fafc);
}

.notification-item.high {
  border-left: 4px solid #dc2626;
}

.notification-item.medium {
  border-left: 4px solid #f59e0b;
}

.notification-item.low {
  border-left: 4px solid #22c55e;
}

.notification-icon {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: 12px;
  background: #eef2ff;
  color: #2563eb;
}

.notification-icon :deep(svg) {
  width: 18px;
  height: 18px;
}

.notification-content strong {
  display: block;
  color: var(--phoenix-heading-color);
  font-size: 0.9rem;
}

.notification-content p {
  margin: 0.2rem 0;
  color: var(--phoenix-body-color);
  font-size: 0.82rem;
}

.notification-content small {
  color: var(--phoenix-secondary-color);
  font-size: 0.75rem;
}

/* ================= DARK MODE ================= */

[data-bs-theme='dark'] .app-navbar {
  border-bottom-color: #24324a;
  background: #0b1220;
}

[data-bs-theme='dark'] .icon-btn {
  border-color: #2a3a56;
  background: #111b2e;
  color: #8fb8ff;
}

[data-bs-theme='dark'] .icon-btn:hover {
  background: #192740;
}

/* ================= MOBILE ================= */

@media (max-width: 576px) {
  .app-navbar {
    padding: 0 16px;
  }

  .app-navbar-inner {
    height: 64px;
  }

  .logo-img {
    height: 42px;
    padding-left: 0;
  }

  .mobile-only {
    display: block;
  }

  .mobile-menu {
    top: 72px;
  }
}
</style>