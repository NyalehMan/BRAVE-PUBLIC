<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCurrentUser,
  logout,
} from '@/api/authApi'

const router = useRouter()

const user = ref(null)
const loading = ref(true)
const loggingOut = ref(false)
const errorMessage = ref('')

onMounted(async () => {
  try {
    const response = await getCurrentUser()

    user.value = response.user ?? response
  } catch {
    await router.replace({ name: 'niat-login' })
  } finally {
    loading.value = false
  }
})

async function signOut() {
  errorMessage.value = ''
  loggingOut.value = true

  try {
    await logout()
    await router.replace({ name: 'niat-login' })
  } catch {
    errorMessage.value =
      'Unable to sign out. Please refresh the page and try again.'
  } finally {
    loggingOut.value = false
  }
}
</script>

<template>
  <main class="reviewer-page">
    <div v-if="loading" class="loading-message">
      Checking reviewer access…
    </div>

    <section v-else-if="user" class="reviewer-card">
      <header class="reviewer-header">
        <div>
          <p class="brand-name">BRAVE</p>
          <p class="portal-name">NIAT Reviewer Portal</p>
        </div>

        <button
          type="button"
          class="logout-button"
          :disabled="loggingOut"
          @click="signOut"
        >
          {{ loggingOut ? 'Signing out…' : 'Sign out' }}
        </button>
      </header>

      <div class="reviewer-content">
        <div class="status-icon">✓</div>

        <h1>Login successful</h1>

        <p class="welcome-message">
          Welcome,
          <strong>{{ user.name || 'NIAT Reviewer' }}</strong>.
        </p>

        <p v-if="user.email" class="user-email">
          {{ user.email }}
        </p>

        <div
          v-if="errorMessage"
          class="error-message"
          role="alert"
        >
          {{ errorMessage }}
        </div>

        <div class="notice">
          Your reviewer session is active. The incident-review
          dashboard will be added here next.
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.reviewer-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  box-sizing: border-box;
  padding: 24px;
  background:
    radial-gradient(
      circle at top right,
      rgba(220, 38, 38, 0.2),
      transparent 35%
    ),
    linear-gradient(135deg, #111827, #030712);
  font-family: Inter, Arial, sans-serif;
}

.reviewer-card {
  width: min(100%, 760px);
  overflow: hidden;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.reviewer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 22px 26px;
  color: #ffffff;
  background: #991b1b;
}

.brand-name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.portal-name {
  margin: 4px 0 0;
  font-size: 13px;
  opacity: 0.85;
}

.logout-button {
  padding: 10px 16px;
  border: 1px solid rgba(255, 255, 255, 0.55);
  border-radius: 8px;
  background: transparent;
  color: #ffffff;
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

.logout-button:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.12);
}

.logout-button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.reviewer-content {
  padding: 48px 30px;
  text-align: center;
}

.status-icon {
  width: 64px;
  height: 64px;
  display: grid;
  place-items: center;
  margin: 0 auto 18px;
  border-radius: 50%;
  background: #dcfce7;
  color: #15803d;
  font-size: 32px;
  font-weight: 800;
}

.reviewer-content h1 {
  margin: 0;
  color: #111827;
  font-size: 29px;
}

.welcome-message {
  margin: 12px 0 0;
  color: #4b5563;
  font-size: 16px;
}

.user-email {
  margin: 5px 0 0;
  color: #6b7280;
  font-size: 14px;
}

.notice {
  max-width: 520px;
  margin: 30px auto 0;
  padding: 18px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f9fafb;
  color: #4b5563;
  font-size: 14px;
  line-height: 1.6;
}

.error-message {
  max-width: 520px;
  margin: 22px auto 0;
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 14px;
}

.loading-message {
  color: #ffffff;
  font-size: 16px;
}
</style>
