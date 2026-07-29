<template>
  <div class="fire-incident-form-page">
    <div class="page-header">
      <div>
        <h2>{{ isEditing ? 'Edit Fire Incident' : 'Add Fire Incident' }}</h2>
        <p>
          {{
            isEditing
              ? 'Update existing fire incident record.'
              : 'Create a new fire incident record.'
          }}
        </p>
      </div>

      <button class="btn btn-phoenix-secondary" type="button" @click="router.push('/fire-list')">
        <FeatherIcon icon="arrow-left" />
        Back to List
      </button>
    </div>

    <div class="form-card">
      <div class="form-grid">
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
            <option value="">Select type</option>
            <option>Wildfire</option>
            <option>Forest Fire</option>
            <option>Car Fire</option>
            <option>Electrical Fire</option>
            <option>House Fire</option>
            <option>Heat Alert</option>
          </select>
        </div>

        <div class="incident-form-group">
          <label>Status / Severity</label>
          <select v-model="form.status">
            <option value="">Select status</option>
            <option>Low</option>
            <option>Medium</option>
            <option>High</option>
            <option>Critical</option>
            <option>Resolved</option>
          </select>
        </div>

        <div class="incident-form-group">
          <label>Reported Time</label>
          <input v-model="form.reportedAt" type="datetime-local" />
        </div>

        <div class="incident-form-group">
          <label>Latitude</label>
          <input v-model="form.latitude" type="number" step="any" placeholder="4.5353" />
        </div>

        <div class="incident-form-group">
          <label>Longitude</label>
          <input v-model="form.longitude" type="number" step="any" placeholder="114.7277" />
        </div>

        <div class="incident-form-group full-width">
          <div class="map-location-header">
            <div>
              <label>Pick Location on Map</label>
              <small>Click the map or detect your current location.</small>
            </div>

            <button
              class="btn btn-phoenix-secondary btn-sm"
              type="button"
              @click="detectCurrentLocation"
              :disabled="detectingLocation"
            >
              <FeatherIcon icon="crosshair" />
              {{ detectingLocation ? 'Detecting...' : 'Detect Current Location' }}
            </button>
          </div>

          <div ref="locationMapDiv" class="location-picker-map"></div>
        </div>

        <div class="incident-form-group full-width">
          <label>Remarks</label>
          <textarea v-model="form.remarks" placeholder="Enter incident details..."></textarea>
        </div>
      </div>

      <div class="form-footer">
        <button class="btn btn-phoenix-secondary" type="button" @click="router.push('/fire-list')">
          Cancel
        </button>

        <button class="btn btn-danger" type="button" @click="saveIncident">
          <FeatherIcon icon="save" />
          {{ isEditing ? 'Update Incident' : 'Save Incident' }}
        </button>
      </div>
    </div>
    <div v-if="toast.show" class="phoenix-toast" :class="toast.type">
      <div class="toast-header">
        <strong>{{ toast.title }}</strong>
        <button @click="toast.show = false">×</button>
      </div>
      <div class="toast-body">
        {{ toast.message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FeatherIcon from '@/components/FeatherIcon.vue'

import WebMap from '@arcgis/core/WebMap'
import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Point from '@arcgis/core/geometry/Point'
import Graphic from '@arcgis/core/Graphic'
import http from '@/api/http'

const WEBMAP_ID = '5ad9cc4eaf3842a7b3c7f6c9c4bf61b7'

const route = useRoute()
const router = useRouter()

const isEditing = computed(() => !!route.params.id)
const editingId = computed(() => Number(route.params.id))

const locationMapDiv = ref(null)
const detectingLocation = ref(false)

let webmap = null
let incidentLayer = null
let locationMapView = null
let locationMarkerGraphic = null

const form = reactive({
  location: '',
  type: '',
  status: '',
  reportedAt: '',
  remarks: '',
  latitude: '',
  longitude: '',
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

function getBruneiDateTimeLocal() {
  const now = new Date()

  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  const hours = String(now.getHours()).padStart(2, '0')
  const minutes = String(now.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day}T${hours}:${minutes}`
}

onMounted(async () => {
  await loadIncidentLayer()

  if (isEditing.value) {
    await loadIncidentForEdit()
  } else {
    form.reportedAt = getBruneiDateTimeLocal()
  }

  await nextTick()
  initLocationPickerMap()
})

onBeforeUnmount(() => {
  destroyLocationPickerMap()
})

async function loadIncidentLayer() {
  webmap = new WebMap({
    portalItem: { id: WEBMAP_ID },
  })

  await webmap.loadAll()

  incidentLayer =
    webmap.allLayers
      .toArray()
      .find((layer) => layer.title?.toLowerCase().trim() === 'incident reported') ||
    webmap.allLayers.toArray().find((layer) => layer.title?.toLowerCase().includes('incident'))

  if (!incidentLayer) {
    showToast('Error', 'Incident layer not found.', 'danger')
    return
  }

  await incidentLayer.load()
}

async function loadIncidentForEdit() {
  const objectIdField = incidentLayer.objectIdField || 'OBJECTID'

  const query = incidentLayer.createQuery()
  query.where = `${objectIdField} = ${editingId.value}`
  query.outFields = ['*']
  query.returnGeometry = true

  const result = await incidentLayer.queryFeatures(query)
  const feature = result.features?.[0]

  if (!feature) {
    showToast('Error', 'Incident not found.', 'danger')

    router.push('/fire-list')
    return
  }

  const attrs = feature.attributes || {}

  form.location = getAttr(attrs, ['district', 'District', 'location', 'Location'], '')
  form.type = getAttr(attrs, ['categories', 'Categories', 'incident_type', 'Incident_Type'], '')
  form.status = getAttr(attrs, ['severity_level', 'Severity_Level', 'status', 'Status'], '')
  form.remarks = getAttr(attrs, ['more_details', 'More_Details', 'remarks', 'Remarks'], '')
  form.reportedAt = toDateTimeLocal(getAttr(attrs, ['datetime_reported', 'Datetime_Reported'], ''))

  form.latitude = feature.geometry?.latitude || ''
  form.longitude = feature.geometry?.longitude || ''
}

function initLocationPickerMap() {
  if (!locationMapDiv.value || locationMapView) return

  const defaultLongitude = Number(form.longitude) || 114.7277
  const defaultLatitude = Number(form.latitude) || 4.5353

  const map = new Map({
    basemap: 'streets-vector',
  })

  locationMapView = new MapView({
    container: locationMapDiv.value,
    map,
    center: [defaultLongitude, defaultLatitude],
    zoom: 12,
  })

  locationMapView.when(() => {
    if (form.latitude && form.longitude) {
      updateLocationMarker(defaultLongitude, defaultLatitude)
    }

    locationMapView.on('click', (event) => {
      const longitude = Number(event.mapPoint.longitude.toFixed(6))
      const latitude = Number(event.mapPoint.latitude.toFixed(6))

      form.longitude = longitude
      form.latitude = latitude

      updateLocationMarker(longitude, latitude)
    })
  })
}

function updateLocationMarker(longitude, latitude) {
  if (!locationMapView) return

  const point = new Point({
    longitude,
    latitude,
    spatialReference: { wkid: 4326 },
  })

  if (!locationMarkerGraphic) {
    locationMarkerGraphic = new Graphic({
      geometry: point,
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color: [220, 53, 69, 1],
        size: 14,
        outline: {
          color: [255, 255, 255, 1],
          width: 2,
        },
      },
    })

    locationMapView.graphics.add(locationMarkerGraphic)
  } else {
    locationMarkerGraphic.geometry = point
  }

  locationMapView.goTo({
    center: [longitude, latitude],
    zoom: 15,
  })
}

function detectCurrentLocation() {
  if (!navigator.geolocation) {
    showToast('Error', 'Geolocation is not supported by this browser.', 'danger')
    return
  }

  detectingLocation.value = true

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const latitude = Number(position.coords.latitude.toFixed(6))
      const longitude = Number(position.coords.longitude.toFixed(6))

      form.latitude = latitude
      form.longitude = longitude

      updateLocationMarker(longitude, latitude)

      detectingLocation.value = false
    },
    (error) => {
      console.error(error)
      detectingLocation.value = false
      showToast(
        'Error',
        'Failed to detect current location. Please allow location permission.',
        'danger',
      )
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    },
  )
}

async function saveIncident() {
  if (!incidentLayer) {
    showToast('Error', 'Incident layer not loaded.', 'danger')
    return
  }

  if (!form.location || !form.type || !form.status) {
    showToast('Error', 'Please fill in location, incident type, and status.', 'danger')
    return
  }

  const attributes = {
    district: form.location,
    categories: form.type,
    severity_level: form.status,
    more_details: form.remarks || '-',
    datetime_reported: form.reportedAt ? new Date(form.reportedAt).getTime() : Date.now(),
  }

  const geometry = buildIncidentGeometry()

  try {
    if (isEditing.value) {
      await updateIncident(attributes, geometry)
    } else {
      await addIncident(attributes, geometry)
    }

    showToast(
      'Success',
      isEditing.value ? 'Incident updated successfully.' : 'Incident created successfully.',
      'success',
    )

    setTimeout(() => {
      router.push('/fire-list')
    }, 1200)
  } catch (error) {
    console.error(error)

    const message =
      error?.response?.data?.message ||
      error?.response?.data?.details?.updateResults?.[0]?.error?.description ||
      error?.message ||
      'Failed to save incident.'

    showToast('Update Failed', message, 'danger')
  }
}

function buildIncidentGeometry() {
  const longitude = Number(form.longitude)
  const latitude = Number(form.latitude)

  if (Number.isNaN(longitude) || Number.isNaN(latitude)) {
    return null
  }

  return new Point({
    longitude,
    latitude,
    spatialReference: { wkid: 4326 },
  })
}

async function addIncident(attributes, geometry) {
  const feature = { attributes }

  if (geometry) {
    feature.geometry = geometry
  }

  const result = await incidentLayer.applyEdits({
    addFeatures: [feature],
  })

  const addResult = result.addFeatureResults?.[0]

  if (addResult?.error) {
    throw addResult.error
  }
}

async function updateIncident(attributes, geometry) {
  await http.put(`/api/fire-incidents/${editingId.value}`, {
    attributes,
    geometry: geometry
      ? {
          x: geometry.longitude,
          y: geometry.latitude,
          spatialReference: {
            wkid: 4326,
          },
        }
      : null,
  })
}
function destroyLocationPickerMap() {
  if (locationMapView) {
    locationMapView.destroy()
    locationMapView = null
    locationMarkerGraphic = null
  }
}

function getAttr(attrs, possibleNames, fallback = '-') {
  for (const name of possibleNames) {
    if (attrs?.[name] !== undefined && attrs?.[name] !== null && attrs?.[name] !== '') {
      return attrs[name]
    }
  }

  return fallback
}

function toDateTimeLocal(value) {
  if (!value || value === '-') return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return ''

  const offset = date.getTimezoneOffset()
  const localDate = new Date(date.getTime() - offset * 60000)

  return localDate.toISOString().slice(0, 16)
}
</script>

<style scoped>
.fire-incident-form-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.page-header h2 {
  font-weight: 900;
  margin-bottom: 0.25rem;
  color: var(--phoenix-heading-color);
}

.page-header p {
  color: var(--phoenix-secondary-color);
  margin-bottom: 0;
}

.page-header .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.form-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.form-grid {
  padding: 1.5rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.incident-form-group {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.incident-form-group.full-width {
  grid-column: span 2;
}

.incident-form-group label {
  font-size: 0.82rem;
  font-weight: 800;
  color: var(--phoenix-heading-color);
}

.incident-form-group input,
.incident-form-group select,
.incident-form-group textarea {
  width: 100%;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.75rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  padding: 0.8rem 0.9rem;
}

.incident-form-group textarea {
  min-height: 120px;
  resize: vertical;
}

.map-location-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 1rem;
}

.map-location-header small {
  display: block;
  margin-top: 0.15rem;
  color: var(--phoenix-secondary-color);
  font-size: 0.78rem;
}

.map-location-header .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  white-space: nowrap;
}

.location-picker-map {
  width: 100%;
  height: 360px;
  border-radius: 1rem;
  border: 1px solid var(--phoenix-border-color);
  overflow: hidden;
  background: var(--phoenix-body-bg);
}

.form-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--phoenix-border-color);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.form-footer .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.phoenix-toast {
  position: fixed;
  top: 24px;
  right: 24px;
  width: 360px;
  z-index: 99999;
  border-radius: 16px;
  padding: 14px;
  color: white;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
  animation: slideInToast 0.25s ease;
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
  margin-bottom: 8px;
}

.toast-header button {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
}

.toast-body {
  font-size: 14px;
  line-height: 1.4;
}

@keyframes slideInToast {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .incident-form-group.full-width {
    grid-column: span 1;
  }

  .map-location-header {
    flex-direction: column;
    align-items: stretch;
  }

  .location-picker-map {
    height: 280px;
  }
}
</style>
