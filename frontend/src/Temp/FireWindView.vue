<template>
  <div class="fire-page" :class="{ 'dispatcher-map-mode': isFullMap }">
    <!-- =========================
         PAGE HEADER
    ========================== -->
    <div class="page-header">
      <div>
        <h2>Road Accidents Data Mapping &amp; Analytics Platform (ROADMAP)</h2>
        <p>Powered by OBSERVE™</p>
      </div>
    </div>

    <!-- =========================
         MAP + RIGHT PANEL
    ========================== -->
    <div class="dashboard-grid" :class="{ 'filters-expanded': showActivityFilters }">
      <div
        v-if="hoverSensorCard.visible"
        class="sensor-hover-card"
        :style="{
          left: hoverSensorCard.x + 'px',
          top: hoverSensorCard.y + 'px',
        }"
      >
        <div class="sensor-hover-header">
          <strong>{{ hoverSensorCard.name }}</strong>
          <span :class="hoverSensorCard.statusClass">{{ hoverSensorCard.status }}</span>
        </div>

        <div class="sensor-hover-row">
          <span>Temperature</span>
          <strong>{{ hoverSensorCard.temperature }}</strong>
        </div>

        <div class="sensor-hover-row">
          <span>Humidity</span>
          <strong>{{ hoverSensorCard.humidity }}</strong>
        </div>

        <div class="sensor-hover-row">
          <span>Battery</span>
          <strong>{{ hoverSensorCard.battery }}</strong>
        </div>
      </div>
      <div class="map-card" :class="{ 'full-map-mode': isFullMap }">
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

        <!-- District filter floating panel -->
        <div v-if="showDistrictPanel" class="district-floating-panel">
          <!-- HEADER -->
          <div class="district-panel-header">
            <div class="district-panel-title">
              <FeatherIcon icon="filter" />
              <span>District Filters</span>
            </div>

            <button type="button" class="district-close-btn" @click="showDistrictPanel = false">
              <FeatherIcon icon="x" />
            </button>
          </div>

          <!-- FILTER LIST -->
          <div
            v-for="(enabled, district) in districtToggles"
            :key="district"
            class="district-toggle-row"
          >
            <div class="district-label">
              <FeatherIcon icon="map-pin" />
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

        <!-- Sensor / incident floating list -->
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
                :key="sensor.objectId"
                type="button"
                class="floating-item sensor-card"
                @click="goToFeature(sensor)"
              >
                <h6>{{ sensor.name }}</h6>
                <p>
                  Temperature: <strong>{{ sensor.temperature }}</strong>
                </p>
                <p>
                  Humidity: <strong>{{ sensor.humidity }}</strong>
                </p>
                <p>
                  Air Quality: <strong>{{ sensor.airQuality }}</strong>
                </p>
                <p>
                  Air Pressure: <strong>{{ sensor.airPressure }}</strong>
                </p>
                <small>Click to navigate · Last updated: {{ sensor.lastUpdated }}</small>
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

        <!-- Map error / loading overlay -->
        <div v-if="mapError" class="map-error">
          <FeatherIcon icon="alert-triangle" />
          <h5>Map failed to load</h5>
          <p>{{ mapError }}</p>
        </div>

        <div v-if="loading" class="map-loading-overlay">
          <div class="spinner-wrapper">
            <div class="spinner"></div>
            <h6>Loading Dashboard</h6>
            <p>Preparing WebMap, sensors, incidents, and analytics...</p>
          </div>
        </div>

        <!-- Top analytics cards inside map -->
        <div v-if="showAnalyticsPanel" class="fire-analytics-panel">
          <div class="analytics-card fire-detected">
            <FeatherIcon icon="alert-triangle" />
            <span>Reported Accidents</span>
            <strong>{{ analytics.fireDetected }}</strong>
          </div>

          <div class="analytics-card">
            <span>Mean Temperature (°C)</span>
            <strong class="orange">{{ analytics.meanTemperature }}</strong>
          </div>

          <div class="analytics-card">
            <span>Mean Air Quality</span>
            <strong class="cyan">{{ analytics.meanAirQuality }}</strong>
          </div>

          <div class="analytics-card">
            <span>Mean Humidity</span>
            <strong class="yellow">{{ analytics.meanHumidity }}</strong>
          </div>
        </div>
        <div class="map-area">
          <div v-show="showWeatherOverlay" ref="weatherMapDiv" class="weather-map-overlay"></div>

          <div v-show="!mapError" ref="mapDiv" class="fire-map"></div>

          <div v-if="showWeatherOverlay" class="weather-layer-toolbar">
            <div class="weather-toolbar-row">
              <div class="dropdown weather-dropdown">
                <button
                  class="btn btn-sm btn-phoenix-secondary dropdown-toggle weather-dropdown-btn"
                  type="button"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                >
                  <FeatherIcon :icon="currentWeatherOption.icon" />
                  <span class="weather-label-text">{{ currentWeatherOption.label }}</span>
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
                <strong>{{
                  weatherSourceReady ? weatherPointerValue || '-' : 'Loading...'
                }}</strong>
              </div>
            </div>
          </div>
        </div>
        <!-- Bottom chart panel inside map -->
        <div v-if="showAnalyticsPanel" class="bottom-analytics-panel">
          <div class="analytics-chart-card">
            <h6>Temperature (°C)</h6>
            <Line :data="temperatureData" :options="chartOptions" />
          </div>

          <div class="analytics-chart-card">
            <h6>Air Quality</h6>
            <Line :data="airQualityData" :options="chartOptions" />
          </div>

          <div class="analytics-chart-card">
            <h6>Air Pressure (hPa)</h6>
            <Line :data="airPressureData" :options="chartOptions" />
          </div>
        </div>
      </div>

      <!-- Right alert panel -->
      <div class="side-panel">
        <div class="activity-feed-card">
          <div class="activity-feed-header">
            <div>
              <h5>Live Activity Feed</h5>
              <small>Latest sensor and incident updates</small>
            </div>
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
              v-for="item in activityFeed"
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

    <!-- =========================
         REPORT ANALYSIS
    ========================== -->
    <div class="report-side-grid">
      <div class="report-analysis-wrapper">
        <div class="report-analysis-card">
          <div class="report-header">
            <div>
              <h5>Report Analysis</h5>
              <small>Interactive summary from live incident records</small>
            </div>

            <div class="report-tabs">
              <button
                type="button"
                :class="{ active: reportView === 'district' }"
                @click="reportView = 'district'"
              >
                District
              </button>
              <button
                type="button"
                :class="{ active: reportView === 'type' }"
                @click="reportView = 'type'"
              >
                Type
              </button>
              <button
                type="button"
                :class="{ active: reportView === 'severity' }"
                @click="reportView = 'severity'"
              >
                Severity
              </button>
            </div>
          </div>

          <div class="report-summary-grid">
            <div class="report-mini-card">
              <span>Total Incidents</span>
              <strong>{{ allIncidents.length }}</strong>
            </div>

            <div class="report-mini-card">
              <span>High Severity</span>
              <strong class="danger">{{ highSeverityCount }}</strong>
            </div>

            <div class="report-mini-card">
              <span>Most Common Type</span>
              <strong>{{ mostCommonIncidentType }}</strong>
            </div>
          </div>

          <div class="report-chart-area">
            <template v-if="reportView === 'district'">
              <h6>Accidents Incidents by District</h6>

              <div v-for="item in districtAnalysis" :key="item.label" class="analysis-row">
                <div class="analysis-label">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.count }}</strong>
                </div>

                <div class="analysis-bar-track">
                  <div class="analysis-bar" :style="{ width: item.percent + '%' }"></div>
                </div>
              </div>
            </template>

            <template v-else-if="reportView === 'type'">
              <h6>Accidents Incidents by Type</h6>

              <div v-for="item in typeAnalysis" :key="item.label" class="analysis-row">
                <div class="analysis-label">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.count }}</strong>
                </div>

                <div class="analysis-bar-track">
                  <div class="analysis-bar orange" :style="{ width: item.percent + '%' }"></div>
                </div>
              </div>
            </template>

            <template v-else>
              <h6>Accidents Incidents by Severity</h6>

              <div v-for="item in severityAnalysis" :key="item.label" class="analysis-row">
                <div class="analysis-label">
                  <span>{{ item.label }}</span>
                  <strong>{{ item.count }}</strong>
                </div>

                <div class="analysis-bar-track">
                  <div class="analysis-bar severity" :style="{ width: item.percent + '%' }"></div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <div class="analysis-panel">
          <div class="analysis-panel-header">
            <h5>Incident by Type</h5>
            <span>{{ allIncidents.length }} records</span>
          </div>

          <apexchart
            height="320"
            type="donut"
            :options="typeDonutOptions"
            :series="typeDonutSeries"
          />
        </div>
      </div>

      <div class="report-analysis-wrapper">
        <div class="analysis-panel">
          <div class="analysis-panel-header">
            <div>
              <h5>District Hotspot Ranking</h5>
              <span>Highest concentration</span>
            </div>
          </div>

          <apexchart
            height="320"
            type="bar"
            :options="districtBarOptions"
            :series="districtBarSeries"
          />
        </div>

        <div class="analysis-panel">
          <div class="analysis-panel-header">
            <div>
              <h5>Top Incident Hotspots</h5>
              <span>{{ typeAnalysis.length }} types shown</span>
            </div>
          </div>

          <apexchart height="320" type="bar" :options="typeBarOptions" :series="typeBarSeries" />
        </div>
      </div>
    </div>

    <!-- =========================
         INCIDENT TABLE
    ========================== -->
    <!-- <div class="table-card incidents-table-card">
      <div class="incidents-table-header">
        <div>
          <h5>Recent Fire Incidents</h5>
          <small>Live records from NIAT Database</small>
        </div>

        <span class="incident-count">{{ incidents.length }} records</span>
      </div>

      <div class="table-responsive custom-table">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Location</th>
              <th>Incident Type</th>
              <th>Status</th>
              <th>Reported Time</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="incidents.length === 0">
              <td colspan="4" class="text-center text-body-secondary py-4">
                No incident records found.
              </td>
            </tr>

            <tr v-for="incident in incidents" :key="incident.objectId">
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
            </tr>
          </tbody>
        </table>
      </div>
    </div> -->
  </div>
