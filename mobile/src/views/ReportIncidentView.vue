<template>
  <div class="mobile-report-page">
    <div class="report-header-card">
      <div class="header-icon">🚨</div>

      <div>
        <p class="eyebrow">SafeBN Incident Intake</p>
        <h2>Report Fire Incident</h2>
        <p class="subtitle">Submit a fire, smoke, or heat alert for operator verification.</p>
      </div>
    </div>

    <div class="form-card">
      <div class="section-title">
        <span>Incident Details</span>
      </div>

      <div class="incident-form-group">
        <label>District / Location</label>
        <select v-model="form.location">
          <option disabled value="">Select district</option>
          <option value="Brunei Muara">Brunei Muara</option>
          <option value="Tutong">Tutong</option>
          <option value="Belait">Belait</option>
          <option value="Temburong">Temburong</option>
        </select>
      </div>

      <div class="incident-form-group">
        <label>Incident Type</label>
        <select v-model="form.type">
          <option disabled value="">Select type</option>
          <option>Wildfire</option>
          <option>Forest Fire</option>
          <option>Smoke Detection</option>
          <option>Heat Alert</option>
          <option>Building Fire</option>
          <option>Vehicle Fire</option>
          <option>Other</option>
        </select>
      </div>

      <div class="incident-form-group">
        <label>Description</label>
        <textarea v-model="form.remarks" placeholder="Describe what you see..."></textarea>
      </div>

      <div class="location-card">
        <div class="location-left">
          <div class="location-icon">📍</div>

          <div>
            <strong>Current Location</strong>
            <small>
              {{
                form.latitude && form.longitude
                  ? `${form.latitude}, ${form.longitude}`
                  : 'No GPS detected'
              }}
            </small>
          </div>
        </div>

        <button type="button" @click="detectCurrentLocation" :disabled="detectingLocation">
          {{ detectingLocation ? 'Detecting...' : 'Detect GPS' }}
        </button>
      </div>

      <div class="gps-map-wrapper">
        <div ref="gpsMapRef" class="gps-arcgis-map"></div>

        <div v-if="!form.latitude || !form.longitude" class="map-placeholder">
          <div>📍</div>
          <strong>Location map will appear here</strong>
        </div>
      </div>

      <div v-if="form.location_description" class="detected-address-box">
        <span>Detected Address</span>
        <p>{{ form.location_description }}</p>
      </div>

      <div class="incident-form-group">
        <label>Photo Evidence</label>

        <div class="photo-action-grid">
          <label class="photo-action-box">
            <input type="file" accept="image/*" capture="environment" @change="handlePhoto" />
            <span>📷</span>
            <strong>Take Photo</strong>
            <small>Use camera</small>
          </label>

          <label class="photo-action-box">
            <input type="file" accept="image/*" @change="handlePhoto" />
            <span>🖼️</span>
            <strong>Upload Photo</strong>
            <small>Choose from gallery</small>
          </label>
        </div>

        <div v-if="selectedPhoto" class="selected-photo-name">
          {{ selectedPhoto.name }}
        </div>

        <img v-if="photoPreview" :src="photoPreview" class="photo-preview" />
      </div>

      <button class="submit-btn" type="button" @click="submitReport" :disabled="submitting">
        {{ submitting ? 'Submitting Report...' : 'Submit Report' }}
      </button>
    </div>

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
import { nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import http from '@/api/http'

import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import Point from '@arcgis/core/geometry/Point'
import * as locator from '@arcgis/core/rest/locator'

const detectingLocation = ref(false)
const submitting = ref(false)
const selectedPhoto = ref(null)
const photoPreview = ref('')
const gpsMapRef = ref(null)

let gpsView = null
let gpsMarker = null

const geocodeUrl = 'https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer'

const form = reactive({
  location: '',
  type: '',
  remarks: '',
  latitude: '',
  longitude: '',
  location_description: '',
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
  }, 4000)
}

