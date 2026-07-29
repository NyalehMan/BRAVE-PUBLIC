<template>
  <div class="mobile-map-page">
    <div class="map-header">
      <div>
        <p class="eyebrow">SafeBN Live Map</p>
        <h2>Incident Map</h2>
        <span>{{ incidents.length }} ongoing incidents</span>
      </div>

      <button @click="refreshMap" :disabled="loading">
        {{ loading ? '...' : '↻' }}
      </button>
    </div>

    <div class="map-card">
      <div ref="mapDiv" class="map-container"></div>

      <button class="gps-btn" @click="goToMyLocation">📍</button>
    </div>

    <div class="incident-sheet">
      <div class="sheet-header">
        <strong>Nearby / Ongoing Incidents</strong>
        <small>Tap an item to zoom</small>
      </div>

      <div v-if="incidents.length === 0" class="empty-state">No ongoing incidents found.</div>

      <button
        v-for="incident in incidents"
        :key="incident.id || incident.objectid"
        class="incident-item"
        @click="zoomToIncident(incident)"
      >
        <div>
          <strong>{{ incident.incident_type || incident.type || 'Incident' }}</strong>
          <small>{{ incident.location || incident.district || 'Unknown location' }}</small>
        </div>

        <span> {{ incident.distance_km ? `${incident.distance_km} KM` : 'Detect GPS' }} </span>
      </button>
    </div>

    <div v-if="toast.show" class="phoenix-toast" :class="toast.type">
      <strong>{{ toast.title }}</strong>
      <p>{{ toast.message }}</p>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Capacitor } from '@capacitor/core'

import { Geolocation } from '@capacitor/geolocation'
import http from '@/api/http'

import WebMap from '@arcgis/core/WebMap'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import GraphicsLayer from '@arcgis/core/layers/GraphicsLayer'
import BasemapGallery from '@arcgis/core/widgets/BasemapGallery'
import Expand from '@arcgis/core/widgets/Expand'

import '@arcgis/core/assets/esri/themes/light/main.css'

const mapDiv = ref(null)
const loading = ref(false)
const incidents = ref([])

let view = null
let incidentLayer = null
let myLocationLayer = null
let basemapGallery = null
let basemapExpand = null

const toast = reactive({
  show: false,
  title: '',
  message: '',
  type: 'success',
})

const currentLocation = ref({
  latitude: null,
  longitude: null,
})

function calculateDistanceKm(lat1, lon1, lat2, lon2) {
  const R = 6371
  const dLat = ((lat2 - lat1) * Math.PI) / 180
  const dLon = ((lon2 - lon1) * Math.PI) / 180

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2)

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))

  return R * c
}

function updateIncidentDistances() {
  if (!currentLocation.value.latitude || !currentLocation.value.longitude) return

  incidents.value = incidents.value
    .map((incident) => {
      const incidentLat = Number(incident.latitude)
      const incidentLng = Number(incident.longitude)

      if (!incidentLat || !incidentLng) {
        return {
          ...incident,
          distance_km: null,
        }
      }

      const distance = calculateDistanceKm(
        currentLocation.value.latitude,
        currentLocation.value.longitude,
        incidentLat,
        incidentLng,
      )

      return {
        ...incident,
        distance_km: distance.toFixed(2),
      }
    })
    .sort((a, b) => Number(a.distance_km || 9999) - Number(b.distance_km || 9999))

  drawIncidents()
}

function showToast(title, message, type = 'success') {
  toast.title = title
  toast.message = message
  toast.type = type
  toast.show = true

  setTimeout(() => {
    toast.show = false
  }, 3500)
}

async function initMap() {
  myLocationLayer = new GraphicsLayer({
    title: 'My Location',
  })

  const WEBMAP_ID = '5ad9cc4eaf3842a7b3c7f6c9c4bf61b7'

  const webmap = new WebMap({
    portalItem: {
      id: WEBMAP_ID,
    },
  })

  view = new MapView({
    container: mapDiv.value,
    map: webmap,
    center: [114.7277, 4.5353],
    zoom: 9,
    ui: {
      components: [],
    },
  })

  await view.when()

  setupWebMapPopups()

  view.map.allLayers.forEach((layer) => {
    if ('featureReduction' in layer) {
      layer.featureReduction = null
    }

    if ('popupEnabled' in layer) {
      layer.popupEnabled = false
    }

    if ('popupTemplate' in layer) {
      layer.popupTemplate = null
    }
  })

  view.on('click', handleMapClick)

  webmap.add(myLocationLayer)

  await loadIncidents()

  basemapGallery = new BasemapGallery({
    view,
  })

  basemapExpand = new Expand({
    view,
    content: basemapGallery,
    expanded: false,
    expandIcon: 'basemap',
    expandTooltip: 'Change basemap',
    mode: 'floating',
  })

  view.ui.add(basemapExpand, 'top-right')

  basemapGallery.watch('activeBasemap', () => {
    basemapExpand.expanded = false
  })
}

