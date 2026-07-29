<template>
  <div class="fire-page" :class="{ 'dispatcher-map-mode': isFullMap }">
    <!-- HEADER -->
    <div class="page-header">
      <div>
        <h2>Flood Detection &amp; Monitoring Dashboard</h2>
        <p>Live flood data pulled from NIAT Database Server.</p>
      </div>
    </div>

    <!-- MAP + PANEL -->
    <div class="dashboard-grid">
      <div
        class="map-card"
        :class="{
          'full-map-mode': isFullMap,
          'weather-active': showWeatherOverlay,
        }"
      >
        <div class="card-header-custom">
          <button
            v-if="isFullMap"
            class="btn btn-sm btn-phoenix-secondary mobile-actions-toggle"
            type="button"
            @click="showFullMapActions = !showFullMapActions"
          >
            <FeatherIcon :icon="showFullMapActions ? 'x' : 'more-vertical'" />
          </button>
          <div>
            <h5>Live WebMap</h5>
            <small>{{ loading ? 'Loading live layers...' : 'Connected to WebMap' }}</small>
          </div>

          <div
            class="map-actions"
            :class="{
              'mobile-collapsed': isFullMap && !showFullMapActions,
              'mobile-expanded': isFullMap && showFullMapActions,
            }"
          >
            <button
              class="btn btn-sm btn-phoenix-secondary filter-icon-btn compact-btn"
              :class="{ active: showDistrictPanel }"
              type="button"
              title="District filter"
              @click="showDistrictPanel = !showDistrictPanel"
            >
              <FeatherIcon icon="filter" />
            </button>

            <!-- <button
              class="btn btn-sm btn-phoenix-secondary"
              type="button"
              @click="togglePanel('sensors')"
            >
              <FeatherIcon icon="cpu" />
              <span class="mobile-btn-text">Sensors</span>
            </button> -->

            <!-- <button
              class="btn btn-sm btn-phoenix-secondary"
              type="button"
              @click="togglePanel('incidents')"
            >
              <FeatherIcon icon="alert-triangle" />
              <span class="mobile-btn-text">Incidents</span>
            </button> -->

            <button
              class="btn btn-sm btn-phoenix-secondary"
              :class="{ active: showWeatherOverlay }"
              type="button"
              @click="toggleWeatherOverlay"
            >
              <FeatherIcon icon="cloud-rain" />
              <span class="mobile-btn-text">Weather</span>
            </button>

            <button
              class="btn btn-sm btn-phoenix-secondary stats-btn"
              :class="{ active: showAnalyticsPanel }"
              type="button"
              title="Toggle analytics"
              @click="showAnalyticsPanel = !showAnalyticsPanel"
            >
              <FeatherIcon :icon="showAnalyticsPanel ? 'eye-off' : 'eye'" />

              <span class="mobile-btn-text">
                {{ showAnalyticsPanel ? 'Hide Stats' : 'Show Stats' }}
              </span>
            </button>

            <button
              class="btn btn-sm btn-phoenix-secondary"
              :class="{ active: soundEnabled }"
              type="button"
              @click="toggleSound"
            >
              <FeatherIcon :icon="soundEnabled ? 'volume-2' : 'volume-x'" />
              <span class="mobile-btn-text">
                {{ soundEnabled ? 'Sound On' : 'Muted' }}
              </span>
            </button>

            <button
              class="btn btn-sm btn-phoenix-secondary full-map-btn"
              type="button"
              @click="toggleFullMap"
            >
              <FeatherIcon :icon="isFullMap ? 'minimize-2' : 'maximize-2'" />
              <span class="mobile-btn-text">
                {{ isFullMap ? 'Exit Full Map' : 'Full Map' }}
              </span>
            </button>
          </div>
        </div>

        <div v-if="showAnalyticsPanel" class="fire-analytics-panel">
          <div class="analytics-card fire-detected">
            <FeatherIcon icon="alert-triangle" />
            <span>Flood Detected</span>
            <strong>{{ floodAnalytics.floodDetected }}</strong>
          </div>

          <div class="analytics-card">
            <span>Max Water Level (m)</span>
            <strong class="orange">{{ floodAnalytics.maxWaterLevel }}</strong>
          </div>

          <div class="analytics-card">
            <span>Max Rainfall Intensity (mm/hr)</span>
            <strong class="cyan">{{ floodAnalytics.maxRainfallIntensity }}</strong>
          </div>

          <div class="analytics-card">
            <span>Average Rainfall (mm)</span>
            <strong class="yellow">{{ floodAnalytics.averageRainfall }}</strong>
          </div>
        </div>

        <div v-if="showAnalyticsPanel" class="bottom-analytics-panel">
          <div class="analytics-chart-card">
            <h6>Water Level (m)</h6>
            <Line :data="waterLevelData" :options="waterLevelChartOptions" />
          </div>

          <div class="analytics-chart-card">
            <h6>Total Rainfall (mm/hr)</h6>
            <Line :data="rainfallData" :options="rainfallChartOptions" />
          </div>

          <div class="analytics-chart-card">
            <h6>Tide (m)</h6>
            <Line :data="tideData" :options="tideChartOptions" />
          </div>
        </div>

        <div v-if="showDistrictPanel" class="district-floating-panel">
          <div
            v-for="(enabled, district) in districtToggles"
            :key="district"
            class="district-toggle-row"
          >
            <div class="district-label">
              <FeatherIcon icon="filter" />
              <span>{{ district.replace('_', '-') }}</span>
            </div>

            <button
              type="button"
              class="mini-toggle"
              :class="{ active: enabled }"
              @click="toggleDistrict(district)"
            >
              <span></span>
            </button>
          </div>
        </div>

        <!-- FLOATING LIST PANEL -->
        <div v-if="activePanel" class="map-floating-panel">
          <div class="floating-header">
            <h5>{{ activePanel === 'sensors' ? 'List of Sensors' : 'List of Incidents' }}</h5>

            <button type="button" class="floating-close" @click="activePanel = null">
              <FeatherIcon icon="x" />
            </button>
          </div>

          <div class="floating-search">
            <FeatherIcon icon="search" />
            <input
              v-model="searchKeyword"
              type="text"
              :placeholder="activePanel === 'sensors' ? 'Search sensors' : 'Search incidents'"
            />
          </div>

          <div class="floating-list">
            <template v-if="activePanel === 'sensors'">
              <button
                v-for="sensor in filteredSensors"
                :key="`${sensor.type}-${sensor.objectId}`"
                type="button"
                class="floating-item sensor-card"
                @click="goToFeature(sensor)"
              >
                <h6>{{ sensor.name }}</h6>

                <template v-if="sensor.type === 'tide'">
                  <p>
                    Tide: <strong>{{ sensor.tide ?? '-' }} m</strong>
                  </p>
                  <p>
                    Status: <strong>{{ sensor.status }}</strong>
                  </p>
                </template>

                <template v-else-if="sensor.type === 'radar'">
                  <p>
                    Radar Distance: <strong>{{ sensor.radarDistance ?? '-' }} m</strong>
                  </p>
                  <p>
                    Temperature: <strong>{{ sensor.temperature ?? '-' }} °C</strong>
                  </p>
                  <p>
                    Humidity: <strong>{{ sensor.humidity ?? '-' }} %</strong>
                  </p>
                </template>

                <template v-else>
                  <p>
                    Water Level: <strong>{{ sensor.waterLevel ?? '-' }} m</strong>
                  </p>
                  <p>
                    Status: <strong>{{ sensor.status ?? '-' }}</strong>
                  </p>
                </template>

                <small>Click to navigate to flood feature</small>
              </button>
            </template>

            <template v-else>
              <button
                v-for="incident in filteredIncidents"
                :key="incident.objectId"
                type="button"
                class="floating-item incident-card"
                @click="goToFeature(incident)"
              >
                <h6>{{ incident.type }}</h6>

                <p>
                  District: <strong>{{ incident.location }}</strong>
                </p>
                <p>
                  Severity: <strong>{{ incident.status }}</strong>
                </p>
                <p>
                  Remarks: <strong>{{ incident.remarks }}</strong>
                </p>
                <p>
                  Date Reported: <strong>{{ incident.reportedAt }}</strong>
                </p>
                <small>Click to navigate to incident location</small>
              </button>
            </template>
          </div>
        </div>

        <div v-if="mapError" class="map-error">
          <FeatherIcon icon="alert-triangle" />
          <h5>Map failed to load</h5>
          <p>{{ mapError }}</p>
        </div>
        <div v-if="loading" class="map-loading-overlay">
          <div class="spinner-wrapper">
            <div class="spinner"></div>
            <h6>Loading BRAVE Dashboard</h6>
            <p>Preparing WebMap, sensors, incidents, and analytics...</p>
          </div>
        </div>

        <!-- WEATHER LAYER CHIPS -->
        <div v-if="showWeatherOverlay" class="weather-layer-toolbar">
          <div class="dropdown weather-dropdown">
            <button
              class="btn btn-sm btn-phoenix-secondary dropdown-toggle weather-dropdown-btn"
              type="button"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <FeatherIcon :icon="currentWeatherOption.icon" />
              {{ currentWeatherOption.label }}
            </button>

            <ul class="dropdown-menu dropdown-menu-end weather-dropdown-menu">
              <li v-for="layer in weatherLayerOptions" :key="layer.id">
                <button
                  type="button"
                  class="dropdown-item weather-dropdown-item"
                  :class="{ active: activeWeatherLayer === layer.id }"
                  @click="changeWeatherLayer(layer.id)"
                >
                  <FeatherIcon :icon="layer.icon" />
                  <span>{{ layer.label }}</span>
                </button>
              </li>
            </ul>
          </div>

          <div class="weather-value-box">
            <span>{{ currentWeatherOption.label }}</span>
            <strong>{{ weatherSourceReady ? weatherPointerValue || '-' : 'Loading...' }}</strong>
          </div>
        </div>

        <!-- <div v-if="showWeatherOverlay" class="weather-value-box">
          <span>{{ weatherLayerOptions.find((l) => l.id === activeWeatherLayer)?.label }}</span>
          <strong>{{ weatherPointerValue || '-' }}</strong>
        </div> -->

        <!-- WEATHER OVERLAY -->
        <div v-show="showWeatherOverlay" ref="weatherMapDiv" class="weather-map-overlay"></div>

        <!-- ARCGIS MAP -->
        <div v-show="!mapError" ref="mapDiv" class="fire-map"></div>
      </div>

      <div class="side-panel">
        <div class="activity-feed-card">
          <div class="activity-feed-header">
            <div>
              <h5>Live Activity Feed</h5>
              <small>Latest sensor and incident updates</small>
            </div>

            <span class="live-pill">LIVE</span>
          </div>
          <div class="activity-filter-wrapper">
            <button
              type="button"
              class="activity-filter-toggle"
              @click="showActivityFilters = !showActivityFilters"
            >
              <div class="filter-toggle-left">
                <FeatherIcon icon="filter" />
                <span>Filters</span>
              </div>

              <FeatherIcon :icon="showActivityFilters ? 'chevron-up' : 'chevron-down'" />
            </button>

            <transition name="filter-collapse">
              <div v-if="showActivityFilters" class="activity-filter-bar">
                <div class="filter-field">
                  <label>From</label>
                  <input v-model="activityFilters.from" type="date" />
                </div>

                <div class="filter-field">
                  <label>To</label>
                  <input v-model="activityFilters.to" type="date" />
                </div>

                <div class="filter-field">
                  <label>District</label>
                  <select v-model="activityFilters.district">
                    <option value="">All</option>
                    <option>Brunei Muara</option>
                    <option>Belait</option>
                    <option>Tutong</option>
                    <option>Temburong</option>
                  </select>
                </div>

                <button class="activity-filter-btn" type="button" @click="applyActivityFilters">
                  <FeatherIcon icon="check" />
                </button>

                <button class="activity-reset-btn" type="button" @click="resetActivityFilters">
                  Reset
                </button>
              </div>
            </transition>
          </div>
          <div class="activity-feed-list">
            <button
              v-for="item in filteredActivityFeed"
              :key="item.id"
              type="button"
              class="activity-feed-item"
              :class="item.severity"
              @click="goToActivityItem(item)"
            >
              <div class="activity-pulse"></div>

              <div class="activity-content">
                <strong>{{ item.title }}</strong>
                <p>{{ item.message }}</p>
                <small>{{ item.time }}</small>
              </div>
            </button>
          </div>
        </div>
        <!-- SUMMARY GRID -->
        <div class="side-summary-grid">
          <div class="summary-card success">
            <span>Resolved</span>
            <h3>{{ resolvedIncidentCount }}</h3>
          </div>

          <div class="summary-card danger">
            <span>Unresolved</span>
            <h3>{{ unresolvedIncidentCount }}</h3>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Line } from 'vue-chartjs'
import FeatherIcon from '@/components/FeatherIcon.vue'

import WebMap from '@arcgis/core/WebMap'
import MapView from '@arcgis/core/views/MapView'
import Expand from '@arcgis/core/widgets/Expand'
import LayerList from '@arcgis/core/widgets/LayerList'
import Legend from '@arcgis/core/widgets/Legend'
import Search from '@arcgis/core/widgets/Search'
import BasemapGallery from '@arcgis/core/widgets/BasemapGallery'

import '@arcgis/core/assets/esri/themes/light/main.css'

const WEBMAP_ID = '17813afa14a4419583dc2a136bf51d99'

const mapDiv = ref(null)
const weatherMapDiv = ref(null)
const showWeatherOverlay = ref(false)
const activeWeatherLayer = ref('temperature')
const weatherPointerValue = ref('')
const weatherSourceReady = ref(false)

let weatherMap = null
let weatherLayer = null
let weatherSyncHandle = null
let weatherMoveHandle = null

const MAPTILER_KEY = 'CAgganf0DMElVWkxBZeq'

const weatherLayerOptions = [
  { id: 'temperature', label: 'Temperature', icon: 'thermometer' },
  { id: 'wind', label: 'Wind', icon: 'wind' },
  { id: 'radar', label: 'Radar', icon: 'radio' },
  { id: 'pressure', label: 'Pressure', icon: 'activity' },
  { id: 'precipitation', label: 'Precipitation', icon: 'cloud-rain' },
]
const mapError = ref('')
const loading = ref(false)
const isFullMap = ref(false)
const showAnalyticsPanel = ref(false)
const showDistrictPanel = ref(false)
const activePanel = ref(null)
const searchKeyword = ref('')
const theme = ref('light')

let view = null
let webmap = null

const stats = reactive({
  activeIncidents: 0,
  onlineSensors: 0,
  activeAlerts: 0,
  responseTeams: 0,
})

const alerts = ref([])
const sensors = ref([])
const incidents = ref([])

const reportView = ref('district')

function groupCount(items, key) {
  const result = {}

  items.forEach((item) => {
    const label = item[key] || 'Unknown'
    result[label] = (result[label] || 0) + 1
  })

  const max = Math.max(...Object.values(result), 1)

  return Object.entries(result)
    .map(([label, count]) => ({
      label,
      count,
      percent: Math.round((count / max) * 100),
    }))
    .sort((a, b) => b.count - a.count)
}

const floodSensors = ref([])
const tideSensors = ref([])
const riverSensors = ref([])

