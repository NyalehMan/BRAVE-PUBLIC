<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getCurrentUser, login } from '@/api/authApi'

const router = useRouter()
const route = useRoute()

function destinationAfterLogin() {
  const redirect = route.query.redirect

  if (
    typeof redirect === 'string' &&
    redirect.startsWith('/') &&
    !redirect.startsWith('//')
  ) {
    return redirect
  }

  return { name: 'niat-reviewer' }
}

const email = ref('')
const password = ref('')
const loading = ref(false)
const checkingSession = ref(true)
const errorMessage = ref('')

onMounted(async () => {
  try {
    await getCurrentUser()
    await router.replace(destinationAfterLogin())
  } catch {
    // No active login session.
  } finally {
    checkingSession.value = false
  }
})

async function submitLogin() {
  errorMessage.value = ''
  loading.value = true

  try {
    await login({
      email: email.value,
      password: password.value,
    })

    await router.replace(destinationAfterLogin())
  } catch (error) {
    const status = error.response?.status

    if (status === 422) {
      errorMessage.value =
        error.response?.data?.errors?.email?.[0] ||
        'The email or password is incorrect.'
    } else if (status === 403) {
      errorMessage.value =
        'This account does not have NIAT reviewer access.'
    } else if (status === 419) {
      errorMessage.value =
        'Your security session expired. Refresh the page and try again.'
    } else {
      errorMessage.value =
        'Unable to connect to the BRAVE server. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-page">
    <section class="login-card">
      <header class="login-header">
        <div class="brand-mark">B</div>

        <div>
          <p class="brand-name">BRAVE</p>
          <p class="brand-description">
            Brunei Response &amp; Action for Vital Emergencies
          </p>
        </div>
      </header>

      <div v-if="checkingSession" class="checking-session">
        Checking your session…
      </div>

      <form v-else class="login-form" @submit.prevent="submitLogin">
        <div>
          <h1>NIAT Reviewer Login</h1>

          <p class="login-introduction">
            Sign in to review and verify submitted incident reports.
          </p>
        </div>

        <div
          v-if="errorMessage"
          class="error-message"
          role="alert"
        >
          {{ errorMessage }}
        </div>

        <label class="field">
          <span>Email address</span>

          <input
            v-model.trim="email"
            type="email"
            autocomplete="email"
            placeholder="name@niat.com.bn"
            required
          />
        </label>

        <label class="field">
          <span>Password</span>

          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            placeholder="Enter your password"
            required
          />
        </label>

        <button
          class="login-button"
          type="submit"
          :disabled="loading"
        >
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <footer class="login-footer">
        Authorised NIAT personnel only
      </footer>
    </section>
  </main>
</template>

<style scoped>
.login-page {
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

.login-card {
  width: min(100%, 440px);
  overflow: hidden;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  background: #ffffff;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
}

.login-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 24px 28px;
  color: #ffffff;
  background: #991b1b;
}

.brand-mark {
  width: 48px;
  height: 48px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 12px;
  background: #ffffff;
  color: #991b1b;
  font-size: 24px;
  font-weight: 800;
}

.brand-name {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: 0.08em;
}

.brand-description {
  margin: 3px 0 0;
  font-size: 12px;
  opacity: 0.85;
}

.login-form {
  display: grid;
  gap: 20px;
  padding: 30px 28px;
}

.login-form h1 {
  margin: 0;
  color: #111827;
  font-size: 25px;
}

.login-introduction {
  margin: 7px 0 0;
  color: #6b7280;
  font-size: 14px;
  line-height: 1.5;
}

.field {
  display: grid;
  gap: 8px;
  color: #374151;
  font-size: 14px;
  font-weight: 600;
}

.field input {
  width: 100%;
  box-sizing: border-box;
  padding: 12px 14px;
  border: 1px solid #d1d5db;
  border-radius: 9px;
  outline: none;
  font: inherit;
  font-weight: 400;
}

.field input:focus {
  border-color: #991b1b;
  box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.12);
}

.error-message {
  padding: 12px 14px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 14px;
}

.login-button {
  padding: 13px 18px;
  border: 0;
  border-radius: 9px;
  background: #991b1b;
  color: #ffffff;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.login-button:hover:not(:disabled) {
  background: #7f1d1d;
}

.login-button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.checking-session {
  padding: 48px 28px;
  color: #6b7280;
  text-align: center;
}

.login-footer {
  padding: 16px 28px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  color: #6b7280;
  font-size: 12px;
  text-align: center;
}
</style>