async function loadIncidents() {
  loading.value = true

  try {
    const response = await http.get('/public/incidents/ongoing')

    incidents.value = response.data?.data || []

    showToast('Map Updated', 'Ongoing incidents loaded.', 'success')
  } catch (error) {
    console.error(error)
    showToast('Map Failed', 'Unable to load incident map data.', 'danger')
  } finally {
    loading.value = false
  }
}

async function handleMapClick(event) {
  try {
    const hit = await view.hitTest(event)

    const result = hit.results.find((item) => {
      return item.graphic && item.graphic.layer && item.graphic.layer.title !== 'My Location'
    })

    if (!result) return

    const graphic = result.graphic
    const attrs = graphic.attributes || {}

    const category = attrs.categories || attrs.category || attrs.type || 'Incident'

    const reportedAt = attrs.CreationDate ? new Date(attrs.CreationDate).toLocaleString() : '-'

    view.openPopup({
      title: category,
      location: graphic.geometry,
      content: `
        <div style="padding:8px 4px;">
          <p><strong>Category :</strong> ${category}</p>
          <p><strong>Date & Time :</strong> ${reportedAt}</p>
          <p><strong>Object ID :</strong> ${attrs.objectid || '-'}</p>
        </div>
      `,
    })
  } catch (error) {
    console.error('Map click failed:', error)
  }
}

function setupWebMapPopups() {
  view.map.allLayers.forEach((layer) => {
    if (!('popupEnabled' in layer)) return

    layer.popupEnabled = true

    layer.popupTemplate = {
      title: 'Incident Reported',
      content: (event) => {
        const attrs = event.graphic.attributes || {}

        return `
          <div style="padding:8px 4px;">
            <p><strong>Category :</strong> ${
              attrs.incident_type ||
              attrs.type ||
              attrs.Category ||
              attrs.category ||
              attrs.Incident_Type ||
              '-'
            }</p>

            <p><strong>District :</strong> ${
              attrs.location || attrs.district || attrs.District || attrs.Location || '-'
            }</p>

            <p><strong>Severity Level :</strong> ${
              attrs.severity_level ||
              attrs.severity ||
              attrs.status ||
              attrs.Status ||
              attrs.Severity_Level ||
              '-'
            }</p>

            <p><strong>Date & Time :</strong> ${
              attrs.created_at ||
              attrs.reportedAt ||
              attrs.ReportedAt ||
              attrs.DateTime ||
              attrs.date_time ||
              attrs.Created_Date ||
              '-'
            }</p>

            <p><strong>Incident Details :</strong> ${
              attrs.description ||
              attrs.remarks ||
              attrs.more_details ||
              attrs.Remarks ||
              attrs.Details ||
              attrs.Incident_Details ||
              '-'
            }</p>
          </div>
        `
      },
    }
  })
}

function drawIncidents() {
  if (!incidentLayer) return

  incidentLayer.removeAll()

  incidents.value.forEach((incident) => {
    const latitude = Number(incident.latitude)
    const longitude = Number(incident.longitude)

    if (!latitude || !longitude) return

    const graphic = new Graphic({
      geometry: {
        type: 'point',
        longitude,
        latitude,
      },
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color: getSeverityColor(incident.severity || incident.severity_level),
        size: 14,
        outline: {
          color: '#ffffff',
          width: 2,
        },
      },
      attributes: incident,
      popupTemplate: {
        title: incident.incident_type || incident.type || 'BRAVE Incident',
        content: `
          <b>District:</b> ${incident.location || incident.district || '-'}<br/>
          <b>Status:</b> ${incident.status || incident.validation_status || 'Ongoing'}<br/>
          <b>Description:</b> ${incident.description || incident.more_details || '-'}<br/>
          <b>Distance:</b> ${incident.distance_km ?? '0.00'} KM
        `,
      },
    })

    incidentLayer.add(graphic)
  })
}

function getSeverityColor(severity) {
  const value = String(severity || '').toLowerCase()

  if (value.includes('critical')) return '#dc3545'
  if (value.includes('high')) return '#fd7e14'
  if (value.includes('medium')) return '#ffc107'
  if (value.includes('low')) return '#198754'

  return '#dc3545'
}

async function goToMyLocation() {
  try {
    if (Capacitor.isNativePlatform()) {
      const permission = await Geolocation.requestPermissions()

      if (permission.location !== 'granted') {
        showToast('Location Required', 'Please allow location permission.', 'danger')
        return
      }

      const position = await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 10000,
      })

      handleCurrentPosition(position.coords.latitude, position.coords.longitude)
      return
    }

    if (!navigator.geolocation) {
      showToast('Location Failed', 'Geolocation is not supported in this browser.', 'danger')
      return
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        handleCurrentPosition(position.coords.latitude, position.coords.longitude)
      },
      (error) => {
        console.error(error)
        showToast('GPS Failed', 'Please allow browser location permission.', 'danger')
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      },
    )
  } catch (error) {
    console.error(error)
    showToast('GPS Failed', 'Unable to get current location.', 'danger')
  }
}

