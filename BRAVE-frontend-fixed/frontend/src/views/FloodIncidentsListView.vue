<template>
  <div class="fire-incidents-page">
    <div class="page-header">
      <div>
        <h2>Flood Incidents List</h2>
        <p>
          {{
            isOperator
              ? 'Manage all flood incidents pulled from ArcGIS WebMap.'
              : 'View current flood incidents pulled from ArcGIS WebMap.'
          }}
        </p>
      </div>

      <div class="header-actions">
        <button class="btn btn-phoenix-secondary" type="button" @click="loadIncidents">
          <FeatherIcon icon="refresh-cw" />
          Refresh
        </button>

        <button
          v-if="isOperator"
          class="btn btn-danger"
          type="button"
          @click="$router.push('/flood-list/add')"
        >
          <FeatherIcon icon="plus" />
          Add Incident
        </button>
      </div>
    </div>

    <div class="table-card incidents-table-card">
      <div class="incidents-table-header">
        <div>
          <small>Live records from Flood ArcGIS WebMap</small>
        </div>

        <div class="incident-header-actions">
          <div class="incident-search">
            <FeatherIcon icon="search" />
            <input v-model="searchKeyword" type="text" placeholder="Search flood incidents..." />
          </div>

          <span class="incident-count">{{ filteredIncidents.length }} records</span>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner-border text-warning" role="status"></div>
        <span>Loading flood incidents...</span>
      </div>

      <div v-else class="table-responsive custom-table">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Location</th>
              <th>Incident Type</th>
              <th>Status</th>
              <th>Reported Time</th>
              <th>Water Level</th>
              <th>Rainfall</th>
              <th>Intensity</th>
              <th>Tide</th>
              <th>Remarks</th>
              <th v-if="isOperator" class="text-end">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="paginatedIncidents.length === 0">
              <td :colspan="isOperator ? 10 : 9" class="text-center text-body-secondary py-4">
                No flood incident records found.
              </td>
            </tr>

            <tr v-for="incident in paginatedIncidents" :key="incident.objectId">
              <td>
                <div class="location-cell">
                  <FeatherIcon icon="map-pin" />
                  <span>{{ incident.location }}</span>
                </div>
              </td>

              <td>
                <span class="incident-pill">{{ incident.type }}</span>
              </td>

              <td>
                <span
                  class="status-pill"
                  :class="`status-${String(incident.status).toLowerCase()}`"
                >
                  {{ incident.status }}
                </span>
              </td>

              <td class="reported-time">{{ incident.reportedAt }}</td>

              <td>
                <span class="metric-pill water"> {{ incident.waterLevel }} m </span>
              </td>

              <td>
                <span class="metric-pill rain"> {{ incident.rainfall }} mm/hr </span>
              </td>

              <td>
                <span class="metric-pill intensity"> {{ incident.rainfallIntensity }} mm </span>
              </td>

              <td>
                <span
                  class="metric-pill tide-pill"
                  :class="
                    getTideStatusClass(
                      tideLookup[`${incident.location}_${incident.reportedAt}`]?.tide,
                    )
                  "
                >
                  {{
                    tideLookup[`${incident.location}_${incident.reportedAt}`]?.tide
                      ? `🌊 ${parseFloat(
                          tideLookup[`${incident.location}_${incident.reportedAt}`].tide,
                        ).toFixed(2)} m`
                      : '-'
                  }}
                </span>
              </td>

              <td class="remarks-cell">
                {{ incident.remarks }}
              </td>

              <td v-if="isOperator">
                <div class="table-actions">
                  <button
                    class="action-btn edit"
                    type="button"
                    @click="$router.push(`/flood-list/${incident.objectId}/edit`)"
                  >
                    <FeatherIcon icon="edit-2" />
                  </button>

                  <button class="action-btn delete" type="button" @click="deleteIncident(incident)">
                    <FeatherIcon icon="trash-2" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="totalPages > 1" class="pagination-wrapper">
          <button class="page-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
            Previous
          </button>

          <button
            v-for="page in totalPages"
            :key="page"
            class="page-btn"
            :class="{ active: currentPage === page }"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>

          <button
            class="page-btn"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import FeatherIcon from '@/components/FeatherIcon.vue'

import WebMap from '@arcgis/core/WebMap'
import Point from '@arcgis/core/geometry/Point'
import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import http from '@/api/http'
import { getCurrentUser } from '@/api/authApi'

const WEBMAP_ID = '17813afa14a4419583dc2a136bf51d99'

const layerTitles = {
  incidents: 'Flood Sensors (Simulated)',
}