const districtToggles = ref({
  Brunei_Muara: false,
  Temburong: false,
  Belait: false,
  Tutong: false,
})

const layerTitles = {
  floodSensorLive: 'Flood Sensor (Live)',
  floodSensorSimulated: 'Flood Sensors (Simulated)',
  tideReading: 'Tide Readings (Simulated)',
  tideSensor: 'Tide Sensor (Simulated)',
  weatherSensor: 'Weather Sensor',
  riverGauge: 'River gauge',
}

/* =========================
   CHART DATA
========================= */

const floodAnalytics = computed(() => {
  const tideValues = tideSensors.value.map((s) => cleanNumber(s.tide)).filter((n) => n !== null)

  const rainfallValues = floodSensors.value
    .map((s) => cleanNumber(s.rainfall))
    .filter((n) => n !== null)

  const rainfallIntensityValues = floodSensors.value
    .map((s) => cleanNumber(s.rainfallIntensity))
    .filter((n) => n !== null)

  const max = (values) => (values.length ? Math.max(...values).toFixed(2) : '-')
  const avg = (values) =>
    values.length ? (values.reduce((sum, v) => sum + v, 0) / values.length).toFixed(2) : '-'

  return {
    floodDetected: tideSensors.value.filter((s) =>
      ['high', 'watch'].includes(String(s.status).toLowerCase()),
    ).length,
    maxWaterLevel: max(tideValues),
    maxRainfallIntensity: max(rainfallIntensityValues),
    averageRainfall: avg(rainfallValues),
  }
})

const waterLevelData = computed(() => ({
  labels: tideSensors.value.map((s) => s.name),
  datasets: [
    {
      label: 'Water Level',
      data: tideSensors.value.map((s) => cleanNumber(s.tide)),
      borderColor: '#ffffff',
      backgroundColor: '#ffffff',
      tension: 0.35,
      pointRadius: 4,
      borderWidth: 3,
    },
  ],
}))

const rainfallData = computed(() => ({
  labels: floodSensors.value.map((s) => s.name),
  datasets: [
    {
      label: 'Rainfall',
      data: floodSensors.value.map((s) => cleanNumber(s.rainfall)),
      borderColor: '#22c55e',
      backgroundColor: '#22c55e',
      tension: 0.35,
      pointRadius: 3,
      borderWidth: 3,
    },
  ],
}))

const tideData = computed(() => ({
  labels: tideSensors.value.map((s) => s.name),
  datasets: [
    {
      label: 'Tide',
      data: tideSensors.value.map((s) => cleanNumber(s.tide)),
      borderColor: '#f59e0b',
      backgroundColor: '#f59e0b',
      tension: 0.35,
      pointRadius: 4,
      borderWidth: 3,
    },
  ],
}))

const baseChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      labels: {
        color: '#fff',
      },
    },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}`,
      },
    },
  },
  scales: {
    x: {
      ticks: {
        color: '#fff',
        maxRotation: 45,
        minRotation: 45,
        font: { size: 9 },
      },
      grid: {
        color: 'rgba(255,255,255,0.08)',
      },
    },
    y: {
      ticks: {
        color: '#fff',
      },
      grid: {
        color: 'rgba(255,255,255,0.08)',
      },
    },
  },
}

const waterLevelChartOptions = {
  ...baseChartOptions,
  scales: {
    ...baseChartOptions.scales,
    y: {
      ...baseChartOptions.scales.y,
      min: 0,
      max: 3,
    },
  },
}

const rainfallChartOptions = {
  ...baseChartOptions,
  scales: {
    ...baseChartOptions.scales,
    y: {
      ...baseChartOptions.scales.y,
      min: 0,
      max: 100,
    },
  },
}

const tideChartOptions = {
  ...baseChartOptions,
  scales: {
    ...baseChartOptions.scales,
    y: {
      ...baseChartOptions.scales.y,
      min: 0,
      max: 3,
    },
  },
}

/* =========================
   FILTERED LISTS
========================= */

const filteredSensors = computed(() => {
  const keyword = searchKeyword.value.toLowerCase().trim()
  if (!keyword) return sensors.value

  return sensors.value.filter((sensor) => JSON.stringify(sensor).toLowerCase().includes(keyword))
})

const filteredIncidents = computed(() => {
  const keyword = searchKeyword.value.toLowerCase().trim()
  if (!keyword) return incidents.value

  return incidents.value.filter((incident) =>
    JSON.stringify(incident).toLowerCase().includes(keyword),
  )
})

/* =========================
   MAP INIT
========================= */

async function loadWebMap() {
  try {
    loading.value = true
    mapError.value = ''

    webmap = new WebMap({
      portalItem: { id: WEBMAP_ID },
    })

    await webmap.loadAll()

    console.log(
      'All WebMap layers:',
      webmap.allLayers.toArray().map((layer) => layer.title),
    )

    webmap.allLayers.toArray().forEach((layer) => {
      if ('popupEnabled' in layer) layer.popupEnabled = false
      if ('popupTemplate' in layer) layer.popupTemplate = null
    })

    view = new MapView({
      container: mapDiv.value,
      map: webmap,
      center: [114.7277, 4.5353],
      zoom: 9,
    })

    await view.when()

    view.on('pointer-move', handleWeatherPointerMove)

    view.popup.autoOpenEnabled = false
    view.popupEnabled = true

    view.on('click', handleMapClick)
    function handleWeatherPointerMove(event) {
      if (!showWeatherOverlay.value || !weatherLayer || !weatherSourceReady.value || !view) return

      const point = view.toMap({
        x: event.x,
        y: event.y,
      })

      if (!point) return

      const lng = point.longitude
      const lat = point.latitude

      const picked = weatherLayer.pickAt(lng, lat)

      if (!picked) {
        weatherPointerValue.value = '-'
        return
      }

      const numberValue =
        activeWeatherLayer.value === 'wind' ? picked.speedMetersPerSecond : picked.value

      if (numberValue === null || numberValue === undefined || Number.isNaN(Number(numberValue))) {
        weatherPointerValue.value = '-'
        return
      }

      const units = {
        temperature: '°C',
        wind: ' m/s',
        radar: ' dBZ',
        pressure: ' hPa',
        precipitation: ' mm',
      }

      weatherPointerValue.value = `${Number(numberValue).toFixed(1)}${units[activeWeatherLayer.value]}`
    }

    await refreshLiveData()
    await addMapWidgets()
  } catch (error) {
    console.error(error)
    mapError.value =
      'Unable to load Flood WebMap. Check WebMap ID, layer sharing, credentials, or network access.'
  } finally {
    loading.value = false
  }
}

async function handleMapClick(event) {
  const hit = await view.hitTest(event)

  const result = hit.results.find((r) => {
    const title = r.graphic?.layer?.title?.toLowerCase() || ''
    return (
      title.includes('flood sensor') ||
      title.includes('tide reading') ||
      title.includes('shelter') ||
      title.includes('rescue') || // ✅ ADD THIS
      title.includes('fire')
    )
  })

  if (!result) return

  const graphic = result.graphic
  const attrs = graphic.attributes || {}
  const layer = graphic.layer
  const layerTitle = layer?.title?.toLowerCase() || ''

  view.closePopup()

  let fullAttrs = attrs

  const objectId =
    attrs.OBJECTID || attrs.objectid || attrs.ObjectId || attrs.objectId || attrs.FID || attrs.fid

  if (objectId && layer?.createQuery) {
    try {
      const objectIdField = layer.objectIdField || 'OBJECTID'

      const query = layer.createQuery()
      query.where = `${objectIdField} = ${objectId}`
      query.outFields = ['*']
      query.returnGeometry = true

      const response = await layer.queryFeatures(query)

      if (response.features?.length) {
        fullAttrs = response.features[0].attributes || attrs
      }
    } catch (e) {
      console.warn('Failed to fetch full flood popup data:', e)
    }
  }

  const isFloodSensorLive = layerTitle.includes('flood sensor (live)')
  const isFloodSensorSimulated = layerTitle.includes('flood sensors (simulated)')
  const isTideReading = layerTitle.includes('tide readings')
  const isShelter = layerTitle.includes('shelter')

  let title =
    fullAttrs.Name ||
    fullAttrs.name ||
    fullAttrs.Station_id ||
    fullAttrs.station_id ||
    fullAttrs.station_name ||
    fullAttrs.Location ||
    fullAttrs.location ||
    'Flood Feature'

  let content = ''

  // ✅ Flood Sensor (Live)
  if (isFloodSensorLive) {
    const waterLevel = fullAttrs.r_20_dist ?? '-'
    const rainfallIntensity = fullAttrs.r_9_pi ?? '-'
    const totalRainfall = fullAttrs.r_9_pt ?? '-'
    const dateTime = fullAttrs.r_ts_fmt ?? fullAttrs.date_only ?? '-'

    content = `
      <div style="padding:8px 4px;">
        <table style="width:100%; border-collapse:collapse;">
          <tbody>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Name</td><td style="padding:6px 10px;">${title}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Water Level (m)</td><td style="padding:6px 10px;">${waterLevel}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Rainfall Intensity (mm)</td><td style="padding:6px 10px;">${rainfallIntensity}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Total Rainfall (mm/hr)</td><td style="padding:6px 10px;">${totalRainfall}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Date and Time</td><td style="padding:6px 10px;">${dateTime}</td></tr>
          </tbody>
        </table>
      </div>
    `
  }

  // ✅ Flood Sensors (Simulated)
  else if (isFloodSensorSimulated) {
    title = fullAttrs.Name || fullAttrs.name || fullAttrs.Sensor || title

    const status = fullAttrs.Status ?? fullAttrs.status ?? '-'
    const location = fullAttrs.Location ?? fullAttrs.location ?? '-'
    const mukim = fullAttrs.Mukim ?? fullAttrs.mukim ?? '-'
    const district = fullAttrs.District ?? fullAttrs.district ?? '-'
    const rainfall =
      fullAttrs.Rainfall ??
      fullAttrs.rainfall ??
      fullAttrs.Rainfall_mm_hr ??
      fullAttrs.Rainfall__mm_hr_ ??
      '-'
    const waterLevel =
      fullAttrs.Water_level ??
      fullAttrs.Water_Level ??
      fullAttrs.Water_level__m_ ??
      fullAttrs.water_level ??
      '-'
    const dateTime =
      fullAttrs.Date_and_Time ??
      fullAttrs.date_and_time ??
      fullAttrs.datetime ??
      fullAttrs.DateTime ??
      fullAttrs.Start_date_time ??
      '-'

    content = `
      <div style="padding:8px 4px;">
        <table style="width:100%; border-collapse:collapse;">
          <tbody>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Status</td><td style="padding:6px 10px;">${status}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Location</td><td style="padding:6px 10px;">${location}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Mukim</td><td style="padding:6px 10px;">${mukim}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">District</td><td style="padding:6px 10px;">${district}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Rainfall (mm/hr)</td><td style="padding:6px 10px;">${rainfall}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Water level (m)</td><td style="padding:6px 10px;">${waterLevel}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Date and Time</td><td style="padding:6px 10px;">${dateTime}</td></tr>
          </tbody>
        </table>
      </div>
    `
  }

  // ✅ Tide Readings (Simulated)
  else if (isTideReading) {
    title = fullAttrs.Station_id || fullAttrs.station_id || fullAttrs.station_name || 'Tide Station'

    const tide = fullAttrs.Tide_m ?? fullAttrs.Tide ?? fullAttrs.tide ?? '-'
    const status = fullAttrs.Status ?? fullAttrs.status ?? '-'
    const stationName = fullAttrs.station_name ?? fullAttrs.Station_name ?? '-'
    const lat = fullAttrs.lat ?? fullAttrs.latitude ?? '-'
    const lon = fullAttrs.long ?? fullAttrs.longitude ?? '-'
    const dateTime = fullAttrs.Start_date_time ?? fullAttrs.start_date_time ?? '-'

    content = `
      <div style="padding:8px 4px;">
        <table style="width:100%; border-collapse:collapse;">
          <tbody>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Station ID</td><td style="padding:6px 10px;">${title}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Station Name</td><td style="padding:6px 10px;">${stationName}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Tide Level (m)</td><td style="padding:6px 10px;">${tide}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Status</td><td style="padding:6px 10px;">${status}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Latitude</td><td style="padding:6px 10px;">${lat}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Longitude</td><td style="padding:6px 10px;">${lon}</td></tr>
            <tr><td style="padding:6px 10px;font-weight:700;background:#f1f1f1;">Date and Time</td><td style="padding:6px 10px;">${dateTime}</td></tr>
          </tbody>
        </table>
      </div>
    `
  }

  // ✅ Shelters
  else if (isShelter) {
    title = fullAttrs.Shelter_Name || fullAttrs.Name || 'Shelter'

    const shelterType = fullAttrs.Shelter_Type || '-'
    const mukim = fullAttrs.Mukim || '-'
    const district = fullAttrs.District || '-'
    const capacity = fullAttrs.Capacity_Estimated ?? '-'
    const status = fullAttrs.Status || '-'

    content = `
    <div style="padding:8px 4px;">
      <table style="width:100%; border-collapse:collapse;">
        <tbody>
          <tr><td class="label">Shelter Name</td><td>${title}</td></tr>
          <tr><td class="label">District</td><td>${district}</td></tr>
          <tr><td class="label">Mukim</td><td>${mukim}</td></tr>
          <tr><td class="label">Shelter Type</td><td>${shelterType}</td></tr>
          <tr><td class="label">Capacity</td><td>${capacity}</td></tr>
          <tr><td class="label">Status</td><td>${status}</td></tr>
        </tbody>
      </table>
    </div>
  `
  } else if (layerTitle.includes('rescue') || layerTitle.includes('fire')) {
    const title = fullAttrs.Name || fullAttrs.name || fullAttrs.Station_Name || 'Rescue Station'

    const address = fullAttrs.Address || fullAttrs.address || fullAttrs.Location || '-'

    const operation = fullAttrs.Operation || fullAttrs.operation || fullAttrs.Branch || '-'

    content = `
    <div style="padding:8px 4px;">
      <table style="width:100%; border-collapse:collapse;">
        <tbody>
          <tr>
            <td class="label">🚒 Station</td>
            <td>${title}</td>
          </tr>
          <tr>
            <td class="label">📍 Address</td>
            <td>${address}</td>
          </tr>
          <tr>
            <td class="label">🏢 Operation</td>
            <td>${operation}</td>
          </tr>
        </tbody>
      </table>
    </div>
  `
  }

  if (!content) return

  view.openPopup({
    title,
    location: graphic.geometry || event.mapPoint,
    content,
  })
}

const currentWeatherOption = computed(() => {
  return (
    weatherLayerOptions.find((layer) => layer.id === activeWeatherLayer.value) ||
    weatherLayerOptions[0]
  )
})

function mapFloodManualIncidentFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    console.log('Simulated flood attrs:', attrs)

    const location =
      attrs.Location || attrs.location || attrs.Name || attrs.name || 'Flood Location'

    const status = attrs.Status || attrs.status || '-'
    const mukim = attrs.Mukim || attrs.mukim || '-'
    const district = attrs.District || attrs.district || '-'

    const rainfall =
      attrs.Rainfall__mm_hr_ ?? attrs.Rainfall_mm_hr ?? attrs.Rainfall ?? attrs.rainfall ?? '-'

    const waterLevel =
      attrs.Water_level__m_ ??
      attrs.Water_Level__m_ ??
      attrs.Water_level ??
      attrs.Water_Level ??
      attrs.water_level ??
      '-'

    const reportedAt =
      attrs.Start_date_time ||
      attrs.start_date_time ||
      attrs.Date_and_Time ||
      attrs.date_and_time ||
      attrs.datetime ||
      attrs.DateTime ||
      '-'

    return {
      objectId: `simulated-${attrs.OBJECTID || attrs.FID}`,
      feedType: 'simulated',
      type: 'Flood Sensor',
      title: `${location} • ${status}`,
      location,
      mukim,
      district,
      status,
      rainfall,
      waterLevel,
      remarks: attrs.Remarks || attrs.remarks || '-',
      reportedAt: formatDate(reportedAt),
      graphic: feature,
      geometry: feature.geometry,
    }
  })
}

/* =========================
   LIVE DATA
========================= */

async function refreshLiveData() {
  if (!webmap) return

  loading.value = true

  try {
    const floodLiveLayer =
      findLayerByTitle('Flood Sensor (Live)') || findLayerByTitleIncludes('flood sensor (live)')

    const floodSimulatedLayer =
      findLayerByTitle('Flood Sensors (Simulated)') || findLayerByTitleIncludes('flood sensors')

    console.log('Live layer:', floodLiveLayer?.title)
    console.log('Simulated layer:', floodSimulatedLayer?.title)

    const liveFeatures = floodLiveLayer ? await getFeatures(floodLiveLayer, { limit: 100 }) : []

    const simulatedFeatures = floodSimulatedLayer
      ? await getFeatures(floodSimulatedLayer, { limit: 100 })
      : []

    console.log('Live features:', liveFeatures.length)
    console.log('Simulated features:', simulatedFeatures.length)

    const liveFeedItems = mapFloodSensorFeedFeatures(liveFeatures)
    const simulatedFeedItems = mapFloodManualIncidentFeatures(simulatedFeatures)

    incidents.value = [...simulatedFeedItems, ...liveFeedItems]

    console.log('Final activity incidents:', incidents.value)
    const floodSensorLayer =
      findLayerByTitle(layerTitles.floodSensorLive) ||
      findLayerByTitle(layerTitles.floodSensorSimulated) ||
      findLayerByTitleIncludes('flood sensor')

    const manualIncidentLayer =
      findLayerByTitle('Flood Sensors (Simulated)') || findLayerByTitleIncludes('flood sensors')
    console.log('Flood sensor layer:', floodSensorLayer?.title)
    console.log('Manual incident layer:', manualIncidentLayer?.title)

    const floodSensorFeatures = await getFeatures(floodSensorLayer, { limit: 100 })
    const manualIncidentFeatures = manualIncidentLayer
      ? await getFeatures(manualIncidentLayer, { limit: 100 })
      : []

    console.log('Manual incident features:', manualIncidentFeatures)
    const sensorFeedItems = mapFloodSensorFeedFeatures(floodSensorFeatures)
    const manualIncidentItems = mapFloodManualIncidentFeatures(manualIncidentFeatures)

    incidents.value = [...manualIncidentItems, ...sensorFeedItems]

    const tideLayer =
      findLayerByTitle('Tide Sensor (Simulated)') ||
      findLayerByTitle('Tide Reading (Simulated)') ||
      findLayerByTitleIncludes('tide')

    const riverLayer = findLayerByTitle(layerTitles.riverGauge) || findLayerByTitleIncludes('river')

    const alertLayer = null
    const tideFeatures = await getFeatures(tideLayer, { limit: 100 })
    const riverFeatures = await getFeatures(riverLayer, { limit: 100 })

    tideSensors.value = mapTideFeatures(tideFeatures)
    riverSensors.value = mapRiverFeatures(riverFeatures)

    sensors.value = [...floodSensors.value, ...tideSensors.value, ...riverSensors.value]
    // incidents.value = mapFloodIncidentFeatures(floodSensorFeatures)
    alerts.value = tideSensors.value
      .filter((item) => cleanNumber(item.tide) >= 2)
      .map((item) => ({
        objectId: item.objectId,
        title: item.name,
        description: `Tide level ${item.tide}m detected.`,
        status: item.status || 'Active',
      }))
    stats.onlineSensors = sensors.value.length
    stats.activeIncidents = incidents.value.length
    stats.activeAlerts = alerts.value.length
    stats.responseTeams = 0

    refreshLayerData()
  } catch (error) {
    console.warn('Flood refresh failed:', error)
  } finally {
    loading.value = false
  }
}

async function toggleWeatherOverlay() {
  showWeatherOverlay.value = !showWeatherOverlay.value

  await nextTick()

  if (showWeatherOverlay.value) {
    await forceWeatherSafeBasemap()
    initWeatherOverlay()
    syncWeatherToArcGIS()
  }
}

function initWeatherOverlay() {
  if (weatherMap || !weatherMapDiv.value || !window.maptilersdk || !window.maptilerweather) return

  window.maptilersdk.config.apiKey = MAPTILER_KEY

  weatherMap = new window.maptilersdk.Map({
    container: weatherMapDiv.value,

    style: {
      version: 8,
      sources: {},
      layers: [
        {
          id: 'empty-background',
          type: 'background',
          paint: {
            'background-color': 'rgba(0,0,0,0)',
            'background-opacity': 0,
          },
        },
      ],
    },

    center: [114.7277, 4.5353],
    zoom: 8,

    interactive: false,

    attributionControl: false,
    navigationControl: false,
    geolocateControl: false,
    terrainControl: false,
  })

  weatherMap.on('load', () => {
    addWeatherLayer(activeWeatherLayer.value)
    syncWeatherToArcGIS()
  })
  const canvas = weatherMap.getCanvas()

  canvas.style.background = 'transparent'
  canvas.style.mixBlendMode = 'screen'
  weatherSyncHandle = view.watch(['center', 'zoom', 'rotation'], () => {
    syncWeatherToArcGIS()
  })
}

function syncWeatherToArcGIS() {
  if (!view || !weatherMap || !view.center) return

  const center = view.center

  weatherMap.jumpTo({
    center: [center.longitude, center.latitude],
    zoom: view.zoom,
    bearing: view.rotation || 0,
  })

  weatherMap.resize()
}

function changeWeatherLayer(type) {
  activeWeatherLayer.value = type
  addWeatherLayer(type)
}

function addWeatherLayer(type) {
  if (!weatherMap || !window.maptilerweather) return

  if (weatherLayer && weatherMap.getLayer(activeWeatherLayer.value)) {
    try {
      weatherMap.removeLayer(activeWeatherLayer.value)
    } catch (e) {
      console.warn(e)
    }
  }

  if (weatherLayer) {
    try {
      weatherMap.removeLayer(weatherLayer.id)
    } catch (e) {
      console.warn(e)
    }
  }

  switch (type) {
    case 'precipitation':
      weatherLayer = new window.maptilerweather.PrecipitationLayer({
        id: type,
        opacity: 0.75,
      })
      break

    case 'pressure':
      weatherLayer = new window.maptilerweather.PressureLayer({
        id: type,
        opacity: 0.75,
      })
      break

    case 'radar':
      weatherLayer = new window.maptilerweather.RadarLayer({
        id: type,
        opacity: 0.8,
      })
      break

    case 'wind':
      weatherLayer = new window.maptilerweather.WindLayer({
        id: type,
        opacity: 0.85,
      })
      break

    default:
      weatherLayer = new window.maptilerweather.TemperatureLayer({
        id: type,
        opacity: 0.75,
        colorramp: window.maptilerweather.ColorRamp.builtin.TEMPERATURE_3,
      })
      break
  }
  weatherLayer.on('sourceReady', () => {
    weatherSourceReady.value = true
  })
  weatherMap.addLayer(weatherLayer)
}
function isImageryBasemap() {
  const title = view?.map?.basemap?.title?.toLowerCase() || ''

  return title.includes('imagery')
}
/* =========================
   MAPPERS
========================= */

function mapFloodRadarFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    return {
      objectId: attrs.FID || attrs.OBJECTID,
      name: attrs.name || 'Flood Radar',
      rainfall: attrs.r_20_dist ?? null,
      rainfallIntensity: attrs.r_20_rel ?? null,
      temperature: attrs.r_20_tmp ?? null,
      humidity: attrs.r_20_rel ?? null,
      radarDistance: attrs.r_20_dist ?? null,
      status: attrs.Status || 'Active',
      type: 'radar',
      graphic: feature,
    }
  })
}

function mapTideFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const tideValue = attrs.Tide_m ?? attrs.tide_m ?? attrs.Tide ?? attrs.tide ?? attrs.TIDE ?? null

    return {
      objectId: attrs.OBJECTID,
      name: attrs.Station_id || attrs.station_id || attrs.station_name || 'Tide Station',
      tide: tideValue,
      waterLevel: tideValue,
      status: attrs.Status ?? attrs.status ?? '-',
      type: 'tide',
      graphic: feature,
    }
  })
}

function mapRiverFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    return {
      objectId: attrs.OBJECTID || attrs.FID,
      name: attrs.station_name || attrs.Name || 'River Gauge',
      waterLevel: attrs.Tide ?? attrs.level ?? attrs.water_level ?? null,
      status: attrs.Status ?? '-',
      type: 'river',
      graphic: feature,
    }
  })
}

function mapFloodIncidentFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const status = attrs.Status ?? attrs.status ?? '-'
    const location = attrs.Location ?? attrs.location ?? 'Flood Location'
    const mukim = attrs.Mukim ?? attrs.mukim ?? '-'
    const district = attrs.District ?? attrs.district ?? '-'

    const rainfall =
      attrs.Rainfall__mm_hr_ ?? attrs.Rainfall_mm_hr ?? attrs.Rainfall ?? attrs.rainfall ?? '-'

    const waterLevel =
      attrs.Water_level__m_ ??
      attrs.Water_Level__m_ ??
      attrs.Water_level ??
      attrs.Water_Level ??
      attrs.water_level ??
      '-'

    const dateTime =
      attrs.Start_date_time ??
      attrs.start_date_time ??
      attrs.Date_and_Time ??
      attrs.date_and_time ??
      attrs.datetime ??
      attrs.DateTime ??
      '-'

    return {
      objectId: attrs.OBJECTID || attrs.FID,
      graphic: feature,
      geometry: feature.geometry,

      type: 'Flood',
      name: location,
      location,
      mukim,
      district,
      status,
      rainfall,
      waterLevel,
      reportedAt: formatDate(dateTime),
    }
  })
}

function getFloodStatus(waterLevel) {
  const value = cleanNumber(waterLevel)

  if (value === null) return 'Active'
  if (value >= 2) return 'High'
  if (value >= 1.5) return 'Watch'

  return 'Normal'
}

/* =========================
   MAP WIDGETS
========================= */

async function addMapWidgets() {
  const searchWidget = new Search({ view })
  const layerList = new LayerList({ view })
  const basemapGallery = new BasemapGallery({ view })

  const legendLayers = [
    findLayerByTitle('Flood Sensor (Live)'),
    findLayerByTitle('Flood Sensors (Simulated)'),
    findLayerByTitle('Tide Readings (Simulated)'),
    findLayerByTitle('Shelters'),
    findLayerByTitle('Fire and Rescue'), // ✅ ADD THIS
  ].filter(Boolean)

  const legend = new Legend({
    view,
    layerInfos: legendLayers.map((layer) => ({
      layer,
      title: layer.title,
    })),
  })

  view.ui.add(searchWidget, 'top-left')

  view.ui.add(
    [
      new Expand({
        view,
        content: legend,
        expandIcon: 'legend',
        expandTooltip: 'Legend',
        expanded: true,
      }),

      new Expand({
        view,
        content: basemapGallery,
        expandIcon: 'basemap',
        expandTooltip: 'Basemap',
      }),
    ],
    'top-right',
  )
}

async function forceWeatherSafeBasemap() {
  if (!view) return

  if (isImageryBasemap()) {
    view.map.basemap = 'streets-night-vector'
    await view.when()
    view.map.watch('basemap', async () => {
      if (showWeatherOverlay.value) {
        await forceWeatherSafeBasemap()
      }
    })
    syncWeatherToArcGIS()
  }
}
/* =========================
   ACTIONS
========================= */

function togglePanel(panel) {
  activePanel.value = activePanel.value === panel ? null : panel
  searchKeyword.value = ''
}

async function toggleFullMap() {
  isFullMap.value = !isFullMap.value

  document.body.classList.toggle('full-map-open', isFullMap.value)
  document.documentElement.classList.toggle('full-map-open', isFullMap.value)

  await nextTick()

  if (view) view.resize()
}

async function toggleDistrict(district) {
  districtToggles.value[district] = !districtToggles.value[district]
  await applyDistrictFilter()
}

async function applyDistrictFilter() {
  if (!webmap) return

  const activeDistricts = Object.keys(districtToggles.value).filter(
    (district) => districtToggles.value[district],
  )

  const where =
    activeDistricts.length > 0
      ? `district IN (${activeDistricts.map((district) => `'${district}'`).join(',')})`
      : '1=1'

  webmap.allLayers.toArray().forEach((layer) => {
    const title = layer.title?.toLowerCase() || ''

    if (
      (title.includes('flood') || title.includes('tide') || title.includes('river')) &&
      'definitionExpression' in layer
    ) {
      layer.definitionExpression = where
      layer.refresh?.()
    }
  })

  await refreshLiveData()
}

async function goToFeature(item) {
  if (!view) return

  const geometry = item?.graphic?.geometry || item?.geometry

  if (!geometry) {
    console.warn('No geometry found for item:', item)
    return
  }

  await view.goTo(
    {
      target: geometry,
      zoom: 15,
    },
    {
      duration: 900,
      easing: 'ease-in-out',
    },
  )

  view.openPopup({
    title: item.name || item.location || item.type || 'Flood Feature',
    location: geometry,
    content: buildPopupContent(item),
  })
}
function buildPopupContent(item) {
  if (item.type === 'tide') {
    return `
      <div style="padding:8px 4px;">
        <p><strong>Station :</strong> ${item.name || '-'}</p>
        <p><strong>Tide :</strong> ${item.tide || '-'} m</p>
        <p><strong>Status :</strong> ${item.status || '-'}</p>
      </div>
    `
  }

  if (item.type === 'radar') {
    return `
      <div style="padding:8px 4px;">
        <p><strong>Sensor :</strong> ${item.name || '-'}</p>
        <p><strong>Radar Distance :</strong> ${item.radarDistance || '-'} m</p>
        <p><strong>Temperature :</strong> ${item.temperature || '-'} °C</p>
        <p><strong>Humidity :</strong> ${item.humidity || '-'} %</p>
      </div>
    `
  }

  return `
    <div style="padding:8px 4px;">
      <p><strong>Name :</strong> ${item.name || item.type || '-'}</p>
      <p><strong>Status :</strong> ${item.status || '-'}</p>
    </div>
  `
}

function getActivitySeverity(status) {
  const value = String(status || '').toLowerCase()

  if (value.includes('critical') || value.includes('danger') || value.includes('high')) {
    return 'critical'
  }

  if (value.includes('warning') || value.includes('watch') || value.includes('medium')) {
    return 'warning'
  }

  return 'normal'
}

function getSensorSeverity(sensor) {
  const tide = cleanNumber(sensor.tide)
  const waterLevel = cleanNumber(sensor.waterLevel)

  if (tide !== null && tide >= 2) return 'critical'
  if (waterLevel !== null && waterLevel >= 2) return 'critical'

  const status = String(sensor.status || '').toLowerCase()

  if (status.includes('high') || status.includes('danger')) return 'critical'
  if (status.includes('watch') || status.includes('warning')) return 'warning'

  return 'normal'
}

function buildSensorActivityMessage(sensor) {
  if (sensor.type === 'tide') {
    return `Tide level ${sensor.tide ?? '-'} m • ${sensor.status ?? '-'}`
  }

  if (sensor.type === 'radar') {
    return `Radar distance ${sensor.radarDistance ?? '-'} m • Humidity ${sensor.humidity ?? '-'}%`
  }

  return `Water level ${sensor.waterLevel ?? '-'} m • ${sensor.status ?? '-'}`
}

/* =========================
   HELPERS
========================= */

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
  if (!layer || !layer.createQuery) return []

  try {
    const query = layer.createQuery()
    query.where = options.where || '1=1'
    query.outFields = ['*']
    query.returnGeometry = true
    query.num = options.limit || 50

    if (options.orderByFields) {
      query.orderByFields = options.orderByFields
    }

    const result = await layer.queryFeatures(query)
    return result.features || []
  } catch (error) {
    console.warn(`Query failed for ${layer.title}`, error)
    return []
  }
}

function refreshLayerData() {
  if (!webmap) return

  webmap.allLayers.forEach((layer) => {
    if (typeof layer.refresh === 'function') {
      layer.refresh()
    }
  })
}

function cleanNumber(value) {
  if (value === null || value === undefined || value === '-') return null

  const number = Number(String(value).replace(/[^\d.-]/g, ''))
  return Number.isNaN(number) ? null : number
}

function formatDate(value) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString()
}

function handleThemeChanged(event) {
  theme.value = event.detail
}

/// New Features

const activityFeed = computed(() => {
  const alertItems = alerts.value.map((alert) => ({
    id: `alert-${alert.objectId}`,
    type: 'alert',
    severity: getActivitySeverity(alert.status),
    title: alert.title || 'Flood Alert',
    message: alert.description || 'Flood alert detected.',
    time: 'Live update',
    source: alert,
  }))

  const incidentItems = incidents.value.map((incident) => ({
    id: `${incident.feedType}-${incident.objectId}`,
    type: 'incident',
    severity: getActivitySeverity(incident.status),
    title: incident.title || `${incident.location} • ${incident.status}`,
    message:
      incident.feedType === 'simulated'
        ? `${incident.mukim}, ${incident.district} • Rainfall: ${incident.rainfall}mm/hr • Water Level: ${incident.waterLevel}m`
        : `Live sensor • Rainfall: ${incident.rainfall}mm/hr • Water Level: ${incident.waterLevel}m`,
    time: incident.reportedAt || 'Live update',
    source: incident,
  }))

  const sensorItems = sensors.value.slice(0, 8).map((sensor) => ({
    id: `sensor-${sensor.type}-${sensor.objectId}`,
    type: 'sensor',
    severity: getSensorSeverity(sensor),
    title: sensor.name || 'Flood Sensor',
    message: buildSensorActivityMessage(sensor),
    time: 'Sensor reading',
    source: sensor,
  }))

  return [...alertItems, ...incidentItems, ...sensorItems].slice(0, 15)
})

const filteredActivityFeed = computed(() => {
  return activityFeed.value.filter((item) => {
    const source = item.source || {}

    const hasDateFilter = activityFilters.from || activityFilters.to
    const hasDistrictFilter = activityFilters.district

    const itemDate = parseActivityDate(
      source.reportedAt ||
        source.Start_date_time ||
        source.start_date_time ||
        source.Date_and_Time ||
        source.date_and_time ||
        item.time,
    )

    // If date filter is active, hide items with no real date
    if (hasDateFilter && !itemDate) return false

    if (activityFilters.from) {
      const fromDate = new Date(`${activityFilters.from}T00:00:00`)
      if (itemDate < fromDate) return false
    }

    if (activityFilters.to) {
      const toDate = new Date(`${activityFilters.to}T23:59:59`)
      if (itemDate > toDate) return false
    }

    if (hasDistrictFilter) {
      const districtText = String(
        source.district ||
          source.District ||
          source.location ||
          source.Location ||
          item.message ||
          '',
      ).toLowerCase()

      if (!districtText.includes(activityFilters.district.toLowerCase())) {
        return false
      }
    }

    return true
  })
})
// new features
const hoverSensorCard = reactive({
  visible: false,
  x: 0,
  y: 0,
  name: '',
  temperature: '-',
  humidity: '-',
  battery: '-',
  status: 'ONLINE',
  statusClass: 'online',
})

let hoverTimer = null
let lastHoverObjectId = null

function handleSensorHover(event) {
  clearTimeout(hoverTimer)

  hoverTimer = setTimeout(async () => {
    if (!view) return

    const hit = await view.hitTest(event)

    const result = hit.results.find((item) => {
      const title = item.graphic?.layer?.title?.toLowerCase() || ''

      return (
        title.includes('sensor') ||
        title.includes('readings') ||
        title.includes('measurement') ||
        title.includes('dryad') ||
        title.includes('alert')
      )
    })

    if (!result) {
      hoverSensorCard.visible = false
      lastHoverObjectId = null
      return
    }

    const graphic = result.graphic
    const layer = graphic.layer
    let attrs = graphic.attributes || {}

    const objectId =
      attrs.OBJECTID || attrs.objectid || attrs.ObjectId || attrs.objectId || attrs.FID || attrs.fid

    if (objectId && objectId === lastHoverObjectId) {
      hoverSensorCard.x = event.x + 18
      hoverSensorCard.y = event.y + 18
      return
    }

    lastHoverObjectId = objectId

    const fullFeature = await queryFeatureByObjectId(layer, attrs)

    if (fullFeature?.attributes) {
      attrs = {
        ...attrs,
        ...fullFeature.attributes,
      }
    }

    const name = getAttr(attrs, [
      'device_nam',
      'Device_nam',
      'DEVICE_NAM',
      'name',
      'Name',
      'NAME',
      'ID',
      'eui',
      'EUI',
    ])

    const temperature = getAttr(attrs, [
      'temperature',
      'Temperature',
      'TEMPERATURE',
      'temperatur',
      'Temperatur',
      'TEMPERATUR',
    ])

    const humidity = getAttr(attrs, [
      'humidity',
      'Humidity',
      'HUMIDITY',
      'humid',
      'Humid',
      'humidty',
      'humidit',
    ])

    const battery = getAttr(attrs, [
      'energy_percentage',
      'Energy_Percentage',
      'ENERGY_PERCENTAGE',
      'energy_lev',
      'Energy_Level',
      'battery',
      'Battery',
      'BATTERY',
      'etag',
    ])

    hoverSensorCard.visible = true
    hoverSensorCard.x = event.x + 18
    hoverSensorCard.y = event.y + 18
    hoverSensorCard.name = name || 'Sensor'
    hoverSensorCard.temperature = formatHoverValue(temperature, '°C')
    hoverSensorCard.humidity = formatHoverValue(humidity, '%')
    hoverSensorCard.battery = formatHoverValue(battery, '%')
    hoverSensorCard.status = 'ONLINE'
    hoverSensorCard.statusClass = 'online'
  }, 80)
}

function mapFloodSensorFeedFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const name = attrs.name || 'Flood Sensor'
    const waterLevel = attrs.r_20_dist ?? '-'
    const rainfall = attrs.r_9_pt ?? '-'
    const intensity = attrs.r_9_pi ?? '-'
    const reportedAt = attrs.r_ts_fmt || attrs.date_time || attrs.date_only || '-'

    return {
      objectId: `sensor-${attrs.FID || attrs.OBJECTID}`,
      feedType: 'sensor',
      type: 'Sensor Alert',

      title: `${name} • Live Sensor`,
      location: name,
      mukim: '-',
      district: '-',
      status: getFloodStatus(waterLevel),

      rainfall,
      waterLevel,
      intensity,

      remarks: `Rainfall: ${rainfall}mm/hr • Water Level: ${waterLevel}m • Intensity: ${intensity}mm`,

      reportedAt: formatDate(reportedAt),

      graphic: feature,
      geometry: feature.geometry,
    }
  })
}

function formatHoverValue(value, unit) {
  if (value === null || value === undefined || value === '' || value === '-') return '-'

  const clean = String(value).replace(/[^\d.-]/g, '')
  const number = Number(clean)

  if (Number.isNaN(number)) return '-'

  return `${number}${unit}`
}

async function goToActivityItem(item) {
  if (!item?.source) return

  activePanel.value = null

  if (item.type === 'alert') {
    const matchingSensor = sensors.value.find(
      (sensor) => String(sensor.objectId) === String(item.source.objectId),
    )

    if (matchingSensor) {
      await goToFeature(matchingSensor)
    }

    return
  }

  await goToFeature(item.source)
}
const soundEnabled = ref(localStorage.getItem('braveSoundEnabled') !== 'false')
const selectedSound = ref(localStorage.getItem('braveAlertSound') || 'chime')
const previousAlertCount = ref(0)
const previousIncidentCount = ref(0)
let liveRefreshInterval = null
const knownIncidentIds = ref(new Set())
const firstLiveLoadDone = ref(false)

const alertSounds = {
  chime: '/sounds/alert-chime.mp3',
  siren: '/sounds/emergency-siren.mp3',
  notification: '/sounds/notification.mp3',
}

let alertAudio = null

function toggleSound() {
  soundEnabled.value = !soundEnabled.value
  localStorage.setItem('braveSoundEnabled', String(soundEnabled.value))
}

function changeAlertSound(type) {
  selectedSound.value = type
  localStorage.setItem('braveAlertSound', type)
}

function playAlertSound() {
  if (!soundEnabled.value) return

  const src = alertSounds[selectedSound.value]
  if (!src) return

  if (alertAudio) {
    alertAudio.pause()
    alertAudio.currentTime = 0
  }

  alertAudio = new Audio(src)
  alertAudio.volume = selectedSound.value === 'siren' ? 0.45 : 0.25
  alertAudio.play().catch(() => {
    console.warn('Sound blocked until user interacts with the page.')
  })
}

function playSpecificAlertSound(type) {
  if (!soundEnabled.value) return

  const src = alertSounds[type]
  if (!src) return

  if (alertAudio) {
    alertAudio.pause()
    alertAudio.currentTime = 0
  }

  alertAudio = new Audio(src)

  // louder for emergency
  alertAudio.volume = type === 'siren' ? 0.45 : 0.25

  alertAudio.play().catch(() => {
    console.warn('Sound blocked until user interacts with the page.')
  })
}

function detectNewIncidents(latestIncidents) {
  const latestIds = new Set(
    latestIncidents
      .map((incident) => incident.objectId)
      .filter((id) => id !== null && id !== undefined),
  )

  // first load
  if (!firstLiveLoadDone.value) {
    knownIncidentIds.value = latestIds
    firstLiveLoadDone.value = true
    return
  }

  // get newly added incidents
  const newIncidents = latestIncidents.filter(
    (incident) => !knownIncidentIds.value.has(incident.objectId),
  )

  if (newIncidents.length > 0) {
    const hasCritical = newIncidents.some((incident) => {
      const status = String(incident.status || '').toLowerCase()

      return status.includes('critical') || status.includes('high')
    })

    // play different sound
    if (hasCritical) {
      playSpecificAlertSound('siren')
    } else {
      playSpecificAlertSound('notification')
    }
  }

  knownIncidentIds.value = latestIds
}

function startLiveAutoRefresh() {
  stopLiveAutoRefresh()

  liveRefreshInterval = setInterval(async () => {
    if (!webmap) return

    await refreshActivityFeedOnly()
  }, 10000)
}

function stopLiveAutoRefresh() {
  if (liveRefreshInterval) {
    clearInterval(liveRefreshInterval)
    liveRefreshInterval = null
  }
}

async function refreshActivityFeedOnly() {
  if (!webmap) return

  try {
    const alertLayer = findLayerByTitle(layerTitles.alerts) || findLayerByTitleIncludes('alert')
    const incidentLayer =
      findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')

    const where = buildActivityWhereClause()
    console.log('WHERE:', where)

    const incidentFeatures = await getFeatures(incidentLayer, {
      limit: 50,
      where,
      orderByFields: ['datetime_reported DESC'],
    })

    const alertFeatures = await getFeatures(alertLayer, { limit: 5 })

    alerts.value = mapAlertFeatures(alertFeatures)
    incidents.value = mapIncidentFeatures(incidentFeatures)

    detectNewIncidents(incidents.value)

    stats.activeAlerts = await getCount(alertLayer)
    stats.activeIncidents = await getCount(incidentLayer)
  } catch (error) {
    console.warn('Activity feed refresh failed:', error)
  }
}

// const resolvedIncidentCount = computed(() => {
//   return allIncidents.value.filter((incident) => {
//     return String(incident.status).toLowerCase() === 'resolved'
//   }).length
// })

// const unresolvedIncidentCount = computed(() => {
//   return allIncidents.value.filter((incident) => {
//     return String(incident.status).toLowerCase() !== 'resolved'
//   }).length
// })
const showActivityFilters = ref(false)

const showFullMapActions = ref(false)

const activityFilters = reactive({
  from: '',
  to: '',
  district: '',
})

function toInputDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function setDefaultActivityDateRange() {
  const today = new Date()
  const past7Days = new Date()
  past7Days.setDate(today.getDate() - 7)

  activityFilters.from = toInputDate(past7Days)
  activityFilters.to = toInputDate(today)
}

function buildActivityWhereClause() {
  const conditions = []

  // FROM DATE
  if (activityFilters.from) {
    const fromDate = new Date(`${activityFilters.from}T00:00:00`)

    const yyyy = fromDate.getFullYear()
    const mm = String(fromDate.getMonth() + 1).padStart(2, '0')
    const dd = String(fromDate.getDate()).padStart(2, '0')

    conditions.push(`datetime_reported >= DATE '${yyyy}-${mm}-${dd}'`)
  }

  // TO DATE
  if (activityFilters.to) {
    const toDate = new Date(`${activityFilters.to}T23:59:59`)

    const yyyy = toDate.getFullYear()
    const mm = String(toDate.getMonth() + 1).padStart(2, '0')
    const dd = String(toDate.getDate()).padStart(2, '0')

    conditions.push(`datetime_reported <= DATE '${yyyy}-${mm}-${dd}'`)
  }

  // DISTRICT
  if (activityFilters.district) {
    const safeDistrict = activityFilters.district.replace(/'/g, "''")

    conditions.push(`district = '${safeDistrict}'`)
  }

  return conditions.length ? conditions.join(' AND ') : '1=1'
}

async function applyActivityFilters() {
  showActivityFilters.value = false
}

function resetActivityFilters() {
  activityFilters.from = ''
  activityFilters.to = ''
  activityFilters.district = ''
  showActivityFilters.value = false
}

function parseActivityDate(value) {
  if (!value || value === '-' || value === 'Live update' || value === 'Sensor reading') {
    return null
  }

  const text = String(value).trim()

  // Format: 15-09-2025 08:00:00
  let match = text.match(/^(\d{1,2})-(\d{1,2})-(\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/)

  if (match) {
    const [, day, month, year, hour, minute, second = '00'] = match
    return new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      Number(hour),
      Number(minute),
      Number(second),
    )
  }

  // Format: 10/31/2025, 7:24:22 AM
  match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4}),?\s+(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)?$/i)

  if (match) {
    let [, month, day, year, hour, minute, second, meridiem] = match

    hour = Number(hour)

    if (meridiem) {
      const upper = meridiem.toUpperCase()
      if (upper === 'PM' && hour < 12) hour += 12
      if (upper === 'AM' && hour === 12) hour = 0
    }

    return new Date(
      Number(year),
      Number(month) - 1,
      Number(day),
      hour,
      Number(minute),
      Number(second),
    )
  }

  const directDate = new Date(text)
  if (!Number.isNaN(directDate.getTime())) {
    return directDate
  }

  return null
}
/* =========================
   LIFECYCLE
========================= */

onMounted(() => {
  theme.value = localStorage.getItem('phoenixTheme') || 'light'
  window.addEventListener('theme-changed', handleThemeChanged)
  loadWebMap()
})

onBeforeUnmount(() => {
  window.removeEventListener('theme-changed', handleThemeChanged)
  if (weatherSyncHandle) {
    weatherSyncHandle.remove()
    weatherSyncHandle = null
  }

  if (weatherMoveHandle) {
    weatherMoveHandle.remove()
    weatherMoveHandle = null
  }

  if (weatherMap) {
    weatherMap.remove()
    weatherMap = null
  }
  if (view) {
    view.destroy()
    view = null
  }

  webmap = null
})
</script>

<style scoped>
/* FULL MAP BUT KEEP TOPBAR + SIDEBAR */
.fire-page.dispatcher-map-mode {
  height: calc(100vh - 72px);
  overflow: hidden;
}

.fire-page.dispatcher-map-mode .page-header,
.fire-page.dispatcher-map-mode .summary-grid,
.fire-page.dispatcher-map-mode .side-panel,
.fire-page.dispatcher-map-mode .table-card {
  display: none;
}

.fire-page.dispatcher-map-mode .dashboard-grid {
  display: block;
  height: 100%;
  margin-bottom: 0;
}

.fire-page.dispatcher-map-mode .map-card {
  height: 100%;
  border-radius: 1rem;
}

.fire-page.dispatcher-map-mode .fire-map {
  height: calc(100vh - 72px - 65px - 4rem);
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.page-header h2 {
  font-weight: 800;
  margin-bottom: 0.25rem;
  color: var(--phoenix-heading-color);
}

.page-header p {
  color: var(--phoenix-secondary-color);
  margin-bottom: 0;
}

.header-actions .btn,
.map-actions .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}

.side-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.9rem;
}

.side-summary-grid .summary-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-left: 5px solid var(--bs-secondary);
  border-radius: 1rem;
  padding: 1rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
}

.side-summary-grid .summary-card span {
  display: block;
  color: var(--phoenix-secondary-color);
  font-size: 0.78rem;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.side-summary-grid .summary-card h3 {
  font-size: 1.8rem;
  font-weight: 900;
  margin: 0;
  color: var(--phoenix-heading-color);
}

.side-summary-grid .summary-card.success {
  border-left-color: #25b003;
}

.side-summary-grid .summary-card.warning {
  border-left-color: #f59f00;
}
.summary-card.info {
  border-left-color: #0dcaf0;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 380px;
  gap: 1.25rem;
  align-items: start;
}

.map-card,
.panel-card,
.table-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.card-header-custom,
.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.card-header-custom {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.card-header-custom h5,
.panel-header h5 {
  font-weight: 800;
  margin-bottom: 0;
  color: var(--phoenix-heading-color);
}

.card-header-custom small {
  color: var(--phoenix-secondary-color);
}

.fire-map {
  width: 100%;
  height: 620px;
}

/* FULL MAP DISPATCHER MODE */
:global(html.full-map-open),
:global(body.full-map-open) {
  overflow: hidden !important;
  height: 100%;
}

.map-card.full-map-mode {
  position: fixed;
  inset: 0;
  z-index: 9999;
  width: 100vw;
  height: 100dvh;
  max-height: 100dvh;
  border-radius: 0;
  margin: 0;
  overflow: hidden;
}

.map-card.full-map-mode .card-header-custom {
  height: 65px;
  min-height: 65px;
  background: var(--phoenix-card-bg);
  position: relative;
  z-index: 5;
  flex-shrink: 0;
}

.map-card.full-map-mode .fire-map {
  height: calc(100dvh - 65px);
  min-height: 0;
  overflow: hidden;
}

.map-card.full-map-mode :deep(.esri-ui-top-left) {
  top: 1rem;
}

.map-card.full-map-mode :deep(.esri-ui-top-right) {
  top: 1rem;
}

.map-error {
  min-height: 620px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--phoenix-secondary-color);
  padding: 2rem;
}

.side-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  position: relative;
  z-index: 20;
  pointer-events: auto;
}

.panel-card {
  padding: 1.25rem;
}

.alert-row {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 0.75rem;
  align-items: start;
  padding: 1rem 0;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.alert-row:last-child {
  border-bottom: 0;
}

.alert-dot {
  width: 10px;
  height: 10px;
  background: #dc3545;
  border-radius: 50%;
  margin-top: 0.35rem;
  box-shadow: 0 0 0 5px rgba(220, 53, 69, 0.12);
}

.alert-info h6 {
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.alert-info p,
.sensor-row p {
  font-size: 0.85rem;
  color: var(--phoenix-secondary-color);
  margin-bottom: 0;
}

.sensor-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 0;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.sensor-row:last-child {
  border-bottom: 0;
}

.empty-state {
  color: var(--phoenix-secondary-color);
  font-size: 0.9rem;
  padding: 1rem 0;
}

.table-card {
  margin-bottom: 1.5rem;
}

.table thead th {
  font-size: 0.8rem;
  text-transform: uppercase;
  color: var(--phoenix-secondary-color);
  background: var(--phoenix-body-bg);
}

:deep(.esri-widget),
:deep(.esri-widget--button),
:deep(.esri-search) {
  border-radius: 0.75rem;
}

:deep(.esri-search__container) {
  border-radius: 0.75rem;
  overflow: hidden;
}

.map-card {
  position: relative;
  margin-bottom: 2rem;
}

.filter-icon-btn {
  width: 38px;
  padding: 0 !important;
  justify-content: center;
}

.map-actions .btn.active,
.filter-icon-btn.active {
  background: #ffc107;
  border-color: #ffc107;
  color: #111827;
  box-shadow: 0 0.35rem 1rem rgba(255, 193, 7, 0.35);
}

[data-bs-theme='dark'] .map-actions .btn {
  background: #111b2e;
  border-color: #24324a;
  color: #e2e8f0;
}

[data-bs-theme='dark'] .map-actions .btn:hover {
  border-color: #85a9ff;
  color: #85a9ff;
}

.filter-icon-btn.active {
  background: #ffcc00;
  border-color: #ffcc00;
  color: #111827;
  box-shadow: 0 0.25rem 0.75rem rgba(255, 204, 0, 0.35);
}

.district-floating-panel {
  position: absolute;
  top: 5.25rem;
  right: 1rem;
  z-index: 9999; /* 🔥 higher than ArcGIS UI */

  width: 390px;
  padding: 0.9rem;

  background: #ffffff !important; /* 🔥 force visible */
  border: 1px solid #d8e2ef;
  border-radius: 1rem;

  box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.35);
}

.district-toggle-row {
  min-height: 48px;
  border: 1px solid var(--phoenix-border-color);
  background: #f8fafc !important; /* 🔥 not transparent */
  color: #111827 !important;
  border-radius: 0.85rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  @click= "districtToggles[district] = !districtToggles[district]"
  gap: 0.75rem;
  padding: 0 0.85rem;
  margin-bottom: 0.65rem;
  transition: 0.2s ease;
}

.district-toggle-row:last-child {
  margin-bottom: 0;
}

.district-toggle-row:hover {
  transform: translateY(-1px);
  border-color: rgba(56, 116, 255, 0.35);
  box-shadow: 0 0.35rem 0.9rem rgba(15, 23, 42, 0.08);
}

.district-label {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  color: var(--phoenix-body-color);
  font-size: 0.92rem;
  font-weight: 700;
}

.district-label svg {
  width: 16px;
  height: 16px;
  color: var(--phoenix-secondary-color);
}

.mini-toggle {
  width: 42px;
  height: 24px;
  border: 0;
  border-radius: 999px;
  background: #d8e2ef;
  padding: 3px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  cursor: pointer;
  transition: 0.2s ease;
}

.mini-toggle span {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #ffffff;
  display: block;
  box-shadow: 0 0.15rem 0.35rem rgba(15, 23, 42, 0.25);
  transition: 0.2s ease;
}

.mini-toggle.active {
  justify-content: flex-end;
  background: #25b003;
}

[data-bs-theme='dark'] .district-floating-panel {
  background: #0f172a;
  border-color: #24324a;
}

[data-bs-theme='dark'] .district-toggle-row {
  background: #111b2e;
  border-color: #24324a;
}

[data-bs-theme='dark'] .mini-toggle {
  background: #334155;
}

.map-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.map-actions .btn {
  height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.75rem;
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
  font-weight: 700;
  padding: 0 0.85rem;
  box-shadow: 0 0.25rem 0.75rem rgba(15, 23, 42, 0.06);
  transition: 0.2s ease;
}

.map-actions .btn:hover {
  transform: translateY(-1px);
  border-color: #3874ff;
  color: #3874ff;
  box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.12);
}

.map-actions .btn svg {
  width: 16px;
  height: 16px;
}

.map-floating-panel {
  position: absolute;
  top: 7rem;
  left: 1rem;
  z-index: 999; /* 🔥 important */
  width: 420px;
  border-radius: 1rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.25);
  overflow: hidden;
  top: 5rem;
  max-height: calc(100vh - 6rem);
  position: absolute;
  top: 5.25rem;
  left: 1rem;
  z-index: 25;
  width: 420px;
  max-width: calc(100% - 2rem);
  max-height: calc(100% - 6.5rem);
  display: flex;
  flex-direction: column;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.18);
  color: var(--phoenix-body-color);
}

.floating-header {
  padding: 1rem 1.25rem;
  border-bottom: 3px solid #dc3545;
  position: relative;
  z-index: 2; /* ensure above children */
  background: var(--phoenix-card-bg);
  color: var(--phoenix-emphasis-color);
  padding: 1rem 1.1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.floating-search input::placeholder {
  color: var(--phoenix-secondary-color);
}

.floating-item {
  width: 100%;
  text-align: left;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
  padding: 1rem;
  margin-bottom: 0.85rem;
  border-radius: 0.9rem;
  transition: 0.2s ease;
  box-shadow: 0 0.35rem 0.9rem rgba(15, 23, 42, 0.05);
}

.floating-item h6,
.floating-item p,
.floating-item small,
.floating-item strong {
  color: inherit;
}
.map-card.full-map-mode {
  position: fixed;
  inset: 0;
  z-index: 9999;
  width: 100vw;
  height: 100vh;
  border-radius: 0;
}

.map-card.full-map-mode .fire-map {
  height: calc(100vh - 65px);
}

/* FULL MAP DISPATCHER MODE - FIXED */
.map-card.full-map-mode {
  position: fixed;
  inset: 0;
  z-index: 9999;
  width: 100vw;
  height: 100vh;
  border-radius: 0;
  margin: 0;
  background: #0f172a;
}

.map-card.full-map-mode .card-header-custom {
  height: 64px;
  min-height: 64px;
  padding: 0.75rem 1.25rem;
  background: var(--phoenix-card-bg);
  border-bottom: 1px solid var(--phoenix-border-color);
  position: relative;
  z-index: 30;
}

.map-card.full-map-mode .fire-map {
  height: calc(100vh - 64px);
  width: 100%;
}

/* prevent page scroll behind fullscreen */
body:has(.map-card.full-map-mode) {
  overflow: hidden;
}

/* MAP ACTION BUTTONS */
.map-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.map-actions .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-weight: 700;
  border-radius: 0.65rem;
}

.floating-header h5 {
  margin-bottom: 0;
  font-weight: 800;
  font-size: 1rem;
  color: var(--phoenix-emphasis-color);
}

.floating-close {
  border: 0;
  background: transparent;
  color: var(--phoenix-secondary-color);
  width: 32px;
  height: 32px;
  border-radius: 50%;
}

.floating-close:hover {
  background: var(--phoenix-body-bg);
  color: #dc3545;
}

/* SEARCH */
.floating-search {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.85rem 1rem;
  background: var(--phoenix-body-bg);
  border-bottom: 1px solid var(--phoenix-border-color);
  position: sticky;
  top: 0;
  z-index: 10; /* 🔥 above list */
  padding: 0.85rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  color: var(--phoenix-body-color);
}

.floating-search input {
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--phoenix-body-color);
  font-size: 0.9rem;
  width: 100%;
  height: 36px;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 0 0.75rem;
  font-size: 0.9rem;
}

.floating-search svg {
  color: var(--phoenix-secondary-color);
}

/* LIST */
.floating-list {
  overflow-y: auto;
  padding: 0.85rem;
  max-height: calc(100vh - 260px);
  padding: 1rem;
  background: var(--phoenix-card-bg);
}

.floating-item:hover {
  transform: translateY(-2px);
  border-color: rgba(220, 53, 69, 0.45);
  box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.12);
}

/* REMOVE ORANGE FIRST ITEM STYLE */
.floating-item:first-child {
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
}

/* INCIDENT TITLE */
.floating-item h6 {
  color: var(--phoenix-heading-color);
  font-weight: 800;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.floating-item h6::before {
  content: '';
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #dc3545;
  box-shadow: 0 0 0 5px rgba(220, 53, 69, 0.12);
}

.floating-item p {
  margin-bottom: 0.35rem;
  font-size: 0.88rem;
  color: var(--phoenix-body-color);
}

.floating-item strong {
  color: var(--phoenix-heading-color);
}

.floating-item small {
  display: block;
  margin-top: 0.65rem;
  color: var(--phoenix-secondary-color);
  font-size: 0.78rem;
}

/* FOOTER */
.panel-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-body-bg);
  color: var(--phoenix-secondary-color);
  font-size: 0.85rem;
}

/* ARCGIS UI POSITION IN FULLSCREEN */
.map-card.full-map-mode :deep(.esri-ui-top-left) {
  top: 1rem;
  left: 1rem;
}

.map-card.full-map-mode :deep(.esri-ui-top-right) {
  top: 1rem;
  right: 1rem;
}

:deep(.esri-view-surface),
:deep(.esri-view-root),
:deep(.esri-view) {
  z-index: 0 !important;
}
.sensor-card h6::before {
  background: #0dcaf0;
  box-shadow: 0 0 0 5px rgba(13, 202, 240, 0.16);
}

.sensor-card:hover {
  border-color: rgba(13, 202, 240, 0.55);
}

.district-filter {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.district-filter .btn {
  font-weight: 700;
  border-radius: 0.6rem;
}

:deep(.esri-expand__content) {
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.25);
}

:deep(.esri-legend) {
  max-height: 620px;
  overflow-y: auto;
  background: #ffffff;
  color: #111827;
  border-radius: 1rem;
}

:deep(.esri-legend__service-label) {
  font-weight: 800;
  color: #111827;
}

:deep(.esri-legend__layer-caption) {
  color: #4b5563;
}

[data-bs-theme='dark'] :deep(.esri-legend) {
  background: #0f172a;
  color: #e5e7eb;
}

[data-bs-theme='dark'] :deep(.esri-legend__service-label) {
  color: #f8fafc;
}

[data-bs-theme='dark'] :deep(.esri-legend__layer-caption) {
  color: #cbd5e1;
}

.fire-analytics-panel {
  position: absolute;
  top: calc(5.25rem + 35px);
  left: 1.25rem;
  z-index: 999;
  width: 280px;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.analytics-card {
  position: relative;
  padding: 1.1rem 1.2rem;
  border-radius: 1.2rem;

  /* 🔥 GLASS EFFECT */
  background: linear-gradient(145deg, rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.65));
  backdrop-filter: blur(10px);

  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow:
    0 10px 25px rgba(0, 0, 0, 0.35),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);

  color: #ffffff;

  display: flex;
  flex-direction: column;
  justify-content: center;

  transition: 0.25s ease;
  text-align: center;
}

.analytics-card:hover {
  transform: translateY(-2px);
  box-shadow:
    0 15px 35px rgba(0, 0, 0, 0.45),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.analytics-card span {
  font-size: 0.85rem;
  font-weight: 600;
  opacity: 0.75;
}

.analytics-card strong {
  font-size: 2.4rem;
  font-weight: 900;
  margin-top: 0.25rem;
}

/* 🔥 FIRE CARD */
.analytics-card.fire-detected {
  align-items: center;
  text-align: center;
}

.analytics-card.fire-detected svg {
  width: 32px;
  height: 32px;
  color: #ff4d4f;
  margin-bottom: 0.3rem;
}

.analytics-card.fire-detected strong {
  font-size: 3rem;
  color: #ff2b2b;
  text-shadow: 0 0 12px rgba(255, 0, 0, 0.6);
}

/* COLORS */
.analytics-card .orange {
  color: #ff8c2a;
  text-align: center;
}

.analytics-card .cyan {
  color: #39c5ff;
  text-align: center;
}

.analytics-card .yellow {
  color: #ffc107;
  text-align: center;
}

.custom-table thead th {
  font-size: 0.78rem;
  text-transform: uppercase;
  font-weight: 800;
  color: #6b7280;
  border-bottom: 1px solid #e5e7eb;
  padding: 0.65rem 0.75rem;
  text-align: left;
}

.custom-table tbody tr {
  transition: 0.2s ease;
}

.custom-table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.custom-table tbody tr:hover {
  background: #eaf2ff;
}

.custom-table tbody td {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.9rem;
  text-align: left;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}
.custom-table table {
  width: 100%;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

/* 🔥 Soft badges (Phoenix style) */
.badge-soft-danger {
  background: rgba(255, 77, 79, 0.12);
  color: #ff4d4f;
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  border-radius: 0.5rem;
}

.badge-soft-warning {
  background: rgba(255, 193, 7, 0.15);
  color: #f59f00;
  font-weight: 700;
  padding: 0.35rem 0.6rem;
  border-radius: 0.5rem;
}

/* Dark mode */
[data-bs-theme='dark'] .custom-table tbody tr:nth-child(even) {
  background: #111b2e;
}

[data-bs-theme='dark'] .custom-table tbody tr:hover {
  background: #1a2942;
}

[data-bs-theme='dark'] .custom-table thead th {
  color: #94a3b8;
  border-color: #24324a;
}
.incidents-table-card {
  border-radius: 1.2rem;
  overflow: hidden;
}

.incidents-table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.incidents-table-header h5 {
  margin-bottom: 0.25rem;
  font-weight: 800;
}

.incidents-table-header small {
  color: var(--phoenix-secondary-color);
}

.incident-count {
  background: #fff4de;
  color: #b76e00;
  font-weight: 800;
  padding: 0.45rem 0.75rem;
  border-radius: 999px;
  font-size: 0.8rem;
}

.custom-table thead th {
  padding: 1rem 1.5rem;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--phoenix-secondary-color);
  background: var(--phoenix-body-bg);
  border-bottom: 1px solid var(--phoenix-border-color);
}

.custom-table tbody tr {
  transition: 0.2s ease;
}

.custom-table tbody tr:nth-child(even) {
  background: rgba(15, 23, 42, 0.025);
}

.custom-table tbody tr:hover {
  background: rgba(255, 193, 7, 0.08);
}

.location-cell {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  font-weight: 700;
}

.location-cell svg {
  width: 15px;
  height: 15px;
  color: #fb6418;
}

.incident-pill,
.status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  border-radius: 0.65rem;
  padding: 0.25rem 0.55rem;
  font-size: 0.75rem;
  min-height: 26px;
}

.incident-pill {
  background: rgba(255, 77, 79, 0.12);
  color: #ff4d4f;
}

.status-pill {
  background: rgba(255, 193, 7, 0.15);
  color: #b76e00;
}

.status-high {
  background: rgba(255, 77, 79, 0.13);
  color: #d92d20;
}

.status-medium {
  background: rgba(255, 193, 7, 0.18);
  color: #b76e00;
}

.status-low {
  background: rgba(37, 176, 3, 0.13);
  color: #258a00;
}

.reported-time {
  font-weight: 600;
  color: var(--phoenix-body-color);
}
/* LOCATION */
.custom-table th:nth-child(1),
.custom-table td:nth-child(1) {
  width: 30%;
}

/* INCIDENT TYPE */
.custom-table th:nth-child(2),
.custom-table td:nth-child(2) {
  width: 20%;
}

/* STATUS */
.custom-table th:nth-child(3),
.custom-table td:nth-child(3) {
  width: 15%;
}

/* TIME */
.custom-table th:nth-child(4),
.custom-table td:nth-child(4) {
  width: 35%;
}

.report-analysis-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.2rem;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
}

.report-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.report-header h5 {
  margin-bottom: 0.25rem;
  font-weight: 800;
}

.report-header small {
  color: var(--phoenix-secondary-color);
}

.report-tabs {
  display: flex;
  gap: 0.5rem;
  background: var(--phoenix-body-bg);
  padding: 0.35rem;
  border-radius: 0.9rem;
}

.report-tabs button {
  border: 0;
  background: transparent;
  color: var(--phoenix-body-color);
  font-weight: 800;
  padding: 0.45rem 0.8rem;
  border-radius: 0.65rem;
}

.report-tabs button.active {
  background: #ffc107;
  color: #111827;
  box-shadow: 0 0.35rem 0.8rem rgba(255, 193, 7, 0.25);
}

.report-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.report-mini-card {
  background: var(--phoenix-body-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1rem;
  padding: 1rem;
}

.report-mini-card span {
  display: block;
  color: var(--phoenix-secondary-color);
  font-size: 0.82rem;
  font-weight: 700;
  margin-bottom: 0.35rem;
}

.report-mini-card strong {
  font-size: 1.35rem;
  font-weight: 900;
  color: var(--phoenix-heading-color);
}

.report-mini-card strong.danger {
  color: #dc3545;
}

.report-chart-area {
  background: var(--phoenix-body-bg);
  border-radius: 1rem;
  padding: 1.25rem;
}

.report-chart-area h6 {
  font-weight: 800;
  margin-bottom: 1rem;
}

.analysis-row {
  margin-bottom: 0.9rem;
}

.analysis-label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.35rem;
  font-size: 0.88rem;
  font-weight: 700;
}

.analysis-bar-track {
  height: 12px;
  background: #e5edf5;
  border-radius: 999px;
  overflow: hidden;
}

.analysis-bar {
  height: 100%;
  background: #3874ff;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.analysis-bar.orange {
  background: #fb6418;
}

.analysis-bar.severity {
  background: #dc3545;
}

[data-bs-theme='dark'] .analysis-bar-track {
  background: #24324a;
}

.analysis-dashboard {
  background: #f8fafc;
  border-radius: 1.25rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
  color: #111827;
}

[data-bs-theme='dark'] .analysis-dashboard {
  background: #071326;
  color: #f8fafc;
}

.analysis-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}

.analysis-panel {
  background: #ffffff;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.2rem;
  padding: 1.25rem;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.05);
}

.analysis-panel-header {
  display: flex;
  justify-content: space-between;
  border-bottom: 1px solid var(--phoenix-border-color);
  padding-bottom: 0.75rem;
  margin-bottom: 1rem;
}

.analysis-panel-header h5 {
  font-weight: 800;
  margin: 0;
}

.analysis-panel-header span {
  color: var(--phoenix-secondary-color);
  font-size: 0.85rem;
}

.ranking-list {
  display: grid;
  gap: 0.85rem;
}

.ranking-row {
  min-height: 48px;
  background: #111e34;
  border-radius: 0.75rem;
  padding: 0 1rem;
  display: grid;
  grid-template-columns: 48px 1fr auto;
  align-items: center;
  gap: 0.75rem;
  color: #dbeafe;
  font-weight: 800;
}

.ranking-row strong {
  color: #bfdbfe;
}

.ranking-row b {
  color: #dbeafe;
  font-size: 1rem;
}

.top-hotspot-panel {
  min-height: 360px;
}

.bar-chart {
  height: 280px;
  display: flex;
  align-items: end;
  gap: 1.25rem;
  padding: 2rem 1rem 0;
  border-left: 1px solid rgba(219, 234, 254, 0.45);
  border-bottom: 1px solid rgba(219, 234, 254, 0.45);
  overflow-x: auto;
}

.bar-item {
  height: 100%;
  min-width: 90px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: end;
  gap: 0.6rem;
  flex: 1; /* each sensor auto fit */
  max-width: 50px; /* prevent too wide */
  display: flex;
  flex-direction: column;
  position: relative;
}

.bar-value {
  width: 70px;
  min-height: 8px;
  border-radius: 0.5rem 0.5rem 0 0;
  background: linear-gradient(to top, #42b8b1, #65d5cd);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 0.35rem;
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 900;
  transition: 0.25s ease;
}

.bar-value:hover {
  transform: translateY(-4px);
  filter: brightness(1.1);
}

.bar-item small {
  margin-top: 6px;
  font-size: 10px;
  color: #d8e2ef;
  text-align: center;
  transform: none; /* ❌ remove rotation */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.report-analysis-wrapper {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
  align-items: stretch;
}

/* Make both cards same height */
.analysis-panel,
.report-analysis-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}
[data-bs-theme='dark'] .analysis-panel,
[data-bs-theme='dark'] .report-analysis-card {
  background: #0f172a;
  border-color: #24324a;
  color: #f8fafc;
}

[data-bs-theme='dark'] .analysis-panel-header,
[data-bs-theme='dark'] .report-header {
  border-color: #24324a;
}

[data-bs-theme='dark'] .analysis-panel-header h5,
[data-bs-theme='dark'] .report-header h5,
[data-bs-theme='dark'] .analysis-panel-header span,
[data-bs-theme='dark'] .report-header small {
  color: #f8fafc;
}

[data-bs-theme='dark'] .report-mini-card,
[data-bs-theme='dark'] .report-chart-area {
  background: #111b2e;
  border-color: #24324a;
  color: #f8fafc;
}

[data-bs-theme='dark'] .report-mini-card span,
[data-bs-theme='dark'] .analysis-label,
[data-bs-theme='dark'] .analysis-label span,
[data-bs-theme='dark'] .analysis-label strong {
  color: #e2e8f0;
}

[data-bs-theme='dark'] .analysis-bar-track {
  background: #24324a;
}

.bottom-analytics-panel {
  position: absolute;
  left: 390px;
  right: 20px;
  bottom: 20px;
  height: 260px;
  z-index: 15;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

/* NORMAL MODE: smaller, fit inside available map space */
.map-card:not(.full-map-mode) .bottom-analytics-panel {
  left: 340px;
  right: 12px;
  bottom: 18px;
  height: 230px;
  gap: 10px;
}

.map-card:not(.full-map-mode) .analytics-chart-card {
  padding: 10px;
  border-radius: 14px;
}

.map-card:not(.full-map-mode) .analytics-chart-card canvas {
  height: 175px !important;
}

/* FULL MAP MODE: bigger charts */
.map-card.full-map-mode .bottom-analytics-panel {
  left: 390px;
  right: 20px;
  bottom: 24px;
  height: 270px;
  gap: 16px;
}
.bottom-analytics-panel h6 {
  font-weight: 600;
  color: #cbd5e1;
}
.map-card.full-map-mode .analytics-chart-card canvas {
  height: 215px !important;
}

.analytics-chart-card {
  height: 100%;
  background: rgba(12, 21, 38, 0.95);
  border-radius: 18px;
  padding: 12px;
  color: #fff;
}

.analytics-chart-card canvas {
  height: 200px !important;
}

.temperature-chart {
  display: flex;
  align-items: flex-end;
  gap: 14px;
  height: 170px;
  min-width: max-content;
  padding: 10px 8px 0;
}

.temp-bar-item {
  width: 42px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.temp-bar-wrapper {
  height: 120px;
  width: 28px;
  display: flex;
  align-items: flex-end;
  background: linear-gradient(to top, #16d69b 0%, #ffc107 55%, #dc3545 80%);
  border-radius: 8px;
  overflow: hidden;
}

.temp-bar {
  width: 100%;
  background: rgba(255, 255, 255, 0.35);
  border-radius: 8px 8px 0 0;
  position: relative;
  display: flex;
  justify-content: center;
  align-items: flex-start;
}

.temp-bar span {
  font-size: 11px;
  font-weight: 700;
  color: #fff;
  margin-top: -18px;
}

.temp-label {
  margin-top: 8px;
  font-size: 11px;
  color: #d8e2ef;
  transform: rotate(-45deg);
  transform-origin: top right;
  white-space: nowrap;
  max-width: 90px;
}

.map-loading-overlay {
  position: absolute;
  inset: 0;
  z-index: 999;
  background: rgba(12, 21, 38, 0.82);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0 0 18px 18px;
}

.spinner-wrapper {
  text-align: center;
  color: #fff;
}

.spinner {
  width: 52px;
  height: 52px;
  border: 5px solid rgba(255, 255, 255, 0.2);
  border-top-color: #ffc107;
  border-radius: 50%;
  animation: brave-spin 0.9s linear infinite;
  margin: 0 auto 14px;
}

.spinner-wrapper h6 {
  color: #fff;
  font-weight: 700;
  margin-bottom: 6px;
}

.spinner-wrapper p {
  font-size: 13px;
  color: #d8e2ef;
  margin: 0;
}

.weather-layer-toolbar {
  position: absolute;
  top: 5.2rem;
  left: 1rem;
  z-index: 30;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.weather-dropdown-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 999px;
  font-weight: 700;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.12);
}

.weather-dropdown-menu {
  border-radius: 0.85rem;
  padding: 0.4rem;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-card-bg);
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.18);
}

.weather-dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border-radius: 0.65rem;
  font-weight: 700;
  color: var(--phoenix-body-color);
  padding: 0.55rem 0.75rem;
}

.weather-dropdown-item.active,
.weather-dropdown-item:hover {
  background: #ffc107;
  color: #111827;
}

.weather-value-box {
  width: fit-content;
  max-width: 220px;
  min-width: 130px;
  height: 36px;

  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;

  padding: 0 0.85rem;
  border-radius: 0.75rem;

  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 800;

  box-shadow: 0 0.5rem 1rem rgba(15, 23, 42, 0.18);
}

.weather-value-box span,
.weather-value-box strong {
  white-space: nowrap;
}

.weather-value-box span {
  font-size: 0.75rem;
  color: var(--phoenix-secondary-color);
  font-weight: 700;
}

.weather-value-box strong {
  font-size: 0.9rem;
  font-weight: 800;
  color: var(--phoenix-heading-color);
}

.weather-chip {
  border: 0;
  background: rgba(15, 23, 42, 0.78);
  color: #fff;
  border-radius: 999px;
  padding: 0.45rem 0.75rem;
  font-size: 0.82rem;
  font-weight: 700;
}

.weather-chip.active {
  background: #ffc107;
  color: #111827;
}

.weather-value-box {
  min-width: 90px;
  padding: 0.4rem 0.75rem;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.78);
  color: #fff;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  backdrop-filter: blur(10px);
}

.weather-value-box span {
  font-size: 0.75rem;
  opacity: 0.8;
}

.weather-value-box strong {
  font-size: 0.9rem;
  font-weight: 800;
}

/* HIDE MAPTILER WEATHER NAVIGATION */

.weather-map-overlay .maplibregl-ctrl-top-right,
.weather-map-overlay .maplibregl-ctrl-top-left,
.weather-map-overlay .maplibregl-ctrl-bottom-right,
.weather-map-overlay .maplibregl-ctrl-bottom-left {
  display: none !important;
}

.weather-map-overlay {
  position: absolute;
  inset: 65px 0 0 0;
  z-index: 5;
  pointer-events: none;
  background: transparent !important;
  mix-blend-mode: screen;
}

.weather-map-overlay canvas {
  background: transparent !important;
}

.weather-map-overlay .maplibregl-ctrl-top-right,
.weather-map-overlay .maplibregl-ctrl-top-left,
.weather-map-overlay .maplibregl-ctrl-bottom-right,
.weather-map-overlay .maplibregl-ctrl-bottom-left {
  display: none !important;
}

.weather-layer-toolbar {
  position: absolute;
  top: 5.2rem;
  left: 1rem;
  z-index: 60;
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.weather-dropdown-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  height: 36px;
  border-radius: 12px;
  font-weight: 700;
}

.weather-dropdown-menu {
  border-radius: 0.85rem;
  padding: 0.4rem;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-card-bg);
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.18);
}

.weather-dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border-radius: 0.65rem;
  font-weight: 700;
  padding: 0.55rem 0.75rem;
}

.weather-dropdown-item.active,
.weather-dropdown-item:hover {
  background: #ffc107;
  color: #111827;
}

.weather-value-box {
  min-width: 100px;
  padding: 0.43rem 0.8rem;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.88);
  color: #fff;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  height: 36px;
}

.weather-value-box span {
  font-size: 0.75rem;
  opacity: 0.85;
  font-weight: 700;
}

.weather-value-box strong {
  font-size: 0.9rem;
  font-weight: 800;
}
@keyframes brave-spin {
  to {
    transform: rotate(360deg);
  }
}

.activity-feed-card {
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.25rem;
  padding: 1.25rem;
  box-shadow: 0 0.5rem 1.5rem rgba(15, 23, 42, 0.08);
}

.activity-feed-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.activity-feed-header h5 {
  margin-bottom: 0.2rem;
  font-weight: 900;
  color: var(--phoenix-heading-color);
}

.activity-feed-header small {
  color: var(--phoenix-secondary-color);
  font-size: 0.8rem;
}

.live-pill {
  background: rgba(220, 53, 69, 0.12);
  color: #dc3545;
  border: 1px solid rgba(220, 53, 69, 0.25);
  border-radius: 999px;
  padding: 0.25rem 0.65rem;
  font-size: 0.68rem;
  font-weight: 900;
}

.activity-feed-list {
  max-height: 285px;
  overflow-y: auto;
  padding-right: 0.35rem;
}

.activity-feed-item {
  width: 100%;
  text-align: left;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  border-radius: 0.9rem;

  display: grid;
  grid-template-columns: 18px 1fr;
  gap: 0.75rem;

  padding: 0.9rem;
  margin-bottom: 0.75rem;

  cursor: pointer;
  transition: 0.2s ease;
}

.activity-feed-item:hover {
  transform: translateY(-2px);
  border-color: rgba(245, 159, 0, 0.45);
  box-shadow: 0 0.75rem 1.2rem rgba(15, 23, 42, 0.1);
}

.activity-content {
  min-width: 0;
}

.activity-content strong,
.activity-content p,
.activity-content small {
  display: block;
  white-space: normal;
  word-break: break-word;
}

.activity-content strong {
  font-weight: 900;
  color: var(--phoenix-heading-color) !important;
  font-size: 0.9rem;
}

.activity-content p {
  margin: 0.25rem 0;
  color: var(--phoenix-body-color) !important;
  font-size: 0.82rem;
}

.activity-content small {
  color: var(--phoenix-secondary-color) !important;
  font-size: 0.74rem;
  font-weight: 700;
}

.activity-pulse {
  width: 10px;
  height: 10px;
  margin-top: 0.35rem;
  border-radius: 50%;
  background: #f59e0b;
  box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.13);
}

.activity-feed-item.high .activity-pulse {
  background: #dc3545;
  box-shadow: 0 0 0 6px rgba(220, 53, 69, 0.13);
}

.activity-feed-item.normal .activity-pulse {
  background: #25b003;
  box-shadow: 0 0 0 6px rgba(37, 176, 3, 0.13);
}

.activity-content strong {
  display: block;
  color: var(--phoenix-heading-color) !important;
  font-size: 0.9rem;
  font-weight: 900;
  margin-bottom: 0.25rem;
}

.activity-content p {
  margin-bottom: 0.35rem;
  color: var(--phoenix-body-color) !important;
  font-size: 0.8rem;
}

.activity-content small {
  color: var(--phoenix-secondary-color) !important;
  font-size: 0.74rem;
  font-weight: 700;
}
.activity-feed-list:hover {
  overflow-y: auto;
}

.activity-feed-item:last-child {
  border-bottom: 0;
}

.activity-pulse {
  width: 10px;
  height: 10px;
  margin-top: 0.35rem;
  border-radius: 50%;
  background: #38bdf8;
  box-shadow: 0 0 0 6px rgba(56, 189, 248, 0.16);
  animation: activityPulse 1.6s infinite;
}

.activity-feed-item.high .activity-pulse {
  background: #ef4444;
  box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.16);
}

.activity-feed-item.medium .activity-pulse {
  background: #f59e0b;
  box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.16);
}

.activity-feed-item.normal .activity-pulse {
  background: #22c55e;
  box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.16);
}

.sensor-hover-card {
  position: absolute;
  z-index: 80;
  width: 230px;
  padding: 0.9rem;
  border-radius: 1rem;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  box-shadow: 0 1rem 2rem rgba(15, 23, 42, 0.22);
  pointer-events: none;
  animation: sensorHoverIn 0.18s ease;
}

.sensor-hover-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.sensor-hover-header strong {
  color: var(--phoenix-heading-color);
  font-weight: 900;
  font-size: 0.92rem;
}

.sensor-hover-header span {
  border-radius: 999px;
  padding: 0.2rem 0.5rem;
  font-size: 0.65rem;
  font-weight: 900;
}

.sensor-hover-header span.online {
  background: rgba(37, 176, 3, 0.14);
  color: #25b003;
}

.sensor-hover-row {
  display: flex;
  justify-content: space-between;
  padding: 0.35rem 0;
  border-top: 1px solid var(--phoenix-border-color);
}

.sensor-hover-row span {
  color: var(--phoenix-secondary-color);
  font-size: 0.78rem;
  font-weight: 700;
}

.sensor-hover-row strong {
  color: var(--phoenix-heading-color);
  font-size: 0.82rem;
  font-weight: 900;
}

@keyframes sensorHoverIn {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes activityPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.35);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}

@keyframes feedSlideIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.map-area {
  position: relative;
  width: 100%;
  height: 620px;
  overflow: hidden;
  border-radius: 0 0 1rem 1rem;
}

.map-area .fire-map,
.map-area .weather-map-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.weather-map-overlay {
  z-index: 5;
  pointer-events: none;
}

.fire-map {
  z-index: 1;
}

.weather-layer-toolbar {
  z-index: 20;
}

.weather-toolbar-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

/* =========================
   FULL MAP MODE
========================= */

.dispatcher-map-mode .map-card {
  position: fixed !important;
  inset: 0 !important;

  width: 100vw !important;
  height: 100vh !important;

  z-index: 9999;
  border-radius: 0 !important;
  margin: 0 !important;
  padding: 0 !important;

  background: #000;
}

.dispatcher-map-mode .map-area {
  width: 100% !important;
  height: 100vh !important;
  min-height: 100vh !important;

  border-radius: 0 !important;
}

.dispatcher-map-mode .fire-map,
.dispatcher-map-mode .weather-map-overlay {
  width: 100% !important;
  height: 100vh !important;
  min-height: 100vh !important;

  inset: 0 !important;
}

.dispatcher-map-mode .card-header-custom {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;

  z-index: 100;

  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(12px);

  border-radius: 0;
}

.dispatcher-map-mode .weather-layer-toolbar {
  z-index: 120;
}

.activity-filter-bar {
  display: grid;
  grid-template-columns: repeat(3, 1fr) auto auto;
  gap: 0.5rem;
  margin: 1rem 0;
  align-items: end;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.filter-field label {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--phoenix-secondary-color);
}

.filter-field input,
.filter-field select {
  height: 34px;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.65rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  padding: 0 0.55rem;
  font-size: 0.78rem;
}

.activity-filter-btn,
.activity-reset-btn {
  height: 34px;
  border: 0;
  border-radius: 0.65rem;
  font-size: 0.75rem;
  font-weight: 800;
}

.activity-filter-btn {
  width: 36px;
  background: rgba(255, 193, 7, 0.18);
  color: #b77900;
}

.activity-reset-btn {
  padding: 0 0.7rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-secondary-color);
}

.activity-filter-wrapper {
  margin: 1rem 0;
}

.activity-filter-toggle {
  width: 100%;
  height: 42px;

  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.9rem;

  background: var(--phoenix-body-bg);
  color: var(--phoenix-heading-color);

  display: flex;
  align-items: center;
  justify-content: space-between;

  padding: 0 1rem;

  font-weight: 800;
  font-size: 0.82rem;

  transition: all 0.2s ease;
}

.activity-filter-toggle:hover {
  background: rgba(255, 193, 7, 0.08);
}

.filter-toggle-left {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.activity-filter-toggle svg {
  width: 16px;
  height: 16px;
}

.activity-filter-bar {
  margin-top: 0.75rem;

  display: grid;
  grid-template-columns: repeat(3, 1fr) auto auto;
  gap: 0.5rem;

  align-items: end;
}

.filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.filter-field label {
  font-size: 0.7rem;
  font-weight: 800;
  color: var(--phoenix-secondary-color);
}

.filter-field input,
.filter-field select {
  height: 36px;

  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.75rem;

  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);

  padding: 0 0.65rem;

  font-size: 0.78rem;
}

.activity-filter-btn,
.activity-reset-btn {
  height: 36px;

  border: 0;
  border-radius: 0.75rem;

  font-size: 0.75rem;
  font-weight: 800;
}

.activity-filter-btn {
  width: 38px;

  display: flex;
  align-items: center;
  justify-content: center;

  background: rgba(255, 193, 7, 0.18);
  color: #b77900;
}

.activity-reset-btn {
  padding: 0 0.85rem;

  background: var(--phoenix-body-bg);
  color: var(--phoenix-secondary-color);
}

.filter-collapse-enter-active,
.filter-collapse-leave-active {
  transition: all 0.25s ease;
  overflow: hidden;
}

.filter-collapse-enter-from,
.filter-collapse-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.dashboard-grid.filters-expanded .side-summary-grid {
  padding-bottom: 1rem;
}

.dashboard-grid.filters-expanded .summary-card {
  margin-bottom: 0.5rem;
}

.mobile-actions-toggle {
  display: none;
}

.district-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;

  padding-bottom: 0.75rem;
  margin-bottom: 0.75rem;

  border-bottom: 1px solid var(--phoenix-border-color);
}

.district-panel-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  font-weight: 800;
  color: var(--phoenix-heading-color);
}

.district-panel-title svg {
  width: 16px;
  height: 16px;
}

.district-close-btn {
  width: 34px;
  height: 34px;

  border: 0;
  border-radius: 12px;

  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);

  display: flex;
  align-items: center;
  justify-content: center;

  transition: all 0.2s ease;
}

.district-close-btn:hover {
  background: var(--phoenix-primary-bg-subtle);
  color: var(--phoenix-primary);
}

.district-close-btn svg {
  width: 16px;
  height: 16px;
}

.activity-feed-card,
.activity-feed-card *,
.side-summary-grid,
.side-summary-grid * {
  pointer-events: auto;
}

.activity-feed-item,
.activity-filter-toggle,
.activity-filter-btn,
.activity-reset-btn,
.activity-filter-bar input,
.activity-filter-bar select {
  position: relative;
  z-index: 25;
  pointer-events: auto;
}
/* =========================
   MOBILE OPTIMIZATION
========================= */

@media (max-width: 768px) {
  .fire-page {
    padding: 0.75rem;
    gap: 1rem;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .page-header h2 {
    font-size: 1.45rem;
    line-height: 1.15;
  }

  .page-header p {
    font-size: 0.9rem;
  }

  .header-actions {
    width: 100%;
  }

  .header-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .map-card {
    position: relative;
  }

  .card-header-custom {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
    padding: 1rem;
    position: relative;
  }

  .card-header-custom h5 {
    font-size: 1.05rem;
  }

  /* =========================
     MAP ACTION BUTTONS
  ========================= */

  .map-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
    width: 100%;
  }

  .map-actions .btn {
    min-height: 46px;
    border-radius: 16px;
    padding: 0.65rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 800;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;

    white-space: nowrap;
  }

  .map-actions .btn svg {
    width: 16px;
    height: 16px;
  }

  .map-actions .btn.active,
  .map-actions .btn-warning,
  .map-actions .btn.sound-active {
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.24);
  }

  .filter-icon-btn {
    width: 46px !important;
    min-width: 46px;
    padding: 0 !important;
    justify-self: start;
  }

  .map-actions .full-map-btn {
    grid-column: span 2;
  }

  /* =========================
     MAP
  ========================= */

  .fire-map {
    height: 430px;
    min-height: 430px;
  }
  .map-area {
    height: 430px;
  }

  .map-area .fire-map,
  .map-area .weather-map-overlay {
    height: 100%;
  }
  .dispatcher-map-mode .map-area {
    height: 100vh !important;
    border-radius: 0;
  }
  /* =========================
     ANALYTICS CARDS
  ========================= */

  .fire-analytics-panel {
    position: static !important;
    width: 100%;
    margin: 0 0 1rem 0;
    padding: 0;

    display: flex;
    gap: 0.75rem;

    overflow-x: auto;
    overflow-y: hidden;

    scrollbar-width: none;
  }

  .fire-analytics-panel::-webkit-scrollbar {
    display: none;
  }

  .analytics-card {
    min-width: 165px;
    height: 110px;
    border-radius: 1rem;
    padding: 0.9rem;
    flex-shrink: 0;
  }

  .analytics-card strong {
    font-size: 1.9rem;
  }

  /* =========================
     CHARTS
  ========================= */

  .bottom-analytics-panel {
    position: static !important;
    width: 100%;
    margin: 1rem 0 0 0;

    display: flex;
    flex-direction: column;
    gap: 1rem;

    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
  }

  .analytics-chart-card {
    width: 100%;
    height: 230px;
    min-height: 230px;
    border-radius: 1rem;
    overflow: hidden;
    flex-shrink: 0;
    margin-top: 1.25rem;
  }

  /* =========================
     SIDE PANEL
  ========================= */

  .side-panel {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .activity-feed-card {
    border-radius: 1rem;
    padding: 1rem;
  }

  .activity-feed-header {
    align-items: flex-start;
    gap: 0.75rem;
  }

  .activity-feed-list {
    max-height: 330px;
  }

  .activity-feed-item {
    width: 100%;
    padding: 0.85rem;
  }

  .activity-content strong {
    font-size: 0.9rem;
  }

  .activity-content p,
  .activity-content small {
    font-size: 0.8rem;
  }

  .side-summary-grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }

  .summary-card {
    padding: 1rem;
  }

  .summary-card h3 {
    font-size: 1.6rem;
  }

  /* =========================
     REPORTS
  ========================= */

  .report-side-grid,
  .report-analysis-wrapper {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .report-analysis-card,
  .analysis-panel,
  .table-card {
    border-radius: 1rem;
  }

  .report-header {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .report-tabs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
  }

  .report-tabs button {
    font-size: 0.75rem;
    padding: 0.55rem 0.4rem;
  }

  .report-summary-grid {
    grid-template-columns: 1fr;
  }

  /* =========================
     TABLE
  ========================= */

  .table-responsive {
    overflow-x: auto;
  }

  .table {
    min-width: 720px;
  }

  .incidents-table-header {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }

  .incident-count {
    width: fit-content;
  }

  /* =========================
     FLOATING PANELS
  ========================= */

  .map-floating-panel {
    left: 0.75rem;
    right: 0.75rem;
    top: 7.5rem;
    width: auto;
    max-height: 360px;
  }

  .district-floating-panel {
    left: 0.75rem;
    right: 0.75rem;
    top: 7.5rem;
    width: auto;
  }

  /* =========================
     WEATHER
  ========================= */

  .weather-layer-toolbar {
    position: absolute !important;
    top: 0.8rem !important;
    left: 4.3rem !important;
    right: auto !important;
    z-index: 80;

    display: flex;
    flex-direction: column;
    gap: 0.4rem;

    width: auto !important;
    max-width: 180px;
  }

  .weather-dropdown-btn {
    width: 42px;
    min-width: 42px;
    padding: 0 !important;

    display: flex;
    align-items: center;
    justify-content: center;
  }

  .weather-value-box {
    width: fit-content !important;
    max-width: 180px;
    min-width: 120px;
  }

  .weather-value-box span,
  .weather-value-box strong {
    font-size: 0.74rem;
    white-space: nowrap;
  }

  .mobile-btn-text {
    display: none;
  }

  .map-actions .btn {
    padding: 0;
    min-height: 50px;
    aspect-ratio: 1 / 1;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 0;
  }

  .map-actions .btn svg {
    width: 20px;
    height: 20px;
  }

  .map-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.65rem;
    width: 100%;
  }
  .map-actions .btn,
  .filter-icon-btn,
  .map-actions .full-map-btn {
    width: 100% !important;
    min-width: 0 !important;
    height: 52px;
    min-height: 52px;
    padding: 0 !important;
    justify-self: stretch !important;
    aspect-ratio: auto;
    border-radius: 16px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .map-actions .btn,
  .filter-icon-btn,
  .map-actions .full-map-btn {
    width: 100% !important;
    min-width: 0 !important;
    height: 52px;
    min-height: 52px;
    padding: 0 !important;
    justify-self: stretch !important;
    aspect-ratio: auto;
    border-radius: 16px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .map-actions .full-map-btn {
    grid-column: span 1;
  }

  .filter-icon-btn {
    width: 100% !important;
    min-width: 0;
  }

  .weather-layer-toolbar {
    position: absolute;
    top: 0.75rem !important;
    left: 0.75rem !important;
    right: 0.75rem !important;
    z-index: 20;

    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }

  .weather-dropdown {
    width: auto;
  }

  .weather-dropdown-btn {
    width: auto !important;
    min-width: 110px;
    height: 38px;

    border-radius: 14px;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;

    padding: 0 0.9rem !important;

    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(12px);
  }

  .weather-value-box {
    width: auto !important;
    min-width: 140px;
    height: 38px;

    border-radius: 14px;
    padding: 0 0.9rem;

    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;

    background: rgba(15, 23, 42, 0.92);
    color: #fff;

    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.2);
  }

  .weather-value-box span,
  .weather-value-box strong {
    font-size: 0.78rem;
    white-space: nowrap;
  }

  .weather-map-overlay {
    top: auto !important;
    bottom: 0 !important;
    height: 430px !important;
  }
  /* =========================
     FULL MAP MOBILE TOOLBAR
  ========================= */

  .mobile-full-toolbar {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;

    z-index: 120;

    display: flex !important;
    flex-direction: column;
    gap: 0.45rem;

    width: auto !important;
  }

  .mobile-full-toolbar .btn {
    width: 44px !important;
    height: 44px !important;
    min-height: 44px !important;

    padding: 0 !important;

    border-radius: 14px;

    display: flex;
    align-items: center;
    justify-content: center;

    backdrop-filter: blur(12px);

    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
  }

  .mobile-full-toolbar .btn svg {
    width: 18px;
    height: 18px;
  }

  .dispatcher-map-mode .map-card,
  .dispatcher-map-mode .fire-map,
  .dispatcher-map-mode .weather-map-overlay {
    height: 100vh !important;
    min-height: 100vh !important;
  }

  .dispatcher-map-mode .weather-map-overlay {
    inset: 0 !important;
  }

  .dispatcher-map-mode .weather-layer-toolbar {
    position: absolute !important;
    top: 9.3rem !important;
    left: 2rem !important;
    z-index: 110;

    width: auto !important;
  }
  .weather-layer-toolbar {
    position: absolute !important;
    top: 8.3rem !important;
    left: 1rem !important;
  }

  .dispatcher-map-mode .weather-toolbar-row {
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }

  .dispatcher-map-mode .weather-dropdown-btn {
    width: 42px !important;
    min-width: 42px;
    height: 42px;

    padding: 0 !important;

    border-radius: 14px;

    display: flex;
    align-items: center;
    justify-content: center;
  }
  .dispatcher-map-mode .weather-label-text {
    display: none;
  }

  .dispatcher-map-mode .weather-value-box {
    height: 42px;
    min-width: 110px;

    border-radius: 14px;
    padding: 0 0.75rem;

    background: rgba(15, 23, 42, 0.92);
    color: #fff;

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;

    backdrop-filter: blur(12px);
  }
  .dispatcher-map-mode .weather-value-box span {
    display: none;
  }

  .dispatcher-map-mode .weather-value-box strong {
    font-size: 0.78rem;
    white-space: nowrap;
  }
  .dispatcher-map-mode .card-header-custom {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 40;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(12px);
  }

  .dispatcher-map-mode .fire-map {
    padding-top: 0 !important;
  }

  .weather-toolbar-row {
    align-items: stretch;
    gap: 0.45rem;
  }

  .weather-dropdown-btn,
  .weather-value-box {
    height: 36px;
  }
  .weather-label-text {
    display: none;
  }

  /* RIGHT ACTION BUTTONS */
  .dispatcher-map-mode .mobile-full-toolbar {
    top: 10.8rem !important;
    right: 0.75rem !important;
  }

  /* Expand widget container */
  .esri-expand__content {
    max-height: 320px !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;

    border-radius: 16px !important;
  }

  /* Basemap gallery */
  .esri-basemap-gallery {
    max-height: 300px !important;
    overflow-y: auto !important;
  }

  /* Prevent fullscreen blocking */
  .esri-view .esri-ui {
    z-index: 20 !important;
  }

  .esri-expand {
    z-index: 30 !important;
  }

  /* Close button always visible */
  .esri-expand__collapse-button {
    display: flex !important;
  }

  /* Better mobile card sizing */
  .esri-basemap-gallery__item {
    min-height: 72px;
  }

  .esri-basemap-gallery__item-thumbnail {
    height: 70px;
  }

  .activity-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .filter-field:nth-child(3) {
    grid-column: span 2;
  }
  .report-analysis-card {
    margin-top: 1.25rem;
  }

  .apexcharts-legend {
    display: grid !important;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.45rem 0.75rem;
    justify-items: start;
    padding-top: 0.75rem;
  }

  .apexcharts-legend-series {
    width: 100%;
    margin: 0 !important;

    display: flex !important;
    align-items: center;
  }

  .apexcharts-legend-text {
    font-size: 0.72rem !important;
  }

  .analytics-chart-card {
    min-height: 420px !important;
    height: auto !important;
  }
  .dispatcher-map-mode .mobile-actions-toggle {
    display: inline-flex;
    position: absolute;
    top: 5.2rem;
    right: 0.75rem;
    z-index: 160;
    width: 46px;
    height: 46px;
    border-radius: 16px;
    align-items: center;
    justify-content: center;
  }
  .mobile-actions-toggle {
    display: inline-flex !important;

    position: absolute;

    top: 1.35rem;
    right: 1rem;

    z-index: 120;

    width: 42px !important;
    height: 38px !important;
    min-height: 28px !important;

    padding: 0 !important;

    border-radius: 14px;

    align-items: center;
    justify-content: center;
  }

  .dispatcher-map-mode .map-actions {
    position: absolute;
    top: 8.4rem;
    right: 0.75rem;
    z-index: 150;
    width: auto;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .dispatcher-map-mode .map-actions.mobile-collapsed {
    display: none;
  }

  .dispatcher-map-mode .map-actions.mobile-expanded {
    display: flex;
  }

  .dispatcher-map-mode .map-actions .btn {
    width: 46px !important;
    height: 46px !important;
    min-height: 46px !important;
    padding: 0 !important;
    border-radius: 16px;
  }

  .dispatcher-map-mode .map-actions .btn > * {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
  }

  .dispatcher-map-mode .map-actions .mobile-btn-text {
    display: none !important;
  }
  /* Hide ArcGIS zoom buttons */
  .fire-map :deep(.esri-ui-top-left .esri-zoom) {
    display: none !important;
  }

  /* Search at top-left */
  .fire-map :deep(.esri-ui-top-left) {
    top: 0.4rem !important;
    left: 1rem !important;
    right: 4.5rem !important;
    width: auto !important;
  }

  .fire-map :deep(.esri-search) {
    width: 100% !important;
    max-width: none !important;
  }

  .fire-map :deep(.esri-search__container) {
    width: 100% !important;
  }

  /* Move legend + basemap below search */
  .fire-map :deep(.esri-ui-top-right) {
    top: 3.25rem !important;
    left: 1rem !important;
    right: auto !important;

    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
  }

  .fire-map :deep(.esri-component),
  .fire-map :deep(.esri-expand) {
    margin: 0 !important;
  }

  /* hide only text, not icons */
  .map-actions .mobile-btn-text {
    display: none !important;
  }

  .map-actions .btn span:not(.feather-icon):not(.icon) {
    display: none;
  }

  .map-actions .btn svg,
  .map-actions .btn :deep(svg) {
    display: block !important;
    width: 20px !important;
    height: 20px !important;
    stroke: currentColor !important;
  }
  .dispatcher-map-mode .compact-btn {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
  }

  .dispatcher-map-mode .compact-btn svg,
  .dispatcher-map-mode .compact-btn :deep(svg) {
    display: block !important;
    width: 20px !important;
    height: 20px !important;
    stroke: currentColor !important;
  }

  /* only hide text labels */
  .dispatcher-map-mode .mobile-btn-text {
    display: none !important;
  }
  .dispatcher-map-mode .stats-btn {
    display: none !important;
  }
  .report-analysis-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  .analysis-panel {
    margin-top: 1.25rem;
  }
}

@media (min-width: 769px) {
  .dispatcher-map-mode .weather-layer-toolbar {
    top: 9.5rem !important;
    left: 2rem !important;
  }

  .weather-layer-toolbar {
    top: 8.5rem !important;
    left: 1rem !important;
  }

  .activity-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .filter-field:nth-child(3) {
    grid-column: span 2;
  }
}
@media (max-width: 480px) {
  .fire-page {
    padding: 0.6rem;
  }

  .page-header h2 {
    font-size: 1.35rem;
  }

  .map-actions {
    grid-template-columns: 1fr 1fr;
  }

  .map-actions .btn {
    font-size: 0.72rem;
  }

  .fire-map {
    height: 390px;
    min-height: 390px;
  }

  .side-summary-grid {
    grid-template-columns: 1fr;
  }

  .activity-feed-list {
    max-height: 300px;
  }
}
@media (max-width: 420px) {
  .map-actions {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 390px) {
  .fire-page {
    padding: 0.5rem;
    gap: 0.85rem;
  }

  .dashboard-grid,
  .report-side-grid,
  .report-analysis-wrapper {
    gap: 0.85rem;
  }

  .analysis-panel,
  .report-analysis-card,
  .table-card,
  .activity-feed-card,
  .analytics-chart-card {
    border-radius: 0.85rem;
  }

  .side-summary-grid {
    grid-template-columns: 1fr;
  }

  .summary-card {
    padding: 0.85rem;
  }

  .summary-card h3 {
    font-size: 1.45rem;
  }

  .analytics-chart-card {
    height: auto !important;
    min-height: 330px !important;
    padding: 1rem 0.75rem 1.5rem !important;
    overflow: visible !important;
  }

  .analytics-chart-card canvas {
    max-height: 220px !important;
  }
  .chart-legend,
  .apexcharts-legend {
    flex-wrap: wrap !important;
    justify-content: center !important;
    gap: 0.35rem 0.75rem !important;
    padding-top: 0.5rem;
  }

  .chart-legend-item,
  .apexcharts-legend-series {
    font-size: 0.72rem !important;
    margin: 0 !important;
  }

  .chart-card,
  .analysis-card {
    padding: 0.85rem;
  }

  .fire-map,
  .map-area {
    height: 360px;
    min-height: 360px;
  }

  .activity-filter-bar {
    grid-template-columns: 1fr;
  }

  .filter-field:nth-child(3) {
    grid-column: span 1;
  }

  .map-actions {
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
  }

  .map-actions .btn,
  .filter-icon-btn,
  .map-actions .full-map-btn {
    height: 46px;
    min-height: 46px;
    border-radius: 14px;
  }
}
</style>
