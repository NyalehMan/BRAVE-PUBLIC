<template>
  <div class="fire-page" :class="{ 'dispatcher-map-mode': isFullMap }">
    <!-- =========================
         PAGE HEADER
    ========================== -->
    <div class="page-header">
      <div>
        <h2>BRAVE Fire Detection &amp; Monitoring Dashboard</h2>
        <p>Live data pulled from NIAT Database Server</p>
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
      <div class="map-card" ref="mapCardRef" :class="{ 'full-map-mode': isFullMap }">
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
            <span>Fire Detected</span>
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
          <div v-if="showFireStationList" class="firestation-list-panel">
            <div class="firestation-list-header">
              <strong>Fire Stations</strong>

              <button type="button" @click="showFireStationList = false">
                <FeatherIcon icon="x" />
              </button>
            </div>

            <div class="firestation-list-body">
              <button
                v-for="station in fireStationList"
                :key="station.id"
                class="firestation-item"
                @click="goToFireStation(station)"
              >
                <strong>{{ station.name }}</strong>
                <span>{{ station.address }}</span>
              </button>
            </div>
          </div>
          <div v-show="!mapError" ref="mapDiv" class="fire-map"></div>
          <button
            v-if="!showPublicReportTool && !mapError"
            class="public-report-map-btn"
            :class="{ 'analytics-visible': showAnalyticsPanel }"
            type="button"
            aria-label="Report a fire"
            title="Report a fire incident"
            @click="togglePublicReportTool"
          >
            <span class="public-report-map-icon" aria-hidden="true">
              <FeatherIcon icon="alert-triangle" />
            </span>
            <span>Report an Incident</span>
          </button>
          <div v-if="showDirectionsTool" class="directions-tool-panel">
            <div class="directions-header">
              <strong>Directions</strong>

              <button type="button" @click="clearRouteTool">
                <FeatherIcon icon="x" />
              </button>
            </div>

            <div class="directions-body">
              <div class="route-pick-buttons">
                <button
                  type="button"
                  :class="{ active: routeState.selecting === 'start' }"
                  @click="startRouteSelection('start')"
                >
                  <FeatherIcon icon="map-pin" />
                  Start
                </button>

                <button
                  type="button"
                  :class="{ active: routeState.selecting === 'end' }"
                  @click="startRouteSelection('end')"
                >
                  <FeatherIcon icon="flag" />
                  Destination
                </button>
              </div>

              <div class="route-summary-card">
                <div>
                  <span>Start</span>
                  <strong>{{ routeState.startPoint ? 'Selected' : 'Not selected' }}</strong>
                </div>

                <div>
                  <span>Destination</span>
                  <strong>{{ routeState.endPoint ? 'Selected' : 'Not selected' }}</strong>
                </div>
              </div>
              <div class="route-options-card">
                <div class="route-field">
                  <label>Mode</label>
                  <select v-model="routeState.mode">
                    <option>Driving Time</option>
                    <option>Driving Distance</option>
                    <option>Walking Time</option>
                    <option>Walking Distance</option>
                    <option>Trucking Time</option>
                    <option>Trucking Distance</option>
                    <option>Rural Driving Time</option>
                    <option>Rural Driving Distance</option>
                  </select>
                </div>

                <div class="route-field">
                  <label>Departure time</label>
                  <select v-model="routeState.departureType">
                    <option value="now">Leave now</option>
                    <option value="depart_at">Depart at</option>
                    <option value="unspecified">Time unspecified</option>
                  </select>
                </div>

                <div v-if="routeState.departureType === 'depart_at'" class="route-date-row">
                  <input v-model="routeState.departureDate" type="date" />
                  <input v-model="routeState.departureTime" type="time" />
                </div>

                <div class="route-toggle-row">
                  <span>Optimize order</span>
                  <button
                    type="button"
                    class="route-switch"
                    :class="{ active: routeState.optimizeOrder }"
                    @click="routeState.optimizeOrder = !routeState.optimizeOrder"
                  >
                    <span></span>
                  </button>
                </div>

                <button
                  type="button"
                  class="route-add-stop-btn"
                  :class="{ active: routeState.selecting === 'stop' }"
                  @click="startRouteSelection('stop')"
                >
                  <FeatherIcon icon="plus" />
                  {{ routeState.selecting === 'stop' ? 'Click map to add stop' : 'Add stop' }}
                </button>

                <div v-if="routeState.stops.length" class="route-stop-list">
                  <div
                    v-for="(stop, index) in routeState.stops"
                    :key="index"
                    class="route-stop-item"
                  >
                    <span>Stop {{ index + 1 }}</span>

                    <button type="button" @click="removeRouteStop(index)">
                      <FeatherIcon icon="trash-2" />
                    </button>
                  </div>
                </div>
              </div>
              <button
                type="button"
                class="route-calculate-btn"
                :disabled="!routeState.startPoint || !routeState.endPoint || routeState.loading"
                @click="calculateRoute"
              >
                {{ routeState.loading ? 'Calculating...' : 'Calculate Route' }}
              </button>

              <div v-if="routeState.error" class="route-error">
                {{ routeState.error }}
              </div>

              <div v-if="routeState.distance !== '-'" class="route-result-card">
                <div>
                  <span>Estimated Distance</span>
                  <strong>{{ routeState.distance }}</strong>
                </div>

                <div>
                  <span>Estimated Time</span>
                  <strong>{{ routeState.time }}</strong>
                </div>
              </div>
            </div>
          </div>
          <div v-if="showLocationTool" class="location-tool-panel">
            <div class="location-tool-header">
              <strong>Location Tool</strong>

              <div class="location-tool-header-actions">
                <button type="button" @click="exportLocationReport">
                  <FeatherIcon icon="upload" />
                </button>

                <button type="button" @click="clearLocationTool">
                  <FeatherIcon icon="trash-2" />
                </button>
              </div>
            </div>

            <div class="location-tool-body">
              <div class="location-tool-actions">
                <button
                  :class="{ active: activeLocationTool === 'point' }"
                  @click="startLocationSketch('point')"
                >
                  <FeatherIcon icon="map-pin" />
                </button>

                <button
                  :class="{ active: activeLocationTool === 'polyline' }"
                  @click="startLocationSketch('polyline')"
                >
                  <FeatherIcon icon="activity" />
                </button>

                <button
                  :class="{ active: activeLocationTool === 'polygon' }"
                  @click="startLocationSketch('polygon')"
                >
                  <FeatherIcon icon="square" />
                </button>
              </div>

              <div class="location-radius-controls">
                <input
                  v-model.number="locationRadius"
                  type="number"
                  min="1"
                  @keyup.enter="rerunLocationAnalysis"
                  @change="rerunLocationAnalysis"
                />
                <select v-model="locationUnit" @change="rerunLocationAnalysis">
                  <option value="kilometers">Kilometers</option>
                  <option value="meters">Meters</option>
                </select>
              </div>

              <div class="result-group" v-if="locationToolResults.incidents.length">
                <div class="arc-result-group-header">
                  <div>
                    <FeatherIcon icon="target" />
                    <span>Incident Reported</span>
                  </div>

                  <strong>{{ locationToolResults.incidents.length }}</strong>
                </div>

                <div
                  v-for="incident in locationToolResults.incidents"
                  :key="incident.id"
                  class="arc-result-card incident"
                >
                  <div class="arc-distance-row">
                    <div>
                      <FeatherIcon icon="alert-triangle" />
                      <span>Approximate Distance</span>
                    </div>

                    <strong>{{ incident.distance }} km</strong>
                  </div>

                  <div class="arc-card-title">
                    <strong>Incident Reported</strong>
                  </div>

                  <div class="arc-card-details">
                    <p>
                      <span>Category :</span>
                      <strong>{{ incident.attrs.categories || '-' }}</strong>
                    </p>

                    <p>
                      <span>District :</span>
                      <strong>{{ incident.attrs.district || '-' }}</strong>
                    </p>

                    <p>
                      <span>Severity Level :</span>
                      <strong>{{ incident.attrs.severity_level || '-' }}</strong>
                    </p>

                    <p>
                      <span>Date & Time :</span>
                      <strong>{{ formatDate(incident.attrs.datetime_reported) }}</strong>
                    </p>

                    <p>
                      <span>Incident Details :</span>
                      <strong>{{ incident.attrs.more_details || '-' }}</strong>
                    </p>
                  </div>
                </div>
                <div class="result-group" v-if="locationToolResults.fireStations.length">
                  <div class="arc-result-group-header">
                    <div>
                      <FeatherIcon icon="shield" />
                      <span>Fire Stations</span>
                    </div>

                    <strong>{{ locationToolResults.fireStations.length }}</strong>
                  </div>

                  <div
                    v-for="station in locationToolResults.fireStations"
                    :key="station.id"
                    class="arc-result-card station"
                  >
                    <div class="arc-distance-row">
                      <div>
                        <FeatherIcon icon="shield" />
                        <span>Approximate Distance</span>
                      </div>

                      <strong>{{ station.distance }} km</strong>
                    </div>

                    <div class="arc-card-title">
                      <strong>{{ station.name }}</strong>
                    </div>

                    <div class="arc-card-details">
                      <p>
                        <span>Address :</span>
                        <strong>
                          {{
                            station.attrs.Address ||
                            station.attrs.address ||
                            station.attrs.Name ||
                            '-'
                          }}
                        </strong>
                      </p>

                      <p>
                        <span>Operation :</span>
                        <strong>
                          {{ station.attrs.Operation || station.attrs.operation || '-' }}
                        </strong>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div
            v-if="showPublicReportTool"
            class="update-report-panel custom-update-panel public-report-panel"
          >
            <div class="update-report-header public-report-header">
              <div>
                <strong>Report a Fire</strong>
                <small>Public incident reporting</small>
              </div>

              <button type="button" aria-label="Close report form" @click="togglePublicReportTool">
                <FeatherIcon icon="x" />
              </button>
            </div>

            <form class="custom-update-body" @submit.prevent="submitPublicReport">
              <div class="public-report-notice">
                <FeatherIcon icon="info" />
                <p>
                  For immediate danger, contact emergency services first. Reports submitted here
                  are reviewed before they are treated as verified incidents.
                </p>
              </div>

              <label for="public-report-category">Incident type <span>*</span></label>
              <select id="public-report-category" v-model="createForm.categories" required>
                <option disabled value="">Select incident type</option>
                <option value="Wildfire">Wildfire</option>
                <option value="Car Fire">Car Fire</option>
                <option value="Electrical Fire">Electrical Fire</option>
                <option value="House Fire">House Fire</option>
                <option value="Smoke">Smoke</option>
                <option value="Rescue">Rescue</option>
              </select>

              <label for="public-report-district">District <span>*</span></label>
              <select id="public-report-district" v-model="createForm.district" required>
                <option disabled value="">Select district</option>
                <option value="Brunei Muara">Brunei Muara</option>
                <option value="Belait">Belait</option>
                <option value="Tutong">Tutong</option>
                <option value="Temburong">Temburong</option>
              </select>

              <label for="public-report-urgency">Observed urgency <span>*</span></label>
              <select id="public-report-urgency" v-model="createForm.severity_level" required>
                <option disabled value="">Select observed urgency</option>
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
                <option value="Critical">Critical</option>
              </select>

              <label for="public-report-details">What can you see? <span>*</span></label>
              <textarea
                id="public-report-details"
                v-model.trim="createForm.more_details"
                rows="4"
                maxlength="800"
                placeholder="Describe the fire, smoke, nearby buildings, people at risk, or other useful details."
                required
              ></textarea>
              <small class="public-character-count">
                {{ createForm.more_details.length }}/800
              </small>

              <label>Incident location <span>*</span></label>
              <div class="public-location-actions">
                <button type="button" class="public-location-btn" @click="useCurrentPublicLocation">
                  <FeatherIcon icon="crosshair" />
                  Use my location
                </button>

                <button
                  type="button"
                  class="public-location-btn"
                  :class="{ active: createLocationSelecting }"
                  @click="startPickCreateLocation"
                >
                  <FeatherIcon icon="map-pin" />
                  {{ createLocationSelecting ? 'Click the map now' : 'Pin on map' }}
                </button>
              </div>

              <div
                class="public-location-status"
                :class="{ selected: createForm.latitude && createForm.longitude }"
              >
                <FeatherIcon :icon="createForm.latitude ? 'check-circle' : 'map-pin'" />
                <span v-if="createForm.latitude && createForm.longitude">
                  Location selected: {{ createForm.latitude }}, {{ createForm.longitude }}
                </span>
                <span v-else>No location selected yet.</span>
              </div>

              <label class="public-report-confirmation">
                <input v-model="publicReportAcknowledged" type="checkbox" />
                <span>I confirm that this report is accurate to the best of my knowledge.</span>
              </label>

              <button
                type="submit"
                class="public-submit-btn"
                :disabled="publicReportSubmitting || !publicReportAcknowledged"
              >
                <FeatherIcon :icon="publicReportSubmitting ? 'loader' : 'send'" />
                {{ publicReportSubmitting ? 'Submitting report...' : 'Submit public report' }}
              </button>
            </form>
          </div>

          <div v-if="showUpdateReportTool" class="update-report-panel custom-update-panel">
            <div class="update-report-header">
              <strong>Update Report</strong>

              <button type="button" @click="toggleUpdateReportTool">
                <FeatherIcon icon="x" />
              </button>
            </div>

            <div class="custom-update-body">
              <!-- MAIN PAGE -->
              <template v-if="updateMode === 'main'">
                <div class="update-header-actions">
                  <button type="button" class="new-incident-btn" @click="openCreateIncident">
                    <FeatherIcon icon="alert-triangle" />
                    New Incident
                  </button>

                  <button type="button" class="new-station-btn" @click="openCreateFireStation">
                    <FeatherIcon icon="home" />
                    New Fire Station
                  </button>
                </div>

                <div class="selection-tool-card">
                  <div class="selection-tool-title">
                    <FeatherIcon icon="edit-3" />
                    <span>Select existing feature</span>
                  </div>

                  <div class="selection-tool-grid">
                    <button
                      type="button"
                      class="selection-tool-btn"
                      :class="{ active: updateSelectionMode === 'point' }"
                      @click="startUpdateSelection('point')"
                    >
                      <FeatherIcon icon="mouse-pointer" />
                      <span>Point</span>
                    </button>

                    <button
                      type="button"
                      class="selection-tool-btn"
                      :class="{ active: updateSelectionMode === 'rectangle' }"
                      @click="startUpdateSelection('rectangle')"
                    >
                      <FeatherIcon icon="square" />
                      <span>Rectangle</span>
                    </button>

                    <button
                      type="button"
                      class="selection-tool-btn"
                      :class="{ active: updateSelectionMode === 'lasso' }"
                      @click="startUpdateSelection('lasso')"
                    >
                      <FeatherIcon icon="edit-3" />
                      <span>Lasso</span>
                    </button>
                  </div>
                </div>

                <div v-if="showUpdateEmptyState" class="update-empty-state">
                  <FeatherIcon icon="map-pin" />
                  <strong>Select incident</strong>
                  <span>Use point, rectangle, or lasso to select an incident.</span>
                </div>

                <div v-if="updateResults.length > 1" class="update-result-list">
                  <div class="update-result-header">
                    <strong>{{ updateResults.length }} incidents found</strong>
                    <small>Select one to edit</small>
                  </div>

                  <button
                    v-for="item in updateResults"
                    :key="item.attributes.OBJECTID"
                    type="button"
                    class="update-result-card"
                    @click="loadIncidentIntoUpdateForm(item)"
                  >
                    <div class="result-icon">
                      <FeatherIcon icon="alert-triangle" />
                    </div>

                    <div class="result-info">
                      <strong>{{ item.attributes.categories || 'Incident' }}</strong>
                      <span>{{ item.attributes.district || '-' }}</span>
                    </div>

                    <div class="result-id">#{{ item.attributes.OBJECTID }}</div>
                  </button>
                </div>
              </template>

              <!-- CREATE PAGE -->
              <template v-else-if="updateMode === 'create'">
                <button type="button" class="update-back-btn" @click="backToUpdateMain">
                  <FeatherIcon icon="arrow-left" />
                  <span>Back to Update Report</span>
                </button>

                <div class="selected-incident-card">
                  <span>Create Incident</span>
                  <strong>NEW</strong>
                </div>

                <label>Date/Time Reported</label>

                <div class="datetime-grid">
                  <input v-model="createForm.datetime_date" type="date" />
                  <input v-model="createForm.datetime_time" type="time" />
                </div>

                <label>Categories</label>
                <select v-model="updateForm.categories">
                <option value="">No value</option>
                <option>Wildfire</option>
                <option>Car Fire</option>
                <option>Electric Fire</option>
                <option>House Fire</option>
                <option>Smoke</option>
                <option>Rescue</option>
              </select>

                <label>Other - Categories</label>
                <input v-model="createForm.other_categories" type="text" />

                <label>More Detail</label>
                <textarea v-model="createForm.more_details" rows="4"></textarea>

                <label>District</label>
                <select v-model="createForm.district">
                  <option value="">No value</option>
                  <option>Brunei Muara</option>
                  <option>Belait</option>
                  <option>Tutong</option>
                  <option>Temburong</option>
                </select>

                <label>Severity Level</label>
                <select v-model="createForm.severity_level">
                  <option value="">No value</option>
                  <option>Low</option>
                  <option>Medium</option>
                  <option>High</option>
                  <option>Critical</option>
                </select>

                <label>Location</label>
                <button type="button" class="pick-location-btn" @click="startPickCreateLocation">
                  <FeatherIcon icon="map-pin" />
                  {{
                    createForm.latitude
                      ? `${createForm.latitude}, ${createForm.longitude}`
                      : 'Pin Incident Location'
                  }}
                </button>

                <label>Attachments</label>
                <div class="attachment-box">
                  <div v-if="!updateAttachmentPreview" class="attachment-empty">No attachments</div>

                  <img v-else :src="updateAttachmentPreview" class="attachment-preview" />

                  <label class="attachment-add-btn">
                    <FeatherIcon icon="plus" />
                    Add picture
                    <input type="file" accept="image/*" hidden @change="handleUpdateAttachment" />
                  </label>
                </div>

                <div class="update-action-row">
                  <button type="button" class="update-btn" @click="createIncidentFromPanel">
                    Create Incident
                  </button>
                </div>
              </template>

              <template v-else-if="updateMode === 'create-station'">
                <button type="button" class="update-back-btn" @click="backToUpdateMain">
                  <FeatherIcon icon="arrow-left" />
                  Back
                </button>

                <div class="selected-incident-card">
                  <span>Create Fire Station</span>
                  <strong>NEW</strong>
                </div>

                <label>Station Name</label>
                <input v-model="createStationForm.name" />

                <label>Address</label>
                <textarea rows="3" v-model="createStationForm.address" />

                <label>Contact</label>
                <input v-model="createStationForm.contact" />

                <label>Mukim</label>
                <input v-model="createStationForm.mukim" />

                <label>District</label>
                <select v-model="createStationForm.district">
                  <option>Brunei Muara</option>
                  <option>Belait</option>
                  <option>Tutong</option>
                  <option>Temburong</option>
                </select>

                <label>Operation</label>
                <input v-model="createStationForm.operation" />

                <label>Number of Firefighters</label>
                <input type="number" v-model="createStationForm.num_firefighters" />

                <label>Number of Engines</label>
                <input type="number" v-model="createStationForm.num_engines" />

                <label>Number of Vehicles</label>
                <input type="number" v-model="createStationForm.num_vehicles" />

                <label>Location</label>

                <button type="button" class="pick-location-btn" @click="startPickStationLocation">
                  <FeatherIcon icon="map-pin" />
                  {{
                    createStationForm.latitude
                      ? `${createStationForm.latitude}, ${createStationForm.longitude}`
                      : 'Pin Station Location'
                  }}
                </button>

                <div class="update-action-row">
                  <button type="button" class="update-btn" @click="createFireStation">
                    Create Fire Station
                  </button>
                </div>
              </template>

              <!-- EDIT PAGE -->
              <template v-else-if="updateMode === 'edit' && updateForm.objectId">
                <button type="button" class="update-back-btn" @click="backToUpdateMain">
                  <FeatherIcon icon="arrow-left" />
                  Back
                </button>

                <div class="selected-incident-card">
                  <span>Edit feature</span>
                  <strong>#{{ updateForm.objectId }}</strong>
                </div>

                <label>Date/Time Reported</label>
                <input v-model="updateForm.datetime_date" type="date" />

                <small class="date-preview">
                  {{ formatDisplayDate(updateForm.datetime_date) }}
                </small>
                <input v-model="updateForm.datetime_time" type="time" />

                <select v-model="updateForm.categories">
                <option value="">No value</option>
                <option value="Wildfire">Wildfire</option>
                <option value="Car Fire">Car Fire</option>
                <option value="Electric Fire">Electric Fire</option>
                <option value="House Fire">House Fire</option>
                <option value="Smoke">Smoke</option>
                <option value="Rescue">Rescue</option>
              </select>

                <label>Other - Categories</label>
                <input v-model="updateForm.other_categories" type="text" />

                <label>More Detail</label>
                <textarea v-model="updateForm.more_details" rows="4"></textarea>

                <label>District</label>
                <select v-model="updateForm.district">
                  <option value="">No value</option>
                  <option>Brunei Muara</option>
                  <option>Belait</option>
                  <option>Tutong</option>
                  <option>Temburong</option>
                </select>

                <label>Severity Level</label>
                <select v-model="updateForm.severity_level">
                  <option value="">No value</option>
                  <option>Low</option>
                  <option>Medium</option>
                  <option>High</option>
                  <option>Critical</option>
                  <option>Resolved</option>
                </select>

                <label>Attachments</label>
                <div class="attachment-box">
                  <div v-if="!updateAttachmentPreview" class="attachment-empty">No attachments</div>

                  <img v-else :src="updateAttachmentPreview" class="attachment-preview" />

                  <label class="attachment-add-btn">
                    <FeatherIcon icon="plus" />
                    Add picture
                    <input type="file" accept="image/*" hidden @change="handleUpdateAttachment" />
                  </label>
                </div>

                <div class="update-action-row">
                  <button
                    type="button"
                    class="update-btn"
                    :disabled="updatingReport"
                    @click="saveCustomUpdateReport"
                  >
                    {{ updatingReport ? 'Updating...' : 'Update' }}
                  </button>

                  <button type="button" class="delete-btn" @click="deleteSelectedIncident">
                    Delete
                  </button>
                </div>
              </template>
            </div>
          </div>
          <div v-if="showBookmarkTool" class="bookmark-tool-panel">
            <div class="bookmark-header">
              <strong>Bookmark Area</strong>
              <button @click="showBookmarkTool = false">
                <FeatherIcon icon="x" />
              </button>
            </div>

            <div class="bookmark-body">
              <button type="button" class="add-bookmark-btn" @click="saveCurrentBookmark">
                <FeatherIcon icon="plus" />
                <span>Add current map view</span>
              </button>

              <div
                v-for="bookmark in bookmarks"
                :key="bookmark.id"
                class="bookmark-card"
                @click="goToBookmark(bookmark)"
              >
                <img v-if="bookmark.image" :src="bookmark.image" class="bookmark-shot" />
                <div class="bookmark-info">
                  <strong>{{ bookmark.name }}</strong>
                  <small>Zoom {{ bookmark.zoom }}</small>
                </div>

                <button class="bookmark-delete" @click.stop="deleteBookmark(bookmark.id)">
                  <FeatherIcon icon="trash-2" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="showWeatherOverlay" class="weather-layer-toolbar">
            <div class="weather-toolbar-row">
              <div class="weather-dropdown custom-weather-dropdown">
                <button
                  class="btn btn-sm btn-phoenix-secondary weather-dropdown-btn"
                  type="button"
                  @click.stop="toggleWeatherDropdown"
                >
                  <FeatherIcon :icon="currentWeatherOption.icon" />

                  <span class="weather-label-text">
                    {{ currentWeatherOption.label }}
                  </span>

                  <FeatherIcon
                    icon="chevron-down"
                    class="weather-chevron"
                    :class="{ open: showWeatherDropdown }"
                  />
                </button>

                <ul v-if="showWeatherDropdown" class="weather-dropdown-menu custom-show">
                  <li v-for="layer in weatherLayerOptions" :key="layer.id">
                    <button
                      type="button"
                      class="weather-dropdown-item"
                      :class="{ active: activeWeatherLayer === layer.id }"
                      @click.stop="selectWeatherLayer(layer.id)"
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
              <h5>Activity Feed</h5>
              <small>Latest incident updates</small>
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
                <div class="filter-inline-row">
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
                <div class="activity-title-row">
                  <strong>{{ item.title }}</strong>

                  <span
                    v-if="item.kind === 'incident'"
                    class="activity-status-pill"
                    :class="item.displayStatus === 'Resolved' ? 'resolved' : 'ongoing'"
                  >
                    {{ item.displayStatus }}
                  </span>
                </div>

                <p>{{ item.message }}</p>
                <small>{{ formatActivityDate(item.time) }}</small>
              </div>
            </button>
          </div>
        </div>
        <!-- SUMMARY GRID -->
        <div class="side-summary-grid">
          <div class="summary-card success">
            <span>Number of Resolved Incident</span>
            <h3>{{ resolvedIncidentCount }}</h3>
          </div>

          <div class="summary-card danger">
            <span>Number of Unresolved Incident</span>
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
              <h6>Fire Incidents by District</h6>

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
              <h6>Fire Incidents by Type</h6>

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
              <h6>Fire Incidents by Severity</h6>

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
  <transition name="drawer-slide">
    <div
      v-if="showIncidentDrawer"
      class="incident-drawer-overlay"
      @click.self="closeIncidentDrawer"
    >
      <div class="incident-drawer">
        <div class="incident-drawer-header">
          <div>
            <h5>
              <FeatherIcon icon="alert-triangle" />
              Incident Details
            </h5>
            <small>Operational incident information</small>
          </div>

          <button class="drawer-close-btn" @click="closeIncidentDrawer">
            <FeatherIcon icon="x" />
          </button>
        </div>

        <div class="incident-drawer-body">
          <div class="incident-status-banner" :class="severityClass(incidentDrawer.severity)">
            ACTIVE INCIDENT
          </div>
          <div class="incident-info-grid">
            <div class="info-item">
              <span>Incident ID</span>
              <strong>{{ incidentDrawer.id }}</strong>
            </div>

            <div class="info-item">
              <span>Category</span>
              <strong>{{ incidentDrawer.category }}</strong>
            </div>

            <div class="info-item">
              <span>Severity</span>
              <strong>
                <span :class="severityClass(incidentDrawer.severity)">
                  {{ incidentDrawer.severity }}
                </span>
              </strong>
            </div>

            <div class="info-item">
              <span>District</span>
              <strong>{{ incidentDrawer.district }}</strong>
            </div>

            <div class="info-item">
              <span>Reported</span>
              <strong>{{ incidentDrawer.reportedAt }}</strong>
            </div>

            <div class="info-item">
              <span>Coordinates</span>
              <strong>{{ incidentDrawer.coordinates }}</strong>
            </div>
          </div>

          <div class="drawer-section">
            <h6>Remarks</h6>
            <div class="remarks-box">
              {{ incidentDrawer.remarks }}
            </div>
          </div>
          <!-- <div class="drawer-section">
            <div class="drawer-section-header">
              <h6>Nearby Resources</h6>
              <small v-if="nearbyResources.loading">Searching...</small>
            </div>

            <div v-if="nearbyResources.loading" class="resource-loading">
              Finding nearest available resources...
            </div>

            <div v-else class="nearby-resource-list">
              <div
                v-for="station in nearbyResources.fireStations"
                :key="station.id"
                class="nearby-resource-card"
                :class="resourceDistanceClass(station.distance)"
              >
                <div>
                  <strong>{{ station.name }}</strong>
                  <span>Fire Station</span>
                </div>

                <div class="resource-distance">{{ station.distance }} km</div>
              </div>

              <div
                v-for="team in nearbyResources.responseTeams"
                :key="team.id"
                class="nearby-resource-card"
              >
                <div>
                  <strong>{{ team.name }}</strong>
                  <span>Response Team</span>
                </div>

                <div class="resource-distance">{{ team.distance }} km</div>
              </div>

              <div
                v-for="sensor in nearbyResources.sensors"
                :key="sensor.id"
                class="nearby-resource-card"
              >
                <div>
                  <strong>{{ sensor.name }}</strong>
                  <span>Nearby Sensor</span>
                </div>

                <div class="resource-distance">{{ sensor.distance }} km</div>
              </div>
            </div>
          </div>
          <div class="drawer-section">
            <h6>Actions</h6>

            <div class="drawer-actions">
              <button class="btn btn-phoenix-warning">
                <FeatherIcon icon="check-circle" />
                Acknowledge
              </button>

              <button class="btn btn-phoenix-primary">
                <FeatherIcon icon="send" />
                Dispatch Team
              </button>

              <button class="btn btn-phoenix-success">
                <FeatherIcon icon="shield" />
                Resolve
              </button>
            </div>
          </div> -->
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
// =========================
// VUE + COMPONENT IMPORTS
// =========================
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Line } from 'vue-chartjs'
import FeatherIcon from '@/components/FeatherIcon.vue'
import { useNotificationStore } from '@/stores/notificationStore'
import http from '@/api/http'
import { useAlertSound } from '@/composables/useAlertSound'

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
import Locate from '@arcgis/core/widgets/Locate'
import Bookmarks from '@arcgis/core/widgets/Bookmarks'
import DistanceMeasurement2D from '@arcgis/core/widgets/DistanceMeasurement2D'
import AreaMeasurement2D from '@arcgis/core/widgets/AreaMeasurement2D'
import GraphicsLayer from '@arcgis/core/layers/GraphicsLayer'
import Sketch from '@arcgis/core/widgets/Sketch'
import Graphic from '@arcgis/core/Graphic'
import * as geometryEngine from '@arcgis/core/geometry/geometryEngine'
import Point from '@arcgis/core/geometry/Point'
import RouteLayer from '@arcgis/core/layers/RouteLayer'
import * as route from '@arcgis/core/rest/route'
import RouteParameters from '@arcgis/core/rest/support/RouteParameters'
import FeatureSet from '@arcgis/core/rest/support/FeatureSet'
import Polyline from '@arcgis/core/geometry/Polyline'
// import Editor from '@arcgis/core/widgets/Editor'
import * as intl from '@arcgis/core/intl'
import esriId from '@arcgis/core/identity/IdentityManager'
import ServerInfo from '@arcgis/core/identity/ServerInfo'
import SimpleFillSymbol from '@arcgis/core/symbols/SimpleFillSymbol'
import SimpleMarkerSymbol from '@arcgis/core/symbols/SimpleMarkerSymbol'
import {
  getDisplayedSymbol,
  renderPreviewHTML,
} from '@arcgis/core/symbols/support/symbolUtils.js'