</template>

<script setup>
// =========================
// VUE + COMPONENT IMPORTS
// =========================
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Line } from 'vue-chartjs'
import FeatherIcon from '@/components/FeatherIcon.vue'

// =========================
// ARCGIS IMPORTS
// =========================
import WebMap from '@arcgis/core/WebMap'
import MapView from '@arcgis/core/views/MapView'
import Expand from '@arcgis/core/widgets/Expand'
import Legend from '@arcgis/core/widgets/Legend'
import Search from '@arcgis/core/widgets/Search'
import BasemapGallery from '@arcgis/core/widgets/BasemapGallery'
import '@arcgis/core/assets/esri/themes/light/main.css'

// =========================
// WEBMAP CONFIG
// =========================
const WEBMAP_ID = '856dfba55848493eaa93565c87c440bf'

const layerTitles = {
  accidents: 'RADED Accident Points',
  hotspots: 'Accident Hotspot',
  population: 'Mukim Population',
}

const activityFeed = computed(() => {
  return incidents.value
    .map((accident, index) => ({
      id: `accident-${accident.objectId || index}`,
      title: `${accident.type || 'Accident'} reported`,
      message: `${accident.location || 'Unknown'} · ${accident.status || 'Unknown'}`,
      time: accident.reportedAt || 'Unknown date',
      sortTime: accident.rawTime || Date.now(),
      severity: String(accident.status).toLowerCase().includes('serious') ? 'high' : 'medium',
      kind: 'accident',
      ...accident,
    }))
    .sort((a, b) => Number(b.sortTime || 0) - Number(a.sortTime || 0))
    .slice(0, 10)
})
// =========================
// MAP STATE
// =========================
const mapDiv = ref(null)
const mapError = ref('')
const loading = ref(false)
const isFullMap = ref(false)

let view = null
let webmap = null

// =========================
// DASHBOARD STATE
// =========================
const stats = reactive({
  activeIncidents: 0,
  onlineSensors: 0,
  activeAlerts: 0,
  responseTeams: 0,
})

const alerts = ref([])
const sensors = ref([])
const incidents = ref([])
const allIncidents = ref([])
const selectedFeature = ref(null)

// =========================
// UI STATE
// =========================
const activePanel = ref(null)
const searchKeyword = ref('')
const reportView = ref('district')
const showAnalyticsPanel = ref(false)
const showDistrictPanel = ref(false)
const weatherMapDiv = ref(null)
const showWeatherOverlay = ref(false)
const activeWeatherLayer = ref('radar')
const weatherPointerValue = ref('')
const weatherSourceReady = ref(false)

let weatherMap = null
let weatherLayer = null
let weatherSyncHandle = null

const MAPTILER_KEY = 'CAgganf0DMElVWkxBZeq'

