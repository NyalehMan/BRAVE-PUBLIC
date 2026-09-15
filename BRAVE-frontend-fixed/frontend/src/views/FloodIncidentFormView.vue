<template>
  <div class="fire-incident-form-page">
    <div class="page-header">
      <div>
        <h2>{{ isEditing ? 'Edit Flood Incident' : 'Add Flood Incident' }}</h2>
        <p>
          {{
            isEditing
              ? 'Update existing flood incident record.'
              : 'Create a new flood incident record.'
          }}
        </p>
      </div>

      <button class="btn btn-phoenix-secondary" type="button" @click="router.push('/flood-list')">
        <FeatherIcon icon="arrow-left" />
        Back to List
      </button>
    </div>

    <div class="form-card">
      <div class="form-grid">
        <div class="incident-form-group">
          <label>Location</label>

          <div class="location-input-group">
            <input v-model="form.location" type="text" placeholder="Example: Kg. Kiarong" />

            <button class="btn btn-phoenix-secondary" type="button" @click="getCurrentLocation">
              <FeatherIcon icon="crosshair" />
              Current Location
            </button>
          </div>
        </div>

        <div class="incident-form-group">
          <label>Incident Type</label>
          <select v-model="form.type">
            <option value="">Select type</option>
            <option>Flood</option>
            <option>Flash Flood</option>
            <option>River Overflow</option>
            <option>High Tide</option>
            <option>Heavy Rainfall</option>
          </select>
        </div>

        <div class="incident-form-group">
          <label>Status</label>
          <select v-model="form.status">
            <option value="">Select status</option>
            <option>Green</option>
            <option>Watch</option>
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
          <label>Water Level (m)</label>
          <input v-model="form.waterLevel" type="number" step="0.01" />
        </div>

        <div class="incident-form-group">
          <label>Rainfall (mm/hr)</label>
          <input v-model="form.rainfall" type="number" step="0.01" />
        </div>

        <div class="incident-form-group">
          <label>Intensity (mm)</label>
          <input v-model="form.intensity" type="number" step="0.01" />
        </div>

        <div class="incident-form-group full-width">
          <label>Remarks</label>
          <textarea v-model="form.remarks" placeholder="Enter remarks..."></textarea>
        </div>

        <div class="incident-form-group">
          <label>Latitude</label>
          <input v-model="form.latitude" type="number" step="0.000001" />
        </div>

        <div class="incident-form-group">
          <label>Longitude</label>
          <input v-model="form.longitude" type="number" step="0.000001" />
        </div>
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

      <div class="form-footer">
        <button class="btn btn-phoenix-secondary" type="button" @click="router.push('/flood-list')">
          Cancel
        </button>

        <button class="btn btn-danger" type="button" @click="saveIncident">
          <FeatherIcon icon="save" />
          {{ isEditing ? 'Update Incident' : 'Save Incident' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FeatherIcon from '@/components/FeatherIcon.vue'
import http from '@/api/http'
import WebMap from '@arcgis/core/WebMap'
import * as locator from '@arcgis/core/rest/locator'

import MapView from '@arcgis/core/views/MapView'
import Point from '@arcgis/core/geometry/Point'
import Graphic from '@arcgis/core/Graphic'

const WEBMAP_ID = '17813afa14a4419583dc2a136bf51d99'

const route = useRoute()
const router = useRouter()

const isEditing = computed(() => !!route.params.id)
const editingId = computed(() => Number(route.params.id))

const locationMapDiv = ref(null)
const detectingLocation = ref(false)

let locationMapView = null
let locationMarkerGraphic = null

let webmap = null
let incidentLayer = null

const form = reactive({
  location: '',
  type: '',
  status: '',
  reportedAt: '',
  waterLevel: '',
  rainfall: '',
  intensity: '',
  remarks: '',
  latitude: '',
  longitude: '',
})
const geometry = new Point({
  longitude: Number(form.longitude),
  latitude: Number(form.latitude),
  spatialReference: { wkid: 4326 },
})
function destroyLocationPickerMap() {
  if (locationMapView) {
    locationMapView.destroy()
    locationMapView = null
    locationMarkerGraphic = null
  }
}

onMounted(async () => {
  await loadIncidentLayer()
  await nextTick()
  initLocationPickerMap()

  if (isEditing.value) {
    await loadIncidentForEdit()
  } else {
    form.reportedAt = getBruneiDateTimeLocal()
  }
})

onBeforeUnmount(() => {
  destroyLocationPickerMap()
})

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
        color: [14, 165, 233, 1],
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
  if (!navigator.geolocation) return

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
    () => {
      detectingLocation.value = false
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    },
  )
}