const loading = ref(false)
const isOperator = ref(false)
const currentPage = ref(1)
const perPage = 50

const totalPages = computed(() => {
  return Math.ceil(filteredIncidents.value.length / perPage)
})

const paginatedIncidents = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredIncidents.value.slice(start, start + perPage)
})

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
}

const searchKeyword = ref('')
const isEditing = ref(false)
const editingId = ref(null)
const tideLookup = ref({})

const incidents = ref([])

let webmap = null
let incidentLayer = null

const form = reactive({
  location: '',
  type: '',
  status: '',
  reportedAt: '',
  remarks: '',
  latitude: '',
  longitude: '',
})

const filteredIncidents = computed(() => {
  const keyword = searchKeyword.value.toLowerCase().trim()

  if (!keyword) return incidents.value

  return incidents.value.filter((incident) =>
    JSON.stringify(incident).toLowerCase().includes(keyword),
  )
})

const locationMapDiv = ref(null)
const detectingLocation = ref(false)
const attributes = {
  Location: form.location,
  Incident_Type: form.type,
  Status: form.status,
  Date_and_Time: new Date(form.reportedAt).getTime(),

  Water_Level: form.waterLevel,
  Rainfall: form.rainfall,
  Intensity: form.intensity,

  Remarks: form.remarks || '-',
}
let locationMapView = null
let locationMarkerGraphic = null

onMounted(async () => {
  try {
    await getCurrentUser()
    isOperator.value = true
  } catch {
    isOperator.value = false
  }

  loadIncidents()
})

async function loadIncidents() {
  loading.value = true

  try {
    webmap = new WebMap({
      portalItem: { id: WEBMAP_ID },
    })

    await webmap.loadAll()

    incidentLayer =
      findLayerByTitle(layerTitles.incidents) ||
      findLayerByTitle('Flood Sensor (Live)') ||
      findLayerByTitleIncludes('flood sensor')

    if (!incidentLayer) {
      console.warn('Flood incident layer not found.')
      incidents.value = []
      return
    }

    await incidentLayer.load()

    const features = await getFeatures(incidentLayer, {
      limit: 1000,
    })

    incidents.value = mapIncidentFeatures(features)
    currentPage.value = 1

    // Fetch real tide data for each incident
    tideLookup.value = {}

    for (const incident of incidents.value) {
      fetchTideForIncident(incident)
    }
  } catch (error) {
    console.error('Failed to load flood incidents:', error)
  } finally {
    loading.value = false
  }
}

async function fetchTideForIncident(incident) {
  if (!incident.location || !incident.reportedAt) return

  const key = `${incident.location}_${incident.reportedAt}`

  if (tideLookup.value[key]) return

  try {
    const response = await http.get('/api/tide/nearest', {
      params: {
        district: incident.location,
        datetime: incident.reportedAt,
      },
    })

    tideLookup.value[key] = response.data
  } catch (error) {
    console.error('Failed to fetch tide:', error)
    tideLookup.value[key] = null
  }
}

function findLayerByTitle(title) {
  if (!webmap) return null

  return webmap.allLayers.toArray().find((layer) => {
    return layer.title?.toLowerCase().trim() === title.toLowerCase().trim()
  })
}

function findLayerByTitleIncludes(keyword) {
  if (!webmap) return null

  return webmap.allLayers.toArray().find((layer) => {
    return layer.title?.toLowerCase().includes(keyword.toLowerCase())
  })
}

function getTideStatusClass(value) {
  const tide = parseFloat(value)

  if (isNaN(tide)) return 'tide-unknown'
  if (tide < 0.8) return 'tide-low'
  if (tide < 1.5) return 'tide-normal'
  if (tide < 2.0) return 'tide-high'

  return 'tide-critical'
}

async function getFeatures(layer, options = {}) {
  if (!layer?.createQuery) return []

  const query = layer.createQuery()
  query.where = options.where || '1=1'
  query.outFields = ['*']
  query.returnGeometry = true
  query.num = options.limit || 1000

  if (options.orderByFields) {
    query.orderByFields = options.orderByFields
  }

  const result = await layer.queryFeatures(query)
  return result.features || []
}

function mapIncidentFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    // console.log('Flood incident attrs:', attrs)

    const objectIdField = incidentLayer?.objectIdField || 'OBJECTID'

    const waterLevel = getAttr(attrs, [
      'Water_level__m_',
      'Water_Level__m_',
      'Water_Level',
      'water_level',
      'Water_level',
      'waterLevel',
      'r_20_dist',
    ])

    const rainfall = getAttr(attrs, [
      'Rainfall__mm_hr_',
      'Rainfall',
      'rainfall',
      'Rainfall_mm_hr',
      'r_9_pt',
    ])

    const rainfallIntensity = getAttr(attrs, [
      'Rainfall_Intensity__mm_',
      'Rainfall_Intensity',
      'rainfall_intensity',
      'Intensity',
      'r_9_pi',
    ])

    return {
      objectId: attrs[objectIdField] || attrs.OBJECTID || attrs.FID,
      graphic: feature,

      location: getAttr(
        attrs,
        ['Location', 'location', 'District', 'district', 'Mukim', 'mukim', 'Name', 'name'],
        'Flood Sensor',
      ),

      type: getAttr(
        attrs,
        ['Incident_Type', 'incident_type', 'Category', 'category', 'Type', 'type'],
        'Flood',
      ),

      status: getAttr(
        attrs,
        ['Status', 'status', 'Severity', 'severity'],
        getFloodStatus(waterLevel),
      ),

      waterLevel,
      rainfall,
      rainfallIntensity,

      remarks: getAttr(attrs, ['Remarks', 'remarks', 'Description', 'description'], '-'),

      reportedAt: formatDate(
        getAttr(
          attrs,
          [
            'Date_and_Time',
            'date_and_time',
            'Start_date_time',
            'start_date_time',
            'datetime',
            'DateTime',
            'r_ts_fmt',
            'date_only',
            'CreationDate',
            'created_at',
          ],
          '',
        ),
      ),

      latitude: feature.geometry?.latitude || '',
      longitude: feature.geometry?.longitude || '',
      rawAttributes: attrs,
    }
  })
}

function getFloodStatus(waterLevel) {
  const value = Number(String(waterLevel).replace(/[^\d.-]/g, ''))

  if (Number.isNaN(value)) return 'Active'
  if (value >= 2) return 'High'
  if (value >= 1.5) return 'Watch'

  return 'Normal'
}

function buildIncidentAttributes() {
  return {
    Location: form.location,
    Latitude: Number(form.latitude),
    Longitude: Number(form.longitude),
    Incident_Type: form.type,
    Status: form.status,
    Date_and_Time: form.reportedAt
      ? new Date(form.reportedAt).getTime()
      : Date.now(),
    Water_Level: form.waterLevel,
    Rainfall: form.rainfall,
    Intensity: form.intensity,
    Remarks: form.remarks || '-',
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
  const feature = {
    attributes,
  }

  if (geometry) {
    feature.geometry = {
      x: geometry.longitude ?? geometry.x,
      y: geometry.latitude ?? geometry.y,
      spatialReference: { wkid: 4326 },
    }
  }

  await http.post('/api/flood-incidents', feature)
}

async function updateIncident(attributes, geometry) {
  const feature = {
    attributes,
  }

  if (geometry) {
    feature.geometry = {
      x: geometry.longitude ?? geometry.x,
      y: geometry.latitude ?? geometry.y,
      spatialReference: { wkid: 4326 },
    }
  }

  await http.put(`/api/flood-incidents/${editingId.value}`, feature)
}

async function deleteIncident(incident) {
  if (!incidentLayer) {
    alert('Incident layer not loaded.')
    return
  }

  const confirmed = confirm(`Delete incident "${incident.type}" at ${incident.location}?`)
  if (!confirmed) return

  try {
    const objectId = Number(incident.objectId)

    if (!objectId || Number.isNaN(objectId)) {
      alert('Invalid incident Object ID.')
      return
    }

    await http.delete(`/api/flood-incidents/${objectId}`)

    await loadIncidents()
  } catch (error) {
    console.error('Failed to delete incident:', error)
    alert(error?.message || 'Failed to delete incident. Make sure the ArcGIS layer allows editing.')
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

function formatDate(value) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString()
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
    alert('Geolocation is not supported by this browser.')
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
      console.error('Failed to detect location:', error)
      detectingLocation.value = false

      alert('Failed to detect current location. Please allow location permission.')
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    },
  )
}

function destroyLocationPickerMap() {
  if (locationMapView) {
    locationMapView.destroy()
    locationMapView = null
    locationMarkerGraphic = null
  }
}

onBeforeUnmount(() => {
  destroyLocationPickerMap()
})
</script>