const weatherLayerOptions = [
  { id: 'temperature', label: 'Temperature', icon: 'thermometer' },
  { id: 'wind', label: 'Wind', icon: 'wind' },
  { id: 'radar', label: 'Radar', icon: 'radio' },
  { id: 'pressure', label: 'Pressure', icon: 'activity' },
  { id: 'precipitation', label: 'Precipitation', icon: 'cloud-rain' },
]

const currentWeatherOption = computed(() => {
  return (
    weatherLayerOptions.find((l) => l.id === activeWeatherLayer.value) || weatherLayerOptions[0]
  )
})

const districtToggles = ref({
  Brunei_Muara: false,
  Temburong: false,
  Belait: false,
  Tutong: false,
})

// =========================
// REPORT ANALYSIS
// =========================
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

const districtAnalysis = computed(() => groupCount(allIncidents.value, 'district'))
const typeAnalysis = computed(() => groupCount(allIncidents.value, 'type'))
const severityAnalysis = computed(() => groupCount(allIncidents.value, 'status'))

const highSeverityCount = computed(() => {
  return allIncidents.value.filter((accident) =>
    String(accident.status).toLowerCase().includes('serious'),
  ).length
})

const mostCommonIncidentType = computed(() => typeAnalysis.value[0]?.label || '-')

// =========================
// APEX CHART CONFIG
// =========================
const chartColors = [
  '#3b82f6',
  '#22c55e',
  '#f59e0b',
  '#ef4444',
  '#8b5cf6',
  '#06b6d4',
  '#fb7185',
  '#14b8a6',
]

const typeDonutSeries = computed(() => typeAnalysis.value.map((item) => item.count))

const typeDonutOptions = computed(() => ({
  chart: {
    type: 'donut',
    toolbar: { show: false },
  },
  labels: typeAnalysis.value.map((item) => item.label),
  colors: chartColors,
  legend: {
    position: 'bottom',
  },
  dataLabels: {
    enabled: true,
  },
  tooltip: {
    y: {
      formatter: (value) => `${value} incidents`,
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total',
            formatter: () => allIncidents.value.length,
          },
        },
      },
    },
  },
}))

const districtBarSeries = computed(() => [
  {
    name: 'Incidents',
    data: districtAnalysis.value.map((item) => item.count),
  },
])

const districtBarOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
  },
  colors: ['#3b82f6'],
  plotOptions: {
    bar: {
      borderRadius: 8,
      horizontal: true,
    },
  },
  xaxis: {
    categories: districtAnalysis.value.map((item) => item.label.replace('_', ' ')),
  },
  tooltip: {
    y: {
      formatter: (value) => `${value} incidents`,
    },
  },
}))

const typeBarSeries = computed(() => [
  {
    name: 'Incidents',
    data: typeAnalysis.value.map((item) => item.count),
  },
])

const typeBarOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
  },
  colors: ['#0ea5e9'],
  plotOptions: {
    bar: {
      borderRadius: 8,
      columnWidth: '45%',
    },
  },
  xaxis: {
    categories: typeAnalysis.value.map((item) => item.label),
  },
  tooltip: {
    y: {
      formatter: (value) => `${value} incidents`,
    },
  },
}))

// =========================
// LINE CHART CONFIG
// =========================
const temperatureData = computed(() => ({
  labels: sensors.value.map((s) => s.name),
  datasets: [
    {
      data: sensors.value.map(() => 28),
      backgroundColor: 'rgba(0, 201, 167, 0.65)',
      borderWidth: 0,
      fill: true,
      pointRadius: 0,
    },
    {
      data: sensors.value.map(() => 33),
      backgroundColor: 'rgba(255, 193, 7, 0.7)',
      borderWidth: 0,
      fill: '-1',
      pointRadius: 0,
    },
    {
      data: sensors.value.map(() => 40),
      backgroundColor: 'rgba(220, 53, 69, 0.7)',
      borderWidth: 0,
      fill: '-1',
      pointRadius: 0,
    },
    {
      label: 'Temperature',
      data: sensors.value.map((s) => cleanNumber(s.temperature)),
      borderColor: '#4ea3ff',
      backgroundColor: '#4ea3ff',
      tension: 0.35,
      pointRadius: 3,
      borderWidth: 3,
    },
  ],
}))

const airQualityData = computed(() => ({
  labels: sensors.value.map((s) => s.name),
  datasets: [
    {
      label: 'Air Quality',
      data: sensors.value.map((s) => cleanNumber(s.airQuality)),
      borderColor: '#38bdf8',
      backgroundColor: '#38bdf8',
      tension: 0.35,
      pointRadius: 3,
      borderWidth: 3,
    },
  ],
}))

