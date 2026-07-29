<template>
  <div class="mobile-reports-page">
    <section class="reports-hero">
      <div class="hero-overlay"></div>

      <div class="hero-content">
        <p class="eyebrow">SafeBN Citizen Portal</p>

        <h2>My Reports</h2>

        <p>Track submitted incidents and operator verification status.</p>
      </div>
    </section>

    <section class="stats-grid">
      <div class="stat-card total">
        <span>Total Reports</span>
        <strong>{{ reports.length }}</strong>
      </div>

      <div class="stat-card pending">
        <span>Pending</span>
        <strong>{{ pendingCount }}</strong>
      </div>

      <div class="stat-card verified">
        <span>Verified</span>
        <strong>{{ verifiedCount }}</strong>
      </div>

      <div class="stat-card rejected">
        <span>Rejected</span>
        <strong>{{ rejectedCount }}</strong>
      </div>
    </section>

    <div v-if="loading" class="loading-state">Loading reports...</div>

    <div v-else-if="reports.length === 0" class="empty-state">
      <div>📋</div>
      <h4>No Reports Yet</h4>
      <p>Your submitted incidents will appear here.</p>
    </div>

    <div v-else class="report-list">
      <div
        v-for="report in reports"
        :key="report.id"
        class="report-card"
        @click="openReport(report)"
      >
        <div class="report-header">
          <div class="report-icon">🔥</div>

          <div class="report-title">
            <strong>{{ report.incident_type }}</strong>

            <small>{{ report.district }}</small>
          </div>

          <span class="status-pill" :class="report.status.toLowerCase()">
            {{ report.status }}
          </span>
        </div>

        <p class="report-description">
          {{ truncate(report.description) }}
        </p>

        <div class="report-footer">
          <span>
            {{ formatDate(report.created_at) }}
          </span>

          <span class="view-link"> View Details</span>
        </div>
      </div>
    </div>

    <transition name="modal-fade">
      <div v-if="selectedReport" class="modal-overlay" @click.self="selectedReport = null">
        <div class="modal-card">
          <div class="modal-handle"></div>

          <div class="modal-header">
            <div class="modal-title-wrap">
              <div class="modal-icon">🔥</div>

              <div>
                <h4>{{ selectedReport.incident_type }}</h4>
                <small>{{ selectedReport.district }}</small>
              </div>
            </div>

            <button class="close-btn" @click="selectedReport = null">✕</button>
          </div>

          <img
            v-if="selectedReport.photo_url"
            :src="selectedReport.photo_url"
            class="detail-photo"
            @error="console.log('Image failed:', selectedReport.photo_url)"
          />

          <div class="detail-grid">
            <div class="detail-item">
              <span>Status</span>
              <strong class="detail-status" :class="selectedReport.status.toLowerCase()">
                {{ selectedReport.status }}
              </strong>
            </div>

            <div class="detail-item">
              <span>Submitted</span>
              <strong>{{ formatDate(selectedReport.created_at) }}</strong>
            </div>
          </div>

          <div class="description-box">
            <span>Description</span>
            <p>{{ selectedReport.description }}</p>
          </div>

          <div v-if="selectedReport.operator_notes" class="operator-notes">
            <h6>BRAVE Operator Notes</h6>
            <p>{{ selectedReport.operator_notes }}</p>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, nextTick } from 'vue'
import http from '@/api/http'
import { Geolocation } from '@capacitor/geolocation'

import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import Point from '@arcgis/core/geometry/Point'

const gpsMapRef = ref(null)
let gpsView = null
let gpsMarker = null

const loading = ref(false)
const reports = ref([])
const selectedReport = ref(null)

const pendingCount = computed(() => reports.value.filter((r) => r.status === 'PENDING').length)

const verifiedCount = computed(() => reports.value.filter((r) => r.status === 'VERIFIED').length)

const rejectedCount = computed(() => reports.value.filter((r) => r.status === 'REJECTED').length)