async function initGpsMap(lat = 4.5353, lng = 114.7277) {
  await nextTick()

  if (gpsView || !gpsMapRef.value) return

  const map = new Map({
    basemap: 'streets-navigation-vector',
  })

  gpsView = new MapView({
    container: gpsMapRef.value,
    map,
    center: [lng, lat],
    zoom: 10,
    constraints: {
      rotationEnabled: false,
    },
  })

  await gpsView.when()

  gpsView.ui.remove('attribution')
}

async function showGpsOnMap(lat, lng) {
  await initGpsMap(lat, lng)

  if (!gpsView) return

  const point = new Point({
    longitude: lng,
    latitude: lat,
  })

  gpsView.graphics.removeAll()

  gpsMarker = new Graphic({
    geometry: point,
    symbol: {
      type: 'simple-marker',
      color: '#D8AE00',
      size: 16,
      outline: {
        color: '#ffffff',
        width: 3,
      },
    },
  })

  gpsView.graphics.add(gpsMarker)

  try {
    await gpsView.goTo(
      {
        target: point,
        zoom: 17,
      },
      {
        animate: false,
      },
    )
  } catch (error) {
    console.warn('Map zoom skipped:', error)
  }
}
async function reverseGeocode(lat, lng) {
  try {
    const result = await locator.locationToAddress(geocodeUrl, {
      location: {
        x: lng,
        y: lat,
      },
    })

    form.location_description = result?.address || `GPS detected at ${lat}, ${lng}`

    autoDetectDistrict(form.location_description, lat, lng)
  } catch (error) {
    console.error('Reverse geocode failed:', error)

    form.location_description = `GPS detected at ${lat}, ${lng}`
    form.location = detectDistrictByCoordinates(lat, lng)
  }
}

function autoDetectDistrict(address, lat, lng) {
  const text = String(address || '').toLowerCase()

  if (text.includes('tutong')) {
    form.location = 'Tutong'
    return
  }

  if (text.includes('belait') || text.includes('kuala belait') || text.includes('seria')) {
    form.location = 'Belait'
    return
  }

  if (text.includes('temburong') || text.includes('bangar')) {
    form.location = 'Temburong'
    return
  }

  if (
    text.includes('brunei muara') ||
    text.includes('brunei-muara') ||
    text.includes('bandar seri begawan') ||
    text.includes('bsb') ||
    text.includes('muara')
  ) {
    form.location = 'Brunei Muara'
    return
  }

  form.location = detectDistrictByCoordinates(lat, lng)
}

function detectDistrictByCoordinates(lat, lng) {
  lat = Number(lat)
  lng = Number(lng)

  if (lat >= 4.65 && lat <= 5.1 && lng >= 114.75 && lng <= 115.15) {
    return 'Brunei Muara'
  }

  if (lat >= 4.35 && lat <= 4.95 && lng >= 114.25 && lng < 114.75) {
    return 'Tutong'
  }

  if (lat >= 4.0 && lat <= 4.95 && lng >= 113.75 && lng < 114.25) {
    return 'Belait'
  }

  if (lat >= 4.35 && lat <= 4.95 && lng >= 115.05 && lng <= 115.45) {
    return 'Temburong'
  }

  return ''
}

