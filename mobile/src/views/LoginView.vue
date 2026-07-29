<template>
  <div class="login-page">
    <section class="login-hero">
      <div class="hero-overlay"></div>

      <div class="hero-content">
        <div class="brand-chip">
          <span>🚨</span>
          <strong>SafeBN App</strong>
        </div>

        <h1>Fire Detection & Monitoring</h1>

        <p>Secure citizen access for incident reporting and public safety awareness.</p>

        <div class="hero-login-card">
          <p class="hero-login-title">Citizen Login</p>

          <div class="form-group">
            <label>IC Number</label>

            <div class="input-wrap">
              <span>🪪</span>

              <input
                v-model="form.ic_no"
                type="text"
                placeholder="00-123456"
                maxlength="9"
                inputmode="numeric"
                @input="formatIc"
              />
            </div>
          </div>

          <div class="form-group">
            <label>Password</label>

            <div class="input-wrap">
              <span>🔒</span>

              <input v-model="form.password" type="password" placeholder="Enter password" />
            </div>
          </div>

          <button class="login-btn" @click="login" :disabled="loading">
            {{ loading ? 'Logging in...' : 'Login to BRAVE' }}
          </button>
        </div>
      </div>
    </section>

    <div v-if="toast.show" class="phoenix-toast" :class="toast.type">
      <strong>{{ toast.title }}</strong>
      <p>{{ toast.message }}</p>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Preferences } from '@capacitor/preferences'
import { Device } from '@capacitor/device'
import http from '@/api/http'

const router = useRouter()
const loading = ref(false)

const form = reactive({
  ic_no: '',
  password: '',
})

const toast = reactive({
  show: false,
  title: '',
  message: '',
  type: 'danger',
})

function showToast(title, message, type = 'danger') {
  toast.title = title
  toast.message = message
  toast.type = type
  toast.show = true

  setTimeout(() => {
    toast.show = false
  }, 3500)
}

function formatIc() {
  let value = form.ic_no.replace(/\D/g, '')

  if (value.length > 2) {
    value = value.slice(0, 2) + '-' + value.slice(2, 8)
  }

  form.ic_no = value
}

async function login() {
  if (!form.ic_no || !form.password) {
    showToast('Missing Information', 'Please enter IC number and password.')
    return
  }

  loading.value = true

  try {
    const device = await Device.getId()

    const response = await http.post('/mobile/login', {
      ic_no: form.ic_no,
      password: form.password,
      device_id: device.identifier,
    })

    await Preferences.set({
      key: 'auth_token',
      value: response.data.token,
    })

    await Preferences.set({
      key: 'auth_user',
      value: JSON.stringify(response.data.user),
    })

    showToast('Login Successful', 'Welcome to BRAVE.', 'success')

    setTimeout(() => {
      router.replace('/')
    }, 500)
  } catch (error) {
    showToast('Login Failed', error?.response?.data?.message || 'Unable to login.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page,
.login-page * {
  box-sizing: border-box;
}

.login-page {
  min-height: 100vh;
  padding: 0.75rem 0.75rem 7.5rem;
  margin-top: 1rem;

  background:
    radial-gradient(circle at top left, rgba(216, 174, 0, 0.18), transparent 32%),
    var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.login-hero {
  position: relative;
  border-radius: 1.6rem;
  overflow: hidden;
  padding: 1.25rem;
  background:
    linear-gradient(135deg, rgba(11, 18, 32, 0.78), rgba(11, 18, 32, 0.58)),
    url('@/assets/brave-hero.png') center / cover no-repeat;
  box-shadow: 0 1.2rem 2.2rem rgba(15, 23, 42, 0.2);
}

.login-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top right, rgba(216, 174, 0, 0.35), transparent 35%),
    linear-gradient(to top, rgba(0, 0, 0, 0.55), transparent 58%);
}

.hero-content {
  position: relative;
  z-index: 2;
  color: #fff;
}

.brand-chip {
  width: fit-content;
  margin-bottom: 1rem;
  padding: 0.5rem 0.75rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(14px);
  font-size: 0.75rem;
  font-weight: 900;
}

.login-hero h1 {
  margin: 0;
  max-width: 280px;
  color: #fff;
  font-size: 1.85rem;
  line-height: 1.05;
  font-weight: 950;
}

.login-hero p {
  margin: 0.75rem 0 0;
  max-width: 300px;
  color: rgba(255, 255, 255, 0.88);
  font-size: 0.82rem;
  line-height: 1.45;
  font-weight: 600;
}

.hero-login-card {
  max-width: 340px;
  margin-top: 2rem;
  padding: 1.25rem;
  border-radius: 1.5rem;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(255, 255, 255, 0.55);
  box-shadow:
    0 20px 40px rgba(0, 0, 0, 0.18),
    0 4px 12px rgba(0, 0, 0, 0.08);
}

.hero-login-title {
  margin: 0 0 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.18);
  color: #dc3545 !important;
  font-size: 0.72rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: 0.14em;
}

.form-group {
  margin-bottom: 0.95rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.45rem;
  font-size: 0.78rem;
  font-weight: 900;
  color: #22304a;
}

.input-wrap {
  min-height: 54px;
  border: 1px solid #d8e2ef;
  border-radius: 1rem;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0 0.9rem;
  transition: all 0.2s ease;
}

.input-wrap:focus-within {
  border-color: rgba(220, 53, 69, 0.55);
  box-shadow: 0 0 0 0.22rem rgba(220, 53, 69, 0.12);
}

.input-wrap span {
  width: 34px;
  height: 34px;
  border-radius: 0.8rem;
  display: grid;
  place-items: center;
  background: rgba(220, 53, 69, 0.1);
  flex: 0 0 auto;
}

.input-wrap input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: transparent;
  color: #22304a;
  font-size: 0.95rem;
  font-weight: 700;
}