async function loadIncidentLayer() {
  webmap = new WebMap({
    portalItem: { id: WEBMAP_ID },
  })

  await webmap.loadAll()

  incidentLayer = webmap.allLayers
    .toArray()
    .find((layer) => layer.title?.toLowerCase().includes('flood sensor'))

  if (!incidentLayer) {
    alert('Flood incident layer not found.')
    return
  }

  await incidentLayer.load()
}

async function loadIncidentForEdit() {
  const objectIdField = incidentLayer.objectIdField || 'OBJECTID'

  const query = incidentLayer.createQuery()
  query.where = `${objectIdField} = ${editingId.value}`
  query.outFields = ['*']

  const result = await incidentLayer.queryFeatures(query)
  const feature = result.features?.[0]

  if (!feature) {
    router.push('/flood-list')
    return
  }

  const attrs = feature.attributes || {}

  form.location = attrs.Location || ''
  form.latitude = attrs.Latitude || ''
  form.longitude = attrs.Longitude || ''
  form.type = attrs.Incident_Type || 'Flood'
  form.status = attrs.Status || ''
  form.reportedAt = toDateTimeLocal(attrs.Date_and_Time)
  form.waterLevel = attrs.Water_Level || attrs.r_20_dist || ''
  form.rainfall = attrs.Rainfall || attrs.r_9_pt || ''
  form.intensity = attrs.Intensity || attrs.r_9_pi || ''
  form.remarks = attrs.Remarks || ''
}

async function saveIncident() {
  const attributes = {
    Location: form.location,
    Latitude: Number(form.latitude),
    Longitude: Number(form.longitude),
    Incident_Type: form.type,
    Status: form.status,
    Date_and_Time: new Date(form.reportedAt).getTime(),
    Water_Level: form.waterLevel,
    Rainfall: form.rainfall,
    Intensity: form.intensity,
    Remarks: form.remarks || '-',
  }

  try {
    if (isEditing.value) {
      await updateIncident(attributes)
    } else {
      await addIncident(attributes)
    }

    router.push('/flood-list')
  } catch (error) {
    console.error(error)
    alert('Failed to save flood incident.')
  }
}

async function addIncident(attributes) {
  const feature = {
    attributes,
  }

  if (form.latitude && form.longitude) {
    feature.geometry = {
      x: Number(form.longitude),
      y: Number(form.latitude),
      spatialReference: { wkid: 4326 },
    }
  }

  await http.post('/api/flood-incidents', feature)
}

async function updateIncident(attributes) {
  const feature = {
    attributes,
  }

  if (form.latitude && form.longitude) {
    feature.geometry = {
      x: Number(form.longitude),
      y: Number(form.latitude),
      spatialReference: { wkid: 4326 },
    }
  }

  await http.put(`/api/flood-incidents/${editingId.value}`, feature)
}

async function getCurrentLocation() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported.')
    return
  }

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const lat = position.coords.latitude
      const lon = position.coords.longitude

      form.latitude = lat.toFixed(6)
      form.longitude = lon.toFixed(6)

      try {
        const response = await locator.locationToAddress(
          'https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer',
          {
            location: {
              x: lon,
              y: lat,
            },
          },
        )

        form.location = response.address || 'Current Location'
      } catch (error) {
        console.warn(error)
        form.location = 'Current Location'
      }
    },
    (error) => {
      console.error(error)
      alert('Unable to get current location.')
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
    },
  )
}

function getBruneiDateTimeLocal() {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 16)
}

function toDateTimeLocal(value) {
  if (!value) return ''

  const date = new Date(value)
  const offset = date.getTimezoneOffset()

  return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16)
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

.location-input-group {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.location-input-group input {
  flex: 1;
}

.location-input-group .btn {
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
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