function detectCurrentLocation() {
  if (!navigator.geolocation) {
    showToast('Error', 'Geolocation is not supported on this device.', 'danger')
    return
  }

  detectingLocation.value = true

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      try {
        const lat = Number(position.coords.latitude.toFixed(6))
        const lng = Number(position.coords.longitude.toFixed(6))

        form.latitude = lat
        form.longitude = lng

        form.location = detectDistrictByCoordinates(lat, lng)

        await showGpsOnMap(lat, lng)
        await reverseGeocode(lat, lng)

        showToast(
          'Location Detected',
          `GPS captured. District set to ${form.location || 'unknown'}.`,
          'success',
        )
      } catch (error) {
        console.error(error)

        form.location = detectDistrictByCoordinates(form.latitude, form.longitude)

        showToast('Location Detected', 'GPS captured, but address lookup failed.', 'warning')
      } finally {
        detectingLocation.value = false
      }
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

function handlePhoto(event) {
  const file = event.target.files?.[0]

  if (!file) return

  selectedPhoto.value = file
  photoPreview.value = URL.createObjectURL(file)
}

async function submitReport() {
  if (!form.location || !form.type || !form.remarks) {
    showToast(
      'Missing Information',
      'Please fill in district, incident type, and description.',
      'danger',
    )
    return
  }

  if (!form.latitude || !form.longitude) {
    showToast('Location Required', 'Please detect your GPS location before submitting.', 'danger')
    return
  }

  submitting.value = true

  try {
    const payload = new FormData()

    payload.append('location', form.location)
    payload.append('district', form.location)
    payload.append('incident_type', form.type)
    payload.append('description', form.remarks)
    payload.append('latitude', form.latitude)
    payload.append('longitude', form.longitude)
    payload.append('location_description', form.location_description)

    if (selectedPhoto.value) {
      payload.append('photo', selectedPhoto.value)
    }

    await http.post('/public/reports', payload, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    showToast(
      'Report Submitted',
      'Your report has been submitted for operator verification.',
      'success',
    )

    form.location = ''
    form.type = ''
    form.remarks = ''
    form.latitude = ''
    form.longitude = ''
    form.location_description = ''

    selectedPhoto.value = null
    photoPreview.value = ''

    if (gpsView) {
      gpsView.graphics.removeAll()
    }
  } catch (error) {
    console.error(error)

    showToast(
      'Submit Failed',
      error?.response?.data?.message || 'Failed to submit report.',
      'danger',
    )
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  if (gpsView) {
    gpsView.destroy()
    gpsView = null
  }
})
</script>

<style scoped>
.mobile-report-page {
  min-height: 100vh;
  padding: 1rem 1rem 6rem;
  background:
    radial-gradient(circle at top left, rgba(220, 53, 69, 0.08), transparent 32%),
    var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.mobile-report-page,
.mobile-report-page * {
  box-sizing: border-box;
}

.report-header-card {
  background:
    linear-gradient(135deg, rgba(11, 18, 32, 0.78), rgba(11, 18, 32, 0.58)),
    url('@/assets/brave-hero.png') center / cover no-repeat;
  color: #fff;
  border-radius: 1.4rem;
  padding: 1.2rem;
  margin-bottom: 1rem;
  display: flex;
  gap: 1rem;
  align-items: flex-start;
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.22);
  overflow: hidden;
  position: relative;
}

.report-header-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top right, rgba(216, 174, 0, 0.28), transparent 35%),
    linear-gradient(to top, rgba(0, 0, 0, 0.42), transparent 60%);
}

.report-header-card > * {
  position: relative;
  z-index: 2;
}

.header-icon {
  width: 48px;
  height: 48px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: rgba(220, 53, 69, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.16);
  font-size: 1.45rem;
  backdrop-filter: blur(12px);
}

.eyebrow {
  margin: 0 0 0.25rem;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 900;
  color: #facc15;
}

.report-header-card h2 {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 950;
  color: #fff;
}

.subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.85rem;
  line-height: 1.35;
  color: rgba(255, 255, 255, 0.88);
}

.form-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-card-border);
  border-radius: 1.4rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  box-shadow: 0 0.65rem 1.5rem rgba(15, 23, 42, 0.06);
}