.input-wrap input::placeholder {
  color: #667085;
  font-weight: 500;
}

.login-btn {
  width: 100%;
  min-height: 52px;
  margin-top: 0.35rem;
  border: none;
  border-radius: 999px;
  padding: 0.95rem;
  font-weight: 950;
  color: white;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  box-shadow: 0 0.85rem 1.5rem rgba(34, 197, 94, 0.28);
  box-shadow: 0 0.85rem 1.5rem rgba(220, 53, 69, 0.28);
}

.login-btn:disabled {
  opacity: 0.65;
}

.phoenix-toast {
  position: fixed;
  top: calc(16px + env(safe-area-inset-top));
  left: 16px;
  right: 16px;
  z-index: 9999;
  border-radius: 1rem;
  padding: 1rem;
  color: white;
  background: linear-gradient(135deg, #dc3545, #bb2d3b);
  box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.18);
}

.phoenix-toast.success {
  background: linear-gradient(135deg, #198754, #157347);
}

.phoenix-toast strong {
  color: #fff;
}

.phoenix-toast p {
  margin: 0.3rem 0 0;
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.9);
}

/* Dark mode */
:global(html[data-bs-theme='dark']) .hero-login-card {
  background: rgba(17, 24, 39, 0.92);
  border-color: rgba(255, 255, 255, 0.16);
}

:global(html[data-bs-theme='dark']) .hero-login-title,
:global(html[data-bs-theme='dark']) .form-group label {
  color: #ffffff;
}

:global(html[data-bs-theme='dark']) .input-wrap {
  background: rgba(15, 23, 42, 0.96);
  border-color: rgba(255, 255, 255, 0.14);
}

:global(html[data-bs-theme='dark']) .input-wrap input {
  color: #ffffff;
}

:global(html[data-bs-theme='dark']) .input-wrap input::placeholder {
  color: #94a3b8;
}

@media (max-width: 390px) {
  .login-page {
    padding: 0.75rem 0.75rem 7.5rem;
  }

  .login-hero {
    border-radius: 1.35rem;
    padding: 1rem;
  }

  .login-hero h1 {
    font-size: 1.65rem;
  }

  .hero-login-card {
    margin-top: 1.5rem;
    padding: 1rem;
    border-radius: 1.25rem;
  }
}
</style>
