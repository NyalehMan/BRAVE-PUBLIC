<template>
  <header class="mobile-topbar">
    <div class="brand-wrap">
      <img src="@/assets/niat-logo.png" alt="NIAT" class="niat-logo" style="height: 50px" />
    </div>

    <div class="topbar-actions">
      <button class="icon-btn" @click="$emit('toggle-theme')">
        <span v-if="isDarkMode">☀️</span>
        <span v-else>🌙</span>
      </button>
    </div>
  </header>
</template>

<script setup>
defineProps({
  isDarkMode: {
    type: Boolean,
    default: false,
  },

  notificationCount: {
    type: Number,
    default: 0,
  },
})

defineEmits(['toggle-theme'])
</script>

<style scoped>
.mobile-topbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;

  height: 64px;

  padding: env(safe-area-inset-top) 1rem 0;

  background: #d8ae00;

  display: flex;
  align-items: center;
  justify-content: center;

  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
}

.brand-wrap {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);

  display: flex;
  align-items: center;
  justify-content: center;
}

.niat-logo {
  height: 34px;
  width: auto;
  object-fit: contain;
}

.topbar-actions {
  position: absolute;
  right: 1rem;

  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.icon-btn {
  width: 42px;
  height: 42px;

  border: none;
  border-radius: 999px;

  background: rgba(255, 255, 255, 0.22);

  display: grid;
  place-items: center;

  color: #1f2937;

  font-size: 1rem;
  font-weight: 700;

  transition: all 0.2s ease;
}

.icon-btn:active {
  transform: scale(0.95);
}

.icon-btn:hover {
  background: rgba(255, 255, 255, 0.35);
}

.notification-btn {
  position: relative;
}

.notification-badge {
  position: absolute;

  top: -2px;
  right: -2px;

  min-width: 18px;
  height: 18px;

  border-radius: 999px;

  background: #dc3545;
  color: white;

  font-size: 0.65rem;
  font-weight: 900;

  display: flex;
  align-items: center;
  justify-content: center;

  border: 2px solid #d8ae00;
}

/* Dark mode */

:global(html[data-bs-theme='dark']) .mobile-topbar {
  background: #c89b00;
}

:global(html[data-bs-theme='dark']) .icon-btn {
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
}
</style>