.section-title {
  font-size: 0.78rem;
  font-weight: 900;
  color: var(--phoenix-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.incident-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.incident-form-group label {
  font-size: 0.8rem;
  font-weight: 800;
  color: var(--phoenix-heading);
}

.incident-form-group input,
.incident-form-group select,
.incident-form-group textarea {
  width: 100%;
  border: 1px solid var(--phoenix-card-border);
  border-radius: 1rem;
  max-width: 100%;
  box-sizing: border-box;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-text);
  padding: 0.9rem 1rem;
  font-size: 0.95rem;
  outline: none;
}

.incident-form-group input::placeholder,
.incident-form-group textarea::placeholder {
  color: var(--phoenix-muted);
}

.incident-form-group input:focus,
.incident-form-group select:focus,
.incident-form-group textarea:focus {
  border-color: rgba(220, 53, 69, 0.55);
  box-shadow: 0 0 0 0.22rem rgba(220, 53, 69, 0.12);
}
.incident-form-group textarea {
  min-height: 125px;
  resize: vertical;
  max-width: 100%;
}

.location-card {
  border: 1px solid var(--phoenix-card-border);
  border-radius: 1.2rem;
  padding: 0.9rem;
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  background: var(--phoenix-body-bg);
}

.location-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-width: 0;
}

.location-icon {
  width: 42px;
  height: 42px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: rgba(220, 53, 69, 0.12);
  flex: 0 0 auto;
}

.location-card strong {
  display: block;
  font-size: 0.9rem;
  color: var(--phoenix-heading);
}

.location-card small {
  display: block;
  margin-top: 0.2rem;
  color: var(--phoenix-muted);
  font-size: 0.74rem;
  word-break: break-word;
}

.location-card button {
  border: none;
  border-radius: 999px;
  padding: 0.72rem 0.9rem;
  font-weight: 900;
  background: var(--phoenix-card-bg);
  color: #dc3545;
  box-shadow: 0 0.35rem 0.8rem rgba(15, 23, 42, 0.08);
  white-space: nowrap;
}

.photo-action-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.photo-action-box {
  border: 1px dashed rgba(220, 53, 69, 0.45);
  border-radius: 1.2rem;
  padding: 1rem 0.65rem;
  background: rgba(220, 53, 69, 0.04);

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;

  text-align: center;
  cursor: pointer;
}

.photo-action-box input {
  display: none;
}

.photo-action-box span {
  font-size: 1.6rem;
}

.photo-action-box strong {
  font-size: 0.82rem;
  color: var(--phoenix-heading);
}

.photo-action-box small {
  font-size: 0.72rem;
  color: var(--phoenix-muted);
}