document.documentElement.lang = 'en-US'
intl.setLocale('en-US')

// =========================
// WEBMAP CONFIG
// =========================
const WEBMAP_ID = '5ad9cc4eaf3842a7b3c7f6c9c4bf61b7'

const layerTitles = {
  alerts: 'DRYAD_alert_features',
  incidents: 'Incident Reported',
  sensors: 'Sensor Readings',
  sensorsAlt: 'DRYAD_measurement_features4',
  taskForce: 'BFRD Task Force',
}

const activityFeed = computed(() => {
  const alertItems = alerts.value.map((alert, index) => ({
    id: `alert-${alert.objectId || index}`,
    title: alert.title || 'Alert detected',
    message: alert.description || 'New alert detected.',
    time: alert.reportedAt || 'Just now',
    sortTime: alert.rawTime || Date.now(),
    severity: 'high',
    kind: 'alert',
    graphic: alert.graphic || null,
  }))

  const incidentItems = incidents.value.map((incident, index) => ({
    id: `incident-${incident.objectId || index}`,
    title: `${incident.type || 'Incident'} reported`,
    message: `${incident.location || 'Unknown'} · ${incident.status || 'Active'}`,
    time: incident.reportedAt || 'Just now',
    sortTime: incident.rawTime || Date.now(),
    severity:
      String(incident.status).toLowerCase().includes('high') ||
      String(incident.status).toLowerCase().includes('critical')
        ? 'high'
        : 'medium',
    kind: 'incident',
    ...incident,
    displayStatus: String(incident.status).toLowerCase() === 'resolved' ? 'Resolved' : 'Ongoing',
  }))

  return [...alertItems, ...incidentItems]
    .sort((a, b) => Number(b.sortTime || 0) - Number(a.sortTime || 0))
    .slice(0, 10)
})

const currentTheme = ref(
  document.documentElement.getAttribute('data-bs-theme') ||
    document.documentElement.getAttribute('data-theme') ||
    'light',
)

const isDark = computed(() => currentTheme.value === 'dark')

let themeObserver = null

onMounted(() => {
  themeObserver = new MutationObserver(() => {
    currentTheme.value =
      document.documentElement.getAttribute('data-bs-theme') ||
      document.documentElement.getAttribute('data-theme') ||
      'light'
  })

  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-bs-theme', 'data-theme'],
  })
})

onBeforeUnmount(() => {
  themeObserver?.disconnect()
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
let locateWidget = null
let bookmarksWidget = null
let distanceWidget = null
let areaWidget = null
let activeArcgisTool = null
let locationGraphicsLayer = null
let locationSketch = null
// let editorWidget = null
// let editorExpand = null
let updateSelectLayer = null
let updateSketch = null

const updateSelectionMode = ref(null)
const updateResults = ref([])
const selectedUpdateGraphic = ref(null)

const locationToolResults = reactive({
  workers: [],
  fireStations: [],
  sensors: [],
  incidents: [],
  geometryType: null,
  visible: false,
})

const locationRadius = ref(5)
const locationUnit = ref('kilometers')
const activeLocationTool = ref(null)
const lastLocationGeometry = ref(null)
const lastLocationToolType = ref(null)
const lastLocationGraphic = ref(null)
const showPublicReportTool = ref(false)
const publicReportSubmitting = ref(false)
const publicReportAcknowledged = ref(false)
const showUpdateReportTool = ref(false)
// const editorDiv = ref(null)
const showBookmarkTool = ref(false)
const bookmarks = ref(JSON.parse(localStorage.getItem('braveBookmarks') || '[]'))
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
const activeWeatherLayer = ref('wind')
const weatherPointerValue = ref('')
const weatherSourceReady = ref(false)
const showLocationTool = ref(false)
const mapCardRef = ref(null)

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

const districtAnalysis = computed(() => groupCount(allIncidents.value, 'location'))
const typeAnalysis = computed(() => groupCount(allIncidents.value, 'type'))
const severityAnalysis = computed(() => groupCount(allIncidents.value, 'status'))

const highSeverityCount = computed(() => {
  return allIncidents.value.filter((incident) => String(incident.status).toLowerCase() === 'high')
    .length
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
    background: 'transparent',
  },

  labels: typeAnalysis.value.map((item) => item.label),

  colors: chartColors,

  legend: {
    position: 'bottom',
    labels: {
      colors: isDark.value ? '#e2e8f0' : '#334155',
    },
  },

  dataLabels: {
    enabled: true,
    style: {
      colors: ['#ffffff'],
      fontWeight: 700,
    },
  },

  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
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

          name: {
            show: true,
            color: isDark.value ? '#e2e8f0' : '#334155',
            fontSize: '14px',
            fontWeight: 600,
          },

          value: {
            show: true,
            color: isDark.value ? '#ffffff' : '#0f172a',
            fontSize: '28px',
            fontWeight: 700,
          },

          total: {
            show: true,
            label: 'Total',
            color: isDark.value ? '#94a3b8' : '#64748b',

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
    background: 'transparent',
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

    labels: {
      style: {
        colors: isDark.value ? '#e2e8f0' : '#1e293b',
      },
    },

    axisBorder: {
      color: isDark.value ? 'rgba(255,255,255,0.12)' : '#cbd5e1',
    },

    axisTicks: {
      color: isDark.value ? 'rgba(255,255,255,0.12)' : '#cbd5e1',
    },
  },

  yaxis: {
    labels: {
      style: {
        colors: isDark.value ? '#e2e8f0' : '#1e293b',
      },
    },
  },

  grid: {
    borderColor: isDark.value ? 'rgba(255,255,255,0.08)' : '#e2e8f0',
  },

  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
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
    background: 'transparent',
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

    labels: {
      style: {
        colors: isDark.value ? '#e2e8f0' : '#1e293b',
      },
    },

    axisBorder: {
      color: isDark.value ? 'rgba(255,255,255,0.12)' : '#cbd5e1',
    },

    axisTicks: {
      color: isDark.value ? 'rgba(255,255,255,0.12)' : '#cbd5e1',
    },
  },

  yaxis: {
    labels: {
      style: {
        colors: isDark.value ? '#e2e8f0' : '#1e293b',
      },
    },
  },

  grid: {
    borderColor: isDark.value ? 'rgba(255,255,255,0.08)' : '#e2e8f0',
  },

  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
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

    routeLayer = new RouteLayer({ title: 'BRAVE Route Layer' })
    routeGraphicsLayer = new GraphicsLayer({
      title: 'BRAVE Route Pins',
      listMode: 'hide',
    })

    locationGraphicsLayer = new GraphicsLayer({
      title: 'Location Tool Graphics',
      listMode: 'hide',
    })

    updateSelectLayer = new GraphicsLayer({
      title: 'Update Report Selection',
      listMode: 'hide',
    })

    webmap.addMany([routeLayer, routeGraphicsLayer, locationGraphicsLayer, updateSelectLayer])

    view = new MapView({
      container: mapDiv.value,
      map: webmap,
      center: [114.7277, 4.5353],
      zoom: 9,
    })

    await view.when()

    optimizeFeatureLayers()

    const incidentLayer =
      findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')

    // show map immediately
    loading.value = false

    // load heavy things after map appears
    webmap.when(() => {
      disableDefaultPopups()

      view.popup.autoOpenEnabled = false
      view.popupEnabled = true

      view.on('click', handleMapClick)
      view.on('pointer-move', handleSensorHover)
      view.on('pointer-move', handleWeatherPointerMove)

      refreshLiveData()
      addMapWidgets()
      startLiveAutoRefresh()
    })
  } catch (error) {
    console.error(error)
    mapError.value = 'Unable to load WebMap.'
    loading.value = false
  }
}

