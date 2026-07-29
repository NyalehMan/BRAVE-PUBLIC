<template>
  <div class="home-page">
    <!-- Hero -->
    <section class="hero-card brave-hero">
      <div class="hero-content">
        <p class="eyebrow">SafeBN App</p>

        <h2>Fire Detection & Monitoring</h2>

        <p>Live incident awareness connected to BRAVE operators.</p>

        <div class="hero-footer">
          <button
            class="location-btn"
            type="button"
            @click="detectCurrentLocation"
            :disabled="detectingLocation"
          >
            <span class="location-icon">
              {{ userLocation.latitude && userLocation.longitude ? '✓' : '📍' }}
            </span>

            <span>
              {{
                detectingLocation
                  ? 'Detecting Location...'
                  : userLocation.latitude && userLocation.longitude
                    ? 'Location Detected'
                    : 'Detect Location'
              }}
            </span>
          </button>
        </div>
      </div>
    </section>

    <!-- Status Overview -->
    <section class="status-grid">
      <div class="status-card danger">
        <small>Ongoing</small>
        <strong>{{ ongoingIncidents.length }}</strong>
        <span>Verified incidents</span>
      </div>

      <div class="status-card success">
        <small>Nearby</small>
        <strong>{{ nearbyCount }}</strong>
        <span>Within radius</span>
      </div>
    </section>

    <!-- Main CTA -->
    <section class="quick-actions">
      <RouterLink to="/report-incident" class="action-card report">
        <span class="action-icon">🚨</span>
        <div>
          <strong>Report Incident</strong>
          <small>Submit fire, smoke, or heat alert</small>
        </div>
      </RouterLink>

      <RouterLink to="/my-reports" class="action-card">
        <span class="action-icon">📋</span>
        <div>
          <strong>My Reports</strong>
          <small>Track submitted reports</small>
        </div>
      </RouterLink>
    </section>

    <!-- Nearby Incidents -->
    <section class="panel-card">
      <div class="section-header">
        <div>
          <h5>Nearby Incidents</h5>
          <small>Check before submitting a new report</small>
        </div>
      </div>

      <div class="radius-filter">
        <button
          v-for="radius in radiusOptions"
          :key="radius"
          type="button"
          :class="{ active: nearbyRadiusKm === radius }"
          @click="nearbyRadiusKm = radius"
        >
          {{ radius }} KM
        </button>
      </div>

      <button
        v-if="!userLocation.latitude || !userLocation.longitude"
        class="gps-detect-card"
        @click="detectCurrentLocation"
        :disabled="detectingLocation"
      >
        <div class="gps-icon">
          {{ detectingLocation ? '⏳' : '📍' }}
        </div>

        <div class="gps-content">
          <strong>
            {{ detectingLocation ? 'Detecting Location...' : 'Enable GPS' }}
          </strong>

          <p>
            {{
              detectingLocation
                ? 'Please wait while we detect your current location.'
                : 'Tap to detect incidents near your current location.'
            }}
          </p>
        </div>

        <div class="gps-arrow">→</div>
      </button>

      <div v-else-if="nearbyIncidents.length === 0" class="empty-state">
        <div class="empty-icon">✅</div>
        <strong>No nearby incidents</strong>
        <p>No verified incidents found within {{ nearbyRadiusKm }} KM.</p>
      </div>

      <div v-else class="incident-list">
        <div
          v-for="incident in nearbyIncidents"
          :key="incident.id || incident.objectid || incident.OBJECTID"
          class="incident-card"
        >
          <div class="incident-main">
            <div class="incident-dot"></div>

            <div class="incident-info">
              <div class="incident-title-row">
                <strong>{{ incident.incident_type || incident.type || 'Fire Incident' }}</strong>

                <span v-if="incident.distance_km !== undefined" class="distance-pill muted">
                  {{ Number(incident.distance_km || 0).toFixed(2) }} KM
                </span>
              </div>

              <small>{{ incident.location || incident.district || 'Unknown location' }}</small>

              <p>{{ incident.description || incident.remarks || 'No description provided.' }}</p>

              <div class="incident-footer">
                <span class="severity-pill" :class="severityClass(incident.severity)">
                  {{ incident.severity || 'High' }}
                </span>

                <span class="status-pill">
                  {{ incident.status || 'Ongoing' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Ongoing Incidents -->
    <section class="panel-card">
      <div class="filter-row">
        <div class="filter-group">
          <label>District</label>

          <select v-model="selectedDistrict" @change="resetOngoingView" class="district-select">
            <option v-for="district in districtOptions" :key="district" :value="district">
              {{ district }}
            </option>
          </select>
        </div>
      </div>
      <div class="section-header">
        <div>
          <h5>Current Ongoing Incidents</h5>
          <small>Verified by BRAVE operators</small>
        </div>

        <button class="refresh-btn" type="button" @click="loadOngoingIncidents" :disabled="loading">
          {{ loading ? '...' : 'Refresh' }}
        </button>
      </div>

      <div v-if="loading" class="empty-state">
        <div class="empty-icon">⏳</div>
        <strong>Loading</strong>
        <p>Fetching ongoing incidents...</p>
      </div>

      <div v-else-if="filteredOngoingIncidents.length === 0" class="empty-state">
        <div class="empty-icon">✅</div>
        <strong>No ongoing incidents</strong>
        <p>No verified ongoing incidents at the moment.</p>
      </div>

      <div v-else class="incident-list">
        <div
          v-for="incident in visibleOngoingIncidents"
          :key="incident.id || incident.objectid || incident.OBJECTID"
          class="incident-card"
        >
          <div class="incident-main">
            <div class="incident-dot"></div>

            <div class="incident-info">
              <div class="incident-title-row">
                <strong>{{ incident.incident_type || incident.type || 'Fire Incident' }}</strong>

                <span v-if="incident.distance_km !== undefined" class="distance-pill muted">
                  {{ Number(incident.distance_km || 0).toFixed(2) }} KM
                </span>
              </div>

              <small>{{ incident.location || incident.district || 'Unknown location' }}</small>

              <p>{{ incident.description || incident.remarks || 'No description provided.' }}</p>

              <div class="incident-footer">
                <span class="severity-pill" :class="severityClass(incident.severity)">
                  {{ incident.severity || 'High' }}
                </span>

                <span class="status-pill">
                  {{ incident.status || 'Ongoing' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-if="hasMoreOngoingIncidents" class="view-more-wrap">
        <button type="button" class="view-more-btn" @click="viewMoreOngoingIncidents">
          View More
          <span>{{ visibleOngoingIncidents.length }} / {{ filteredOngoingIncidents.length }}</span>
        </button>
      </div>
    </section>

    <div v-if="toast.show" class="phoenix-toast" :class="toast.type">
      <div class="toast-header">
        <strong>{{ toast.title }}</strong>
        <button @click="toast.show = false">×</button>
      </div>
      <div class="toast-body">{{ toast.message }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http from '@/api/http'

const loading = ref(false)
const detectingLocation = ref(false)

const userLocation = reactive({
  latitude: '',
  longitude: '',
})

const ongoingIncidents = ref([])

const toast = reactive({
  show: false,
  title: '',
  message: '',
  type: 'success',
})

const nearbyRadiusKm = ref(5)
const radiusOptions = [5, 10, 15, 20]

const nearbyIncidents = computed(() => {
  return ongoingIncidents.value.filter((incident) => {
    if (incident.distance_km === undefined || incident.distance_km === null) return false
    return Number(incident.distance_km) <= nearbyRadiusKm.value
  })
})

const nearbyCount = computed(() => nearbyIncidents.value.length)
const isDarkMode = ref(false)

const selectedDistrict = ref('All')
const visibleIncidentCount = ref(10)

const districtOptions = ['All', 'Brunei Muara', 'Tutong', 'Belait', 'Temburong']

const filteredOngoingIncidents = computed(() => {
  if (selectedDistrict.value === 'All') {
    return ongoingIncidents.value
  }

  return ongoingIncidents.value.filter((incident) => {
    const district = incident.location || incident.district || ''
    return district.toLowerCase() === selectedDistrict.value.toLowerCase()
  })
})

const visibleOngoingIncidents = computed(() => {
  return filteredOngoingIncidents.value.slice(0, visibleIncidentCount.value)
})

const hasMoreOngoingIncidents = computed(() => {
  return visibleIncidentCount.value < filteredOngoingIncidents.value.length
})

function viewMoreOngoingIncidents() {
  visibleIncidentCount.value += 10
}

function resetOngoingView() {
  visibleIncidentCount.value = 10
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-bs-theme', theme)
  document.documentElement.setAttribute('data-theme', theme)
  localStorage.setItem('brave-theme', theme)

  isDarkMode.value = theme === 'dark'
}

function toggleTheme() {
  applyTheme(isDarkMode.value ? 'light' : 'dark')
}

onMounted(async () => {
  const savedTheme = localStorage.getItem('brave-theme') || 'light'
  applyTheme(savedTheme)

  await loadOngoingIncidents()
})

function severityClass(severity) {
  const value = String(severity || 'high').toLowerCase()

  if (value === 'critical') return 'critical'
  if (value === 'high') return 'high'
  if (value === 'medium') return 'medium'
  if (value === 'low') return 'low'

  return 'high'
}

function showToast(title, message, type = 'success') {
  toast.title = title
  toast.message = message
  toast.type = type
  toast.show = true

  setTimeout(() => {
    toast.show = false
  }, 4000)
}

async function loadOngoingIncidents() {
  loading.value = true

  try {
    const params = {}

    if (userLocation.latitude && userLocation.longitude) {
      params.lat = userLocation.latitude
      params.lng = userLocation.longitude
    }

    const response = await http.get('/public/incidents/ongoing', { params })
    const result = response.data?.data || response.data || []

    ongoingIncidents.value = Array.isArray(result)
      ? result.filter((item) => item && typeof item === 'object')
      : []
  } catch (error) {
    console.error(error)

    ongoingIncidents.value = []

    showToast(
      'API Failed',
      error?.response?.data?.message || error?.message || 'Failed to connect to backend.',
      'danger',
    )
  } finally {
    loading.value = false
  }
}

function detectCurrentLocation() {
  if (!navigator.geolocation) {
    showToast('GPS Not Supported', 'This device does not support geolocation.', 'danger')
    return
  }

  detectingLocation.value = true

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      userLocation.latitude = Number(position.coords.latitude.toFixed(6))
      userLocation.longitude = Number(position.coords.longitude.toFixed(6))

      detectingLocation.value = false
      showToast('Location Detected', 'Nearby incidents refreshed.', 'success')

      await loadOngoingIncidents()
    },
    () => {
      detectingLocation.value = false
      showToast('Location Failed', 'Please allow location permission and try again.', 'danger')
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    },
  )
}
</script>

<style scoped>
.page-shell {
  position: relative;
  width: 100%;
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(216, 174, 0, 0.18), transparent 32%),
    var(--phoenix-body-bg, #f5f7fb);
  overflow-x: hidden;
}
.home-page {
  --brave-yellow: #d8ae00;
  --brave-yellow-light: #f4c430;
  --brave-red: #dc3545;
  --brave-dark: #27364f;

  min-height: 100vh;
  padding: 40px 1rem 7.5rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.status-card,
.action-card,
.panel-card,
.incident-card,
.empty-state {
  background: var(--phoenix-card-bg);
  border-color: var(--phoenix-card-border);
}

.section-header h5,
.status-card strong,
.action-card strong,
.incident-title-row strong,
.empty-state strong {
  color: var(--phoenix-heading);
}

.section-header small,
.status-card small,
.status-card span,
.action-card small,
.incident-info small,
.incident-info p,
.empty-state p {
  color: var(--phoenix-muted) !important;
}
.mobile-topbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  margin: 0 -1rem 1rem;
  padding: calc(0.75rem + env(safe-area-inset-top)) 1rem 0.75rem;
  background: var(--brave-yellow);
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08);
}

.brand-wrap {
  display: flex;
  align-items: center;
}

.niat-logo {
  height: 42px;
  width: auto;
  object-fit: contain;
  display: block;
}

.topbar-actions {
  display: flex;
  gap: 0.55rem;
}

.icon-btn {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.32);
  color: #111827;
  font-weight: 900;
}

.brand-mark {
  width: 54px;
  height: 42px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  font-weight: 950;
  color: #111827;
  letter-spacing: -0.04em;
}

.topbar-actions {
  display: flex;
  gap: 0.55rem;
}

.icon-btn {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.32);
  color: #111827;
  font-weight: 900;
}