.selected-photo-name {
  margin-top: 0.7rem;
  padding: 0.65rem 0.8rem;
  border-radius: 0.9rem;
  background: var(--phoenix-body-bg);
  border: 1px solid var(--phoenix-card-border);
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--phoenix-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upload-box input {
  display: none;
}

.upload-box span {
  font-size: 1.6rem;
}

.upload-box strong {
  font-size: 0.9rem;
  color: var(--phoenix-heading);
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}

.upload-box small {
  color: var(--phoenix-muted);
}

.photo-preview {
  width: 100%;
  max-height: 260px;
  object-fit: cover;
  border-radius: 1.2rem;
  border: 1px solid var(--phoenix-card-border);
}

.submit-btn {
  width: 100%;
  border: none;
  border-radius: 999px;
  padding: 0.95rem 1rem;
  font-weight: 900;
  color: #fff;
  font-size: 1rem;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  box-shadow: 0 0.8rem 1.4rem rgba(220, 53, 69, 0.25);
}

.submit-btn:disabled,
.location-card button:disabled {
  opacity: 0.65;
}

.phoenix-toast {
  position: fixed;
  top: 16px;
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

.toast-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.toast-header strong {
  color: #fff;
}

.toast-header button {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
}

.toast-body {
  font-size: 0.86rem;
  line-height: 1.4;
}
.gps-map-wrapper {
  position: relative;
  width: 100%;
  height: 230px;
  margin-top: 1rem;
  margin-bottom: 1rem;
  border-radius: 1.25rem;
  overflow: hidden;
  border: 1px solid rgba(216, 174, 0, 0.45);
  background: #f8fafc;
  padding: 0;
  line-height: 0;
}
.map-placeholder {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  text-align: center;
  background: #ffffff;
  color: #0f172a;
  pointer-events: none;
}
.map-placeholder div {
  width: 46px;
  height: 46px;
  border-radius: 1rem;
  display: grid;
  place-items: center;
  background: rgba(216, 174, 0, 0.16);
}

.map-placeholder small {
  color: #64748b;
  font-size: 0.78rem;
}

.detected-address-box {
  margin-bottom: 1rem;
  padding: 0.9rem 1rem;
  border-radius: 1rem;
  background: rgba(216, 174, 0, 0.12);
  border: 1px solid rgba(216, 174, 0, 0.25);
}

.detected-address-box span {
  display: block;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #8a6f00;
  margin-bottom: 0.35rem;
}

.detected-address-box p {
  margin: 0;
  font-size: 0.85rem;
  color: #0f172a;
}
.gps-arcgis-map {
  width: 100%;
  height: 100%;
  margin-top: 1rem;
  border-radius: 1.25rem;
  overflow: hidden;
  border: 1px solid rgba(216, 174, 0, 0.35);
  min-height: 230px;
  padding: 0;
  margin: 0;
}

.gps-map {
  width: 100%;
  height: 220px;
  border: 0;
}

.gps-info {
  padding: 0.9rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.gps-info strong {
  color: var(--phoenix-body-color);
}

.gps-info span {
  font-size: 0.8rem;
  color: var(--phoenix-secondary-color);
}

.gps-arcgis-map .esri-view-root,
.gps-arcgis-map .esri-view-surface,
.gps-arcgis-map canvas {
  padding: 0 !important;
  margin: 0 !important;
  inset: 0 !important;
}
/* ==========================================================
   DARK MODE READABILITY FIX
   Uses html[data-bs-theme='dark'] from phoenix.css
   ========================================================== */

:global(html[data-bs-theme='dark']) .mobile-report-page {
  color: #f8fafc;
}

:global(html[data-bs-theme='dark']) .form-card,
:global(html[data-bs-theme='dark']) .location-card {
  background: #111827;
  border-color: rgba(255, 255, 255, 0.18);
}

:global(html[data-bs-theme='dark']) .section-title,
:global(html[data-bs-theme='dark']) .location-card small,
:global(html[data-bs-theme='dark']) .upload-box small,
:global(html[data-bs-theme='dark']) .subtitle {
  color: #cbd5e1 !important;
}

:global(html[data-bs-theme='dark']) .incident-form-group label,
:global(html[data-bs-theme='dark']) .location-card strong,
:global(html[data-bs-theme='dark']) .upload-box strong {
  color: #ffffff !important;
}

:global(html[data-bs-theme='dark']) .incident-form-group input,
:global(html[data-bs-theme='dark']) .incident-form-group select,
:global(html[data-bs-theme='dark']) .incident-form-group textarea {
  background: #0f172a;
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.15);
}

:global(html[data-bs-theme='dark']) .incident-form-group input::placeholder,
:global(html[data-bs-theme='dark']) .incident-form-group textarea::placeholder {
  color: #94a3b8;
}

:global(html[data-bs-theme='dark']) .incident-form-group select option {
  background: #0f172a;
  color: #ffffff;
}

:global(html[data-bs-theme='dark']) .location-card button {
  background: #172033;
  color: #ffffff;
}

:global(html[data-bs-theme='dark']) .upload-box {
  background: rgba(220, 53, 69, 0.08);
  border-color: rgba(220, 53, 69, 0.35);
}

:global(html[data-bs-theme='dark']) .photo-preview {
  border-color: rgba(255, 255, 255, 0.18);
}

@media (max-width: 390px) {
  .mobile-report-page {
    padding: 0.75rem 0.75rem 5.8rem;
  }

  .report-header-card,
  .form-card {
    border-radius: 1.15rem;
  }

  .location-card {
    flex-direction: column;
    align-items: stretch;
  }

  .location-card button {
    width: 100%;
  }
}
</style>