function disableDefaultPopups() {
  if (!webmap) return

  webmap.allLayers.toArray().forEach((layer) => {
    const title = layer.title?.toLowerCase() || ''
    const keepPopup = title.includes('fire') || title.includes('station') || title.includes('bfrd')

    if (!keepPopup) {
      if ('popupEnabled' in layer) layer.popupEnabled = false
      if ('popupTemplate' in layer) layer.popupTemplate = null
    }
  })
}

// =========================
// MAP CLICK HANDLER
// =========================
async function handleMapClick(event) {
  if (!view) return

  if (showDirectionsTool.value && routeState.selecting) {
    await handleRouteMapClick(event)
    return
  }

  if ((showPublicReportTool.value || showUpdateReportTool.value) && createLocationSelecting.value) {
    const point = event.mapPoint

    createForm.longitude = Number(point.longitude.toFixed(6))
    createForm.latitude = Number(point.latitude.toFixed(6))

    createLocationSelecting.value = false

    highlightCreateLocation(point)

    showToast('Location Selected', 'Incident location pinned.', 'success')
    return
  }
  // Custom Update Report point selection
  if (showUpdateReportTool.value && updateSelectionMode.value === 'point') {
    await selectIncidentByPoint(event)
    return
  }

  const result = await getClickedDashboardFeature(event)
  if (!result) return

  const graphic = result.graphic
  const attrs = graphic.attributes || {}
  const layer = graphic.layer
  const layerTitle = layer?.title?.toLowerCase() || ''

  view.closePopup()

  if (isSensorLayer(layerTitle)) {
    await openSensorPopup({ graphic, attrs, layer, event })
    return
  }

  if (isFireLayer(layerTitle)) {
    openFireStationPopup(graphic)
    return
  }

  if (isCluster(attrs)) {
    openClusterPopup(graphic, attrs, event)
    return
  }

  await openIncidentPopup({ graphic, attrs, layer })
}

async function getClickedDashboardFeature(event) {
  const hit = await view.hitTest(event)

  return hit.results.find((result) => {
    const title = result.graphic?.layer?.title?.toLowerCase() || ''

    return (
      title.includes('sensor') ||
      title.includes('readings') ||
      title.includes('measurement') ||
      title.includes('dryad') ||
      title.includes('alert') ||
      title.includes('incident') ||
      title.includes('fire') ||
      title.includes('station') ||
      title.includes('bfrd')
    )
  })
}

function isSensorLayer(layerTitle) {
  return (
    layerTitle.includes('sensor') ||
    layerTitle.includes('readings') ||
    layerTitle.includes('measurement') ||
    layerTitle.includes('dryad') ||
    layerTitle.includes('alert')
  )
}

