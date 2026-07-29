<template>
  <div class="fire-incidents-page">
    <div class="page-header">
      <div>
        <h2>Fire Incidents List</h2>
        <p>Manage all fire incidents pulled from ArcGIS WebMap.</p>
      </div>

      <div class="header-actions">
        <button class="btn btn-phoenix-secondary" type="button" @click="loadIncidents">
          <FeatherIcon icon="refresh-cw" />
          Refresh
        </button>

        <button class="btn btn-danger" type="button" @click="$router.push('/fire-list/add')">
          <FeatherIcon icon="plus" />
          Add Incident
        </button>
      </div>
    </div>

    <div class="table-card incidents-table-card">
      <div class="incidents-table-header">
        <div>
          <small>Live records from ArcGIS WebMap</small>
        </div>

        <div class="incident-header-actions">
          <div class="incident-search">
            <FeatherIcon icon="search" />
            <input v-model="searchKeyword" type="text" placeholder="Search incidents..." />
          </div>

          <span class="incident-count">{{ filteredIncidents.length }} records</span>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner-border text-warning" role="status"></div>
        <span>Loading incidents...</span>
      </div>

      <div v-else class="table-responsive custom-table">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th class="text-center">Location</th>
              <th>Incident Type</th>
              <th>Status</th>
              <th>Reported Time</th>
              <th>Remarks</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="paginatedIncidents.length === 0">
              <td colspan="6" class="text-center text-body-secondary py-4">
                No incident records found.
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

              <td class="reported-time">{{ formatDate(incident.reportedAt) }}</td>
              <td class="remarks-cell">
                {{ incident.remarks }}
              </td>

              <td>
                <div class="table-actions">
                  <button
                    class="action-btn resolve"
                    @click="confirmResolve(incident)"
                    :disabled="isResolved(incident)"
                    :title="isResolved(incident) ? 'Already resolved' : 'Mark as resolved'"
                  >
                    <FeatherIcon icon="check" />
                  </button>

                  <button
                    class="action-btn edit"
                    type="button"
                    @click="$router.push(`/fire-list/${incident.objectId}/edit`)"
                    title="Edit"
                  >
                    <FeatherIcon icon="edit-2" />
                  </button>

                  <button
                    class="action-btn delete"
                    type="button"
                    @click="deleteIncident(incident)"
                    title="Delete Data"
                  >
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

    <transition name="toast-fade">
      <div v-if="toast.show" class="toast-wrap">
        <div class="toast-card" :class="toast.type">
          <div class="toast-title">{{ toast.title }}</div>
          <div class="toast-message">{{ toast.message }}</div>
        </div>
      </div>
    </transition>

    <transition name="toast-fade">
      <div v-if="confirmToast.show" class="toast-wrap confirm-toast-wrap">
        <div class="toast-card confirm-toast-card">
          <div class="toast-title">{{ confirmToast.title }}</div>
          <div class="toast-message">{{ confirmToast.message }}</div>

          <div class="toast-actions">
            <button type="button" class="toast-btn cancel-btn" @click="cancelDelete">Cancel</button>

            <button
              type="button"
              class="toast-btn delete-confirm-btn"
              :disabled="deletingId === confirmToast.incident?.objectId"
              @click="confirmDelete"
            >
              {{ deletingId === confirmToast.incident?.objectId ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import FeatherIcon from '@/components/FeatherIcon.vue'
import http from '@/api/http'
import { useAlertSound } from '@/composables/useAlertSound'

import WebMap from '@arcgis/core/WebMap'
import Point from '@arcgis/core/geometry/Point'
import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import { Toast } from 'bootstrap'

const WEBMAP_ID = '5ad9cc4eaf3842a7b3c7f6c9c4bf61b7'

const layerTitles = {
  incidents: 'Incident Reported',
}

const loading = ref(false)
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

function isResolved(incident) {
  return String(incident.status).toLowerCase() === 'resolved'
}

const searchKeyword = ref('')
const showModal = ref(false)
const isEditing = ref(false)
const editingId = ref(null)

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
const { playAlertSound, playSpecificAlertSound } = useAlertSound()

let locationMapView = null
let locationMarkerGraphic = null

onMounted(() => {
  loadIncidents()
})

async function loadIncidents() {
  loading.value = true

  try {
    if (!webmap) {
      webmap = new WebMap({
        portalItem: { id: WEBMAP_ID },
      })

      await webmap.load()

      incidentLayer =
        findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')

      if (!incidentLayer) {
        console.warn('Incident layer not found.')
        incidents.value = []
        return
      }

      await incidentLayer.load()
    }

    const features = await getFeatures(incidentLayer, {
      limit: 1000,
      orderByFields: ['datetime_reported DESC'],
    })

    incidents.value = mapIncidentFeatures(features)
    currentPage.value = 1
  } catch (error) {
    console.error('Failed to load incidents:', error)
  } finally {
    loading.value = false
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

async function getFeatures(layer, options = {}) {
  if (!layer?.createQuery) return []

  const query = layer.createQuery()

  query.where = options.where || '1=1'
  query.outFields = [
    layer.objectIdField || 'OBJECTID',
    'district',
    'categories',
    'severity_level',
    'more_details',
    'datetime_reported',
    'resolved',
  ]

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
    const objectIdField = incidentLayer?.objectIdField || 'OBJECTID'

    return {
      objectId: attrs[objectIdField],
      graphic: feature,

      location: getAttr(
        attrs,
        ['district', 'District', 'location', 'Location', 'mukim', 'Mukim'],
        'Unknown',
      ),

      type: getAttr(
        attrs,
        ['categories', 'Categories', 'category', 'Category', 'incident_type', 'Incident_Type'],
        'Unknown',
      ),

      status: getAttr(
        attrs,
        ['severity_level', 'Severity_Level', 'severity', 'Severity', 'status', 'Status'],
        'Unknown',
      ),

      remarks: getAttr(
        attrs,
        ['more_details', 'More_Details', 'remarks', 'Remarks', 'description', 'Description'],
        '-',
      ),

      reportedAt: formatDate(
        getAttr(
          attrs,
          [
            'datetime_reported',
            'Datetime_Reported',
            'created_date',
            'Created_Date',
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

function closeModal() {
  showModal.value = false
  resetForm()
}

function resetForm() {
  editingId.value = null

  form.location = ''
  form.type = ''
  form.status = ''
  form.reportedAt = ''
  form.remarks = ''
  form.latitude = ''
  form.longitude = ''
}

async function saveIncident() {
  if (!incidentLayer) return

  const attributes = buildIncidentAttributes()
  const geometry = buildIncidentGeometry()

  try {
    if (isEditing.value) {
      await updateIncident(attributes, geometry)
    } else {
      await addIncident(attributes, geometry)

      const severity = String(form.status).toLowerCase()

      if (severity.includes('critical') || severity.includes('high')) {
        playSpecificAlertSound('siren')
      } else {
        playAlertSound()
      }
    }

    closeModal()
    await loadIncidents()
  } catch (error) {
    console.error(error)
  }
}

function buildIncidentAttributes() {
  return {
    district: form.location,
    categories: form.type,
    severity_level: form.status,
    more_details: form.remarks || '-',
    datetime_reported: form.reportedAt ? new Date(form.reportedAt).getTime() : Date.now(),
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
  const objectIdField = incidentLayer.objectIdField || 'OBJECTID'

  const feature = {
    attributes: {
      ...attributes,
      [objectIdField]: editingId.value,
    },
  }

  if (geometry) {
    feature.geometry = geometry
  }

  const result = await incidentLayer.applyEdits({
    updateFeatures: [feature],
  })

  const updateResult = result.updateFeatureResults?.[0]

  if (updateResult?.error) {
    throw updateResult.error
  }
}

function deleteIncident(incident) {
  confirmToast.value = {
    show: true,
    title: 'Delete Incident',
    message: `Delete "${incident.type}" at ${incident.location}?`,
    incident,
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

function formatDate(dateString) {
  if (!dateString) return '-'

  const date = new Date(dateString)

  if (isNaN(date)) return dateString

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()

  const hours = date.getHours()
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')

  const ampm = hours >= 12 ? 'PM' : 'AM'
  const formattedHours = String(hours % 12 || 12).padStart(2, '0')

  return `${day}/${month}/${year}, ${formattedHours}:${minutes}:${seconds} ${ampm}`
}

function toDateTimeLocal(value) {
  if (!value || value === '-') return ''

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const offset = date.getTimezoneOffset()
  const localDate = new Date(date.getTime() - offset * 60000)

  return localDate.toISOString().slice(0, 16)
}

watch(showModal, async (visible) => {
  if (visible) {
    await nextTick()
    initLocationPickerMap()
  } else {
    destroyLocationPickerMap()
  }
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

const deletingId = ref(null)

const confirmToast = ref({
  show: false,
  title: '',
  message: '',
  incident: null,
})

const toast = ref({
  show: false,
  type: 'success',
  title: '',
  message: '',
})

function cancelDelete() {
  confirmToast.value = {
    show: false,
    title: '',
    message: '',
    incident: null,
  }
}

function showToast(type, title, message) {
  toast.value = {
    show: true,
    type,
    title,
    message,
  }

  setTimeout(() => {
    toast.value.show = false
  }, 2500)
}

async function confirmDelete() {
  const incident = confirmToast.value.incident
  if (!incident || !incidentLayer) return

  deletingId.value = incident.objectId

  try {
    await incidentLayer.load()

    const objectIdField = incidentLayer.objectIdField || 'OBJECTID'
    const featureToDelete =
      incident.graphic ||
      new Graphic({
        attributes: {
          [objectIdField]: incident.objectId,
        },
      })

    const result = await incidentLayer.applyEdits({
      deleteFeatures: [featureToDelete],
    })

    const deleteResult = result.deleteFeatureResults?.[0]

    if (!deleteResult) {
      throw new Error('ArcGIS did not return a deletion result.')
    }

    if (deleteResult.error) {
      throw deleteResult.error
    }

    incidentLayer.refresh()

    window.dispatchEvent(
      new CustomEvent('fire-incident-updated', {
        detail: {
          action: 'deleted',
          objectId: incident.objectId,
        },
      }),
    )

    showToast('success', 'Deleted', 'Incident removed successfully.')
    cancelDelete()
    await loadIncidents()
  } catch (error) {
    console.error(error)

    const arcgisMessage =
      error?.details?.messages?.[0] ||
      error?.details?.message ||
      error?.message

    showToast(
      'error',
      'Delete failed',
      arcgisMessage ||
        'Unable to delete incident. Enable Delete under the hosted feature layer editing settings.',
    )

    cancelDelete()
  } finally {
    deletingId.value = null
  }
}

function confirmResolve(incident) {
  const toast = document.createElement('div')
  toast.className = 'toast align-items-center border-0 phoenix-confirm-toast'
  toast.setAttribute('role', 'alert')
  toast.setAttribute('aria-live', 'assertive')
  toast.setAttribute('aria-atomic', 'true')

  toast.innerHTML = `
  <div class="toast-header">
    <span class="me-2">✓</span>
    <strong class="me-auto">Confirm Resolution</strong>
    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
  </div>

  <div class="toast-body">
    <div>Mark this incident as resolved?</div>

    <div class="toast-actions">
      <button class="btn-cancel cancel-btn">Cancel</button>
      <button class="btn-resolve confirm-btn">Yes, Resolve</button>
    </div>
  </div>
`

  let container = document.getElementById('toast-container')

  if (!container) {
    container = document.createElement('div')
    container.id = 'toast-container'
    container.className = 'toast-container position-fixed top-0 end-0 p-3'
    container.style.zIndex = '99999'
    document.body.appendChild(container)
  }

  container.appendChild(toast)

  const bsToast = new Toast(toast, {
    autohide: false,
  })

  toast.querySelector('.confirm-btn').addEventListener('click', async () => {
    bsToast.hide()
    await markResolved(incident)
  })

  toast.querySelector('.cancel-btn').addEventListener('click', () => {
    bsToast.hide()
  })

  toast.addEventListener('hidden.bs.toast', () => {
    toast.remove()
  })

  bsToast.show()
}

async function markResolved(incident) {
  try {
    await http.put(`/api/fire-incidents/${incident.objectId}`, {
      attributes: {
        severity_level: 'Resolved',
        resolved: 'Yes',
        unresolved: 'No',
      },
    })

    showToast('success', 'Resolved', 'Incident marked as resolved.')

    window.dispatchEvent(
      new CustomEvent('fire-incident-updated', {
        detail: {
          action: 'resolved',
          objectId: incident.objectId,
        },
      }),
    )

    await loadIncidents()
  } catch (error) {
    console.error(error)
    showToast('error', 'Update failed', 'Unable to mark incident as resolved.')
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
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.15);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.incidents-table-header {
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  padding: 1.25rem 1.5rem;
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
  color: var(--phoenix-secondary-color);
  background: var(--phoenix-body-bg);
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  text-align: center;
  vertical-align: middle;
}

/* Location column */
.table thead th:first-child,
.table tbody td:first-child {
  text-align: left;
}

/* Actions column */
.table thead th:last-child,
.table tbody td:last-child {
  text-align: center;
}

/* ALTERNATE ROW COLORS */
.table tbody tr:nth-child(odd) {
  background: #ffffff;
}

.table tbody tr:nth-child(even) {
  background: #f8fafc;
}

/* HOVER EFFECT */
.table tbody tr {
  transition: all 0.2s ease;
}

.table tbody tr:hover {
  background: #fff7d6 !important;
}

.table tbody td {
  padding: 1rem 1.25rem;
  text-align: center;
  vertical-align: middle;
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
  justify-content: center;
}

.location-cell svg {
  width: 16px;
  height: 16px;
}

.incident-pill,
.status-pill {
  padding: 0.4rem 0.8rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 90px;
}

.incident-pill {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.status-pill {
  background: rgba(255, 193, 7, 0.16);
  color: #a05a00;
}

.status-high,
.status-critical {
  background: rgba(239, 68, 68, 0.14);
  color: #dc2626;
}

.status-medium {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.status-low,
.status-resolved {
  background: rgba(34, 197, 94, 0.14);
  color: #16a34a;
}

.reported-time {
  color: var(--phoenix-body-color);
  font-weight: 700;
  text-align: center;
  max-width: 320px;
}

.remarks-cell {
  max-width: 320px;
  color: var(--phoenix-secondary-color);
}

.table-actions {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  align-items: center;
}

.action-btn {
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.action-btn svg {
  width: 15px;
  height: 15px;
}

.action-btn.edit {
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
}

.action-btn.edit:hover {
  background: #0420c0;
  color: white;
  transform: translateY(-1px);
}

.action-btn.delete {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.action-btn.delete:hover {
  background: #ff0000;
  color: white;
  transform: translateY(-1px);
}

.loading-state {
  padding: 2rem;
  text-align: center;
  color: var(--phoenix-secondary-color);
}

.incident-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 12000;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.incident-modal-card {
  width: 100%;
  max-width: 760px;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.25rem;
  box-shadow: 0 2rem 4rem rgba(15, 23, 42, 0.25);
  overflow: hidden;
}

.incident-modal-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--phoenix-border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.incident-modal-header h5 {
  margin-bottom: 0.2rem;
  font-weight: 900;
  color: var(--phoenix-heading-color);
}

.incident-modal-header small {
  color: var(--phoenix-secondary-color);
}

.incident-modal-close {
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 50%;
  background: var(--phoenix-body-bg);
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

.incident-modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--phoenix-border-color);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
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

.toast-wrap {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 3000;
}

.toast-card {
  min-width: 280px;
  max-width: 360px;
  border-radius: 14px;
  padding: 14px 16px;
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.18);
  color: #fff;
  font-size: 0.875rem;
}

.toast-card.success {
  background: #16a34a;
}

.toast-card.error {
  background: #dc2626;
}

.toast-title {
  font-weight: 700;
}

.toast-message {
  margin-top: 4px;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.25s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

.confirm-toast-wrap {
  top: 24px;
  right: 24px;
}

.confirm-toast-card {
  min-width: 320px;
  max-width: 380px;
  border-radius: 14px;
  padding: 16px;
  background: var(--phoenix-card-bg, var(--phoenix-body-bg));
  border: 1px solid var(--phoenix-border-color);
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.16);
  color: var(--phoenix-body-color);
}

.toast-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 14px;
}

.toast-btn {
  min-height: 36px;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid var(--phoenix-border-color);
}

.action-btn.resolve {
  background: rgba(34, 197, 94, 0.12);
  color: #16a34a;
}

.action-btn.resolve:hover {
  background: #198754;
  color: white;
  transform: translateY(-1px);
}

.phoenix-confirm-toast {
  width: 380px;
  border: 0;
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
}

.phoenix-confirm-toast .toast-header {
  padding: 1rem 1.1rem;
  background: linear-gradient(135deg, #f8fafc, #ffffff);
  border-bottom: 1px solid #edf2f7;
}

.phoenix-confirm-toast .toast-header span {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgba(25, 135, 84, 0.12);
  color: #198754;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 900;
}

.phoenix-confirm-toast .toast-body {
  padding: 1.1rem;
  background: #fff;
  color: #334155;
  font-weight: 600;
}

.phoenix-confirm-toast .toast-actions {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
}

.phoenix-confirm-toast .btn-cancel {
  border: 1px solid #dbe3ef;
  background: #f8fafc;
  color: #475569;
  border-radius: 12px;
  padding: 0.5rem 0.9rem;
  font-weight: 800;
}

.phoenix-confirm-toast .btn-resolve {
  border: 0;
  background: #198754;
  color: #fff;
  border-radius: 12px;
  padding: 0.5rem 0.95rem;
  font-weight: 900;
}

:global(.phoenix-confirm-toast) {
  width: 380px;
  border: 0;
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
}

:global(.phoenix-confirm-toast .toast-actions) {
  margin-top: 1rem;
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
}

:global(.phoenix-confirm-toast .btn-cancel),
:global(.phoenix-confirm-toast .btn-resolve) {
  border: 0;
  border-radius: 12px;
  padding: 0.5rem 0.95rem;
  font-weight: 800;
}

:global(.phoenix-confirm-toast .btn-cancel) {
  background: #f1f5f9;
  color: #475569;
}

:global(.phoenix-confirm-toast .btn-resolve) {
  background: #198754;
  color: #fff;
}

.action-btn.resolve:disabled {
  background: #d1d5db;
  border-color: #d1d5db;
  color: #6b7280;
  cursor: not-allowed;
  opacity: 0.65;
  box-shadow: none;
  transform: none;
}

.action-btn.resolve:disabled:hover {
  background: #d1d5db;
  border-color: #d1d5db;
  color: #6b7280;
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