const airPressureData = computed(() => ({
  labels: sensors.value.map((s) => s.name),
  datasets: [
    {
      label: 'Air Pressure',
      data: sensors.value.map((s) => cleanNumber(s.airPressure)),
      borderColor: '#ffc107',
      backgroundColor: '#ffc107',
      tension: 0.35,
      pointRadius: 3,
      borderWidth: 3,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.raw}`,
      },
    },
  },
  scales: {
    x: {
      ticks: {
        color: '#fff',
        maxRotation: 45,
        minRotation: 45,
        font: { size: 10 },
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

// =========================
// FILTERED LISTS
// =========================
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

// =========================
// MAIN ANALYTICS
// =========================
const analytics = computed(() => {
  const temperatureValues = sensors.value
    .map((s) => parseFloat(String(s.temperature).replace(/[^\d.-]/g, '')))
    .filter((n) => !Number.isNaN(n))

  const humidityValues = sensors.value
    .map((s) => parseFloat(String(s.humidity).replace(/[^\d.-]/g, '')))
    .filter((n) => !Number.isNaN(n))

  const airQualityValues = sensors.value
    .map((s) => parseFloat(String(s.airQuality).replace(/[^\d.-]/g, '')))
    .filter((n) => !Number.isNaN(n))

  const avg = (values) => {
    if (!values.length) return '-'
    return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(2)
  }

  const fireDetected = incidents.value.filter((incident) => {
    return String(incident.type).toLowerCase().includes('fire')
  }).length

  return {
    fireDetected,
    meanTemperature: avg(temperatureValues),
    meanAirQuality: avg(airQualityValues),
    meanHumidity: avg(humidityValues),
  }
})

// =========================
// MAP LOADING
// =========================
async function loadWebMap() {
  try {
    loading.value = true
    mapError.value = ''

    webmap = new WebMap({
      portalItem: { id: WEBMAP_ID },
    })

    await webmap.loadAll()

    disableDefaultPopups()

    view = new MapView({
      container: mapDiv.value,
      map: webmap,
      center: [114.7277, 4.5353],
      zoom: 9,
    })

    await view.when()

    view.popup.autoOpenEnabled = false
    view.popupEnabled = false

    view.on('pointer-move', handleSensorHover)
    view.on('pointer-move', handleWeatherPointerMove)

    view.map.watch('basemap', async () => {
      // console.log('Basemap changed:', view.map.basemap?.title)

      if (showWeatherOverlay.value) {
        await forceWeatherSafeBasemap()
      }
    })

    view.on('click', async (event) => {
      const hit = await view.hitTest(event)

      // console.log('=== MAP CLICK DEBUG ===')
      // console.log('Clicked point:', event.mapPoint)
      // console.log('Hit results:', hit.results)

      hit.results.forEach((result, index) => {
        const graphic = result.graphic
        const layer = graphic?.layer

        // console.log(`Hit Result ${index + 1}:`, {
        //   layerTitle: layer?.title,
        //   layerType: layer?.type,
        //   attributes: graphic?.attributes,
        //   geometry: graphic?.geometry,
        //   isCluster: graphic?.attributes?.cluster_count !== undefined,
        // })
      })

      await handleMapClick(event)
    })

    await refreshLiveData()
    await addMapWidgets()
    startLiveAutoRefresh()
  } catch (error) {
    console.error('Failed to load WebMap:', error)

    mapError.value =
      'Unable to load WebMap. Check WebMap ID, layer sharing, credentials, or network access.'
  } finally {
    loading.value = false
  }
}

function disableDefaultPopups() {
  if (!webmap) return

  webmap.allLayers.toArray().forEach((layer) => {
    if ('popupEnabled' in layer) {
      layer.popupEnabled = false
    }

    if ('popupTemplate' in layer) {
      layer.popupTemplate = null
    }

    // Disable broken cluster popup templates
    if (layer.featureReduction) {
      layer.featureReduction = {
        ...layer.featureReduction,
        popupEnabled: false,
        popupTemplate: null,
      }
    }
  })
}

async function openCustomFeaturePopup({ title, attrs, geometry, layer }) {
  const layerTitle = String(title || '').toLowerCase()

  if (layerTitle.includes('raded accident points')) {
    await openAccidentPopup({
      attrs,
      geometry,
      layer,
    })
    return
  }

  const rows = buildPopupRows(attrs)

  view.openPopup({
    title,
    location: geometry,
    content: `
      <div class="custom-map-popup">
        ${rows
          .map(
            (row) => `
              <div class="custom-popup-row">
                <span>${row.label}</span>
                <strong>${row.value}</strong>
              </div>
            `,
          )
          .join('')}
      </div>
    `,
  })
}

async function openAccidentPopup({ attrs, geometry, layer }) {
  let fullAttrs = attrs

  const fullFeature = await queryAccidentFeature(layer, attrs)

  if (fullFeature) {
    fullAttrs = fullFeature.attributes || attrs
    // console.log('=== FULL ACCIDENT ATTRIBUTES ===')
    // console.table(fullAttrs)
  }

  const dateValue = formatAccidentDate(
    getAttr(fullAttrs, ['datetime_reported', 'DATE', 'Accident_Date', 'date']),
  )
  console.log('USING IN POPUP:', {
    date: fullAttrs.Accident_date,
    category: fullAttrs.Accident_category,
    severity: fullAttrs.Accident_severity,
    type: fullAttrs.Accident_type,
  })
  view.openPopup({
    title: 'Accident Details',
    location: geometry,
    content: `
    <div class="accident-popup">
      <div class="popup-section">
       <p><strong>Date:</strong> ${formatAccidentDate(fullAttrs.Accident_date)}</p>
        <p><strong>Category:</strong> ${fullAttrs.Accident_category || 'Undefined'}</p>
        <p><strong>Severity:</strong> ${fullAttrs.Accident_severity || 'Undefined'}</p>
        <p><strong>Type:</strong> ${fullAttrs.Accident_type || 'Undefined'}</p>
        <p><strong>Mukim & District:</strong> ${fullAttrs['Mukim'] || 'Undefined'}, ${fullAttrs['District'] || 'Undefined'}</p>
        <p><strong>First Road Name:</strong> ${fullAttrs['First_road_name'] || 'Undefined'}</p>
        <p><strong>No. of involved victims:</strong> ${getAccidentAttr(fullAttrs, ['No_of_victims'])}</p>
        <p><strong>No. of casualties:</strong> ${getAccidentAttr(fullAttrs, ['No_of_casualties'])}</p>
        <p><strong>No. of affected vehicles:</strong> ${getAccidentAttr(fullAttrs, ['No_of_affected_vehicles'])}</p>
        <p><strong>Contributary factors:</strong> ${fullAttrs.Contributary_factor || 'Undefined'}</p>
      </div>

      <div class="popup-section">
        <h6>Driver Details:</h6>
        <p><strong>Age of driver:</strong> ${getAccidentAttr(fullAttrs, ['Age_of_driver'])}</p>
        <p><strong>Sex of driver:</strong> ${getAccidentAttr(fullAttrs, ['Sex_of_driver'])}</p>
        <p><strong>Driver years of driving:</strong> ${getAccidentAttr(fullAttrs, ['Driver_years_of_driving'])}</p>
        <p><strong>Nationality:</strong> ${getAccidentAttr(fullAttrs, ['Driver_nationality'])}</p>
      </div>

      <div class="popup-section">
        <h6>Casualty Details:</h6>
        <p><strong>Age of casualty:</strong> ${getAccidentAttr(fullAttrs, ['Age_of_casualty'])}</p>
        <p><strong>Sex of casualty:</strong> ${getAccidentAttr(fullAttrs, ['Sex_of_casualty'])}</p>
        <p><strong>Casualty class:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_class'])}</p>
        <p><strong>Casualty Severity:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_severity'])}</p>
        <p><strong>Injury:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_injury'])}</p>
      </div>

      <div class="popup-section">
        <h6>Environmental & Road Conditions:</h6>
        <p><strong>Weather:</strong> ${getAccidentAttr(fullAttrs, ['Weather'])}</p>
        <p><strong>Light condition:</strong> ${getAccidentAttr(fullAttrs, ['Light_condition'])}</p>
        <p><strong>Road condition:</strong> ${getAccidentAttr(fullAttrs, ['Road_type'])}</p>
        <p><strong>Road junction:</strong> ${getAccidentAttr(fullAttrs, ['Road_junction'])}</p>
        <p><strong>Road junction control:</strong> ${getAccidentAttr(fullAttrs, ['Road_junction_category'])}</p>
        <p><strong>Road surface condition:</strong> ${getAccidentAttr(fullAttrs, ['Road_surface_condition'])}</p>
        <p><strong>Road surface type:</strong> ${getAccidentAttr(fullAttrs, ['Road_surface_type'])}</p>
      </div>

      <div class="popup-section">
        <h6>Vehicle details:</h6>
        <p><strong>Vehicle type:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_type'])}</p>
        <p><strong>Age of vehicle (year):</strong> ${getAccidentAttr(fullAttrs, ['Age_of_vehicle'])}</p>
        <p><strong>First point impact:</strong> ${getAccidentAttr(fullAttrs, ['First_point_impact'])}</p>
        <p><strong>Vehicle model:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_model'])}</p>
        <p><strong>Vehicle usage:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_usage'])}</p>
      </div>
    </div>
  `,
  })
}

async function queryAccidentFeature(layer, attrs) {
  if (!layer?.createQuery) return null

  console.table(attrs)

  const objectIdField = layer.objectIdField || 'OBJECTID'

  const objectId =
    attrs[objectIdField] ||
    attrs.OBJECTID ||
    attrs.ObjectId ||
    attrs.objectid ||
    attrs.objectId ||
    attrs.FID ||
    attrs.FID2

  if (!objectId) {
    console.warn('No object id found for accident feature')
    return null
  }

  try {
    const query = layer.createQuery()
    query.where = `${objectIdField} = ${objectId}`
    query.outFields = ['*']
    query.returnGeometry = true

    const result = await layer.queryFeatures(query)

    console.table(result.features?.[0]?.attributes)

    return result.features?.[0] || null
  } catch (error) {
    console.error('Accident query failed:', error)
    return null
  }
}
function getAccidentAttr(attrs, names, fallback = 'Undefined') {
  const value = getAttr(attrs, names, fallback)

  if (value === null || value === undefined || value === '' || value === '-') {
    return fallback
  }

  return value
}

function getMukimDistrict(attrs) {
  const mukim = getAccidentAttr(attrs, ['mukim', 'Mukim'])
  const district = getAccidentAttr(attrs, ['district', 'District'])

  if (mukim === 'Undefined' && district === 'Undefined') return 'Undefined'
  if (mukim === 'Undefined') return district
  if (district === 'Undefined') return mukim

  return `${mukim}, ${district}`
}

function formatAccidentDate(value) {
  if (!value || value === '-') return 'Undefined'

  const date = new Date(Number(value))

  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}
function buildPopupRows(attrs) {
  const hiddenFields = [
    'objectid',
    'shape',
    'globalid',
    'created_user',
    'created_date',
    'last_edited_user',
    'last_edited_date',
  ]

  const entries = Object.entries(attrs || {}).filter(([key, value]) => {
    if (value === null || value === undefined || value === '') return false

    const lowerKey = key.toLowerCase()

    if (hiddenFields.some((field) => lowerKey.includes(field))) return false

    return true
  })

  if (!entries.length) {
    return [{ label: 'Details', value: 'No attributes available' }]
  }

  return entries.slice(0, 12).map(([key, value]) => ({
    label: formatPopupLabel(key),
    value: formatPopupValue(value),
  }))
}

function formatPopupLabel(key) {
  return String(key)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

function formatPopupValue(value) {
  if (typeof value === 'number') {
    // ArcGIS date timestamp
    if (value > 1000000000000) {
      return new Date(value).toLocaleString()
    }

    return value.toLocaleString()
  }

  return String(value)
}

// =========================
// MAP CLICK HANDLER
// =========================
async function handleMapClick(event) {
  if (!view) return

  const hit = await view.hitTest(event)

  const result = hit.results.find((item) => {
    const layerTitle = item.graphic?.layer?.title?.toLowerCase() || ''
    const attrs = item.graphic?.attributes || {}

    return (
      attrs.cluster_count !== undefined ||
      layerTitle.includes('accident') ||
      layerTitle.includes('hotspot') ||
      layerTitle.includes('incident') ||
      layerTitle.includes('sensor') ||
      layerTitle.includes('hospital') ||
      layerTitle.includes('police') ||
      layerTitle.includes('station') ||
      layerTitle.includes('population') ||
      layerTitle.includes('mukim')
    )
  })

  if (!result) return

  const graphic = result.graphic
  const attrs = graphic.attributes || {}
  const layer = graphic.layer
  const layerTitle = layer?.title || 'Feature'

  selectedFeature.value = {
    title: layerTitle,
    attrs,
    geometry: graphic.geometry,
  }

  openCustomFeaturePopup({
    title: layerTitle,
    attrs,
    geometry: graphic.geometry || event.mapPoint,
    layer,
  })
}

function isImageryBasemap() {
  const title = view?.map?.basemap?.title?.toLowerCase() || ''

  return title.includes('imagery')
}

function handleWeatherPointerMove(event) {
  if (!showWeatherOverlay.value || !weatherLayer || !weatherSourceReady.value || !view) return

  const point = view.toMap({ x: event.x, y: event.y })
  if (!point) return

  const picked = weatherLayer.pickAt(point.longitude, point.latitude)

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
async function forceWeatherSafeBasemap() {
  if (!view) return

  if (showWeatherOverlay.value && isImageryBasemap()) {
    view.map.basemap = 'streets-night-vector'

    await view.when()
    await nextTick()

    forceMapRepaint()
  }
}

function forceMapRepaint() {
  if (weatherMap) {
    weatherMap.resize()
    syncWeatherToArcGIS()
  }

  const container = view?.container

  if (container) {
    container.style.transform = 'translateZ(0)'
    requestAnimationFrame(() => {
      container.style.transform = ''
    })
  }
}

async function toggleWeatherOverlay() {
  showWeatherOverlay.value = !showWeatherOverlay.value

  await nextTick()

  if (showWeatherOverlay.value) {
    await forceWeatherSafeBasemap()
    initWeatherOverlay()

    setTimeout(() => {
      if (weatherMap) {
        weatherMap.resize()
        syncWeatherToArcGIS()
      }
    }, 300)
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

  weatherSourceReady.value = false
  weatherPointerValue.value = ''

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
function isCluster(attrs) {
  return attrs.cluster_count !== undefined && attrs.cluster_count > 1
}

// =========================
// CUSTOM POPUPS
// =========================

async function openIncidentPopup({ graphic, attrs, layer }) {
  let fullAttrs = attrs

  const fullFeature = await queryFeatureByObjectId(layer, attrs)
  if (fullFeature) {
    fullAttrs = fullFeature.attributes || attrs
  }

  const dateRaw = fullAttrs.datetime_reported || fullAttrs.created_date
  const dateValue = dateRaw ? new Date(dateRaw).toLocaleString() : '-'
  console.log('USING IN POPUP:', {
    date: fullAttrs.Accident_date,
    category: fullAttrs.Accident_category,
    severity: fullAttrs.Accident_severity,
    type: fullAttrs.Accident_type,
  })
  view.openPopup({
    title: 'Accident Details',
    location: geometry,
    content: `
    <div class="accident-popup">
      <div class="popup-section">
       <p><strong>Date:</strong> ${formatAccidentDate(fullAttrs.Accident_date)}</p>
        <p><strong>Category:</strong> ${fullAttrs.Accident_category || 'Undefined'}</p>
        <p><strong>Severity:</strong> ${fullAttrs.Accident_severity || 'Undefined'}</p>
        <p><strong>Type:</strong> ${fullAttrs.Accident_type || 'Undefined'}</p>
        <p><strong>Mukim & District:</strong> ${fullAttrs['Mukim'] || 'Undefined'}, ${fullAttrs['District'] || 'Undefined'}</p>
        <p><strong>First Road Name:</strong> ${fullAttrs['First_road_name'] || 'Undefined'}</p>
        <p><strong>No. of involved victims:</strong> ${getAccidentAttr(fullAttrs, ['No_of_victims'])}</p>
        <p><strong>No. of casualties:</strong> ${getAccidentAttr(fullAttrs, ['No_of_casualties'])}</p>
        <p><strong>No. of affected vehicles:</strong> ${getAccidentAttr(fullAttrs, ['No_of_affected_vehicles'])}</p>
        <p><strong>Contributary factors:</strong> ${fullAttrs.Contributary_factor || 'Undefined'}</p>
      </div>

      <div class="popup-section">
        <h6>Driver Details:</h6>
        <p><strong>Age of driver:</strong> ${getAccidentAttr(fullAttrs, ['Age_of_driver'])}</p>
        <p><strong>Sex of driver:</strong> ${getAccidentAttr(fullAttrs, ['Sex_of_driver'])}</p>
        <p><strong>Driver years of driving:</strong> ${getAccidentAttr(fullAttrs, ['Driver_years_of_driving'])}</p>
        <p><strong>Nationality:</strong> ${getAccidentAttr(fullAttrs, ['Driver_nationality'])}</p>
      </div>

      <div class="popup-section">
        <h6>Casualty Details:</h6>
        <p><strong>Age of casualty:</strong> ${getAccidentAttr(fullAttrs, ['Age_of_casualty'])}</p>
        <p><strong>Sex of casualty:</strong> ${getAccidentAttr(fullAttrs, ['Sex_of_casualty'])}</p>
        <p><strong>Casualty class:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_class'])}</p>
        <p><strong>Casualty Severity:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_severity'])}</p>
        <p><strong>Injury:</strong> ${getAccidentAttr(fullAttrs, ['Casualty_injury'])}</p>
      </div>

      <div class="popup-section">
        <h6>Environmental & Road Conditions:</h6>
        <p><strong>Weather:</strong> ${getAccidentAttr(fullAttrs, ['Weather'])}</p>
        <p><strong>Light condition:</strong> ${getAccidentAttr(fullAttrs, ['Light_condition'])}</p>
        <p><strong>Road condition:</strong> ${getAccidentAttr(fullAttrs, ['Road_type'])}</p>
        <p><strong>Road junction:</strong> ${getAccidentAttr(fullAttrs, ['Road_junction'])}</p>
        <p><strong>Road junction control:</strong> ${getAccidentAttr(fullAttrs, ['Road_junction_category'])}</p>
        <p><strong>Road surface condition:</strong> ${getAccidentAttr(fullAttrs, ['Road_surface_condition'])}</p>
        <p><strong>Road surface type:</strong> ${getAccidentAttr(fullAttrs, ['Road_surface_type'])}</p>
      </div>

      <div class="popup-section">
        <h6>Vehicle details:</h6>
        <p><strong>Vehicle type:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_type'])}</p>
        <p><strong>Age of vehicle (year):</strong> ${getAccidentAttr(fullAttrs, ['Age_of_vehicle'])}</p>
        <p><strong>First point impact:</strong> ${getAccidentAttr(fullAttrs, ['First_point_impact'])}</p>
        <p><strong>Vehicle model:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_model'])}</p>
        <p><strong>Vehicle usage:</strong> ${getAccidentAttr(fullAttrs, ['Vehicle_usage'])}</p>
      </div>
    </div>
  `,
  })
}

// =========================
// ARCGIS QUERY HELPERS
// =========================
async function queryFeatureByObjectId(layer, attrs) {
  if (!layer?.createQuery) return null

  const objectId =
    attrs.OBJECTID || attrs.objectid || attrs.ObjectId || attrs.objectId || attrs.FID || attrs.fid
  if (!objectId) return null

  try {
    const objectIdField = layer.objectIdField || 'OBJECTID'
    const query = layer.createQuery()

    query.where = `${objectIdField} = ${objectId}`
    query.outFields = ['*']
    query.returnGeometry = true

    const response = await layer.queryFeatures(query)
    return response.features?.[0] || null
  } catch (error) {
    console.warn('Failed to query feature by object ID:', error)
    return null
  }
}

async function queryNearbyMeasurement(mapPoint) {
  const measurementLayer =
    findLayerByTitle(layerTitles.sensorsAlt) ||
    findLayerByTitleIncludes('measurement') ||
    findLayerByTitleIncludes('sensor readings') ||
    findLayerByTitleIncludes('readings')

  if (!measurementLayer?.createQuery) return null

  try {
    const query = measurementLayer.createQuery()
    query.geometry = mapPoint
    query.distance = 200
    query.units = 'meters'
    query.spatialRelationship = 'intersects'
    query.outFields = ['*']
    query.returnGeometry = true

    const response = await measurementLayer.queryFeatures(query)
    return response.features?.[0] || null
  } catch (error) {
    console.warn('Failed to query nearby measurement:', error)
    return null
  }
}

async function getCount(layer, where = '1=1') {
  if (!layer?.createQuery) return 0

  try {
    const query = layer.createQuery()
    query.where = where
    return await layer.queryFeatureCount(query)
  } catch (error) {
    console.warn(`Count failed for ${layer.title}`, error)
    return 0
  }
}

async function getFeatures(layer, options = {}) {
  if (!layer?.createQuery) return []

  try {
    const query = layer.createQuery()
    query.where = options.where || '1=1'
    query.outFields = ['*']
    query.returnGeometry = true
    query.num = options.limit || 10

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

// =========================
// LAYER HELPERS
// =========================
function findLayerByTitle(title) {
  if (!webmap || !title) return null

  return webmap.allLayers.toArray().find((layer) => {
    return (
      String(layer.title || '')
        .toLowerCase()
        .trim() === String(title).toLowerCase().trim()
    )
  })
}

function findLayerByTitleIncludes(keyword) {
  if (!webmap) return null

  return webmap.allLayers.toArray().find((layer) => {
    return layer.title?.toLowerCase().includes(keyword.toLowerCase())
  })
}

async function addMapWidgets() {
  const searchWidget = new Search({ view })
  const basemapGallery = new BasemapGallery({ view })

  const accidentLayer =
    findLayerByTitle(layerTitles.accidents) || findLayerByTitleIncludes('raded accident points')

  const hotspotLayer = findLayerByTitle(layerTitles.hotspots) || findLayerByTitleIncludes('hotspot')

  const populationLayer =
    findLayerByTitle(layerTitles.population) || findLayerByTitleIncludes('population')

  const legendLayers = [accidentLayer, hotspotLayer, populationLayer].filter(Boolean)

  for (const layer of legendLayers) {
    try {
      await layer.load()
    } catch (error) {
      console.warn('Legend load failed:', layer.title)
    }
  }

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
      }),
      new Expand({
        view,
        content: basemapGallery,
        expandIcon: 'basemap',
      }),
    ],
    'top-right',
  )
}

// =========================
// LIVE DATA REFRESH
// =========================
async function refreshLiveData() {
  if (!webmap) return

  loading.value = true

  try {
    const accidentLayer =
      findLayerByTitle(layerTitles.accidents) ||
      findLayerByTitleIncludes('raded accident points') ||
      findLayerByTitleIncludes('accident')

    const accidentWhere = buildActivityWhereClause()

    const filteredAccidentFeatures = await getFeatures(accidentLayer, {
      limit: 50,
      where: accidentWhere,
      orderByFields: ['Accident_date DESC'],
    })

    const allAccidentFeatures = await getFeatures(accidentLayer, {
      limit: 1000,
      where: '1=1',
      orderByFields: ['Accident_date DESC'],
    })

    incidents.value = mapAccidentFeatures(filteredAccidentFeatures)
    allIncidents.value = mapAccidentFeatures(allAccidentFeatures)

    detectNewIncidents(incidents.value)

    stats.activeIncidents = await getCount(accidentLayer, accidentWhere)
    stats.activeAlerts = 0
    stats.onlineSensors = 0
    stats.responseTeams = 0

    refreshLayerData()
  } finally {
    loading.value = false
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

// =========================
// DISTRICT FILTER
// =========================
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

  const filterLayers = [
    'Incident Reported',
    'Resolved Incident',
    'Sensor Readings',
    'Sensor Alerts',
    'Fire Sensors',
  ]

  webmap.allLayers.toArray().forEach((layer) => {
    if (filterLayers.includes(layer.title) && 'definitionExpression' in layer) {
      layer.definitionExpression = where
      layer.refresh?.()
    }
  })

  await refreshLiveData()
}

// =========================
// DATA MAPPERS
// =========================
function mapAlertFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const rawTime = getAttr(
      attrs,
      [
        'datetime_reported',
        'datetime',
        'created_date',
        'Created_Date',
        'CreationDate',
        'created_at',
      ],
      Date.now(),
    )

    return {
      objectId: getAttr(attrs, ['OBJECTID', 'ObjectId', 'objectid']),
      graphic: feature,
      title: getAttr(attrs, ['name', 'Name', 'title', 'Title', 'alert_type', 'AlertType'], 'Alert'),
      description: getAttr(
        attrs,
        ['description', 'Description', 'remarks', 'Remarks', 'message', 'Message'],
        'Alert detected.',
      ),
      status: getAttr(attrs, ['status', 'Status', 'severity', 'Severity'], 'Active'),
      reportedAt: formatDate(rawTime),
      rawTime,
    }
  })
}

function mapAccidentFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const rawTime = getAttr(attrs, ['Accident_date'], Date.now())

    return {
      objectId: getAttr(attrs, ['FID', 'FID2', 'OBJECTID', 'ObjectId', 'objectid']),
      graphic: feature,

      reference: getAttr(attrs, ['Accident_reference'], '-'),
      type: getAttr(attrs, ['Accident_type'], 'Unknown'),
      category: getAttr(attrs, ['Accident_category'], 'Unknown'),
      status: getAttr(attrs, ['Accident_severity'], 'Unknown'),

      location: `${getAttr(attrs, ['Mukim'], 'Unknown')}, ${getAttr(attrs, ['District'], 'Unknown')}`,
      district: getAttr(attrs, ['District'], 'Unknown'),
      mukim: getAttr(attrs, ['Mukim'], 'Unknown'),

      roadName: getAttr(attrs, ['First_road_name'], '-'),
      weather: getAttr(attrs, ['Weather'], '-'),
      roadType: getAttr(attrs, ['Road_Type'], '-'),
      roadSurface: getAttr(attrs, ['Road_surface_condition'], '-'),

      casualties: getAttr(attrs, ['No_of_casualties'], 0),
      victims: getAttr(attrs, ['No_of_victims'], 0),
      affectedVehicles: getAttr(attrs, ['No_of_affected_vehicles'], 0),

      remarks: getAttr(attrs, ['Contributary_factor'], '-'),
      reportedAt: formatAccidentDate(rawTime),
      rawTime,
      resolved: 'No',
    }
  })
}

// =========================
// FORMAT HELPERS
// =========================
function getAttr(attrs, possibleNames, fallback = '-') {
  for (const name of possibleNames) {
    if (attrs?.[name] !== undefined && attrs?.[name] !== null && attrs?.[name] !== '') {
      return attrs[name]
    }
  }

  return fallback
}

function cleanNumber(value) {
  if (value === null || value === undefined || value === '-') return null

  const num = Number(String(value).replace(/[^\d.-]/g, ''))
  return Number.isNaN(num) ? null : num
}

function formatDate(value) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleString()
}

function formatSensorValue(value, unit) {
  if (value === '-' || value === null || value === undefined || value === '') return '-'

  const cleanValue = String(value).trim()

  if (
    cleanValue.toLowerCase().includes('urn:') ||
    cleanValue.toLowerCase().includes('forestfloor')
  ) {
    return '-'
  }

  const numberValue = Number(cleanValue)

  if (Number.isNaN(numberValue)) {
    return '-'
  }

  return `${numberValue} ${unit}`
}

// =========================
// UI ACTIONS
// =========================
async function toggleFullMap() {
  isFullMap.value = !isFullMap.value

  document.body.classList.toggle('full-map-open', isFullMap.value)
  document.documentElement.classList.toggle('full-map-open', isFullMap.value)

  await nextTick()

  setTimeout(() => {
    if (weatherMap) {
      weatherMap.resize()
      syncWeatherToArcGIS()
    }
  }, 250)
}

function togglePanel(panel) {
  activePanel.value = activePanel.value === panel ? null : panel
  searchKeyword.value = ''
}

async function goToFeature(item) {
  if (!view || !item.graphic?.geometry) return

  view.popup.autoOpenEnabled = false

  await view.goTo(
    {
      target: item.graphic.geometry,
      zoom: 15,
    },
    {
      duration: 900,
      easing: 'ease-in-out',
    },
  )

  selectedFeature.value = buildSelectedFeature(item)

  const isIncident = item.type !== undefined && item.reportedAt !== undefined

  if (isIncident) {
    view.openPopup({
      title: 'Incident Reported',
      location: item.graphic.geometry,
      content: `
        <div style="padding:8px 4px;">
          <p><strong>Category :</strong> ${item.type || '-'}</p>
          <p><strong>District :</strong> ${item.location || '-'}</p>
          <p><strong>Severity Level :</strong> ${item.status || '-'}</p>
          <p><strong>Date & Time :</strong> ${item.reportedAt || '-'}</p>
          <p><strong>Incident Details :</strong> ${item.remarks || '-'}</p>
        </div>
      `,
    })

    return
  }

  view.openPopup({
    title: selectedFeature.value.title || item.name || 'Sensor',
    location: item.graphic.geometry,
    content: `
      <div style="padding:8px 4px;">
        <p><strong>Temperature :</strong> ${item.temperature || '-'}</p>
        <p><strong>Humidity :</strong> ${item.humidity || '-'}</p>
        <p><strong>Air Quality :</strong> ${item.airQuality || '-'}</p>
        <p><strong>Air Pressure :</strong> ${item.airPressure || '-'}</p>
        <p><strong>Last Updated :</strong> ${item.lastUpdated || '-'}</p>
      </div>
    `,
  })
}

function buildSelectedFeature(item) {
  if (item.temperature !== undefined) {
    return {
      kind: 'Sensor',
      title: item.name,
      rows: [
        { label: 'Location', value: item.location },
        { label: 'Temperature', value: item.temperature },
        { label: 'Humidity', value: item.humidity },
        { label: 'Air Quality', value: item.airQuality },
        { label: 'Air Pressure', value: item.airPressure },
        { label: 'Last Updated', value: item.lastUpdated },
      ],
    }
  }

  return {
    kind: 'Incident',
    title: item.type,
    rows: [
      { label: 'Location', value: item.location },
      { label: 'Status', value: item.status },
      { label: 'Reported', value: item.reportedAt },
    ],
  }
}

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

function formatHoverValue(value, unit) {
  if (value === null || value === undefined || value === '' || value === '-') return '-'

  const clean = String(value).replace(/[^\d.-]/g, '')
  const number = Number(clean)

  if (Number.isNaN(number)) return '-'

  return `${number}${unit}`
}

async function goToActivityItem(item) {
  if (!item?.graphic?.geometry) return

  activePanel.value = null

  await view.goTo(
    {
      target: item.graphic.geometry,
      zoom: 15,
    },
    {
      duration: 900,
      easing: 'ease-in-out',
    },
  )

  view.openPopup({
    title: item.title || 'Activity',
    location: item.graphic.geometry,
    content: `
      <div style="padding:8px 4px;">
        <p><strong>Type :</strong> ${item.type || item.kind || '-'}</p>
        <p><strong>Location :</strong> ${item.location || '-'}</p>
        <p><strong>Status :</strong> ${item.status || '-'}</p>
        <p><strong>Remarks :</strong> ${item.remarks || item.message || '-'}</p>
        <p><strong>Reported :</strong> ${item.reportedAt || item.time || '-'}</p>
      </div>
    `,
  })
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
    const accidentLayer =
      findLayerByTitle(layerTitles.accidents) ||
      findLayerByTitleIncludes('raded accident points') ||
      findLayerByTitleIncludes('accident')

    const where = buildActivityWhereClause()

    const accidentFeatures = await getFeatures(accidentLayer, {
      limit: 50,
      where,
      orderByFields: ['Accident_date DESC'],
    })

    incidents.value = mapAccidentFeatures(accidentFeatures)

    detectNewIncidents(incidents.value)

    stats.activeIncidents = await getCount(accidentLayer, where)
    stats.activeAlerts = 0
  } catch (error) {
    console.warn('Accident feed refresh failed:', error)
  }
}

const resolvedIncidentCount = computed(() => {
  return allIncidents.value.filter((incident) => {
    return String(incident.status).toLowerCase() === 'resolved'
  }).length
})

const unresolvedIncidentCount = computed(() => {
  return allIncidents.value.filter((incident) => {
    return String(incident.status).toLowerCase() !== 'resolved'
  }).length
})

const showFullMapActions = ref(false)

// =========================
// LIFECYCLE
// =========================
onMounted(() => {
  setDefaultActivityDateRange()

  loadWebMap()
})

onBeforeUnmount(() => {
  stopLiveAutoRefresh()
  document.body.classList.remove('full-map-open')
  document.documentElement.classList.remove('full-map-open')

  if (view) {
    view.destroy()
    view = null
  }

  webmap = null
})

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

  if (activityFilters.from) {
    const fromDate = new Date(`${activityFilters.from}T00:00:00`).getTime()
    conditions.push(`Accident_date >= ${fromDate}`)
  }

  if (activityFilters.to) {
    const toDate = new Date(`${activityFilters.to}T23:59:59`).getTime()
    conditions.push(`Accident_date <= ${toDate}`)
  }

  if (activityFilters.district) {
    const safeDistrict = activityFilters.district.replace(/'/g, "''")
    conditions.push(`District = '${safeDistrict}'`)
  }

  return conditions.length ? conditions.join(' AND ') : '1=1'
}

async function applyActivityFilters() {
  await refreshActivityFeedOnly()
}

async function resetActivityFilters() {
  setDefaultActivityDateRange()
  activityFilters.district = ''
  await refreshActivityFeedOnly()
}
const showActivityFilters = ref(false)
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

.side-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
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

.accident-popup {
  padding: 8px 4px;
  max-width: 420px;
  font-size: 13px;
}

.accident-popup p {
  margin: 4px 0;
  line-height: 1.35;
}

.accident-popup h6 {
  margin: 12px 0 6px;
  font-size: 13px;
  font-weight: 700;
  color: #1f2937;
}

.popup-section {
  padding-bottom: 8px;
  border-bottom: 1px solid #e5e7eb;
}

.popup-section:last-child {
  border-bottom: 0;
  padding-bottom: 0;
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