async function loadReports() {
  loading.value = true

  try {
    const response = await http.get('/public/my-reports')

    reports.value = response.data.data || []
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

async function showGpsOnArcgisMap(lat, lng) {
  await nextTick()

  if (!gpsView) {
    const map = new Map({
      basemap: 'streets-navigation-vector',
    })

    gpsView = new MapView({
      container: gpsMapRef.value,
      map,
      center: [lng, lat],
      zoom: 16,
    })

    gpsView.ui.remove('attribution')
  }

  const point = new Point({
    longitude: lng,
    latitude: lat,
  })

  if (gpsMarker) {
    gpsView.graphics.remove(gpsMarker)
  }

  gpsMarker = new Graphic({
    geometry: point,
    symbol: {
      type: 'simple-marker',
      color: '#D8AE00',
      size: 14,
      outline: {
        color: '#ffffff',
        width: 2,
      },
    },
  })

  gpsView.graphics.add(gpsMarker)

  gpsView.goTo({
    center: [lng, lat],
    zoom: 17,
  })
}

async function detectGps() {
  try {
    const permission = await Geolocation.requestPermissions()

    if (permission.location !== 'granted') {
      alert('Please allow location permission')
      return
    }

    const position = await Geolocation.getCurrentPosition({
      enableHighAccuracy: true,
      timeout: 10000,
    })

    const lat = position.coords.latitude
    const lng = position.coords.longitude

    form.latitude = lat
    form.longitude = lng

    form.district = detectDistrict(lat, lng)
    form.location_description = `GPS detected at ${lat.toFixed(6)}, ${lng.toFixed(6)}`
  } catch (error) {
    console.error(error)
    alert('Unable to detect GPS location')
  }

  form.latitude = lat
  form.longitude = lng
  form.district = detectDistrict(lat, lng)
  form.location_description = `GPS detected at ${lat.toFixed(6)}, ${lng.toFixed(6)}`

  await showGpsOnArcgisMap(lat, lng)
}

function detectDistrict(lat, lng) {
  // Approximate Brunei district boundaries
  if (lat >= 4.45 && lat <= 4.95 && lng >= 114.45 && lng <= 115.05) {
    return 'Brunei Muara'
  }

  if (lat >= 4.35 && lat <= 4.9 && lng >= 114.25 && lng <= 114.8) {
    return 'Tutong'
  }

  if (lat >= 4.0 && lat <= 4.9 && lng >= 113.8 && lng <= 114.45) {
    return 'Belait'
  }

  if (lat >= 4.35 && lat <= 4.9 && lng >= 115.0 && lng <= 115.45) {
    return 'Temburong'
  }

  return ''
}

function openReport(report) {
  selectedReport.value = report
}

function truncate(text) {
  if (!text) return ''

  return text.length > 120 ? text.substring(0, 120) + '...' : text
}

function formatDate(date) {
  if (!date) return '-'

  const d = new Date(date)

  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()

  let hours = d.getHours()
  const minutes = String(d.getMinutes()).padStart(2, '0')

  const ampm = hours >= 12 ? 'PM' : 'AM'

  hours = hours % 12
  hours = hours ? hours : 12

  return `${day}/${month}/${year} ${hours}:${minutes} ${ampm}`
}

onMounted(() => {
  loadReports()
})
</script>

<style scoped>
.mobile-reports-page {
  min-height: 100vh;
  padding: 1rem;
  padding-bottom: 8rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.reports-hero {
  position: relative;
  min-height: 180px;
  border-radius: 1.5rem;
  overflow: hidden;
  padding: 1.25rem;

  background:
    linear-gradient(135deg, rgba(11, 18, 32, 0.82), rgba(11, 18, 32, 0.58)),
    url('@/assets/brave-hero.png') center / cover no-repeat;

  display: flex;
  align-items: flex-end;

  border: 1px solid var(--phoenix-card-border);
  box-shadow: var(--phoenix-shadow-md);
}

.hero-content {
  position: relative;
  z-index: 2;
  color: white;
}

.eyebrow {
  margin: 0;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: #facc15;
  font-weight: 900;
}

.reports-hero h2 {
  margin: 0.35rem 0;
  font-size: 1.8rem;
  font-weight: 950;
  color: #fff;
}

.reports-hero p {
  margin: 0;
  color: rgba(255, 255, 255, 0.85);
}

.stats-grid {
  margin-top: 1rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
}

.stat-card {
  padding: 1rem;
  border-radius: 1.25rem;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-card-border);
  color: var(--phoenix-text);
  box-shadow: var(--phoenix-shadow-sm);
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  display: block;
  width: 36px;
  height: 4px;
  border-radius: 999px;
  margin-bottom: 0.75rem;
}

.total::before {
  background: #0ea5e9;
}

.pending::before {
  background: #f59e0b;
}

.verified::before {
  background: #22c55e;
}

.rejected::before {
  background: #dc3545;
}

.stat-card span {
  display: block;
  font-size: 0.75rem;
  color: var(--phoenix-muted);
  margin-bottom: 0.35rem;
}

.stat-card strong {
  font-size: 1.85rem;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.report-list {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.report-card {
  background: var(--phoenix-card-bg);
  border-radius: 20px;
  padding: 1rem;
  box-shadow: var(--phoenix-shadow-sm);
  border: 1px solid var(--phoenix-card-border);
  color: var(--phoenix-text);
}

.report-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.report-icon {
  width: 44px;
  height: 44px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: var(--phoenix-danger-bg);
  color: var(--phoenix-danger-text);
}

.report-title {
  flex: 1;
  min-width: 0;
}

.report-title strong {
  display: block;
  color: var(--phoenix-heading);
  font-weight: 950;
}

.report-title small {
  color: var(--phoenix-muted);
}

.report-description {
  margin: 0.85rem 0;
  color: var(--phoenix-text);
  line-height: 1.45;
}

.report-footer {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: var(--phoenix-muted);
}

.view-link {
  color: #dc3545;
  font-weight: 800;
  white-space: nowrap;
}

.report-photo {
  width: 100%;
  height: 140px;
  object-fit: cover;
  border-radius: 16px;
  margin: 0.75rem 0;
  border: 1px solid var(--phoenix-card-border);
}

.loading-state,
.empty-state {
  margin-top: 1rem;
  padding: 1.2rem;
  border-radius: 1.25rem;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-card-border);
  color: var(--phoenix-text);
  text-align: center;
  box-shadow: var(--phoenix-shadow-sm);
}

.empty-state h4 {
  color: var(--phoenix-heading);
}

.empty-state p {
  color: var(--phoenix-muted);
}

/* Status Pills */
.status-pill {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 900;
  text-transform: uppercase;
  white-space: nowrap;
}

.status-pill.verified {
  background: var(--phoenix-success-bg);
  color: var(--phoenix-success-text);
  border: 1px solid var(--phoenix-success-border);
}

.status-pill.rejected {
  background: var(--phoenix-danger-bg);
  color: var(--phoenix-danger-text);
  border: 1px solid var(--phoenix-danger-border);
}

.status-pill.pending {
  background: var(--phoenix-warning-bg);
  color: var(--phoenix-warning-text);
  border: 1px solid var(--phoenix-warning-border);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 99999;
  background: rgba(15, 23, 42, 0.58);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: flex-end;
}

.modal-card {
  width: 100%;
  max-height: 86vh;
  overflow-y: auto;
  background: var(--phoenix-card-bg);
  border-radius: 28px 28px 0 0;
  padding: 0.75rem 1.15rem 1.25rem;
  box-shadow: 0 -18px 40px rgba(0, 0, 0, 0.22);
  border: 1px solid var(--phoenix-card-border);
  color: var(--phoenix-text);
}

.modal-handle {
  width: 44px;
  height: 5px;
  border-radius: 999px;
  background: var(--phoenix-card-border);
  margin: 0 auto 1rem;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
  margin-bottom: 1rem;
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.modal-icon {
  width: 46px;
  height: 46px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: var(--phoenix-danger-bg);
  color: var(--phoenix-danger-text);
}

.modal-header h4 {
  margin: 0;
  color: var(--phoenix-heading);
  font-size: 1.05rem;
  font-weight: 950;
}

.modal-header small {
  display: block;
  margin-top: 0.15rem;
  color: var(--phoenix-muted);
  font-size: 0.78rem;
}

.close-btn {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 999px;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-heading);
  font-weight: 900;
}

.detail-photo {
  width: 100%;
  max-height: 240px;
  object-fit: cover;
  border-radius: 16px;
  margin-bottom: 1rem;
  border: 1px solid var(--phoenix-card-border);
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 0.9rem;
}

.detail-item {
  padding: 0.85rem;
  border-radius: 1rem;
  background: var(--phoenix-body-bg);
  border: 1px solid var(--phoenix-card-border);
}

.detail-item span,
.description-box span {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--phoenix-muted);
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.detail-item strong {
  color: var(--phoenix-heading);
  font-size: 0.86rem;
  line-height: 1.35;
}

.detail-status.verified {
  color: var(--phoenix-success-text);
}

.detail-status.rejected {
  color: var(--phoenix-danger-text);
}

.detail-status.pending {
  color: var(--phoenix-warning-text);
}

.description-box {
  padding: 0.95rem;
  border-radius: 1rem;
  background: var(--phoenix-body-bg);
  border: 1px solid var(--phoenix-card-border);
  margin-bottom: 0.9rem;
}

.description-box p {
  margin: 0;
  color: var(--phoenix-heading);
  line-height: 1.45;
  font-size: 0.9rem;
}

.operator-notes {
  padding: 0.95rem;
  border-radius: 1rem;
  background: var(--phoenix-danger-bg);
  border: 1px solid var(--phoenix-danger-border);
}

.operator-notes h6 {
  margin: 0 0 0.45rem;
  color: var(--phoenix-danger-text);
  font-size: 0.78rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.operator-notes p {
  margin: 0;
  color: var(--phoenix-heading);
  line-height: 1.45;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

:global(html[data-bs-theme='dark']) .mobile-reports-page {
  background:
    radial-gradient(circle at top left, rgba(216, 174, 0, 0.08), transparent 35%),
    var(--phoenix-body-bg);
}

:global(html[data-bs-theme='dark']) .stat-card,
:global(html[data-bs-theme='dark']) .report-card,
:global(html[data-bs-theme='dark']) .loading-state,
:global(html[data-bs-theme='dark']) .empty-state {
  box-shadow: 0 12px 24px rgba(0, 0, 0, 0.22);
}

:global(html[data-bs-theme='dark']) .reports-hero {
  border-color: rgba(255, 255, 255, 0.08);
}
</style>
