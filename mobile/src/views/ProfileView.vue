<template>
  <div class="mobile-profile-page">
    <div class="profile-hero">
      <div class="profile-photo-wrap">
        <img v-if="user.id_picture" :src="user.id_picture" class="profile-photo" alt="ID Picture" />
        <div v-else class="profile-placeholder">👤</div>
      </div>

      <p class="eyebrow">SafeBN Citizen Profile</p>
      <h2>{{ user.full_name || 'Loading profile...' }}</h2>
      <p>{{ user.ic_no || '-' }}</p>
    </div>

    <div class="profile-card">
      <div class="section-title">Identity Information</div>

      <div class="info-row">
        <span>Full Name</span>
        <strong>{{ user.full_name || '-' }}</strong>
      </div>

      <div class="info-row">
        <span>IC Number</span>
        <strong>{{ user.ic_no || '-' }}</strong>
      </div>

      <div class="info-row">
        <span>Nationality</span>
        <strong>{{ user.nationality || '-' }}</strong>
      </div>

      <div class="info-row address">
        <span>Address</span>
        <strong>{{ user.address || '-' }}</strong>
      </div>
    </div>

    <div class="profile-card">
      <div class="section-title">Account Security</div>

      <div class="security-box">
        <div>
          <strong>Device Bound Account</strong>
          <p>This SafeBN account is linked to this mobile device.</p>
        </div>
        <span class="secure-badge">Active</span>
      </div>
    </div>

    <button class="logout-btn" @click="logout">Logout</button>

    <div v-if="toast.show" class="phoenix-toast" :class="toast.type">
      <strong>{{ toast.title }}</strong>
      <p>{{ toast.message }}</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { Preferences } from '@capacitor/preferences'
import http from '@/api/http'

const router = useRouter()

const user = reactive({
  full_name: '',
  ic_no: '',
  address: '',
  nationality: '',
  id_picture: '',
})

const toast = reactive({
  show: false,
  title: '',
  message: '',
  type: 'success',
})

function showToast(title, message, type = 'success') {
  toast.title = title
  toast.message = message
  toast.type = type
  toast.show = true

  setTimeout(() => {
    toast.show = false
  }, 3500)
}

async function loadProfile() {
  try {
    const cachedUser = await Preferences.get({ key: 'auth_user' })

    if (cachedUser.value) {
      Object.assign(user, JSON.parse(cachedUser.value))
    }

    const response = await http.get('/mobile/me')

    if (response.data?.user) {
      Object.assign(user, response.data.user)

      await Preferences.set({
        key: 'auth_user',
        value: JSON.stringify(response.data.user),
      })
    }
  } catch (error) {
    showToast(
      'Profile Failed',
      error?.response?.data?.message || 'Unable to load profile.',
      'danger',
    )
  }
}

async function logout() {
  await Preferences.remove({ key: 'auth_token' })
  await Preferences.remove({ key: 'auth_user' })

  router.replace('/login')
}

onMounted(() => {
  loadProfile()
})
</script>

<style scoped>
.mobile-profile-page {
  min-height: 100vh;
  padding: 1rem 1rem 7.5rem;
  background:
    radial-gradient(circle at top left, rgba(216, 174, 0, 0.18), transparent 34%),
    var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.profile-hero {
  background:
    linear-gradient(135deg, rgba(11, 18, 32, 0.82), rgba(11, 18, 32, 0.58)),
    url('@/assets/brave-hero.png') center / cover no-repeat;
  border-radius: 1.5rem;
  padding: 1.4rem 1rem;
  text-align: center;
  color: #fff;
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.22);
}

.profile-photo-wrap {
  width: 96px;
  height: 96px;
  margin: 0 auto 1rem;
  border-radius: 999px;
  padding: 4px;
  background: #d8ae00;
  box-shadow: 0 0.8rem 1.5rem rgba(216, 174, 0, 0.28);
}

.profile-photo,
.profile-placeholder {
  width: 100%;
  height: 100%;
  border-radius: 999px;
  object-fit: cover;
  background: #fff;
}

.profile-placeholder {
  display: grid;
  place-items: center;
  font-size: 2.5rem;
}

.eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #facc15;
}

.profile-hero h2 {
  margin: 0.35rem 0 0.2rem;
  font-size: 1.35rem;
  font-weight: 950;
  color: #fff;
}

.profile-hero p {
  margin: 0;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.85);
}

.profile-card {
  margin-top: 1rem;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-card-border);
  border-radius: 1.4rem;
  padding: 1rem;
  box-shadow: 0 0.7rem 1.5rem rgba(15, 23, 42, 0.06);
}

.section-title {
  margin-bottom: 0.75rem;
  font-size: 0.74rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--phoenix-muted);
}

.info-row {
  padding: 0.85rem 0;
  border-bottom: 1px solid var(--phoenix-card-border);
}

.info-row:last-child {
  border-bottom: none;
}

.info-row span {
  display: block;
  margin-bottom: 0.25rem;
  font-size: 0.72rem;
  font-weight: 900;
  color: var(--phoenix-muted);
}

.info-row strong {
  display: block;
  font-size: 0.95rem;
  color: var(--phoenix-heading);
  word-break: break-word;
}

.security-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
}

.security-box strong {
  color: var(--phoenix-heading);
}

.security-box p {
  margin: 0.25rem 0 0;
  font-size: 0.78rem;
  color: var(--phoenix-muted);
}

.secure-badge {
  padding: 0.4rem 0.65rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 950;
  color: #065f46;
  background: rgba(25, 135, 84, 0.14);
}

.logout-btn {
  width: 100%;
  margin-top: 1rem;
  border: none;
  border-radius: 999px;
  padding: 0.95rem 1rem;
  font-weight: 950;
  color: #fff;
  background: linear-gradient(135deg, #dc3545, #b00020);
  box-shadow: 0 0.8rem 1.4rem rgba(220, 53, 69, 0.24);
}

.phoenix-toast {
  position: fixed;
  top: 16px;
  left: 16px;
  right: 16px;
  z-index: 99999;
  border-radius: 1rem;
  padding: 1rem;
  color: white;
  background: linear-gradient(135deg, #198754, #157347);
}

.phoenix-toast.danger {
  background: linear-gradient(135deg, #dc3545, #bb2d3b);
}

.phoenix-toast p {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
}

:global(html[data-bs-theme='dark']) .profile-card {
  background: #111827;
  border-color: rgba(255, 255, 255, 0.18);
}

:global(html[data-bs-theme='dark']) .section-title,
:global(html[data-bs-theme='dark']) .info-row span,
:global(html[data-bs-theme='dark']) .security-box p {
  color: #cbd5e1;
}

:global(html[data-bs-theme='dark']) .info-row strong,
:global(html[data-bs-theme='dark']) .security-box strong {
  color: #fff;
}

:global(html[data-bs-theme='dark']) .info-row {
  border-color: rgba(255, 255, 255, 0.14);
}
</style>
