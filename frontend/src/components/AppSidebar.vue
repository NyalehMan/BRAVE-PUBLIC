<template>
  <aside class="app-sidebar" :class="{ collapsed }">
    <div class="sidebar-top">
      <button
        type="button"
        class="toggle-btn"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('toggle')"
      >
        <span v-if="collapsed">☰</span>
        <span v-else>←</span>
      </button>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
      <RouterLink
        to="/"
        class="nav-item"
        :class="{ active: route.path === '/' }"
        data-label="Home"
        aria-label="Home"
      >
        <FeatherIcon icon="home" />

        <span v-if="!collapsed" class="nav-label">
          Home
        </span>
      </RouterLink>

      <RouterLink
        to="/fire-public"
        class="nav-item"
        :class="{ active: route.path === '/fire-public' }"
        data-label="Fire Dashboard"
        aria-label="Fire Dashboard"
      >
        <FeatherIcon icon="alert-triangle" />

        <span v-if="!collapsed" class="nav-label">
          Fire Dashboard
        </span>
      </RouterLink>


      <!--
      <RouterLink
        to="/flood"
        class="nav-item"
        :class="{ active: route.path.startsWith('/flood') }"
        data-label="Flood Dashboard"
        aria-label="Flood Dashboard"
      >
        <FeatherIcon icon="droplet" />

        <span v-if="!collapsed" class="nav-label">
          Flood Dashboard
        </span>
      </RouterLink>
      -->
    </nav>
  </aside>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router'
import FeatherIcon from '@/components/FeatherIcon.vue'

defineProps({
  collapsed: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['toggle'])
const route = useRoute()
</script>

<style scoped>
.app-sidebar {
  position: sticky;
  top: 72px;
  z-index: 1050;
  width: 260px;
  min-width: 260px;
  height: calc(100vh - 72px);
  align-self: flex-start;
  display: flex;
  flex-direction: column;
  overflow: visible;
  border-right: 1px solid var(--phoenix-border-color);
  background: var(
    --phoenix-navbar-vertical-bg,
    var(--phoenix-card-bg, #fff)
  );
  transition:
    width 0.22s ease,
    min-width 0.22s ease,
    box-shadow 0.22s ease;
}

.app-sidebar.collapsed {
  width: 72px;
  min-width: 72px;
}

.sidebar-top {
  display: flex;
  justify-content: flex-end;
  padding: 14px 12px 8px;
}

.app-sidebar.collapsed .sidebar-top {
  justify-content: center;
}

.toggle-btn {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 12px;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-emphasis-color);
  font-size: 16px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.toggle-btn:hover {
  color: #facc15;
  border-color: #111827;
  background: #111827;
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.2);
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px 12px 16px;
  overflow: visible;
}

.nav-item {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 46px;
  padding: 0 14px;
  border-radius: 14px;
  color: var(--phoenix-body-color);
  text-decoration: none;
  transition:
    color 0.2s ease,
    background-color 0.2s ease,
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

/* Normal hover: dark background and white content */
.nav-item:hover {
  z-index: 10;
  color: #ffffff;
  background: #111827;
  transform: translateX(3px);
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.2);
}

/* Selected page: yellow background and dark content */
.nav-item.active {
  color: #111827;
  background: #facc15;
  box-shadow: 0 7px 16px rgba(250, 204, 21, 0.3);
}

/* Selected page hover: inverted colours */
.nav-item.active:hover {
  color: #facc15;
  background: #111827;
  box-shadow: 0 9px 20px rgba(15, 23, 42, 0.24);
}

.nav-item :deep(svg) {
  width: 19px;
  height: 19px;
  flex-shrink: 0;
  stroke: currentColor;
  stroke-width: 2;
  transition:
    transform 0.2s ease,
    filter 0.2s ease;
}

.nav-item:hover :deep(svg) {
  stroke: currentColor;
  transform: scale(1.16);
  filter: drop-shadow(0 4px 4px rgba(15, 23, 42, 0.18));
}

.nav-item.active :deep(svg) {
  stroke: currentColor;
}

.nav-label {
  overflow: hidden;
  white-space: nowrap;
}

/* Collapsed sidebar */
.app-sidebar.collapsed .nav-item {
  width: 48px;
  min-width: 48px;
  padding: 0;
  justify-content: center;
}

/* Floating icon effect */
.app-sidebar.collapsed .nav-item:hover {
  transform: translateX(5px) scale(1.05);
}

/* Floating page-name tooltip */
.app-sidebar.collapsed .nav-item::after {
  content: attr(data-label);
  position: absolute;
  top: 50%;
  left: calc(100% + 12px);
  z-index: 1200;
  padding: 9px 13px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 9px;
  background: #111827;
  color: #ffffff;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.24);
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  visibility: hidden;
  transform: translate(-6px, -50%);
  transition:
    opacity 0.18s ease,
    visibility 0.18s ease,
    transform 0.18s ease;
}

/* Tooltip pointer */
.app-sidebar.collapsed .nav-item::before {
  content: "";
  position: absolute;
  top: 50%;
  left: calc(100% + 6px);
  z-index: 1201;
  width: 11px;
  height: 11px;
  border-radius: 2px;
  background: #111827;
  pointer-events: none;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-50%) rotate(45deg);
  transition:
    opacity 0.18s ease,
    visibility 0.18s ease;
}

.app-sidebar.collapsed .nav-item:hover::after,
.app-sidebar.collapsed .nav-item:hover::before,
.app-sidebar.collapsed .nav-item:focus-visible::after,
.app-sidebar.collapsed .nav-item:focus-visible::before {
  opacity: 1;
  visibility: visible;
}

.app-sidebar.collapsed .nav-item:hover::after,
.app-sidebar.collapsed .nav-item:focus-visible::after {
  transform: translate(0, -50%);
}

.nav-item:focus-visible,
.toggle-btn:focus-visible {
  outline: 3px solid rgba(250, 204, 21, 0.55);
  outline-offset: 3px;
}

@media (max-width: 992px) {
  .app-sidebar {
    position: fixed;
    top: 72px;
    left: 0;
    z-index: 1100;
    height: calc(100vh - 72px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
  }
}

@media (max-width: 576px) {
  .app-sidebar,
  .app-sidebar.collapsed {
    display: none;
  }
}
</style>