function handleCurrentPosition(latitude, longitude) {
  currentLocation.value = {
    latitude,
    longitude,
  }

  updateIncidentDistances()

  myLocationLayer.removeAll()

  myLocationLayer.add(
    new Graphic({
      geometry: {
        type: 'point',
        longitude,
        latitude,
      },
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color: '#2563eb',
        size: 14,
        outline: {
          color: '#ffffff',
          width: 3,
        },
      },
      popupTemplate: {
        title: 'Your Location',
        content: 'Current device GPS location.',
      },
    }),
  )

  view.goTo(
    {
      center: [longitude, latitude],
      zoom: 15,
    },
    {
      animate: false,
    },
  )

  showToast('Location Detected', 'Map moved to your current location.', 'success')
}

function zoomToIncident(incident) {
  const latitude = Number(incident.latitude)
  const longitude = Number(incident.longitude)

  if (!latitude || !longitude || !view) return

  view.popup.open({
    title: incident.incident_type || 'SafeBN Incident',
    content: `
      <b>District:</b> ${incident.location || incident.district || '-'}<br/>
      <b>Status:</b> ${incident.status || 'ONGOING'}<br/>
      <b>Description:</b> ${incident.description || '-'}<br/>
      <b>Distance:</b> ${incident.distance_km || '-'} KM
    `,
    location: {
      type: 'point',
      longitude,
      latitude,
    },
  })

  view.goTo(
    {
      center: [longitude, latitude],
      zoom: 17,
    },
    {
      animate: false,
    },
  )
}

function refreshMap() {
  loadIncidents()
}

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  if (view) {
    view.destroy()
    view = null
  }
})
</script>

<style scoped>
.mobile-map-page {
  min-height: 100vh;
  padding: 1rem 1rem 7.5rem;
  background:
    radial-gradient(circle at top left, rgba(216, 174, 0, 0.18), transparent 34%),
    var(--phoenix-body-bg);
  color: var(--phoenix-text);
}

.map-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.eyebrow {
  margin: 0 0 0.2rem;
  font-size: 0.7rem;
  font-weight: 950;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #dc3545;
}

.map-header h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 950;
  color: var(--phoenix-heading);
}

.map-header span {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.78rem;
  color: var(--phoenix-muted);
}

.map-header button {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 999px;
  font-size: 1.25rem;
  font-weight: 950;
  color: #111827;
  background: #d8ae00;
  box-shadow: 0 0.5rem 1rem rgba(216, 174, 0, 0.25);
}

.map-card {
  position: relative;
  height: 430px;
  border-radius: 1.5rem;
  overflow: hidden;
  border: 1px solid var(--phoenix-card-border);
  background: var(--phoenix-card-bg);
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.12);
}

.map-container {
  width: 100%;
  height: 100%;
}

.gps-btn {
  position: absolute;
  right: 14px;
  bottom: 14px;
  width: 46px;
  height: 46px;
  border: none;
  border-radius: 999px;
  background: #ffffff;
  color: #dc3545;
  font-size: 1.25rem;
  box-shadow: 0 0.8rem 1.5rem rgba(15, 23, 42, 0.22);
  z-index: 5;
}

.incident-sheet {
  margin-top: 1rem;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-card-border);
  border-radius: 1.4rem;
  padding: 1rem;
  box-shadow: 0 0.7rem 1.5rem rgba(15, 23, 42, 0.06);
}

.sheet-header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.8rem;
}

.sheet-header strong {
  font-size: 0.95rem;
  color: var(--phoenix-heading);
}

.sheet-header small {
  font-size: 0.72rem;
  color: var(--phoenix-muted);
}

.incident-item {
  width: 100%;
  border: none;
  border-bottom: 1px solid var(--phoenix-card-border);
  background: transparent;
  padding: 0.85rem 0;
  display: flex;
  justify-content: space-between;
  gap: 0.8rem;
  text-align: left;
}

.incident-item:last-child {
  border-bottom: none;
}

.incident-item strong {
  display: block;
  font-size: 0.9rem;
  color: var(--phoenix-heading);
}

.incident-item small {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: var(--phoenix-muted);
}

.incident-item span {
  flex: 0 0 auto;
  align-self: center;
  padding: 0.35rem 0.55rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 950;
  color: #7c2d12;
  background: rgba(216, 174, 0, 0.2);
}

.empty-state {
  padding: 1.5rem 0;
  text-align: center;
  color: var(--phoenix-muted);
  font-size: 0.85rem;
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

:global(html[data-bs-theme='dark']) .map-header h2,
:global(html[data-bs-theme='dark']) .sheet-header strong,
:global(html[data-bs-theme='dark']) .incident-item strong {
  color: #ffffff;
}

:global(html[data-bs-theme='dark']) .map-header span,
:global(html[data-bs-theme='dark']) .sheet-header small,
:global(html[data-bs-theme='dark']) .incident-item small,
:global(html[data-bs-theme='dark']) .empty-state {
  color: #cbd5e1;
}

:global(html[data-bs-theme='dark']) .map-card,
:global(html[data-bs-theme='dark']) .incident-sheet {
  background: #111827;
  border-color: rgba(255, 255, 255, 0.18);
}

:global(html[data-bs-theme='dark']) .incident-item {
  border-color: rgba(255, 255, 255, 0.14);
}
</style>