<style scoped>
.fire-incidents-page {
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

.header-actions,
.incident-header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.header-actions .btn,
.incident-header-actions .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.table-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.incidents-table-header {
  padding: 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.incidents-table-header h5 {
  font-weight: 900;
  margin-bottom: 0.25rem;
  color: var(--phoenix-heading-color);
}

.incidents-table-header small {
  color: var(--phoenix-secondary-color);
}

.incident-search {
  height: 38px;
  min-width: 260px;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 999px;
  padding: 0 0.85rem;
  background: var(--phoenix-body-bg);
}

.incident-search input {
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--phoenix-body-color);
  font-size: 0.9rem;
}

.incident-search svg {
  width: 15px;
  height: 15px;
  color: var(--phoenix-secondary-color);
}

.incident-count {
  background: rgba(245, 159, 0, 0.14);
  color: #a05a00;
  border-radius: 999px;
  padding: 0.45rem 0.8rem;
  font-weight: 900;
  font-size: 0.8rem;
  white-space: nowrap;
}

.custom-table {
  padding: 0;
}

.table {
  margin-bottom: 0;
}

.table thead th {
  font-size: 0.78rem;
  text-transform: uppercase;
  color: var(--phoenix-secondary-color);
  background: var(--phoenix-body-bg);
  padding: 1rem 1.25rem;
  border-bottom: 0;
}

.table tbody td {
  padding: 1rem 1.25rem;
  color: var(--phoenix-body-color);
  border-bottom: 1px solid var(--phoenix-border-color);
}

.table tbody tr:last-child td {
  border-bottom: 0;
}

.location-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--phoenix-heading-color);
  font-weight: 800;
}

.location-cell svg {
  width: 16px;
  height: 16px;
}

.incident-pill,
.status-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.35rem 0.7rem;
  font-weight: 800;
  font-size: 0.78rem;
}

.incident-pill {
  background: rgba(220, 53, 69, 0.12);
  color: #dc3545;
}

.status-pill {
  background: rgba(255, 193, 7, 0.16);
  color: #a05a00;
}

.status-high,
.status-critical {
  background: rgba(220, 53, 69, 0.14);
  color: #dc3545;
}

.status-medium {
  background: rgba(245, 159, 0, 0.16);
  color: #a05a00;
}

.status-low,
.status-resolved {
  background: rgba(37, 176, 3, 0.14);
  color: #25b003;
}

.reported-time {
  color: var(--phoenix-body-color);
  font-weight: 700;
}

.remarks-cell {
  max-width: 320px;
  color: var(--phoenix-secondary-color);
}

.table-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.action-btn {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 0.65rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.action-btn svg {
  width: 15px;
  height: 15px;
}

.action-btn.edit {
  background: rgba(56, 116, 255, 0.12);
  color: #3874ff;
}

.action-btn.delete {
  background: rgba(220, 53, 69, 0.12);
  color: #dc3545;
}

.loading-state {
  padding: 2rem;
  text-align: center;
  color: var(--phoenix-secondary-color);
}

.incident-form-grid {
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
  height: 280px;
  border-radius: 1rem;
  border: 1px solid var(--phoenix-border-color);
  overflow: hidden;
  background: var(--phoenix-body-bg);
}

.loading-state {
  min-height: 320px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  font-weight: 700;
  color: var(--phoenix-secondary-color);
}

.pagination-wrapper {
  padding: 1.25rem 1.5rem 2rem;
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.page-btn {
  min-width: 38px;
  height: 38px;
  border-radius: 0.75rem;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
  font-weight: 700;
}

.page-btn.active {
  background: #f59e0b;
  border-color: #f59e0b;
  color: #fff;
}

.page-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.incidents-table-card {
  margin-bottom: 2.5rem;
}

.metric-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 90px;
  padding: 0.45rem 0.75rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.8rem;
}

.metric-pill.water {
  color: #0369a1;
  background: rgba(14, 165, 233, 0.12);
}

.metric-pill.rain {
  color: #047857;
  background: rgba(16, 185, 129, 0.12);
}

.metric-pill.intensity {
  color: #92400e;
  background: rgba(245, 158, 11, 0.14);
}

.metric-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 120px;
  padding: 10px 18px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 14px;
  gap: 6px;
  white-space: nowrap;
}

.tide-pill {
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
}

.tide-low {
  background: #e0f2fe;
  color: #0369a1;
}

.tide-normal {
  background: #dcfce7;
  color: #15803d;
}

.tide-high {
  background: #fef3c7;
  color: #b45309;
}

.tide-critical {
  background: #fee2e2;
  color: #dc2626;
}

.tide-unknown {
  background: #f1f5f9;
  color: #64748b;
}

@media (max-width: 768px) {
  .page-header,
  .incidents-table-header,
  .incident-header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .incident-search {
    width: 100%;
  }

  .incident-form-grid {
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
    height: 240px;
  }
}
</style>