.hero-card {
  background: linear-gradient(135deg, var(--brave-yellow), var(--brave-yellow-light));
  color: #1f2937;
  border-radius: 1.5rem;
  padding: 1.2rem;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  box-shadow: 0 1rem 2rem rgba(216, 174, 0, 0.22);
}

.eyebrow {
  margin: 0 0 0.35rem;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  font-weight: 950;
  opacity: 0.8;
}

.hero-card h2 {
  margin: 0;
  font-size: 1.45rem;
  line-height: 1.1;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.hero-card p {
  margin: 0.5rem 0 0;
  font-size: 0.85rem;
  opacity: 0.82;
  line-height: 1.45;
}

.status-grid {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

.status-card {
  background: var(--phoenix-card-bg, #fff);
  border-radius: 1.2rem;
  padding: 1rem;
  box-shadow: 0 0.75rem 1.6rem rgba(15, 23, 42, 0.055);
  position: relative;
  overflow: hidden;
}

.status-card::before {
  content: '';
  position: absolute;
  inset: 0 auto 0 0;
  width: 5px;
}

.status-card.danger::before {
  background: var(--brave-red);
}

.status-card.success::before {
  background: #16a34a;
}

.status-card small {
  font-weight: 950;
  color: var(--phoenix-muted);
}

.status-card strong {
  display: block;
  margin-top: 0.3rem;
  font-size: 1.9rem;
  line-height: 1;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.status-card span {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.72rem;
  color: var(--phoenix-muted);
}

.quick-actions {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

.action-card {
  text-decoration: none;
  color: var(--phoenix-body-color, #27364f);
  background: var(--phoenix-card-bg, #fff);
  border-radius: 1.2rem;
  padding: 1rem;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  box-shadow: 0 0.75rem 1.6rem rgba(15, 23, 42, 0.055);
}

.action-icon {
  width: 48px;
  height: 48px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: rgba(220, 53, 69, 0.1);
  font-size: 1.25rem;
}

.action-card strong {
  display: block;
  font-size: 0.95rem;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.action-card small {
  color: var(--phoenix-muted);
}

.panel-card {
  margin-top: 1rem;
  background: var(--phoenix-card-bg, #fff);
  border-radius: 1.45rem;
  padding: 1rem;
  box-shadow: 0 0.75rem 1.6rem rgba(15, 23, 42, 0.055);
}

.section-header {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: flex-start;
  margin-bottom: 0.9rem;
}

.section-header h5 {
  margin: 0;
  font-size: 1rem;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.section-header small {
  color: var(--phoenix-muted);
}

.refresh-btn {
  border: none;
  border-radius: 999px;
  padding: 0.55rem 0.75rem;
  font-weight: 950;
  background: var(--phoenix-body-bg, #f5f7fb);
  color: var(--phoenix-heading);
}

.radius-filter {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.45rem;
  margin-bottom: 0.9rem;
  padding: 0.25rem;
  border-radius: 999px;
  background: var(--phoenix-body-bg, #f5f7fb);
}

.radius-filter button {
  border: none;
  background: transparent;
  color: var(--phoenix-muted);
  border-radius: 999px;
  padding: 0.58rem 0.25rem;
  font-size: 0.72rem;
  font-weight: 950;
}

.radius-filter button.active {
  background: var(--brave-red);
  color: #fff;
  box-shadow: 0 0.55rem 1rem rgba(220, 53, 69, 0.24);
}

.empty-state {
  border-radius: 1.1rem;
  padding: 1.15rem;
  text-align: center;
}

.empty-icon {
  width: 44px;
  height: 44px;
  margin: 0 auto 0.6rem;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: rgba(216, 174, 0, 0.16);
}

.empty-state strong {
  display: block;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.empty-state p {
  margin: 0.35rem 0 0;
  color: var(--phoenix-muted);
  font-size: 0.84rem;
}

.incident-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.incident-card {
  text-decoration: none;
  color: var(--phoenix-body-color, #27364f);
  border-radius: 1.15rem;
  padding: 0.9rem;
  background: var(--phoenix-body-bg, #f8fafc);
}

.incident-card.nearby {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.055), rgba(216, 174, 0, 0.045));
}

.incident-main {
  display: flex;
  gap: 0.75rem;
}

.incident-dot {
  width: 10px;
  height: 10px;

  border-radius: 999px;

  background: #dc3545;

  position: relative;

  animation: dotBlink 1.5s infinite;
}

.incident-dot::after {
  content: '';

  position: absolute;
  inset: 0;

  border-radius: 999px;

  background: rgba(220, 53, 69, 0.4);

  animation: ripple 1.5s infinite;
}

@keyframes dotBlink {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.65;
  }
}

@keyframes ripple {
  0% {
    transform: scale(1);
    opacity: 0.6;
  }

  100% {
    transform: scale(3);
    opacity: 0;
  }
}

.incident-info {
  flex: 1;
  min-width: 0;
}

.incident-title-row {
  display: flex;
  justify-content: space-between;
  gap: 0.65rem;
  align-items: flex-start;
}

.incident-title-row strong {
  font-size: 0.95rem;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.incident-info small {
  display: block;
  margin-top: 0.15rem;
  color: var(--phoenix-muted);
  font-size: 0.78rem;
}

.incident-info p {
  margin: 0.6rem 0;
  color: var(--phoenix-muted);
  font-size: 0.84rem;
  line-height: 1.45;
}

.incident-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.distance-pill,
.status-pill,
.severity-pill {
  height: fit-content;
  border-radius: 999px;
  padding: 0.32rem 0.55rem;
  font-size: 0.68rem;
  font-weight: 950;
  white-space: nowrap;
}

.distance-pill {
  background: rgba(22, 163, 74, 0.12);
  color: #15803d;
}

.distance-pill.muted {
  background: rgba(100, 116, 139, 0.12);
  color: var(--phoenix-muted);
}

.status-pill {
  background: rgba(245, 158, 11, 0.16);
  color: #b45309;
}

.severity-pill.critical {
  background: rgba(220, 38, 38, 0.14);
  color: #dc2626;
}

.severity-pill.high {
  background: rgba(234, 88, 12, 0.14);
  color: #ea580c;
}

.severity-pill.medium {
  background: rgba(217, 119, 6, 0.16);
  color: #d97706;
}

.severity-pill.low {
  background: rgba(100, 116, 139, 0.14);
  color: #64748b;
}

.floating-report-btn {
  position: fixed;
  right: 1rem;
  bottom: calc(1rem + env(safe-area-inset-bottom));
  z-index: 50;
  min-width: 104px;
  height: 54px;
  padding: 0 1rem;
  border-radius: 999px;
  background: linear-gradient(135deg, var(--brave-red), #b91c1c);
  color: #fff;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  font-weight: 950;
  box-shadow: 0 1rem 2rem rgba(220, 53, 69, 0.35);
}

.phoenix-toast {
  position: fixed;
  top: calc(16px + env(safe-area-inset-top));
  left: 16px;
  right: 16px;
  z-index: 99999;
  border-radius: 16px;
  padding: 14px;
  color: white;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
}

.phoenix-toast.success {
  background: linear-gradient(135deg, #198754, #157347);
}

.phoenix-toast.danger {
  background: linear-gradient(135deg, #dc3545, #bb2d3b);
}

.phoenix-toast.warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.toast-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.toast-header button {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
}

.toast-body {
  margin-top: 0.4rem;
  font-size: 0.86rem;
}

.brave-hero {
  position: relative;
  min-height: 250px;
  border-radius: 1.55rem;
  padding: 1.35rem;
  overflow: hidden;

  background:
    linear-gradient(135deg, rgba(11, 18, 32, 0.78), rgba(11, 18, 32, 0.56)),
    url('@/assets/brave-hero.png') center / cover no-repeat;

  display: flex;
  align-items: flex-end;

  box-shadow: 0 1.2rem 2.2rem rgba(15, 23, 42, 0.22);
}

.brave-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top right, rgba(216, 174, 0, 0.32), transparent 34%),
    linear-gradient(to top, rgba(0, 0, 0, 0.58), transparent 58%);
}

.hero-content {
  position: relative;
  z-index: 2;
  color: #fff;
  text-align: left;
  width: 100%;
}

.eyebrow {
  margin: 0 0 0.45rem;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.13em;
  font-weight: 950;
  color: #facc15;
}

.hero-content h2 {
  margin: 0;
  max-width: 260px;
  font-size: 1.75rem;
  line-height: 1.05;
  font-weight: 950;
  color: #fff;
}

.hero-content p {
  margin: 0.75rem 0 0;
  max-width: 270px;
  font-size: 0.86rem;
  line-height: 1.45;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.86);
}

.hero-footer {
  margin-top: 1.1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.location-chip {
  min-height: 42px;
  padding: 0.5rem 0.75rem;
  border-radius: 999px;

  display: inline-flex;
  align-items: center;
  gap: 0.45rem;

  background: rgba(255, 255, 255, 0.16);
  color: #fff;

  backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.14);

  font-size: 0.72rem;
  font-weight: 950;
}

.location-btn {
  min-height: 48px;
  padding: 0.75rem 1rem;

  border: none;
  border-radius: 999px;

  display: inline-flex;
  align-items: center;
  gap: 0.65rem;

  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(14px);

  color: #fff;

  font-size: 0.82rem;
  font-weight: 900;

  border: 1px solid rgba(255, 255, 255, 0.12);

  transition: all 0.25s ease;
}
.location-btn:hover {
  background: rgba(255, 255, 255, 0.22);
}

.location-btn:disabled {
  opacity: 0.8;
}

.location-icon {
  width: 24px;
  height: 24px;

  border-radius: 999px;

  display: grid;
  place-items: center;

  background: rgba(216, 174, 0, 0.22);

  font-size: 0.8rem;
}

.location-btn.detected {
  background: rgba(34, 197, 94, 0.18);
  border-color: rgba(34, 197, 94, 0.35);
}
.filter-row {
  margin-bottom: 1rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-group label {
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--phoenix-muted);
}

.district-select {
  width: 100%;
  height: 52px;

  appearance: none;
  -webkit-appearance: none;

  border: 1px solid var(--phoenix-card-border);
  border-radius: 1rem;

  background: var(--phoenix-card-bg)
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%2364758b' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E")
    no-repeat right 1rem center;

  color: var(--phoenix-heading);

  padding: 0 3rem 0 1rem;

  font-size: 0.92rem;
  font-weight: 700;

  transition: all 0.2s ease;
}

.district-select:focus {
  outline: none;
  box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.12);
}

/* Dark Mode */

:global(html[data-bs-theme='dark']) .district-select {
  background: #111827
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%23cbd5e1' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E")
    no-repeat right 1rem center;

  color: #f8fafc;
}

:global(html[data-bs-theme='dark']) .district-select option {
  background: #111827;
  color: #f8fafc;
}

.view-more-wrap {
  margin-top: 0.9rem;
  display: flex;
  justify-content: center;
}

.view-more-btn {
  width: 100%;
  border: none;
  border-radius: 999px;
  padding: 0.8rem 1rem;
  background: var(--phoenix-body-bg, #f8f9fa);
  color: #dc3545;
  font-weight: 900;
}

.view-more-btn span {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.7rem;
  color: var(--phoenix-secondary-color, #6c757d);
}
.gps-detect-card {
  width: 100%;
  border: 0;
  border-radius: 1.25rem;
  padding: 1rem;

  display: flex;
  align-items: center;
  gap: 1rem;

  background: linear-gradient(135deg, rgba(216, 174, 0, 0.15), rgba(216, 174, 0, 0.08));

  border: 1px solid rgba(216, 174, 0, 0.3);

  text-align: left;

  transition: all 0.25s ease;
}

.gps-detect-card:active {
  transform: scale(0.98);
}

.gps-detect-card:disabled {
  opacity: 0.7;
}

.gps-icon {
  width: 54px;
  height: 54px;
  border-radius: 999px;

  display: flex;
  align-items: center;
  justify-content: center;

  font-size: 1.5rem;

  background: #d8ae00;
  color: white;

  flex-shrink: 0;
}

.gps-content {
  flex: 1;
}

.gps-content strong {
  display: block;
  color: var(--phoenix-heading);
  font-size: 0.95rem;
  font-weight: 900;
}

.gps-content p {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  color: var(--phoenix-muted);
}

.gps-arrow {
  font-size: 1.25rem;
  font-weight: 900;
  color: #d8ae00;
}

.incident-card {
  cursor: default;
  text-decoration: none;
}

.incident-card:hover {
  transform: none;
}

:global(html[data-bs-theme='dark']) .gps-detect-card {
  background: rgba(216, 174, 0, 0.08);
  border-color: rgba(216, 174, 0, 0.25);
}

:global(html[data-bs-theme='dark']) .gps-content strong {
  color: #fff;
}

:global(html[data-bs-theme='dark']) .gps-content p {
  color: #cbd5e1;
}

@media (prefers-color-scheme: dark) {
  :global(html[data-bs-theme='dark']) .home-page {
    background: var(--phoenix-body-bg);
    color: var(--phoenix-text);
  }

  :global(html[data-bs-theme='dark']) .status-card,
  :global(html[data-bs-theme='dark']) .action-card,
  :global(html[data-bs-theme='dark']) .panel-card,
  :global(html[data-bs-theme='dark']) .incident-card,
  :global(html[data-bs-theme='dark']) .empty-state {
    border: none;
    box-shadow: 0 0 0 1px rgba(36, 49, 73, 0.8);
  }

  :global(html[data-bs-theme='dark']) .status-card strong,
  :global(html[data-bs-theme='dark']) .action-card strong,
  :global(html[data-bs-theme='dark']) .section-header h5,
  :global(html[data-bs-theme='dark']) .incident-title-row strong,
  :global(html[data-bs-theme='dark']) .empty-state strong {
    color: var(--phoenix-heading);
  }

  :global(html[data-bs-theme='dark']) .status-card small,
  :global(html[data-bs-theme='dark']) .status-card span,
  :global(html[data-bs-theme='dark']) .action-card small,
  :global(html[data-bs-theme='dark']) .section-header small,
  :global(html[data-bs-theme='dark']) .incident-info small,
  :global(html[data-bs-theme='dark']) .incident-info p,
  :global(html[data-bs-theme='dark']) .empty-state p {
    color: var(--phoenix-muted);
  }

  :global(html[data-bs-theme='dark']) .radius-filter {
    background: var(--phoenix-secondary-bg);
  }

  :global(html[data-bs-theme='dark']) .radius-filter button {
    color: var(--phoenix-muted);
  }

  :global(html[data-bs-theme='dark']) .radius-filter button.active {
    background: #dc3545;
    color: #fff;
  }

  :global(html[data-bs-theme='dark']) .refresh-btn {
    background: var(--phoenix-secondary-bg);
    color: var(--phoenix-secondary-text);
  }

  :global(html[data-bs-theme='dark']) .action-icon,
  :global(html[data-bs-theme='dark']) .empty-icon {
    background: var(--phoenix-danger-bg);
  }
}

@media (max-width: 390px) {
  .home-page {
    padding-left: 0.75rem;
    padding-right: 0.75rem;
  }

  .mobile-topbar {
    margin-left: -0.75rem;
    margin-right: -0.75rem;
  }

  .hero-card,
  .panel-card {
    border-radius: 1.2rem;
  }

  .hero-card h2 {
    font-size: 1.25rem;
  }

  .incident-title-row {
    flex-direction: column;
    gap: 0.35rem;
  }
  .gps-btn {
    top: 3rem;
    right: 1rem;
  }
}
</style>