function isFireLayer(layerTitle) {
  return (
    layerTitle.includes('fire') || layerTitle.includes('station') || layerTitle.includes('bfrd')
  )
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
const showWeatherDropdown = ref(false)

function toggleWeatherDropdown() {
  showWeatherDropdown.value = !showWeatherDropdown.value
}

function selectWeatherLayer(id) {
  changeWeatherLayer(id)
  showWeatherDropdown.value = false
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
async function openSensorPopup({ graphic, attrs, layer, event }) {
  let sensorAttrs = attrs
  let sensorGeometry = graphic.geometry

  const fullFeature = await queryFeatureByObjectId(layer, attrs)
  if (fullFeature) {
    sensorAttrs = fullFeature.attributes || sensorAttrs
    sensorGeometry = fullFeature.geometry || sensorGeometry
  }

  const nearbyMeasurement = await queryNearbyMeasurement(event.mapPoint)
  if (nearbyMeasurement) {
    sensorAttrs = {
      ...sensorAttrs,
      ...(nearbyMeasurement.attributes || {}),
    }
    sensorGeometry = nearbyMeasurement.geometry || sensorGeometry
  }

  const title = getAttr(
    sensorAttrs,
    [
      'device_nam',
      'Device_nam',
      'DEVICE_NAM',
      'device_name',
      'Device_Name',
      'name',
      'Name',
      'NAME',
    ],
    `Sensor ${sensorAttrs.ID || sensorAttrs.FID || ''}`,
  )

  const battery = getAttr(sensorAttrs, [
    'energy_percentage',
    'Energy_Percentage',
    'ENERGY_PERCENTAGE',
    'energypercent',
    'EnergyPercent',
    'battery',
    'Battery',
    'BATTERY',
    'battery_level',
    'Battery_Level',
    'etag',
  ])

  const humidity = getAttr(sensorAttrs, [
    'humidity',
    'Humidity',
    'HUMIDITY',
    'humid',
    'Humid',
    'humidty',
    'humidit',
  ])

  const temperature = getAttr(sensorAttrs, [
    'temperature',
    'Temperature',
    'TEMPERATURE',
    'temperatur',
    'Temperatur',
    'TEMPERATUR',
  ])

  const airQuality = getAttr(sensorAttrs, [
    'air_quality',
    'Air_Quality',
    'AIR_QUALITY',
    'airquality',
    'AirQuality',
    'air_qualit',
    'aqi',
    'AQI',
  ])

  const airPressure = getAttr(sensorAttrs, [
    'air_pressure',
    'Air_Pressure',
    'AIR_PRESSURE',
    'airpressure',
    'AirPressure',
    'air_pressu',
    'pressure',
    'Pressure',
    'PRESSURE',
  ])

  const lat = sensorGeometry?.latitude?.toFixed(6) || '-'
  const lon = sensorGeometry?.longitude?.toFixed(6) || '-'

  view.openPopup({
    title,
    location: sensorGeometry || event.mapPoint,
    content: `
      <div style="padding:8px 4px;">
        <p><strong>Energy Percentage :</strong> ${battery}</p>
        <p style="margin-top:12px;"><strong>Battery level</strong></p>
        <p>Current level ${battery} %</p>
        <p><strong>Location :</strong> ${lat}° N, ${lon}° E</p>
        <p><strong>Humidity :</strong> ${humidity} %</p>
        <p><strong>Temperature :</strong> ${temperature} °C</p>
        <p><strong>Air Quality :</strong> ${airQuality}</p>
        <p><strong>Air Pressure :</strong> ${airPressure} hPa</p>
      </div>
    `,
  })
}

function openFireStationPopup(graphic) {
  view.openPopup({
    features: [graphic],
    location: graphic.geometry,
  })
}

function openClusterPopup(graphic, attrs, event) {
  const clusterCount = attrs.cluster_count || 1
  const category =
    attrs.cluster_type_categories || attrs.categories || attrs.Categories || attrs.category || '-'

  view.openPopup({
    title: 'Cluster summary',
    location: graphic.geometry || event.mapPoint,
    content: `
      <div style="padding:8px 4px;">
        <p>This cluster represents <strong>${clusterCount}</strong> features.</p>
        <p>
          The predominant value of <strong>Categories</strong> within this cluster is
          <strong>${category}</strong>.
        </p>
      </div>
    `,
  })
}

function escapePopupHtml(value, fallback = 'Not provided') {
  const text =
    value === null || value === undefined || value === '' ? fallback : String(value)

  return text.replace(/[&<>"']/g, (character) => {
    const entities = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    }

    return entities[character]
  })
}

function formatPopupDate(value) {
  if (!value) return 'Not provided'

  const normalizedValue =
    typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value

  const date = new Date(normalizedValue)

  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Brunei',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  }).format(date)
}

function getIncidentStatus(attrs) {
  const resolved = getAttr(attrs, ['resolved', 'Resolved'], '')
  const unresolved = getAttr(attrs, ['unresolved', 'Unresolved'], '')
  const status = getAttr(attrs, ['status', 'Status'], '')

  if (String(resolved).toLowerCase() === 'yes') return 'Resolved'
  if (String(resolved).toLowerCase() === 'true') return 'Resolved'
  if (String(resolved) === '1') return 'Resolved'

  return unresolved || status || 'Ongoing'
}

function getBadgeAppearance(value, kind = 'severity') {
  const normalized = String(value || '').toLowerCase()

  if (normalized.includes('critical')) {
    return { background: '#fee2e2', color: '#b91c1c', border: '#fecaca' }
  }

  if (normalized.includes('high')) {
    return { background: '#ffedd5', color: '#c2410c', border: '#fed7aa' }
  }

  if (normalized.includes('medium')) {
    return { background: '#fef3c7', color: '#a16207', border: '#fde68a' }
  }

  if (normalized.includes('resolved') || normalized === 'yes') {
    return { background: '#dcfce7', color: '#15803d', border: '#bbf7d0' }
  }

  if (kind === 'status') {
    return { background: '#e0f2fe', color: '#0369a1', border: '#bae6fd' }
  }

  return { background: '#ecfeff', color: '#0f766e', border: '#a5f3fc' }
}

// Use the same new BRAVE-FIRE artwork in the popup instead of relying on a
// previously cached ArcGIS picture-marker preview. Older category values are
// kept as aliases so existing incident records still receive the correct icon.
const incidentPopupIcons = {
  wildfire: '/images/fire-icons/wildfire-webmap.png?v=20260721',
  carfire: '/images/fire-icons/car-fire-webmap.png?v=20260721',
  'car fire': '/images/fire-icons/car-fire-webmap.png?v=20260721',
  'vehicle fire': '/images/fire-icons/car-fire-webmap.png?v=20260721',
  'electric fire': '/images/fire-icons/electrical-fire-webmap.png?v=20260721',
  'electrical fire': '/images/fire-icons/electrical-fire-webmap.png?v=20260721',
  'house fire': '/images/fire-icons/house-fire-webmap.png?v=20260721',
  'building fire': '/images/fire-icons/house-fire-webmap.png?v=20260721',
}

function getIncidentPopupIcon(category) {
  const normalizedCategory = String(category || '')
    .trim()
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')

  return incidentPopupIcons[normalizedCategory] || ''
}

async function openIncidentPopup({ graphic, attrs, layer }) {
  if (!view || !graphic) return

  let fullAttrs = attrs || {}
  let popupGraphic = graphic

  const fullFeature = await queryFeatureByObjectId(layer, fullAttrs)

  if (fullFeature) {
    fullAttrs = fullFeature.attributes || fullAttrs
    popupGraphic = fullFeature
  }

  const category = getAttr(
    fullAttrs,
    ['categories', 'Categories', 'category', 'Category', 'incident_type', 'Incident_Type'],
    'Fire Incident',
  )

  const district = getAttr(
    fullAttrs,
    ['district', 'District', 'location', 'Location'],
    'Location not provided',
  )

  const severity = getAttr(
    fullAttrs,
    ['severity_level', 'Severity_Level', 'severity', 'Severity'],
    'Unknown',
  )

  const status = getIncidentStatus(fullAttrs)

  const remarks = getAttr(
    fullAttrs,
    ['more_details', 'More_Details', 'remarks', 'Remarks', 'description', 'Description'],
    'No additional incident information provided.',
  )

  const reportedAt = getAttr(
    fullAttrs,
    ['datetime_reported', 'Datetime_Reported', 'created_date', 'Created_Date'],
    null,
  )

  const incidentId = getAttr(
    fullAttrs,
    ['OBJECTID', 'objectid', 'ObjectId', 'objectId', 'FID'],
    '-',
  )

  const latitude = popupGraphic.geometry?.latitude
  const longitude = popupGraphic.geometry?.longitude

  const coordinates =
    Number.isFinite(latitude) && Number.isFinite(longitude)
      ? `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`
      : 'Not available'

  const severityAppearance = getBadgeAppearance(severity)
  const statusAppearance = getBadgeAppearance(status, 'status')
  const incidentIcon = getIncidentPopupIcon(category)
  const popupContent = document.createElement('div')

  popupContent.innerHTML = `
    <div
      style="
        width: 100%;
        min-width: 280px;
        max-width: 370px;
        color: #183b46;
        font-family: inherit;
      "
    >
      <div
        style="
          display: flex;
          align-items: center;
          gap: 14px;
          padding: 14px;
          margin-bottom: 12px;
          border: 1px solid #d6e9e5;
          border-radius: 14px;
          background: linear-gradient(135deg, #eefaf6, #ffffff);
        "
      >
        <div
          data-incident-symbol
          aria-label="${escapePopupHtml(category)} map symbol"
          style="
            width: 58px;
            height: 58px;
            flex: 0 0 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #ffffff;
            border: 1px solid #d6e9e5;
            overflow: hidden;
          "
        >
          ${
            incidentIcon
              ? `<img
                  src="${escapePopupHtml(incidentIcon)}"
                  alt="${escapePopupHtml(category)} icon"
                  width="52"
                  height="52"
                  style="display: block; width: 52px; height: 52px; object-fit: contain;"
                />`
              : '<span aria-hidden="true" style="font-size: 30px;">🔥</span>'
          }
        </div>

        <div style="min-width: 0;">
          <div
            style="
              margin-bottom: 3px;
              font-size: 17px;
              font-weight: 800;
              line-height: 1.25;
            "
          >
            ${escapePopupHtml(category)}
          </div>

          <div style="font-size: 13px; color: #5c737a;">
            ${escapePopupHtml(district)}
          </div>
        </div>
      </div>

      <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 13px;">
        <span
          style="
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid ${severityAppearance.border};
            background: ${severityAppearance.background};
            color: ${severityAppearance.color};
            font-size: 12px;
            font-weight: 800;
          "
        >
          Severity: ${escapePopupHtml(severity)}
        </span>

        <span
          style="
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid ${statusAppearance.border};
            background: ${statusAppearance.background};
            color: ${statusAppearance.color};
            font-size: 12px;
            font-weight: 800;
          "
        >
          ${escapePopupHtml(status)}
        </span>
      </div>

      <div
        style="
          display: grid;
          grid-template-columns: 105px minmax(0, 1fr);
          gap: 9px 12px;
          padding: 12px;
          border-radius: 12px;
          background: #f7faf9;
          font-size: 13px;
        "
      >
        <span style="color: #6a7f85;">District</span>
        <strong>${escapePopupHtml(district)}</strong>

        <span style="color: #6a7f85;">Reported</span>
        <strong>${escapePopupHtml(formatPopupDate(reportedAt))}</strong>

        <span style="color: #6a7f85;">Coordinates</span>
        <strong>${escapePopupHtml(coordinates)}</strong>

        <span style="color: #6a7f85;">Incident ID</span>
        <strong>#${escapePopupHtml(incidentId)}</strong>
      </div>

      <div
        style="
          margin-top: 12px;
          padding: 12px;
          border-left: 4px solid #e5a000;
          border-radius: 8px;
          background: #fffaf0;
        "
      >
        <div
          style="
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 800;
            color: #785800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
          "
        >
          Incident details
        </div>

        <div
          style="
            font-size: 13px;
            line-height: 1.55;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
          "
        >${escapePopupHtml(remarks)}</div>
      </div>

      <button
        data-open-incident-details
        type="button"
        style="
          width: 100%;
          margin-top: 13px;
          padding: 10px 14px;
          border: none;
          border-radius: 9px;
          background: #214f5d;
          color: #ffffff;
          font-weight: 700;
          cursor: pointer;
        "
      >
        View full incident and nearby resources
      </button>
    </div>
  `

  const symbolContainer = popupContent.querySelector('[data-incident-symbol]')

  // For any category without a dedicated BRAVE-FIRE icon, retain the current
  // WebMap renderer as a fallback.
  if (!incidentIcon) {
    try {
      const displayedSymbol = await getDisplayedSymbol(popupGraphic, {
        scale: view.scale,
        spatialReference: view.spatialReference,
        resolution: view.resolution,
      })

      if (displayedSymbol && symbolContainer) {
        const symbolPreview = await renderPreviewHTML(displayedSymbol, {
          size: { width: 48, height: 48 },
          maxSize: 48,
          disableUpsampling: false,
        })

        if (symbolPreview) {
          symbolContainer.replaceChildren(symbolPreview)
        }
      }
    } catch (error) {
      console.warn('Unable to render incident popup symbol:', error)
    }
  }

  popupContent
    .querySelector('[data-open-incident-details]')
    ?.addEventListener('click', async () => {
      view.closePopup()

      await openIncidentDrawer({
        graphic: popupGraphic,
        attrs: fullAttrs,
        layer,
      })
    })

  await view.openPopup({
    title: `${category} Incident`,
    location: popupGraphic.geometry,
    content: popupContent,
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
const showFireStationList = ref(false)
const fireStationList = ref([])

async function addMapWidgets() {
  const searchWidget = new Search({ view })
  const basemapGallery = new BasemapGallery({ view })

  locateWidget = new Locate({ view })
  bookmarksWidget = new Bookmarks({ view })
  distanceWidget = new DistanceMeasurement2D({ view })
  areaWidget = new AreaMeasurement2D({ view })

  locationSketch = new Sketch({
    view,
    layer: locationGraphicsLayer,
    creationMode: 'single',

    pointSymbol: {
      type: 'simple-marker',
      style: 'circle',
      color: [255, 193, 7, 1],
      size: 12,
      outline: {
        color: [255, 255, 255, 1],
        width: 2,
      },
    },

    polylineSymbol: {
      type: 'simple-line',
      color: [255, 193, 7, 1],
      width: 4,
      style: 'solid',
    },

    polygonSymbol: {
      type: 'simple-fill',
      color: [255, 193, 7, 0.45],
      outline: {
        color: [255, 193, 7, 1],
        width: 3,
      },
    },

    visibleElements: {
      createTools: {
        point: false,
        polyline: false,
        polygon: false,
        rectangle: false,
        circle: false,
      },
      selectionTools: {
        'lasso-selection': false,
        'rectangle-selection': false,
      },
      settingsMenu: false,
      undoRedoMenu: false,
    },
  })

  locationSketch.on('create', async (event) => {
    if (event.state !== 'complete') return

    activeLocationTool.value = null

    lastLocationGraphic.value = event.graphic
    lastLocationGeometry.value = event.graphic.geometry

    await analyzeLocationGeometry(event.graphic.geometry)
  })

  const sensorLayer = findLayerByTitle(layerTitles.sensors) || findLayerByTitleIncludes('sensor')
  const temperatureLayer =
    findLayerByTitle('temperatur') ||
    findLayerByTitleIncludes('temperatur') ||
    findLayerByTitleIncludes('temperature')
  const incidentLayer =
    findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')
  const clusterLayer =
    findLayerByTitle('Number of features') || findLayerByTitleIncludes('features')

  const legendLayers = [sensorLayer, temperatureLayer, incidentLayer, clusterLayer].filter(Boolean)

  for (const layer of legendLayers) {
    try {
      await layer.load()
    } catch (error) {
      console.warn('Failed to load legend layer:', layer.title, error)
    }
  }

  const legend = new Legend({
    view,
    layerInfos: legendLayers.map((layer) => ({
      layer,
      title: layer.title,
    })),
  })

  const fireStationListButton = document.createElement('button')
  fireStationListButton.className =
    'esri-widget esri-widget--button esri-interactive firestation-esri-btn'
  fireStationListButton.title = 'Fire Station List'
  fireStationListButton.type = 'button'
  fireStationListButton.innerHTML = `<span class="esri-icon-layer-list"></span>`

  fireStationListButton.addEventListener('click', () => {
    console.log('Fire station list clicked')

    toggleFireStationList()
  })

  // view.ui.add(searchWidget, 'top-left')
  view.ui.add(
    [
      // new Expand({
      //   view,
      //   content: legend,
      //   expandIcon: 'legend',
      //   expandTooltip: 'Legend',
      //   collapseTooltip: 'Close',
      //   expanded: false,
      //   mode: 'floating',
      // }),
      new Expand({
        view,
        content: basemapGallery,
        expandIcon: 'basemap',
        expandTooltip: 'Basemap',
        expanded: false,
        collapseTooltip: 'Close',
        mode: 'floating',
      }),
      fireStationListButton,
    ],
    'top-right',
  )
  // editorWidget = new Editor({
  //   view,
  //   allowedWorkflows: ['create', 'update'],
  // })

  // editorExpand = new Expand({
  //   view,
  //   content: editorWidget,
  //   expandIcon: 'pencil',
  //   expandTooltip: 'Update Report',
  //   collapseTooltip: 'Close Update Report',
  //   mode: 'floating',
  // })

  // view.ui.add(editorExpand, 'manual')
}

// =========================
// LIVE DATA REFRESH
// =========================
async function refreshLiveData({ checkNewIncidents = true } = {}) {
  if (!webmap) return

  loading.value = true

  try {
    const alertLayer = findLayerByTitle(layerTitles.alerts) || findLayerByTitleIncludes('alert')
    const incidentLayer =
      findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')

    const sensorLayer =
      findLayerByTitle(layerTitles.sensors) ||
      findLayerByTitle(layerTitles.sensorsAlt) ||
      findLayerByTitleIncludes('measurement')

    const taskForceLayer =
      findLayerByTitle(layerTitles.taskForce) || findLayerByTitleIncludes('task force')

    const incidentWhere = buildActivityWhereClause()

    const incidentFeatures = await getFeatures(incidentLayer, {
      limit: 50,
      where: incidentWhere,
      orderByFields: ['datetime_reported DESC'],
    })

    const allIncidentFeatures = await getFeatures(incidentLayer, {
      limit: 1000,
      where: '1=1',
      orderByFields: ['datetime_reported DESC'],
    })

    const sensorFeatures = await getFeatures(sensorLayer, { limit: 50 })
    const alertFeatures = await getFeatures(alertLayer, { limit: 5 })

    alerts.value = mapAlertFeatures(alertFeatures)
    sensors.value = mapSensorFeatures(sensorFeatures)
    incidents.value = mapIncidentFeatures(incidentFeatures)
    allIncidents.value = mapIncidentFeatures(allIncidentFeatures)

    if (checkNewIncidents) {
      detectNewIncidents(allIncidents.value)
    } else {
      syncSeenIncidents(allIncidents.value)
    }

    stats.activeAlerts = await getCount(alertLayer)
    stats.activeIncidents = await getCount(incidentLayer, incidentWhere)
    stats.onlineSensors = sensors.value.length
    stats.responseTeams = await getCount(taskForceLayer)

    // refreshLayerData()
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

function mapSensorFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    return {
      objectId: getAttr(attrs, ['FID', 'OBJECTID', 'ObjectId', 'objectid']),
      graphic: feature,
      name: getAttr(attrs, ['name', 'device_nam', 'Name', 'ID', 'eui'], 'Sensor'),
      location: getAttr(attrs, ['site_name', 'district', 'District', 'coordinate'], 'Unknown'),
      temperature: formatSensorValue(getAttr(attrs, ['temperatur', 'temperature'], '-'), '°'),
      humidity: formatSensorValue(getAttr(attrs, ['humidity'], '-'), '%'),
      airQuality: formatSensorValue(getAttr(attrs, ['air_qualit'], '-'), 'ppm'),
      airPressure: formatSensorValue(getAttr(attrs, ['air_pressu'], '-'), 'hpa'),
      eui: getAttr(attrs, ['eui'], '-'),
      energyLevel: getAttr(attrs, ['energy_lev'], '-'),
      lastUpdated: formatDate(getAttr(attrs, ['datetime', 'Modified', 'Created'], '')),
      online: true,
    }
  })
}

function mapIncidentFeatures(features) {
  return features.map((feature) => {
    const attrs = feature.attributes || {}

    const rawTime = getAttr(attrs, ['datetime_reported'], Date.now())

    return {
      objectId: getAttr(attrs, ['objectid', 'OBJECTID', 'ObjectId']),
      graphic: feature,
      type: getAttr(attrs, ['categories'], 'Unknown'),
      location: getAttr(attrs, ['district'], 'Unknown'),
      status: getAttr(attrs, ['severity_level'], 'Unknown'),
      remarks: getAttr(attrs, ['more_details'], '-'),
      reportedAt: formatDate(rawTime),
      rawTime,
      resolved: getAttr(attrs, ['resolved'], 'No'),
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

  document.documentElement.classList.toggle('full-map-open', isFullMap.value)
  document.body.classList.toggle('full-map-open', isFullMap.value)

  await nextTick()

  // requestAnimationFrame(() => {
  //   view?.resize()
  // })
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
    await openIncidentPopup({
      graphic: item.graphic,
      attrs:
        item.graphic?.attributes || {
          categories: item.type,
          district: item.location,
          severity_level: item.status,
          datetime_reported: item.rawTime || item.reportedAt,
          more_details: item.remarks,
          resolved: item.resolved,
        },
      layer: item.graphic?.layer,
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

function getIncidentLayer() {
  return findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident')
}

function clearUpdateSelection() {
  updateSelectionMode.value = null
  updateResults.value = []
  selectedUpdateGraphic.value = null
  updateSelectLayer?.removeAll()
  updateSketch?.destroy()
  updateSketch = null
  resetUpdateForm()
}

function resetUpdateForm() {
  updateForm.objectId = null
  updateForm.datetime_date = ''
  updateForm.datetime_time = ''
  updateForm.categories = ''
  updateForm.other_categories = ''
  updateForm.more_details = ''
  updateForm.district = ''
  updateForm.resolved = ''
  updateForm.unresolved = ''
}

function startUpdateSelection(mode) {
  if (!view || !updateSelectLayer) return

  updateSelectionMode.value = mode
  updateSelectLayer.removeAll()

  updateSketch?.destroy()
  updateSketch = null

  // POINT = click actual incident symbol
  if (mode === 'point') {
    const clickHandler = view.on('click', async (event) => {
      const hit = await view.hitTest(event)

      const incidentHit = hit.results.find((result) => {
        const title = result.graphic?.layer?.title?.toLowerCase() || ''
        return title.includes('incident')
      })

      if (!incidentHit) {
        alert('No incident found. Click directly on the incident icon.')
        return
      }

      loadIncidentIntoUpdateForm(incidentHit.graphic)
      clickHandler.remove()
    })

    return
  }

  // RECTANGLE / LASSO = Sketch selection
  updateSketch = new Sketch({
    view,
    layer: updateSelectLayer,
    availableCreateTools: [],
    visibleElements: {
      createTools: false,
      selectionTools: false,
      settingsMenu: false,
      undoRedoMenu: false,
    },
  })

  updateSketch.on('create', async (event) => {
    if (event.state !== 'complete') return
    await selectIncidentsByGeometry(event.graphic.geometry)
  })

  if (mode === 'rectangle') {
    updateSketch.create('rectangle')
  }

  if (mode === 'lasso') {
    updateSketch.create('polygon', { mode: 'freehand' })
  }
}

function buildDateTimeValue() {
  if (!updateForm.datetime_date) return null

  const time = updateForm.datetime_time || '00:00'
  return new Date(`${updateForm.datetime_date}T${time}:00`).getTime()
}

function buildUpdateAttributes() {
  return {
    district: updateForm.district,
    categories: updateForm.categories,
    severity_level: updateForm.unresolved || updateForm.resolved || '',
    more_details: updateForm.more_details || '-',
    datetime_reported: buildDateTimeValue() || Date.now(),
  }
}

function buildUpdateGeometry() {
  const geometry = selectedUpdateGraphic.value?.geometry

  if (!geometry) return null

  return {
    x: geometry.longitude,
    y: geometry.latitude,
    spatialReference: {
      wkid: 4326,
    },
  }
}

async function saveCustomUpdateReport() {
  if (!updateForm.objectId) return

  updatingReport.value = true

  try {
    await http.put(`/api/fire-incidents/${updateForm.objectId}`, {
      attributes: {
        district: updateForm.district,
        categories: updateForm.categories,
        severity_level: updateForm.severity_level || updateForm.unresolved || '',
        more_details: updateForm.more_details || '-',
        datetime_reported: buildDateTimeValue() || Date.now(),
      },
      geometry: selectedUpdateGraphic.value?.geometry
        ? {
            x: selectedUpdateGraphic.value.geometry.longitude,
            y: selectedUpdateGraphic.value.geometry.latitude,
            spatialReference: { wkid: 4326 },
          }
        : null,
    })

    await refreshLiveData()

    showToast('Success', 'Incident updated successfully.', 'success')
  } catch (error) {
    console.error(error)

    const message =
      error?.response?.data?.message ||
      error?.response?.data?.details?.updateResults?.[0]?.error?.description ||
      error?.message ||
      'Failed to update incident.'

    showToast('Update Failed', message, 'danger')
  } finally {
    updatingReport.value = false
  }
}

async function deleteSelectedIncident() {
  if (!updateForm.objectId) return

  if (!confirm('Delete this incident?')) return

  try {
    await http.delete(`/api/fire-incidents/${updateForm.objectId}`)

    clearUpdateSelection()
    await refreshLiveData()

    alert('Incident deleted successfully.')
  } catch (error) {
    console.error(error)

    const message =
      error?.response?.data?.message ||
      error?.response?.data?.details?.deleteResults?.[0]?.error?.description ||
      error?.message ||
      'Failed to delete incident.'

    alert(message)
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
  if (showUpdateReportTool.value || view?.updating || view?.interacting) return
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

  if (item.kind === 'incident' || item.type) {
    const layer = item.graphic.layer
    const attrs = item.graphic.attributes || {}

    await openIncidentPopup({
      graphic: item.graphic,
      attrs,
      layer,
    })

    return
  }

  view.openPopup({
    title: item.title || 'Alert',
    location: item.graphic.geometry,
    content: `
    <div style="padding:8px 4px;">
      <p><strong>Type :</strong> ${item.kind || '-'}</p>
      <p><strong>Status :</strong> ${item.status || '-'}</p>
      <p><strong>Remarks :</strong> ${item.message || '-'}</p>
      <p><strong>Reported :</strong> ${item.time || '-'}</p>
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
let incidentNotificationReady = false
const seenIncidentIds = ref(new Set())
const updatingReport = ref(false)

const updateForm = reactive({
  objectId: null,
  datetime_date: '',
  datetime_time: '',
  categories: '',
  other_categories: '',
  more_details: '',
  district: '',
  resolved: '',
  unresolved: '',
})

const alertSounds = {
  chime: '/sounds/alert-chime.mp3',
  siren: '/sounds/emergency-siren.mp3',
  notification: '/sounds/notification.mp3',
}

let alertAudio = null
let audioUnlocked = false

function getSoundSrc(type = selectedSound.value) {
  return alertSounds[type] || alertSounds.chime
}

async function unlockAudio(type = selectedSound.value) {
  try {
    alertAudio = new Audio(getSoundSrc(type))
    alertAudio.preload = 'auto'
    alertAudio.volume = 0.01

    await alertAudio.play()
    alertAudio.pause()
    alertAudio.currentTime = 0

    audioUnlocked = true
  } catch (err) {
    audioUnlocked = false
    console.warn('Audio unlock failed:', err)
  }
}

async function toggleSound() {
  soundEnabled.value = !soundEnabled.value
  localStorage.setItem('braveSoundEnabled', String(soundEnabled.value))

  if (soundEnabled.value) {
    await unlockAudio()
  }
}

async function changeAlertSound(type) {
  selectedSound.value = type
  localStorage.setItem('braveAlertSound', type)

  if (soundEnabled.value) {
    await unlockAudio(type)
  }
}

async function playSpecificAlertSound(type = selectedSound.value) {
  if (!soundEnabled.value) return

  try {
    if (!alertAudio || !audioUnlocked) {
      await unlockAudio(type)
    }

    alertAudio.src = getSoundSrc(type)
    alertAudio.volume = type === 'siren' ? 0.45 : 0.25
    alertAudio.currentTime = 0

    await alertAudio.play()
  } catch (err) {
    console.warn('Sound blocked:', err)
  }
}

function playAlertSound() {
  playSpecificAlertSound(selectedSound.value)
}

function getIncidentKey(incident) {
  return String(
    incident.objectId ||
      incident.id ||
      `${incident.type}-${incident.location}-${incident.reportedAt}`,
  )
}

function syncSeenIncidents(items) {
  items.forEach((item) => {
    seenIncidentIds.value.add(getIncidentKey(item))
  })
}

function detectNewIncidents(items) {
  const newItems = items.filter((item) => {
    const key = getIncidentKey(item)
    return !seenIncidentIds.value.has(key)
  })

  syncSeenIncidents(items)

  if (!incidentNotificationReady) {
    incidentNotificationReady = true
    return
  }

  newItems.forEach((incident) => {
    notificationStore.addNotification({
      title: `${incident.type || 'Incident'} reported`,
      message: `${incident.location || 'Unknown'} · ${incident.status || 'Active'}`,
      type: 'danger',
    })

    const severity = String(incident.status || '').toLowerCase()

    if (severity.includes('critical') || severity.includes('high')) {
      playSpecificAlertSound('siren')
    } else {
      playAlertSound()
    }
  })
}

function startLiveAutoRefresh() {
  stopLiveAutoRefresh()

  liveRefreshInterval = setInterval(async () => {
    if (!webmap || !view) return
    if (view.updating || view.interacting || showUpdateReportTool.value) return

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

    // force ArcGIS layer refresh first
    alertLayer?.refresh?.()
    incidentLayer?.refresh?.()

    await new Promise((resolve) => setTimeout(resolve, 500))

    const where = buildActivityWhereClause()

    const incidentFeatures = await getFeatures(incidentLayer, {
      limit: 50,
      where,
      orderByFields: ['datetime_reported DESC'],
    })

    const alertFeatures = await getFeatures(alertLayer, {
      limit: 10,
      orderByFields: ['datetime_reported DESC'],
    })

    const latestIncidents = mapIncidentFeatures(incidentFeatures)

    alerts.value = mapAlertFeatures(alertFeatures)
    incidents.value = latestIncidents

    detectNewIncidents(latestIncidents)

    stats.activeAlerts = await getCount(alertLayer)
    const allIncidentFeatures = await getFeatures(incidentLayer, {
      limit: 1000,
      where: '1=1',
      orderByFields: ['datetime_reported DESC'],
    })

    allIncidents.value = mapIncidentFeatures(allIncidentFeatures)
    stats.activeIncidents = await getCount(incidentLayer, where)
  } catch (error) {
    console.warn('Activity feed refresh failed:', error)
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

const showIncidentDrawer = ref(false)

const incidentDrawer = reactive({
  id: '',
  category: '-',
  severity: '-',
  district: '-',
  reportedAt: '-',
  coordinates: '-',
  remarks: '-',
  status: '-',
  graphic: null,
})

async function openIncidentDrawer({ graphic, attrs, layer }) {
  let fullAttrs = attrs

  const fullFeature = await queryFeatureByObjectId(layer, attrs)

  if (fullFeature) {
    fullAttrs = fullFeature.attributes || attrs
  }

  const lat = graphic.geometry?.latitude?.toFixed(6) || '-'
  const lon = graphic.geometry?.longitude?.toFixed(6) || '-'

  const rawDate = fullAttrs.datetime_reported || fullAttrs.created_date || fullAttrs.Created_Date

  incidentDrawer.id = fullAttrs.OBJECTID || fullAttrs.objectid || fullAttrs.ObjectId || '-'

  incidentDrawer.category = fullAttrs.categories || '-'

  incidentDrawer.severity = fullAttrs.severity_level || '-'

  incidentDrawer.district = fullAttrs.district || '-'

  incidentDrawer.reportedAt = rawDate ? formatDate(rawDate) : '-'

  incidentDrawer.coordinates = `${lat}, ${lon}`

  incidentDrawer.remarks = fullAttrs.more_details || '-'

  incidentDrawer.status = fullAttrs.status || '-'

  incidentDrawer.graphic = graphic

  showIncidentDrawer.value = true

  await loadNearbyResources(graphic.geometry)
}

function closeIncidentDrawer() {
  showIncidentDrawer.value = false
}

function severityClass(level) {
  const value = String(level).toLowerCase()

  if (value.includes('critical')) return 'severity-critical'
  if (value.includes('high')) return 'severity-high'
  if (value.includes('medium')) return 'severity-medium'

  return 'severity-low'
}

const nearbyResources = reactive({
  loading: false,
  fireStations: [],
  responseTeams: [],
  sensors: [],
})

async function loadNearbyResources(incidentGeometry) {
  if (!incidentGeometry || !webmap) return

  nearbyResources.loading = true
  nearbyResources.fireStations = []
  nearbyResources.responseTeams = []
  nearbyResources.sensors = []

  try {
    const fireStationLayer =
      findLayerByTitleIncludes('fire station') || findLayerByTitleIncludes('bfrd')

    const responseTeamLayer =
      findLayerByTitle(layerTitles.taskForce) || findLayerByTitleIncludes('task force')

    const sensorLayer =
      findLayerByTitle(layerTitles.sensors) ||
      findLayerByTitle(layerTitles.sensorsAlt) ||
      findLayerByTitleIncludes('sensor') ||
      findLayerByTitleIncludes('measurement')

    nearbyResources.fireStations = await queryNearestResources(
      fireStationLayer,
      incidentGeometry,
      'Fire Station',
      3,
    )

    nearbyResources.responseTeams = await queryNearestResources(
      responseTeamLayer,
      incidentGeometry,
      'Response Team',
      3,
    )

    // nearbyResources.sensors = await queryNearestResources(
    //   sensorLayer,
    //   incidentGeometry,
    //   'Sensor',
    //   3,
    // )
  } catch (error) {
    console.warn('Nearby resources failed:', error)
  } finally {
    nearbyResources.loading = false
  }
}

async function queryNearestResources(layer, incidentGeometry, type, limit = 3) {
  if (!layer?.createQuery || !incidentGeometry) return []

  try {
    const query = layer.createQuery()

    query.geometry = incidentGeometry
    query.distance = 20
    query.units = 'kilometers'
    query.spatialRelationship = 'intersects'
    query.outFields = ['*']
    query.returnGeometry = true
    query.num = 50

    const response = await layer.queryFeatures(query)

    return (response.features || [])
      .map((feature) => {
        const attrs = feature.attributes || {}

        return {
          id:
            getAttr(attrs, ['OBJECTID', 'objectid', 'ObjectId', 'FID']) ||
            `${type}-${Math.random()}`,
          name: getResourceName(attrs, type),
          type,
          distance: calculateDistanceKm(incidentGeometry, feature.geometry),
          graphic: feature,
        }
      })
      .filter((item) => item.distance !== null)
      .sort((a, b) => a.distance - b.distance)
      .slice(0, limit)
  } catch (error) {
    console.warn(`Nearest query failed for ${layer?.title}:`, error)
    return []
  }
}

function calculateDistanceKm(pointA, pointB) {
  if (!pointA || !pointB) return null

  const lat1 = pointA.latitude
  const lon1 = pointA.longitude
  const lat2 = pointB.latitude
  const lon2 = pointB.longitude

  if ([lat1, lon1, lat2, lon2].some((v) => v === undefined || v === null)) {
    return null
  }

  const earthRadiusKm = 6371

  const dLat = degreesToRadians(lat2 - lat1)
  const dLon = degreesToRadians(lon2 - lon1)

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(degreesToRadians(lat1)) *
      Math.cos(degreesToRadians(lat2)) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2)

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))

  return Number((earthRadiusKm * c).toFixed(2))
}

function degreesToRadians(value) {
  return (value * Math.PI) / 180
}

function getResourceName(attrs, type) {
  if (type === 'Fire Station') {
    return getAttr(
      attrs,
      ['name', 'Name', 'station_name', 'Station_Name', 'STATION_NAME', 'fire_station'],
      'Fire Station',
    )
  }

  if (type === 'Response Team') {
    return getAttr(
      attrs,
      ['name', 'Name', 'team_name', 'Team_Name', 'unit_name', 'Unit_Name'],
      'Response Team',
    )
  }

  return getAttr(attrs, ['device_nam', 'Device_nam', 'name', 'Name', 'ID', 'eui'], 'Sensor')
}

const notificationStore = useNotificationStore()

function resourceDistanceClass(distance) {
  const km = Number(distance)

  if (Number.isNaN(km)) return 'resource-unknown'
  if (km <= 3) return 'resource-near'
  if (km <= 8) return 'resource-medium'

  return 'resource-far'
}

function clearArcgisTool() {
  if (!view) return

  if (distanceWidget) {
    view.ui.remove(distanceWidget)
    distanceWidget.viewModel.clear()
  }

  if (areaWidget) {
    view.ui.remove(areaWidget)
    areaWidget.viewModel.clear()
  }

  if (bookmarksWidget) {
    view.ui.remove(bookmarksWidget)
  }

  activeArcgisTool = null
}

function activateLocateTool() {
  if (!locateWidget) return
  locateWidget.locate()
}

function toggleBookmarksTool() {
  if (!view || !bookmarksWidget) return

  if (activeArcgisTool === 'bookmarks') {
    clearArcgisTool()
    return
  }

  clearArcgisTool()
  activeArcgisTool = 'bookmarks'
  view.ui.add(bookmarksWidget, 'bottom-left')
}

function toggleDistanceTool() {
  if (!view || !distanceWidget) return

  if (activeArcgisTool === 'distance') {
    clearArcgisTool()
    return
  }

  clearArcgisTool()
  activeArcgisTool = 'distance'
  view.ui.add(distanceWidget, 'bottom-left')
  distanceWidget.viewModel.start()
}

function toggleAreaTool() {
  if (!view || !areaWidget) return

  if (activeArcgisTool === 'area') {
    clearArcgisTool()
    return
  }

  clearArcgisTool()
  activeArcgisTool = 'area'
  view.ui.add(areaWidget, 'bottom-left')
  areaWidget.viewModel.start()
}

function startLocationSketch(type) {
  if (!locationSketch) {
    console.warn('Location Sketch is not ready')
    return
  }

  activeLocationTool.value = type
  lastLocationToolType.value = type

  locationSketch.cancel()
  locationSketch.create(type)

  resizeMapView()
}

function clearLocationTool() {
  activeLocationTool.value = null
  lastLocationGeometry.value = null

  locationSketch?.cancel()
  locationGraphicsLayer?.removeAll()

  locationToolResults.visible = false
  locationToolResults.workers = []
  locationToolResults.fireStations = []
  locationToolResults.sensors = []
  locationToolResults.incidents = []

  resizeMapView()
}

function clearLocationGraphics() {
  activeLocationTool.value = null

  locationSketch?.cancel()
  locationGraphicsLayer?.removeAll()
}

async function analyzeLocationGeometry(geometry) {
  if (!geometry) return

  lastLocationGeometry.value = geometry

  locationToolResults.visible = true
  locationToolResults.geometryType = geometry.type

  let analysisGeometry = geometry

  const shouldBuffer =
    geometry.type === 'point' || geometry.type === 'polyline' || geometry.type === 'polygon'

  if (shouldBuffer) {
    analysisGeometry = geometryEngine.buffer(
      geometry,
      Number(locationRadius.value || 1),
      locationUnit.value,
    )

    drawLocationBuffer(analysisGeometry)
  }

  locationToolResults.incidents = await queryResourcesInsideGeometry(
    findLayerByTitle(layerTitles.incidents) || findLayerByTitleIncludes('incident'),
    analysisGeometry,
  )

  locationToolResults.fireStations = await queryResourcesInsideGeometry(
    findLayerByTitleIncludes('fire station') || findLayerByTitleIncludes('bfrd'),
    analysisGeometry,
  )

  locationToolResults.sensors = await queryResourcesInsideGeometry(
    findLayerByTitleIncludes('sensor') || findLayerByTitleIncludes('measurement'),
    analysisGeometry,
  )

  await zoomToLocationRadius(analysisGeometry)
}
async function queryResourcesInsideGeometry(layer, geometry) {
  if (!layer || !geometry) return []

  try {
    const query = layer.createQuery()

    query.geometry = geometry
    query.spatialRelationship = 'intersects'
    query.returnGeometry = true
    query.outFields = ['*']

    const result = await layer.queryFeatures(query)

    return (result.features || []).map((feature) => {
      addResourceSquareHighlight(feature)

      const attrs = feature.attributes || {}

      return {
        id: attrs.OBJECTID || attrs.objectid || attrs.ObjectId || Math.random().toString(36),

        name:
          attrs.categories ||
          attrs.name ||
          attrs.Name ||
          attrs.station_name ||
          attrs.device_nam ||
          'Unknown',

        distance: calculateDistanceKm(lastLocationGeometry.value, feature.geometry),

        attrs,
        graphic: feature,
      }
    })
  } catch (error) {
    console.warn(`Location resource query failed for ${layer?.title}:`, error)
    return []
  }
}

function resetRouteStateOnly() {
  routeState.selecting = null
  routeState.startPoint = null
  routeState.endPoint = null
  routeState.distance = '-'
  routeState.time = '-'
  routeState.loading = false
  routeState.error = ''

  routeLayer?.stops?.removeAll()
  routeLayer?.directionLines?.removeAll?.()
  routeGraphicsLayer?.removeAll()
}

function addResourceSquareHighlight(feature) {
  if (!feature?.geometry || !locationGraphicsLayer) return

  const geometry = feature.geometry

  const point = geometry.type === 'point' ? geometry : geometry.extent?.center

  if (!point) return

  const size = 0.0035 // adjust if too big/small

  const square = {
    type: 'polygon',
    rings: [
      [
        [point.longitude - size, point.latitude - size],
        [point.longitude + size, point.latitude - size],
        [point.longitude + size, point.latitude + size],
        [point.longitude - size, point.latitude + size],
        [point.longitude - size, point.latitude - size],
      ],
    ],
    spatialReference: point.spatialReference,
  }

  locationGraphicsLayer.add(
    new Graphic({
      geometry: square,
      symbol: {
        type: 'simple-fill',
        color: [255, 0, 255, 0.08],
        outline: {
          color: [255, 0, 255, 1],
          width: 3,
        },
      },
    }),
  )
}

async function rerunLocationAnalysis() {
  if (!lastLocationGeometry.value) return

  locationGraphicsLayer?.removeAll()

  // redraw the original inner shape
  if (lastLocationGraphic.value) {
    locationGraphicsLayer.add(
      new Graphic({
        geometry: lastLocationGraphic.value.geometry,
        symbol: lastLocationGraphic.value.symbol,
      }),
    )
  }

  await analyzeLocationGeometry(lastLocationGeometry.value)
}

function drawLocationBuffer(bufferGeometry) {
  if (!locationGraphicsLayer || !bufferGeometry) return

  locationGraphicsLayer.add(
    new Graphic({
      geometry: bufferGeometry,
      symbol: {
        type: 'simple-fill',
        color: [255, 140, 0, 0.32],
        outline: {
          color: [255, 140, 0, 1],
          width: 2,
        },
      },
    }),
  )
}

const showDirectionsTool = ref(false)

const routeState = reactive({
  selecting: null,
  startPoint: null,
  endPoint: null,
  stops: [],
  distance: '-',
  time: '-',
  loading: false,
  error: '',
  mode: 'Driving Time',
  departureType: 'now',
  departureDate: '',
  departureTime: '',
  optimizeOrder: false,
})

let routeLayer = null
let routeGraphicsLayer = null

function startRouteSelection(type) {
  routeState.selecting = type
  routeState.error = ''
}
function clearRouteTool() {
  showDirectionsTool.value = false
  resetRouteStateOnly()
}

async function handleRouteMapClick(event) {
  if (!routeGraphicsLayer || !event.mapPoint) return

  const point = event.mapPoint.clone()
  const type = routeState.selecting

  if (type !== 'stop') {
    routeGraphicsLayer.graphics
      .filter((g) => g.attributes?.routeType === type)
      .forEach((g) => routeGraphicsLayer.remove(g))
  }

  if (type === 'start') routeState.startPoint = point
  if (type === 'end') routeState.endPoint = point
  if (type === 'stop') routeState.stops.push(point)

  routeGraphicsLayer.add(
    new Graphic({
      geometry: point,
      attributes: {
        routeType: type,
        stopIndex: type === 'stop' ? routeState.stops.length - 1 : null,
      },
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color:
          type === 'start'
            ? [34, 197, 94, 1]
            : type === 'end'
              ? [239, 68, 68, 1]
              : [245, 158, 11, 1],
        size: type === 'stop' ? 13 : 16,
        outline: {
          color: [255, 255, 255, 1],
          width: 3,
        },
      },
    }),
  )

  routeState.selecting = null
}

function removeRouteStop(index) {
  routeState.stops.splice(index, 1)

  routeGraphicsLayer.graphics
    .filter((g) => g.attributes?.routeType === 'stop' && g.attributes?.stopIndex === index)
    .forEach((g) => routeGraphicsLayer.remove(g))

  routeGraphicsLayer.graphics
    .filter((g) => g.attributes?.routeType === 'route-line')
    .forEach((g) => routeGraphicsLayer.remove(g))

  routeState.distance = '-'
  routeState.time = '-'
}

async function calculateRoute() {
  if (!routeState.startPoint || !routeState.endPoint) return

  routeState.loading = true
  routeState.error = ''

  try {
    const response = await http.post('/api/fire/route', {
      start: {
        latitude: routeState.startPoint.latitude,
        longitude: routeState.startPoint.longitude,
      },

      end: {
        latitude: routeState.endPoint.latitude,
        longitude: routeState.endPoint.longitude,
      },

      stops: routeState.stops.map((stop) => ({
        latitude: stop.latitude,
        longitude: stop.longitude,
      })),

      mode: routeState.mode,
      departureType: routeState.departureType,
      departureDate: routeState.departureDate,
      departureTime: routeState.departureTime,
      optimizeOrder: routeState.optimizeOrder,
    })

    const route = response.data?.routes?.features?.[0]

    if (!route?.geometry?.paths) {
      routeState.error = 'No route found.'
      return
    }

    // remove old route line only, keep start/destination pins
    routeGraphicsLayer.graphics
      .filter((g) => g.attributes?.routeType === 'route-line')
      .forEach((g) => routeGraphicsLayer.remove(g))

    const routeGeometry = new Polyline({
      paths: route.geometry.paths,
      spatialReference: route.geometry.spatialReference || { wkid: 4326 },
    })

    const routeGraphic = new Graphic({
      geometry: routeGeometry,
      attributes: {
        routeType: 'route-line',
      },
      symbol: {
        type: 'simple-line',
        color: [0, 132, 255, 1],
        width: 6,
        cap: 'round',
        join: 'round',
      },
    })

    routeGraphicsLayer.add(routeGraphic)

    routeState.distance = `${Number(route.attributes.Total_Kilometers).toFixed(2)} km`
    routeState.time = `${Math.round(Number(route.attributes.Total_TravelTime))} mins`

    if (routeGeometry.extent) {
      await view.goTo({
        target: routeGeometry.extent.expand(1.3),
      })
    }
  } catch (err) {
    console.error(err)
    routeState.error = 'Failed to calculate route.'
  } finally {
    routeState.loading = false
  }
}
async function resizeMapView() {
  await nextTick()

  if (!view?.container) return

  requestAnimationFrame(() => {
    view.container.style.width = '100%'
    view.container.style.height = '100%'

    view.requestRender()
  })
}

function resetUpdateReportPanel() {
  updateMode.value = 'main'

  clearUpdateSelection()

  updateResults.value = []
  selectedUpdateGraphic.value = null

  resetUpdateForm()
  resetCreateForm()

  createLocationSelecting.value = false
  updateSelectionMode.value = null

  updateSelectLayer?.removeAll()
}

async function saveCurrentBookmark() {
  if (!view) return

  const screenshot = await view.takeScreenshot({
    width: 280,
    height: 150,
    format: 'png',
  })

  const bookmark = {
    id: Date.now(),
    name: `Bookmark ${bookmarks.value.length + 1}`,
    image: screenshot.dataUrl,
    center: {
      longitude: view.center.longitude,
      latitude: view.center.latitude,
    },
    zoom: view.zoom,
    rotation: view.rotation || 0,
  }

  bookmarks.value.unshift(bookmark)
  localStorage.setItem('braveBookmarks', JSON.stringify(bookmarks.value))
}

async function loadIncidentIntoUpdateForm(graphic) {
  const layer = graphic.layer || getIncidentLayer()
  const attrs = graphic.attributes || {}

  const fullFeature = await queryFeatureByObjectId(layer, attrs)
  const finalGraphic = fullFeature || graphic
  const finalAttrs = finalGraphic.attributes || {}

  selectedUpdateGraphic.value = finalGraphic

  updateForm.objectId =
    finalAttrs.OBJECTID || finalAttrs.objectid || finalAttrs.ObjectId || finalAttrs.objectId

  updateForm.categories = finalAttrs.categories || finalAttrs.Categories || ''

  updateForm.other_categories = finalAttrs.other_categories || finalAttrs.Other_Categories || ''

  updateForm.more_details =
    finalAttrs.more_details || finalAttrs.More_Details || finalAttrs.remarks || ''

  updateForm.district = finalAttrs.district || finalAttrs.District || ''

  updateForm.severity_level =
    finalAttrs.severity_level || finalAttrs.Severity_Level || finalAttrs.status || ''

  updateForm.resolved = finalAttrs.resolved || finalAttrs.Resolved || ''

  updateForm.unresolved = finalAttrs.unresolved || finalAttrs.Unresolved || ''

  const rawDate =
    finalAttrs.datetime_reported ||
    finalAttrs.Datetime_Reported ||
    finalAttrs.created_date ||
    finalAttrs.CreationDate

  if (rawDate) {
    const date = new Date(rawDate)

    if (!Number.isNaN(date.getTime())) {
      updateForm.datetime_date = date.toISOString().slice(0, 10)
      updateForm.datetime_time = date.toTimeString().slice(0, 5)
    }
  }
  updateMode.value = 'edit'
  highlightSelectedIncident(finalGraphic)
}

async function goToBookmark(bookmark) {
  if (!view) return

  await view.goTo({
    center: [bookmark.center.longitude, bookmark.center.latitude],
    zoom: bookmark.zoom,
    rotation: bookmark.rotation || 0,
  })
}

function deleteBookmark(id) {
  bookmarks.value = bookmarks.value.filter((item) => item.id !== id)
  localStorage.setItem('braveBookmarks', JSON.stringify(bookmarks.value))
}

async function exportLocationReport() {
  if (!view || !lastLocationGeometry.value) return

  const originalView = {
    center: view.center.clone(),
    zoom: view.zoom,
    rotation: view.rotation,
  }

  const escapeHtml = (value) =>
    String(value ?? '-')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;')

  const severityBadge = (value) => {
    const label = String(value || '-')
    const className = label.toLowerCase().replace(/\s+/g, '-')
    return `<span class="badge ${className}">${escapeHtml(label)}</span>`
  }

  try {
    let exportGeometry = lastLocationGeometry.value

    if (['point', 'polyline', 'polygon'].includes(lastLocationGeometry.value.type)) {
      exportGeometry = geometryEngine.buffer(
        lastLocationGeometry.value,
        Number(locationRadius.value || 1),
        locationUnit.value,
      )
    }

    await view.goTo(
      {
        target: exportGeometry.extent.expand(1.15),
      },
      {
        duration: 700,
        easing: 'ease-in-out',
      },
    )

    await new Promise((resolve) => setTimeout(resolve, 600))

    const screenshot = await view.takeScreenshot({
      format: 'png',
      quality: 100,
    })

    const incidents = locationToolResults.incidents || []
    const fireStations = locationToolResults.fireStations || []

    const reportWindow = window.open('', '_blank')

    reportWindow.document.write(`
      <!doctype html>
      <html>
        <head>
          <title>Near Me Report</title>

          <style>
            * {
              box-sizing: border-box;
            }

            body {
              margin: 0;
              font-family: Arial, sans-serif;
              background: #eef2f7;
              color: #1f2937;
            }

            .toolbar {
              position: fixed;
              top: 14px;
              right: 14px;
              z-index: 99;
              display: flex;
              gap: 8px;
            }

            .toolbar button {
              border: 0;
              border-radius: 10px;
              padding: 9px 14px;
              font-weight: 700;
              cursor: pointer;
              background: #f6c000;
              color: #111827;
              box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            }

            .toolbar button.secondary {
              background: #e5e7eb;
              color: #374151;
            }

            .report-page {
              max-width: 1180px;
              margin: 28px auto;
              background: #ffffff;
              border-radius: 22px;
              padding: 30px;
              box-shadow: 0 22px 60px rgba(15, 23, 42, 0.14);
            }

            .report-header {
              display: flex;
              justify-content: space-between;
              gap: 20px;
              align-items: flex-start;
              border-bottom: 4px solid #f6c000;
              padding-bottom: 20px;
              margin-bottom: 24px;
            }

            .brand-row {
              display: flex;
              align-items: center;
              gap: 12px;
              margin-bottom: 10px;
            }

            .brand-mark {
              width: 42px;
              height: 42px;
              border-radius: 14px;
              background: linear-gradient(135deg, #f6c000, #ff7a18);
              display: grid;
              place-items: center;
              font-weight: 900;
              color: #111827;
            }

            .report-title h1 {
              margin: 0;
              font-size: 30px;
              color: #111827;
              letter-spacing: -0.03em;
            }

            .report-title p {
              margin: 6px 0 0;
              color: #64748b;
              font-size: 13px;
            }

            .meta-grid {
              display: grid;
              grid-template-columns: repeat(2, minmax(130px, 1fr));
              gap: 12px;
              min-width: 320px;
            }

            .meta-card {
              background: #fff8df;
              border: 1px solid #ffe08a;
              border-radius: 16px;
              padding: 12px 14px;
            }

            .meta-card small {
              display: block;
              color: #64748b;
              font-weight: 800;
              font-size: 11px;
              text-transform: uppercase;
              letter-spacing: 0.05em;
            }

            .meta-card strong {
              display: block;
              margin-top: 5px;
              color: #111827;
              font-size: 14px;
            }

            .summary-grid {
              display: grid;
              grid-template-columns: repeat(3, 1fr);
              gap: 14px;
              margin-bottom: 22px;
            }

            .summary-card {
              border: 1px solid #e5e7eb;
              border-radius: 18px;
              padding: 16px;
              background: #f9fafb;
            }

            .summary-card small {
              display: block;
              color: #64748b;
              font-weight: 800;
              text-transform: uppercase;
              font-size: 11px;
            }

            .summary-card strong {
              display: block;
              margin-top: 6px;
              font-size: 26px;
              color: #111827;
            }

            .map-frame {
              background: #111827;
              border-radius: 22px;
              padding: 12px;
              margin-bottom: 28px;
              box-shadow: inset 0 0 0 1px rgba(255,255,255,0.08);
            }

            .map-img {
              width: 100%;
              max-height: 570px;
              object-fit: contain;
              border-radius: 16px;
              display: block;
              background: #111827;
            }

            .section-title {
              display: flex;
              justify-content: space-between;
              align-items: center;
              gap: 12px;
              margin: 26px 0 12px;
              padding-bottom: 8px;
              border-bottom: 1px solid #e5e7eb;
            }

            .section-title h2 {
              margin: 0;
              font-size: 18px;
              color: #111827;
            }

            .count-pill {
              background: #fff3bf;
              color: #92400e;
              border: 1px solid #fde68a;
              border-radius: 999px;
              padding: 7px 12px;
              font-weight: 800;
              font-size: 12px;
            }

            table {
              width: 100%;
              border-collapse: separate;
              border-spacing: 0;
              overflow: hidden;
              border-radius: 16px;
              font-size: 12px;
              border: 1px solid #e5e7eb;
              background: #ffffff;
            }

            th {
              background: #fff8df;
              color: #374151;
              text-transform: uppercase;
              font-size: 11px;
              letter-spacing: 0.04em;
              padding: 12px;
              border-bottom: 1px solid #ffe08a;
              text-align: left;
            }

            td {
              padding: 12px;
              border-bottom: 1px solid #e5e7eb;
              vertical-align: top;
            }

            tr:last-child td {
              border-bottom: none;
            }

            tbody tr:nth-child(even) {
              background: #f9fafb;
            }

            .badge {
              display: inline-block;
              border-radius: 999px;
              padding: 5px 10px;
              font-weight: 800;
              font-size: 11px;
              white-space: nowrap;
            }

            .badge.low {
              background: #dcfce7;
              color: #15803d;
            }

            .badge.medium {
              background: #fef3c7;
              color: #b45309;
            }

            .badge.high {
              background: #fee2e2;
              color: #b91c1c;
            }

            .badge.critical {
              background: #7f1d1d;
              color: #ffffff;
            }

            .badge.resolved {
              background: #dbeafe;
              color: #1d4ed8;
            }

            .empty-row {
              text-align: center;
              color: #64748b;
              padding: 20px;
            }

            .footer {
              margin-top: 30px;
              padding-top: 16px;
              border-top: 1px solid #e5e7eb;
              display: flex;
              justify-content: space-between;
              color: #64748b;
              font-size: 12px;
            }

            @media print {
              body {
                background: #ffffff;
              }

              .toolbar {
                display: none;
              }

              .report-page {
                margin: 0;
                max-width: none;
                border-radius: 0;
                box-shadow: none;
                padding: 10mm;
              }

              .map-img {
                max-height: 420px;
              }

              .summary-grid {
                grid-template-columns: repeat(3, 1fr);
              }

              table {
                font-size: 10px;
              }

              th,
              td {
                padding: 7px;
              }
            }
          </style>
        </head>

        <body>
          <div class="toolbar">
            <button onclick="window.print()">Print / Save PDF</button>
            <button class="secondary" onclick="window.close()">Close</button>
          </div>

          <main class="report-page">
            <header class="report-header">
              <div class="report-title">
                <div class="brand-row">
                  <div class="brand-mark">B</div>
                  <div>
                    <h1>Near Me Report</h1>
                    <p>Generated location analysis report from BRAVE Fire Dashboard</p>
                  </div>
                </div>
              </div>

              <div class="meta-grid">
                <div class="meta-card">
                  <small>Radius</small>
                  <strong>${escapeHtml(locationRadius.value)} ${escapeHtml(locationUnit.value)}</strong>
                </div>

                <div class="meta-card">
                  <small>Date</small>
                  <strong>${escapeHtml(new Date().toLocaleString())}</strong>
                </div>
              </div>
            </header>

            <section class="summary-grid">
              <div class="summary-card">
                <small>Incidents Found</small>
                <strong>${incidents.length}</strong>
              </div>

              <div class="summary-card">
                <small>Fire Stations Found</small>
                <strong>${fireStations.length}</strong>
              </div>

              <div class="summary-card">
                <small>Analysis Unit</small>
                <strong>${escapeHtml(locationUnit.value)}</strong>
              </div>
            </section>

            <section class="map-frame">
              <img class="map-img" src="${screenshot.dataUrl}" />
            </section>

            <section>
              <div class="section-title">
                <h2>Incident Reported</h2>
                <span class="count-pill">Total count: ${incidents.length}</span>
              </div>

              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Date/Time Reported</th>
                    <th>Category</th>
                    <th>Severity Level</th>
                    <th>District</th>
                    <th>Details</th>
                    <th>Distance</th>
                  </tr>
                </thead>

                <tbody>
                  ${
                    incidents.length
                      ? incidents
                          .map(
                            (incident, i) => `
                              <tr>
                                <td>${i + 1}</td>
                                <td>${escapeHtml(formatDate(incident.attrs.datetime_reported))}</td>
                                <td>${escapeHtml(incident.attrs.categories || '-')}</td>
                                <td>${severityBadge(incident.attrs.severity_level)}</td>
                                <td>${escapeHtml(incident.attrs.district || '-')}</td>
                                <td>${escapeHtml(incident.attrs.more_details || '-')}</td>
                                <td><strong>${escapeHtml(incident.distance || '-')} km</strong></td>
                              </tr>
                            `,
                          )
                          .join('')
                      : `<tr><td colspan="7" class="empty-row">No incidents found within this radius.</td></tr>`
                  }
                </tbody>
              </table>
            </section>

            <section>
              <div class="section-title">
                <h2>Fire Stations</h2>
                <span class="count-pill">Total count: ${fireStations.length}</span>
              </div>

              <table>
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Operation</th>
                    <th>Distance</th>
                  </tr>
                </thead>

                <tbody>
                  ${
                    fireStations.length
                      ? fireStations
                          .map(
                            (station, i) => `
                              <tr>
                                <td>${i + 1}</td>
                                <td><strong>${escapeHtml(station.name || '-')}</strong></td>
                                <td>${escapeHtml(station.attrs.Address || station.attrs.address || station.attrs.Name || '-')}</td>
                                <td>${escapeHtml(station.attrs.Operation || station.attrs.operation || '-')}</td>
                                <td><strong>${escapeHtml(station.distance || '-')} km</strong></td>
                              </tr>
                            `,
                          )
                          .join('')
                      : `<tr><td colspan="5" class="empty-row">No fire stations found within this radius.</td></tr>`
                  }
                </tbody>
              </table>
            </section>

            <footer class="footer">
              <span>BRAVE Fire Dashboard</span>
              <span>Generated by Location Tool</span>
            </footer>
          </main>
        </body>
      </html>
    `)

    reportWindow.document.close()
  } finally {
    await view.goTo(originalView)
  }
}
async function zoomToLocationRadius(geometry) {
  if (!view || !geometry?.extent) return

  try {
    const extent = geometry.extent.clone()

    await view.goTo(
      {
        target: extent.expand(1.15),
      },
      {
        duration: 650,
        easing: 'ease-in-out',
      },
    )
  } catch (error) {
    console.warn('Zoom to radius failed:', error)
  }
}
async function registerArcGISToken() {
  const { data } = await http.get('/api/arcgis/token')

  const serverRoot = 'https://services3.arcgis.com/8YIgE6UDOzHb5gjd'

  esriId.registerToken({
    server: serverRoot,
    token: data.token,
    expires: data.expires,
  })
}

async function selectIncidentsByGeometry(geometry) {
  const incidentLayer = getIncidentLayer()

  if (!incidentLayer?.createQuery) {
    console.warn('Incident layer not found')
    return
  }

  const query = incidentLayer.createQuery()
  query.geometry = geometry
  query.spatialRelationship = 'intersects'
  query.outFields = ['*']
  query.returnGeometry = true

  try {
    const result = await incidentLayer.queryFeatures(query)

    updateResults.value = result.features || []

    if (updateResults.value.length === 0) {
      alert('No incident found in selected area.')
      return
    }

    if (updateResults.value.length === 1) {
      loadIncidentIntoUpdateForm(updateResults.value[0])
    }
  } catch (error) {
    console.error('Failed to select incident:', error)
  }
}

function highlightSelectedIncident(graphic) {
  if (!updateSelectLayer || !graphic?.geometry) return

  updateSelectLayer.removeAll()

  updateSelectLayer.add(
    new Graphic({
      geometry: graphic.geometry,
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color: [255, 0, 0, 0.35],
        size: 34,
        outline: {
          color: [255, 255, 255, 1],
          width: 2,
        },
      },
    }),
  )

  safeGoTo({
    target: graphic.geometry,
    zoom: Math.max(view.zoom, 17),
  })
}
const updateAttachmentFile = ref(null)
const updateAttachmentPreview = ref('')

function handleUpdateAttachment(event) {
  const file = event.target.files?.[0]
  if (!file) return

  if (updateAttachmentPreview.value) {
    URL.revokeObjectURL(updateAttachmentPreview.value)
  }

  updateAttachmentFile.value = file
  updateAttachmentPreview.value = URL.createObjectURL(file)
}

async function safeGoTo(target) {
  if (!view) return

  try {
    await view.goTo(target)
  } catch (error) {
    if (error?.name === 'AbortError' || error?.name === 'view:goto-interrupted') {
      return
    }

    console.error(error)
  }
}

async function selectIncidentByPoint(event) {
  const hit = await view.hitTest(event)

  const incidentHit = hit.results.find((result) => {
    const title = result.graphic?.layer?.title?.toLowerCase() || ''
    return title.includes('incident')
  })

  if (!incidentHit) {
    showToast('Not Found', 'No incident found. Click directly on the incident icon.', 'danger')
    return
  }

  await loadIncidentIntoUpdateForm(incidentHit.graphic)
}

const updateMode = ref('main') // main | create | edit
const createLocationSelecting = ref(false)

const createForm = reactive({
  datetime_date: '',
  datetime_time: '',
  categories: '',
  other_categories: '',
  more_details: '',
  district: '',
  severity_level: '',
  latitude: '',
  longitude: '',
})
function startCreateIncident() {
  updateMode.value = 'create'

  resetCreateForm()

  const now = new Date()

  createForm.datetime_date = now.toISOString().slice(0, 10)
  createForm.datetime_time = now.toTimeString().slice(0, 5)

  clearUpdateSelection()
}

function startPickCreateLocation() {
  createLocationSelecting.value = true
  updateSelectionMode.value = null

  showToast('Pick Location', 'Click on the map to pin incident location.', 'success')
}

function highlightCreateLocation(point) {
  updateSelectLayer.removeAll()

  updateSelectLayer.add(
    new Graphic({
      geometry: point,
      symbol: {
        type: 'simple-marker',
        style: 'circle',
        color: [255, 0, 0, 0.55],
        size: 22,
        outline: {
          color: [255, 255, 255, 1],
          width: 3,
        },
      },
    }),
  )
}

async function createIncidentFromPanel() {
  if (!createForm.categories || !createForm.district || !createForm.severity_level) {
    showToast('Missing Info', 'Please fill in category, district, and severity.', 'danger')
    return
  }

  if (!createForm.latitude || !createForm.longitude) {
    showToast('Missing Location', 'Please pin the incident location first.', 'danger')
    return
  }

  try {
    await http.post('/api/fire-incidents', {
      attributes: {
        district: createForm.district,
        categories: createForm.categories,
        other_categories: createForm.other_categories,
        severity_level: createForm.severity_level,
        more_details: createForm.more_details || '-',
        datetime_reported: new Date(
          `${createForm.datetime_date}T${createForm.datetime_time || '00:00'}:00`,
        ).getTime(),
      },
      geometry: {
        x: Number(createForm.longitude),
        y: Number(createForm.latitude),
        spatialReference: { wkid: 4326 },
      },
    })

    await refreshLiveData()
    updateMode.value = 'select'
    updateSelectLayer.removeAll()

    showToast('Success', 'Incident created successfully.', 'success')
  } catch (error) {
    console.error(error)
    showToast('Create Failed', error?.message || 'Failed to create incident.', 'danger')
  }
}

const updateLayerType = ref('incident')
function changeUpdateLayerType(type) {
  updateLayerType.value = type
  clearUpdateSelection()
}

function resetCreateForm() {
  createForm.datetime_date = ''
  createForm.datetime_time = ''
  createForm.categories = ''
  createForm.other_categories = ''
  createForm.more_details = ''
  createForm.district = ''
  createForm.severity_level = ''
  createForm.latitude = ''
  createForm.longitude = ''
}

function backToUpdateMain() {
  updateMode.value = 'main'
  createLocationSelecting.value = false
  resetCreateForm()
  resetUpdateForm()
  updateResults.value = []
  updateSelectLayer?.removeAll()
}

function openCreateIncident() {
  updateMode.value = 'create'
  resetUpdateForm()
  resetCreateForm()

  updateResults.value = []
  updateSelectionMode.value = null
  updateSelectLayer?.removeAll()

  const now = new Date()
  createForm.datetime_date = now.toISOString().slice(0, 10)
  createForm.datetime_time = now.toTimeString().slice(0, 5)
}

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

function optimizeFeatureLayers() {
  if (!webmap) return

  webmap.allLayers.toArray().forEach((layer) => {
    if (layer.type !== 'feature') return

    layer.refreshInterval = 0

    if ('popupEnabled' in layer) {
      layer.popupEnabled = false
    }

    if ('popupTemplate' in layer) {
      layer.popupTemplate = null
    }
  })
}

function closeOtherMapTools(except = '') {
  if (except !== 'location' && showLocationTool.value) {
    clearLocationTool()
    showLocationTool.value = false
  }

  if (except !== 'directions' && showDirectionsTool.value) {
    clearRouteTool()
    showDirectionsTool.value = false
  }

  if (except !== 'update' && showUpdateReportTool.value) {
    resetUpdateReportPanel()
    showUpdateReportTool.value = false
  }

  if (except !== 'public-report' && showPublicReportTool.value) {
    resetPublicReport()
    showPublicReportTool.value = false
  }

  if (except !== 'bookmark' && showBookmarkTool.value) {
    showBookmarkTool.value = false
  }
}

function toggleLocationTool() {
  if (showLocationTool.value) {
    clearLocationTool()
    showLocationTool.value = false
    resizeMapView()
    return
  }

  closeOtherMapTools('location')

  showLocationTool.value = true
  resetRouteStateOnly()
  clearArcgisTool()
}

function toggleDirectionsTool() {
  if (showDirectionsTool.value) {
    clearRouteTool()
    showDirectionsTool.value = false
    return
  }

  closeOtherMapTools('directions')

  showDirectionsTool.value = true
  routeState.error = ''
  clearArcgisTool()
}

function resetPublicReport() {
  createLocationSelecting.value = false
  publicReportAcknowledged.value = false
  resetCreateForm()
  updateSelectLayer?.removeAll()
}

function togglePublicReportTool() {
  if (showPublicReportTool.value) {
    resetPublicReport()
    showPublicReportTool.value = false
    return
  }

  closeOtherMapTools('public-report')

  resetPublicReport()

  const now = new Date()
  createForm.datetime_date = now.toISOString().slice(0, 10)
  createForm.datetime_time = now.toTimeString().slice(0, 5)

  showPublicReportTool.value = true
  showAnalyticsPanel.value = false
  showWeatherOverlay.value = false
  activePanel.value = null
}

function useCurrentPublicLocation() {
  if (!navigator.geolocation) {
    showToast('Location Unavailable', 'This browser does not support location detection.', 'danger')
    return
  }

  showToast('Finding Location', 'Allow location access when your browser asks.', 'success')

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const latitude = Number(position.coords.latitude.toFixed(6))
      const longitude = Number(position.coords.longitude.toFixed(6))
      const point = new Point({
        latitude,
        longitude,
        spatialReference: { wkid: 4326 },
      })

      createForm.latitude = latitude
      createForm.longitude = longitude
      createLocationSelecting.value = false
      highlightCreateLocation(point)

      safeGoTo({ target: point, zoom: 17 })
      showToast('Location Selected', 'Your current location has been added.', 'success')
    },
    (error) => {
      const message =
        error.code === error.PERMISSION_DENIED
          ? 'Location permission was denied. You can still pin the incident on the map.'
          : 'Your location could not be detected. Please pin it on the map.'

      showToast('Location Unavailable', message, 'danger')
    },
    {
      enableHighAccuracy: true,
      timeout: 12000,
      maximumAge: 30000,
    },
  )
}

async function submitPublicReport() {
  if (publicReportSubmitting.value) return

  if (
    !createForm.categories ||
    !createForm.district ||
    !createForm.severity_level ||
    !createForm.more_details
  ) {
    showToast('Missing Information', 'Please complete all required report fields.', 'danger')
    return
  }

  if (!createForm.latitude || !createForm.longitude) {
    showToast('Missing Location', 'Use your location or pin the incident on the map.', 'danger')
    return
  }

  if (!publicReportAcknowledged.value) {
    showToast('Confirmation Required', 'Please confirm that the report is accurate.', 'danger')
    return
  }

  publicReportSubmitting.value = true

  try {
    await http.post('/api/fire-incidents', {
      attributes: {
        district: createForm.district,
        categories: createForm.categories,
        other_categories: 'Public report - pending verification',
        severity_level: createForm.severity_level,
        more_details: createForm.more_details,
        datetime_reported: Date.now(),
      },
      geometry: {
        x: Number(createForm.longitude),
        y: Number(createForm.latitude),
        spatialReference: { wkid: 4326 },
      },
    })

    showToast(
      'Report Submitted',
      'Thank you. Your report has been sent for verification.',
      'success',
    )

    showPublicReportTool.value = false
    resetPublicReport()
    await refreshLiveData({ checkNewIncidents: false })
  } catch (error) {
    console.error('Public fire report failed:', error)

    const message =
      error?.response?.data?.message ||
      error?.response?.data?.error ||
      error?.message ||
      'The report could not be submitted. Please try again.'

    showToast('Submission Failed', message, 'danger')
  } finally {
    publicReportSubmitting.value = false
  }
}

async function toggleUpdateReportTool() {
  if (showUpdateReportTool.value) {
    resetUpdateReportPanel()
    showUpdateReportTool.value = false
    return
  }

  closeOtherMapTools('update')

  showUpdateReportTool.value = true
  showAnalyticsPanel.value = false
  showWeatherOverlay.value = false
  activePanel.value = null

  resetUpdateReportPanel()

  await registerArcGISToken()
  await nextTick()
}

function toggleBookmarkTool() {
  if (showBookmarkTool.value) {
    showBookmarkTool.value = false
    return
  }

  closeOtherMapTools('bookmark')

  showBookmarkTool.value = true
}
function formatActivityDate(value) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = String(date.getFullYear()).slice(-2)

  let hours = date.getHours()
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const seconds = String(date.getSeconds()).padStart(2, '0')

  const ampm = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12 || 12

  return `${day}/${month}/${year}, ${hours}:${minutes}:${seconds} ${ampm}`
}
function formatDisplayDate(value) {
  if (!value) return ''

  const date = new Date(value)

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = String(date.getFullYear()).slice(-2)

  return `${day}/${month}/${year}`
}

function openCreateFireStation() {
  updateMode.value = 'create-station'

  createLocationSelecting.value = false

  createStationForm.name = ''
  createStationForm.address = ''
  createStationForm.contact = ''
  createStationForm.mukim = ''
  createStationForm.district = ''
  createStationForm.operation = ''
  createStationForm.num_firefighters = ''
  createStationForm.num_engines = ''
  createStationForm.num_vehicles = ''
  createStationForm.latitude = ''
  createStationForm.longitude = ''
}

const createStationForm = reactive({
  name: '',
  address: '',
  contact: '',
  mukim: '',
  district: '',
  operation: '',
  num_firefighters: '',
  num_engines: '',
  num_vehicles: '',
  latitude: '',
  longitude: '',
})

const showUpdateEmptyState = computed(() => {
  return updateMode.value === 'main' && !updateForm.objectId && updateResults.value.length === 0
})

async function toggleFireStationList() {
  showFireStationList.value = !showFireStationList.value

  if (showFireStationList.value && fireStationList.value.length === 0) {
    await loadFireStationList()
  }
}

async function loadFireStationList() {
  const layer = findLayerByTitleIncludes('fire station') || findLayerByTitleIncludes('bfrd')

  const features = await getFeatures(layer, {
    limit: 100,
    where: '1=1',
  })

  fireStationList.value = features.map((feature) => {
    const attrs = feature.attributes || {}

    return {
      id: getAttr(attrs, ['OBJECTID', 'objectid', 'ObjectId', 'FID']),
      name: getResourceName(attrs, 'Fire Station'),
      address: getAttr(attrs, ['Address', 'address', 'Name', 'name'], '-'),
      graphic: feature,
    }
  })
}

async function goToFireStation(station) {
  if (!view || !station.graphic?.geometry) return

  await view.goTo({
    target: station.graphic.geometry,
    zoom: 15,
  })

  view.openPopup({
    features: [station.graphic],
    location: station.graphic.geometry,
  })
}
//  tag sini =========================
// LIFECYCLE
// =========================
onMounted(() => {
  setDefaultActivityDateRange()

  loadWebMap()
})

onBeforeUnmount(() => {
  if (updateAttachmentPreview.value) {
    URL.revokeObjectURL(updateAttachmentPreview.value)
  }

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
  activityFilters.from = ''
  activityFilters.to = ''
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
  await refreshLiveData({ checkNewIncidents: false })
}

async function resetActivityFilters() {
  activityFilters.district = ''
  await refreshLiveData({ checkNewIncidents: false })
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
  height: 100vh !important;
  height: 100dvh !important;
  overflow: hidden !important;
  max-height: 100dvh;
  border-radius: 0;
  margin: 0;
  background: #0f172a;
  display: flex;
  flex-direction: column;
}

.map-card.full-map-mode .card-header-custom {
  height: 65px;
  min-height: 65px;
  flex-shrink: 0;
  position: relative;
  z-index: 200;
  background: var(--phoenix-card-bg);
}

.map-card.full-map-mode .map-area {
  position: relative;
  height: calc(100vh - 65px) !important;
  height: calc(100dvh - 65px) !important;
  max-height: calc(100dvh - 65px) !important;
  overflow: hidden !important;
  flex: 1;
  min-height: 0;
  z-index: 1;
}

.map-card.full-map-mode .fire-map {
  height: 100%;
  max-height: 100%;
}
.map-card.full-map-mode .location-tool-panel,
.map-card.full-map-mode .directions-tool-panel,
.map-card.full-map-mode .district-floating-panel,
.map-card.full-map-mode .map-floating-panel {
  z-index: 80;
}

.map-card.full-map-mode .fire-map,
.map-card.full-map-mode .esri-view,
.map-card.full-map-mode .esri-view-root {
  height: 100% !important;
  max-height: 100% !important;
  overflow: hidden !important;
}

.map-card.full-map-mode :deep(.esri-attribution) {
  bottom: 0 !important;
}
.map-card.full-map-mode .directions-tool-panel {
  top: 5rem;
  bottom: 5.8rem;
  max-height: none;
}

.map-card.full-map-mode .directions-body {
  min-height: 0;
  overflow-y: auto;
  padding-bottom: 1.5rem;
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
  color: #ff051e;
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

.weather-dropdown-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 999px;
  font-weight: 700;
  box-shadow: 0 0.35rem 1rem rgba(15, 23, 42, 0.12);
}

.weather-dropdown-menu {
  min-width: 240px;
  padding: 0.75rem;
  border: none;
  border-radius: 20px;

  background: rgba(15, 23, 42, 0.96);
  backdrop-filter: blur(18px);

  box-shadow:
    0 20px 40px rgba(15, 23, 42, 0.25),
    0 0 0 1px rgba(255, 255, 255, 0.05);

  overflow: hidden;
  position: absolute;
  top: 0;
  left: 0;
  z-index: 1000;
}

.weather-dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;

  width: 100%;
  padding: 0.9rem 1rem;

  border: none;
  border-radius: 14px;

  background: transparent;

  color: #e2e8f0;
  font-weight: 600;

  transition: all 0.2s ease;
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
  z-index: 99999;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  overflow: visible;
}

.weather-dropdown-item:hover {
  background: rgba(246, 192, 0, 0.12);
  color: #facc15;
  transform: translateX(4px);
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
  height: 100dvh !important;
  z-index: 9999;
  border-radius: 0 !important;
  margin: 0 !important;
  padding: 0 !important;
  background: #000;
  display: flex !important;
  flex-direction: column !important;
}

.dispatcher-map-mode .map-area {
  width: 100% !important;
  height: calc(100dvh - 65px) !important;
  min-height: 0 !important;
  flex: 1 !important;
  border-radius: 0 !important;
  overflow: hidden !important;
}

.dispatcher-map-mode .fire-map,
.dispatcher-map-mode .weather-map-overlay {
  width: 100% !important;
  height: 100% !important;
  min-height: 0 !important;
  inset: 0 !important;
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

.filter-inline-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  width: 100%;
}

.filter-inline-row .filter-field {
  flex: 1;
  min-width: 0;
}

.filter-inline-row .filter-field label {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  margin-bottom: 0.45rem;
  color: var(--phoenix-body-color);
}

.filter-inline-row .filter-field select {
  width: 100%;
  min-width: 0;
  height: 44px;
  border: 0;
  border-radius: 14px;
  background: #f1f5f9;
  padding: 0 0.85rem;
  font-weight: 500;
  font-size: 0.9rem;
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
  flex: 0 0 48px;
  width: 48px;
  height: 54px;
  border: 0;
  border-radius: 14px;
  background: #f5c400;
  color: #1f2a44;
  display: flex;
  align-items: center;
  justify-content: center;
}

.activity-reset-btn {
  flex: 0 0 auto;
  height: 44px;
  padding: 0 0.9rem;
  border: 0;
  border-radius: 14px;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-secondary-color);
  font-weight: 600;
  white-space: nowrap;
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
  display: block !important;
  margin-top: 1rem;
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

.incident-drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  z-index: 9999;
  display: flex;
  justify-content: flex-end;
}

.incident-drawer {
  width: 560px;
  max-width: 95vw;
  height: 100%;
  background: #fff;
  box-shadow: -12px 0 30px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
}

.incident-drawer-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.incident-drawer-header h5 {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-weight: 700;
}

.incident-drawer-body {
  padding: 1.25rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.incident-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
}

.info-item {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  border: 1px solid #e5e7eb;
}

.info-item span {
  font-size: 0.78rem;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.info-item strong {
  font-size: 1rem;
  color: #1e293b;
  font-weight: 700;
}

.drawer-section {
  margin-top: 1.5rem;
}

.drawer-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.drawer-actions .btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}
.drawer-close-btn {
  border: none;
  background: transparent;
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
  transition: all 0.25s ease;
}

.drawer-slide-enter-from,
.drawer-slide-leave-to {
  opacity: 0;
}

.drawer-slide-enter-from .incident-drawer,
.drawer-slide-leave-to .incident-drawer {
  transform: translateX(100%);
}

.severity-critical,
.severity-high,
.severity-medium,
.severity-low {
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  width: fit-content;
}
.severity-critical {
  background: rgba(220, 38, 38, 0.12);
  color: #dc2626;
}

.severity-high {
  background: rgba(249, 115, 22, 0.12);
  color: #f97316;
}

.severity-medium {
  background: rgba(234, 179, 8, 0.12);
  color: #ca8a04;
}

.severity-low {
  background: rgba(34, 197, 94, 0.12);
  color: #16a34a;
}

.remarks-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 14px;
  line-height: 1.6;
  color: #334155;
}

.incident-status-banner {
  padding: 0.85rem 1rem;
  border-radius: 14px;
  font-weight: 700;
  margin-bottom: 1rem;
}
.drawer-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.drawer-section-header small {
  color: #64748b;
  font-size: 0.78rem;
}

.resource-loading {
  padding: 1rem;
  border-radius: 14px;
  background: #f8fafc;
  color: #64748b;
  font-size: 0.9rem;
}

.nearby-resource-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.nearby-resource-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.9rem 1rem;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
}

.nearby-resource-card strong {
  display: block;
  color: #1e293b;
  font-weight: 700;
}

.nearby-resource-card span {
  display: block;
  color: #64748b;
  font-size: 0.8rem;
}

.resource-distance {
  font-weight: 800;
  color: #f59e0b;
  white-space: nowrap;
}

.nearby-resource-card {
  border-left: 5px solid transparent;
}

.nearby-resource-card.resource-near {
  border-left-color: #22c55e;
}

.nearby-resource-card.resource-near .resource-distance {
  color: #16a34a;
}

.nearby-resource-card.resource-medium {
  border-left-color: #f59e0b;
}

.nearby-resource-card.resource-medium .resource-distance {
  color: #f59e0b;
}

.nearby-resource-card.resource-far {
  border-left-color: #ef4444;
}

.nearby-resource-card.resource-far .resource-distance {
  color: #dc2626;
}

.nearby-resource-card.resource-unknown {
  border-left-color: #94a3b8;
}

.nearby-resource-card.resource-unknown .resource-distance {
  color: #64748b;
}

.map-tool-dock {
  position: absolute;
  left: 1rem;
  bottom: 1.25rem;
  z-index: 35;
  display: flex;
  gap: 0.75rem;
}

.map-card.full-map-mode .map-tool-dock {
  bottom: 1.25rem;
  left: 1rem;
}

.map-tool-btn {
  width: 42px;
  height: 42px;
  border: none;
  border-radius: 50%;
  background: #ffffff;
  color: #1e293b;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.18);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.map-tool-btn:hover {
  transform: translateY(-2px);
  background: #ffc107;
}

.location-tool-panel {
  position: absolute;
  left: 1rem;
  bottom: 5.5rem;
  z-index: 60;
  width: 380px;
  max-height: 560px;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 22px 55px rgba(15, 23, 42, 0.22);
  overflow: hidden;
}

/* full map */
.map-card.full-map-mode .location-tool-panel {
  bottom: 5.8rem;
  z-index: 80;
}

.location-tool-header {
  padding: 1rem;
  background: linear-gradient(180deg, #e3b61a 0%, #d4a900 100%);
  color: #fff;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.location-tool-header strong {
  font-size: 0.95rem;
  font-weight: 800;
}

.location-tool-header button {
  border: none;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  width: 32px;
  height: 32px;
  border-radius: 10px;
}

.location-tool-body {
  padding: 1rem;
  max-height: 455px;
  overflow-y: auto;
}

.location-tool-actions {
  display: flex;
  gap: 0.65rem;
  margin-bottom: 0.85rem;
}

.location-tool-actions button {
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 14px;
  background: #f1f5f9;
  color: #1e293b;
}

.location-tool-actions button.active,
.location-tool-actions button:hover {
  background: #ffc107;
  color: #0f172a;
}

.location-radius-controls {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.location-radius-controls input,
.location-radius-controls select {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.55rem 0.7rem;
  background: #f8fafc;
  color: #1e293b;
  font-weight: 600;
}

.location-results {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.location-results-header {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 0.85rem;
}

.location-results-header strong {
  display: block;
  color: #1e293b;
  font-weight: 800;
}

.location-results-header span {
  display: block;
  color: #64748b;
  font-size: 0.78rem;
  margin-top: 0.2rem;
}

.result-group {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.result-group-title {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: #334155;
  font-weight: 800;
  font-size: 0.85rem;
}

.result-group-title strong {
  margin-left: auto;
  background: #eef2ff;
  color: #2563eb;
  border-radius: 999px;
  padding: 0.15rem 0.55rem;
  font-size: 0.75rem;
}

.resource-result-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-left: 5px solid #f59e0b;
  border-radius: 16px;
  padding: 0.85rem;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
}

.resource-result-card.worker {
  border-left-color: #22c55e;
}

.resource-result-card.station {
  border-left-color: #f97316;
}

.resource-result-card.sensor {
  border-left-color: #06b6d4;
}

.resource-icon {
  width: 38px;
  height: 38px;
  border-radius: 13px;
  background: #f1f5f9;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.resource-content strong {
  display: block;
  color: #1e293b;
  font-size: 0.88rem;
  font-weight: 800;
}

.resource-content span {
  display: block;
  color: #64748b;
  font-size: 0.75rem;
}

.resource-result-card.incident {
  border-left-color: #ef4444;
}

.resource-result-card.incident .resource-icon {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}
.arc-result-group-header {
  margin-top: 1rem;
  padding: 0.85rem 1rem;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.arc-result-card {
  margin-top: 0.75rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.arc-distance-row {
  padding: 0.85rem 1rem;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
}

.arc-card-title {
  padding: 0.85rem 1rem;
  font-weight: 800;
}

.arc-card-details {
  margin: 0 1rem 1rem;
  padding: 0.9rem 1rem;
  border-left: 4px solid #ef4444;
  background: #f8fafc;
  border-radius: 14px;
}

.arc-card-details p {
  margin: 0 0 0.65rem;
  display: grid;
  grid-template-columns: 115px 1fr;
  gap: 0.4rem;
}

.arc-card-details p:last-child {
  margin-bottom: 0;
}

.arc-card-details span {
  color: #64748b;
}

.arc-card-details strong {
  color: #1e293b;
}

.arc-result-card.incident .arc-card-details {
  border-left-color: #ef4444;
}

.arc-result-card.station .arc-card-details {
  border-left-color: #84cc16;
}

.directions-tool-panel {
  position: absolute;
  left: 1rem;
  top: 5.5rem;
  bottom: 5.8rem;
  z-index: 61;
  width: 360px;
  background: #fff;
  border-radius: 22px;
  box-shadow: 0 22px 55px rgba(15, 23, 42, 0.24);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.directions-body {
  padding: 1rem;
  padding-bottom: 1.5rem;
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  gap: 14px;
  box-sizing: border-box;
}

.directions-body > * {
  min-width: 0;
}

.directions-header {
  height: 64px;
  padding: 0 1rem;
  background: linear-gradient(180deg, #e3b61a 0%, #d4a900 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.directions-header strong {
  font-size: 0.95rem;
  font-weight: 800;
}

.directions-header button {
  border: 0;
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
  width: 34px;
  height: 34px;
  border-radius: 12px;
}

.route-pick-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.route-pick-buttons button {
  border: 0;
  border-radius: 14px;
  padding: 13px 10px;
  background: #f1f5f9;
  font-weight: 800;
  color: #0f172a;
}

.route-pick-buttons button.active {
  background: #ffc107;
}

.route-summary-card,
.route-options-card,
.route-result-card {
  border: 1px solid #dbe3ef;
  background: #f8fafc;
  border-radius: 16px;
  padding: 14px;
}

.route-summary-card,
.route-result-card {
  display: grid;
  gap: 12px;
}

.route-summary-card div,
.route-result-card div {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.route-options-card {
  display: grid;
  gap: 12px;
}

.route-field {
  display: grid;
  gap: 6px;
}

.route-field label,
.route-toggle-row span {
  font-size: 0.82rem;
  font-weight: 800;
  color: #475569;
}

.route-field select,
.route-date-row input {
  width: 100%;
  height: 43px;
  border: 1px solid #dbe3ef;
  border-radius: 11px;
  padding: 0 12px;
  background: #fff;
  color: #334155;
  font-weight: 800;
}

.route-toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.route-add-stop-btn,
.route-calculate-btn {
  border: 0;
  border-radius: 14px;
  height: 48px;
  font-weight: 900;
}

.route-add-stop-btn {
  background: #fff3cd;
  color: #92400e;
}

.route-calculate-btn:disabled {
  opacity: 0.55;
}

.directions-body::-webkit-scrollbar {
  width: 6px;
}

.directions-body::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

.route-pick-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.route-pick-buttons button,
.route-calculate-btn {
  border: none;
  border-radius: 14px;
  padding: 0.8rem;
  font-weight: 800;
}

.route-pick-buttons button.active {
  background: #ffc107;
}

.route-summary-card,
.route-result-card {
  margin-top: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 1rem;
}

.route-summary-card div,
.route-result-card div {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.6rem;
}

.route-calculate-btn {
  width: 100%;
  margin-top: 1rem;
  background: #ffc107;
}

.route-calculate-btn:disabled {
  opacity: 0.5;
}

.route-error {
  margin-top: 1rem;
  padding: 0.8rem;
  border-radius: 14px;
  background: #fee2e2;
  color: #991b1b;
  font-weight: 700;
}

.route-options-card {
  margin-top: 14px;
  padding: 14px;
  border: 1px solid rgba(203, 213, 225, 0.9);
  border-radius: 16px;
  background: #f8fafc;
  display: grid;
  gap: 10px;
}

.route-field {
  display: grid;
  gap: 5px;
}

.route-field label,
.route-toggle-row span {
  font-size: 0.82rem;
  font-weight: 700;
  color: #475569;
}

.route-field select,
.route-date-row input {
  width: 100%;
  border: 1px solid #dbe3ef;
  border-radius: 10px;
  padding: 9px 11px;
  font-weight: 700;
  color: #334155;
  background: #fff;
}

.route-date-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.route-toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.route-switch {
  width: 42px;
  height: 24px;
  border: 0;
  border-radius: 999px;
  background: #cbd5e1;
  padding: 3px;
}

.route-switch span {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s ease;
}

.route-switch.active {
  background: #ffc107;
}

.route-switch.active span {
  transform: translateX(18px);
}

.route-add-stop-btn {
  border: 0;
  border-radius: 12px;
  padding: 10px 12px;
  background: #fff3cd;
  color: #92400e;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.route-stop-list {
  display: grid;
  gap: 8px;
}

.route-stop-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 8px 10px;
  font-weight: 700;
}

.route-stop-item button {
  border: 0;
  background: #fee2e2;
  color: #b91c1c;
  border-radius: 8px;
  padding: 5px 7px;
}

.fire-page.dispatcher-map-mode .map-card.full-map-mode .card-header-custom,
.map-card.full-map-mode .card-header-custom {
  display: flex !important;
  visibility: visible !important;
  opacity: 1 !important;
  position: relative !important;
  z-index: 99999 !important;
  height: 65px !important;
  min-height: 65px !important;
  flex-shrink: 0 !important;
}

.map-card.full-map-mode .map-area {
  height: calc(100dvh - 65px) !important;
  max-height: calc(100dvh - 65px) !important;
  flex: 1 1 auto !important;
  margin-top: 0 !important;
}

.dispatcher-map-mode .card-header-custom {
  display: flex !important;
  height: 65px !important;
  min-height: 65px !important;
  flex-shrink: 0 !important;
  z-index: 99999 !important;
  background: var(--phoenix-card-bg);
}
.update-report-panel {
  position: absolute;
  left: 1rem;
  top: 1rem;
  bottom: 1rem; /* NEW */
  z-index: 80;

  width: 410px;
  max-width: calc(100% - 2rem);

  background: #fff;
  border-radius: 22px;
  box-shadow: 0 22px 55px rgba(15, 23, 42, 0.28);
  overflow: hidden;

  display: flex;
  flex-direction: column;
}

.map-card.full-map-mode .update-report-panel {
  top: 16.8rem;
  bottom: 5.8rem;
  max-height: none;
}

.update-report-editor {
  flex: 1;
  min-height: 0;
  height: 100%;
  overflow-y: auto;
  background: #fff;
}

.update-report-header {
  height: 58px;
  padding: 0 1.1rem;
  background: linear-gradient(135deg, #d4a900, #e8bf10);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.update-report-header strong {
  font-size: 0.95rem;
  font-weight: 900;
}

.update-report-header button {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

.update-report-editor {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

/* ArcGIS Editor Phoenix polish */
.update-report-panel :deep(.esri-editor) {
  width: 100%;
  height: 100%;
  background: #fff;
  color: #334155;
}

.update-report-panel :deep(.esri-editor__header) {
  display: none;
}

.update-report-panel :deep(.esri-editor__content) {
  background: #fff;
}

.update-report-panel :deep(.esri-editor__scroller) {
  max-height: none;
}

.update-report-panel :deep(.esri-widget),
.update-report-panel :deep(.esri-editor),
.update-report-panel :deep(.esri-editor__content) {
  font-family: inherit;
}

.update-report-panel :deep(.esri-editor__panel-content) {
  background: #f8fafc;
}

.update-report-panel :deep(.esri-button),
.update-report-panel :deep(.esri-editor__feature-list-item) {
  border-radius: 12px;
}

.update-report-panel :deep(.esri-button--secondary) {
  border-color: #dbe3ef;
}

.update-report-panel :deep(.esri-button--primary) {
  background: #ffc107;
  border-color: #ffc107;
  color: #111827;
  font-weight: 800;
}
.bookmark-tool-panel {
  position: absolute;
  left: 1rem;
  bottom: 6.4rem;
  z-index: 75;

  width: 360px;
  max-height: 500px;

  background: #ffffff;
  border-radius: 22px;
  box-shadow:
    0 20px 45px rgba(15, 23, 42, 0.16),
    0 6px 16px rgba(15, 23, 42, 0.08);

  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* HEADER */
.bookmark-header {
  padding: 1rem 1.2rem;
  background: linear-gradient(180deg, #e3b61a 0%, #d4a900 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bookmark-header strong {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.2px;
}

.bookmark-header button {
  width: 38px;
  height: 38px;
  border: none;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.18);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: 0.2s ease;
}

.bookmark-header button:hover {
  background: rgba(255, 255, 255, 0.28);
}

/* BODY */
.bookmark-body {
  padding: 1rem;
  overflow-y: auto;
  max-height: 430px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: #f8fafc;
}

.bookmark-body::-webkit-scrollbar {
  width: 6px;
}

.bookmark-body::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

/* ADD CARD */
.add-bookmark-btn {
  border: 2px dashed #cbd5e1;
  border-radius: 18px;
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  padding: 1.3rem;
  cursor: pointer;

  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;

  transition: all 0.2s ease;
}

.add-bookmark-btn:hover {
  border-color: #f59e0b;
  background: #fff7ed;
  transform: translateY(-2px);
}

.add-bookmark-btn svg {
  width: 24px;
  height: 24px;
  color: #f59e0b;
}

.add-bookmark-btn span {
  font-weight: 700;
  color: #475569;
  font-size: 1rem;
}

/* BOOKMARK CARD */
.bookmark-card {
  display: flex;
  align-items: center;
  gap: 14px;

  padding: 12px;
  border-radius: 18px;
  border: 1px solid #dbe3ef;
  background: #ffffff;

  cursor: pointer;
  transition: all 0.2s ease;
}

.bookmark-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

/* THUMBNAIL */
.bookmark-shot {
  width: 92px;
  height: 62px;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid #dbe3ef;
  flex-shrink: 0;
}

/* INFO */
.bookmark-info {
  flex: 1;
  min-width: 0;
}

.bookmark-info strong {
  display: block;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.bookmark-info small {
  font-size: 0.85rem;
  color: #64748b;
}

/* DELETE */
.bookmark-delete {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  border: none;
  background: #fee2e2;
  color: #dc2626;

  display: flex;
  align-items: center;
  justify-content: center;

  cursor: pointer;
  flex-shrink: 0;
  transition: 0.2s ease;
}

.bookmark-delete:hover {
  background: #fecaca;
  transform: scale(1.05);
}

.activity-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.activity-status-pill {
  font-size: 0.65rem;
  font-weight: 900;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  white-space: nowrap;
}

.activity-status-pill.ongoing {
  background: rgba(245, 158, 11, 0.16);
  color: #b45309;
}

.activity-status-pill.resolved {
  background: rgba(34, 197, 94, 0.16);
  color: #15803d;
}

.location-tool-header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.custom-update-panel {
  width: 360px;
  max-height: 520px;
  overflow: hidden;
}

.custom-update-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 1rem;
}

.update-tools-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.update-tools-row button {
  border: 1px solid transparent;
  border-radius: 0.75rem;
  padding: 0.7rem 0.5rem;
  background: transparent;
  color: #334155;
  font-weight: 800;
}

.update-tools-row button.active {
  background: linear-gradient(135deg, #d4a900, #c89b00);
  color: #fff;
  box-shadow: 0 10px 22px rgba(212, 169, 0, 0.28);
}
.custom-update-body label {
  display: block;
  margin-top: 1rem;
  margin-bottom: 0.4rem;

  font-size: 0.85rem;
  font-weight: 800;

  color: #334155;
}

.custom-update-body select,
.custom-update-body textarea {
  width: 100%;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 0.75rem;
  padding: 0.65rem 0.75rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  outline: none;
}

.update-empty-state {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  color: var(--phoenix-secondary-color);
  text-align: center;
}
.update-result-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.update-result-list button {
  text-align: left;
  border: 1px solid var(--phoenix-border-color);
  background: var(--phoenix-emphasis-bg);
  border-radius: 0.75rem;
  padding: 0.7rem;
}
.update-empty-state svg {
  width: 36px;
  height: 36px;
  color: #d4a900;
}

.selected-incident-card {
  display: flex;
  justify-content: space-between;
  align-items: center;

  padding: 1rem 1.2rem;
  margin-bottom: 1.25rem;

  background: linear-gradient(135deg, rgba(212, 169, 0, 0.08), rgba(212, 169, 0, 0.16));

  border: 1px solid rgba(212, 169, 0, 0.35);
  border-radius: 1rem;
}

.selected-incident-card span {
  font-size: 1rem;
  font-weight: 800;
}

.selected-incident-card strong {
  background: #d4a900;
  color: white;

  padding: 0.35rem 0.75rem;
  border-radius: 999px;

  font-size: 0.8rem;
}
.attachment-empty {
  text-align: center;
  padding: 1.5rem;
  color: var(--phoenix-secondary-color);
}

.update-action-row {
  position: sticky;
  bottom: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  background: #f8fafc;
  padding: 1rem 0 0.25rem;
  margin-top: 1.25rem;
}

.update-btn,
.delete-btn {
  width: 100%;
  height: 52px;
  border-radius: 1rem;
  font-weight: 900;
  font-size: 0.95rem;
}

.update-btn {
  border: none;
  background: linear-gradient(135deg, #22b8f0, #0284c7);
  color: #fff;
}

.delete-btn {
  border: 1.5px solid #ef4444;
  background: #fff;
  color: #ef4444;
}
.save-update-btn {
  margin-top: 0.5rem;
  border: none;
  border-radius: 999px;
  padding: 0.75rem 1rem;
  background: linear-gradient(135deg, #d4a900, #b88900);
  color: #fff;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.save-update-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.custom-update-body input,
.custom-update-body select,
.custom-update-body textarea {
  width: 100%;

  border: 1px solid #dbe3ec;
  border-radius: 0.95rem;

  padding: 0.9rem 1rem;

  background: white;
  color: #1e293b;

  transition: all 0.2s ease;
}

.custom-update-body input:focus,
.custom-update-body select:focus,
.custom-update-body textarea:focus {
  border-color: #d4a900;
  box-shadow: 0 0 0 4px rgba(212, 169, 0, 0.12);
}

.update-report-panel.custom-update-panel {
  position: absolute;
  top: 1rem;
  left: 1rem;
  width: 410px;
  max-height: 520px;
  background: #f8fafc;
  color: #263445;
  border-radius: 1.35rem;
  overflow: hidden;
  z-index: 999;
  box-shadow: 0 22px 55px rgba(15, 23, 42, 0.28);
  border: 1px solid rgba(255, 255, 255, 0.7);
}

.public-report-map-btn {
  position: absolute;
  left: 50%;
  bottom: 1.6rem;
  z-index: 55;
  min-height: 54px;
  padding: 0.45rem 1.25rem 0.45rem 0.55rem;
  border: 1px solid rgba(255, 255, 255, 0.75);
  border-radius: 999px;
  background: linear-gradient(135deg, #b91c1c, #ef4444);
  color: #fff;
  box-shadow:
    0 14px 32px rgba(127, 29, 29, 0.34),
    0 3px 8px rgba(15, 23, 42, 0.2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  font-size: 0.92rem;
  font-weight: 900;
  letter-spacing: 0.01em;
  white-space: nowrap;
  transform: translateX(-50%);
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.public-report-map-btn:hover {
  background: linear-gradient(135deg, #991b1b, #dc2626);
  box-shadow:
    0 18px 38px rgba(127, 29, 29, 0.4),
    0 4px 10px rgba(15, 23, 42, 0.24);
  transform: translate(-50%, -2px);
}

.public-report-map-btn:focus-visible {
  outline: 3px solid rgba(254, 202, 202, 0.95);
  outline-offset: 3px;
}

.public-report-map-icon {
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.public-report-map-icon svg {
  width: 21px;
  height: 21px;
  stroke-width: 2.4;
}

.public-report-map-btn.analytics-visible {
  bottom: 17.5rem;
}

.public-report-panel {
  max-height: min(700px, calc(100% - 2rem)) !important;
}

.public-report-header {
  min-height: 68px;
  height: auto;
  background: linear-gradient(135deg, #b91c1c, #dc2626);
}

.public-report-header > div {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.public-report-header strong {
  font-size: 1rem;
}

.public-report-header small {
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.72rem;
}

.public-report-notice {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  padding: 0.85rem;
  border: 1px solid #fed7aa;
  border-radius: 0.9rem;
  background: #fff7ed;
  color: #9a3412;
}

.public-report-notice svg {
  width: 19px;
  height: 19px;
  flex: 0 0 auto;
  margin-top: 0.1rem;
}

.public-report-notice p {
  margin: 0;
  font-size: 0.78rem;
  line-height: 1.45;
}

.public-report-panel .custom-update-body label > span:first-child,
.public-report-panel .custom-update-body > label > span {
  color: #dc2626;
}

.public-character-count {
  display: block;
  margin-top: 0.35rem;
  color: #64748b;
  font-size: 0.72rem;
  text-align: right;
}

.public-location-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
}

.public-location-btn {
  min-height: 50px;
  border: 1px solid #cbd5e1;
  border-radius: 0.9rem;
  background: #fff;
  color: #334155;
  font-weight: 800;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
}

.public-location-btn:hover,
.public-location-btn.active {
  border-color: #dc2626;
  background: #fff1f2;
  color: #b91c1c;
}

.public-location-status {
  margin-top: 0.7rem;
  padding: 0.75rem 0.85rem;
  border: 1px dashed #cbd5e1;
  border-radius: 0.85rem;
  background: #f8fafc;
  color: #64748b;
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  font-size: 0.76rem;
  line-height: 1.4;
}

.public-location-status.selected {
  border-color: #86efac;
  background: #f0fdf4;
  color: #15803d;
}

.public-location-status svg {
  width: 18px;
  height: 18px;
  flex: 0 0 auto;
}

.public-report-confirmation {
  display: flex !important;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.85rem;
  border-radius: 0.85rem;
  background: #f1f5f9;
  cursor: pointer;
}

.public-report-confirmation input {
  width: 18px !important;
  height: 18px;
  flex: 0 0 auto;
  margin: 0.1rem 0 0 !important;
  padding: 0 !important;
}

.public-report-confirmation span {
  color: #475569 !important;
  font-size: 0.78rem;
  font-weight: 600;
  line-height: 1.4;
}

.public-submit-btn {
  width: 100%;
  min-height: 52px;
  margin-top: 1rem;
  border: none;
  border-radius: 0.95rem;
  background: linear-gradient(135deg, #b91c1c, #dc2626);
  color: #fff;
  font-weight: 900;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
}

.public-submit-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 576px) {
  .public-report-map-btn,
  .public-report-map-btn.analytics-visible {
    bottom: 1rem;
    min-height: 50px;
    padding: 0.35rem 1rem 0.35rem 0.45rem;
    font-size: 0.84rem;
  }

  .public-report-map-icon {
    width: 38px;
    height: 38px;
    flex-basis: 38px;
  }

  .public-location-actions {
    grid-template-columns: 1fr;
  }
}

.attachment-box {
  border: 1px dashed #cbd5e1;
  border-radius: 1rem;
  padding: 1rem;
  background: #fff;
  text-align: center;
}

.attachment-preview {
  width: 100%;
  max-height: 180px;
  object-fit: cover;
  border-radius: 0.85rem;
  margin-bottom: 0.8rem;
}

.attachment-add-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  cursor: pointer;
  font-weight: 800;
  color: #0284c7;
}

.attachment-add-btn svg {
  width: 18px;
  height: 18px;
}

.update-header-actions {
  margin-bottom: 0.75rem;
}

.new-incident-btn {
  width: 100%;
  border: none;
  border-radius: 0.85rem;
  padding: 0.85rem;
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #fff;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.pick-location-btn {
  width: 100%;

  border: 1px dashed #d4a900;
  border-radius: 1rem;

  background: rgba(212, 169, 0, 0.06);

  padding: 1rem;

  font-weight: 700;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.update-back-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;

  border: none;
  background: transparent;

  color: #64748b;
  font-weight: 700;

  margin-bottom: 1rem;
  padding: 0;
}

.update-back-btn:hover {
  color: #d4a900;
}

.datetime-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.update-result-list {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.update-result-header {
  padding: 0.85rem 1rem;
  border-radius: 1rem;
  background: rgba(212, 169, 0, 0.1);
  border: 1px solid rgba(212, 169, 0, 0.25);
}

.update-result-header strong,
.update-result-header small {
  display: block;
}

.update-result-header small {
  color: #64748b;
  margin-top: 0.2rem;
}

.update-result-card {
  width: 100%;
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 1rem;
  padding: 0.85rem;
  display: grid;
  grid-template-columns: 42px 1fr auto;
  align-items: center;
  gap: 0.75rem;
  text-align: left;
}

.result-icon {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
}

.result-info strong {
  display: block;
  color: #1e293b;
}

.result-info span {
  font-size: 0.82rem;
  color: #64748b;
}

.result-id {
  font-weight: 900;
  color: #d4a900;
}

.new-station-btn {
  width: 100%;
  margin-top: 0.75rem;

  border: none;
  border-radius: 1rem;

  padding: 0.95rem;

  background: linear-gradient(135deg, #0ea5e9, #0284c7);

  color: white;
  font-weight: 800;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.selection-tool-card {
  margin-top: 1rem;
  padding: 0.85rem;
  border: 1px solid #e2e8f0;
  border-radius: 1.1rem;
  background: #fff;
}

.selection-tool-title {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin-bottom: 0.75rem;
  font-size: 0.82rem;
  font-weight: 900;
  color: #64748b;
}

.selection-tool-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.55rem;
}

.selection-tool-btn {
  border: 1px solid #e2e8f0;
  border-radius: 0.9rem;
  background: #f8fafc;
  color: #334155;
  padding: 0.75rem 0.45rem;
  font-weight: 900;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
}

.selection-tool-btn.active {
  border-color: #d4a900;
  background: linear-gradient(135deg, #d4a900, #c89b00);
  color: #fff;
  box-shadow: 0 12px 24px rgba(212, 169, 0, 0.28);
}
.custom-weather-dropdown {
  position: relative;
}

.custom-show {
  display: block;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 190px;
  z-index: 99999;
  padding: 8px;
  margin: 0;
  list-style: none;
  background: var(--phoenix-body-bg, #fff);
  border: 1px solid rgba(148, 163, 184, 0.25);
  border-radius: 14px;
  box-shadow: 0 14px 35px rgba(15, 23, 42, 0.18);
}

.weather-dropdown-item:hover,
.weather-dropdown-item.active {
  background: rgba(246, 192, 0, 0.16);
  color: #111827;
}

.weather-dropdown-item.active {
  background: linear-gradient(135deg, rgba(246, 192, 0, 0.18), rgba(255, 196, 0, 0.08));

  color: #facc15;

  border: 1px solid rgba(246, 192, 0, 0.25);
}
.weather-dropdown-item svg {
  width: 18px;
  height: 18px;
}

.weather-dropdown-item.active svg {
  color: #facc15;
}

.weather-dropdown {
  position: relative;
}

.firestation-list-panel {
  position: absolute;
  top: 5.25rem;
  right: 4rem;
  z-index: 99999;

  width: 340px;
  max-height: 460px;

  background: #ffffff;
  border: 1px solid #d8e2ef;
  border-radius: 1rem;
  box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.35);

  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.firestation-list-header {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;

  display: flex;
  align-items: center;
  justify-content: space-between;
}

.firestation-list-header strong {
  color: #111827;
  font-weight: 900;
}

.firestation-list-header button {
  border: 0;
  background: #f1f5f9;
  border-radius: 10px;
  width: 34px;
  height: 34px;
}

.firestation-list-body {
  padding: 0.75rem;
  overflow-y: auto;
}

.firestation-item {
  width: 100%;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  color: #111827;

  border-radius: 0.85rem;
  padding: 0.85rem;
  margin-bottom: 0.65rem;

  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.firestation-item:hover {
  background: #fff7d6;
  border-color: #ffc107;
}
/* tag css =========================
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

  .incident-info-grid {
    grid-template-columns: 1fr;
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
    position: relative;
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
  .update-report-panel {
    left: 0.5rem;
    right: 0.5rem;
    width: auto;
    top: 0.5rem;
    bottom: 0.5rem;
    border-radius: 18px;
  }
}

@media (min-width: 769px) {
  /* Normal dashboard: beside the ArcGIS + and - buttons */
  .fire-page:not(.dispatcher-map-mode) .weather-layer-toolbar {
    position: absolute !important;
    top: 0.9rem !important;
    left: 4rem !important;
    right: auto !important;
    z-index: 99999 !important;
  }

  /* Full-map mode */
  .dispatcher-map-mode .weather-layer-toolbar {
    position: absolute !important;
    top: 9.5rem !important;
    left: 2rem !important;
    right: auto !important;
    z-index: 99999 !important;
  }

  .activity-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .filter-field:nth-child(3) {
    grid-column: span 2;
  }
}

  .activity-filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .filter-field:nth-child(3) {
    grid-column: span 2;
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
