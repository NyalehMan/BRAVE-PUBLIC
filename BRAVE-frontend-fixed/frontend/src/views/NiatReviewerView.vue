<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  getCurrentUser,
  logout,
} from '../api/authApi'
import {
  getPublicReports,
  updatePublicReportStatus,
} from '../api/operatorPublicReportsApi'

const router = useRouter()

const statusOptions = [
  {
    value: 'PENDING',
    label: 'Pending',
    description: 'Waiting for review',
  },
  {
    value: 'NEEDS_VERIFY',
    label: 'Needs Verification',
    description: 'More information required',
  },
  {
    value: 'VERIFIED',
    label: 'Verified',
    description: 'Approved reports',
  },
  {
    value: 'REJECTED',
    label: 'Rejected',
    description: 'Reports not approved',
  },
]

const user = ref(null)
const reports = ref([])
const selectedReport = ref(null)
const activeStatus = ref('PENDING')
const searchText = ref('')
const reviewerNotes = ref('')
const loading = ref(true)
const updating = ref(false)
const signingOut = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const filteredReports = computed(() => {
  const query = searchText.value.trim().toLowerCase()

  return reports.value.filter((report) => {
    if (getStatus(report) !== activeStatus.value) {
      return false
    }

    if (!query) {
      return true
    }

    return [
      getReference(report),
      getCategory(report),
      getLocation(report),
      report.description,
      report.reporter_full_name,
      report.reporter_ic_no,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
      .includes(query)
  })
})

function getStatus(report) {
  return String(report?.status ?? 'PENDING').toUpperCase()
}

function getCount(status) {
  return reports.value.filter(
    (report) => getStatus(report) === status,
  ).length
}

function getReference(report) {
  return `Report #${report?.id ?? '—'}`
}

function getCategory(report) {
  return report?.incident_type || 'Unspecified incident'
}

function getLocation(report) {
  return report?.district || 'Location not provided'
}

function getDetails(report) {
  return (
    report?.description ||
    'No additional information was provided.'
  )
}

function getReportedDate(report) {
  return report?.created_at ?? null
}

function formatDate(value) {
  if (!value) {
    return 'Not provided'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return String(value)
  }

  return new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function getPhotoUrl(report) {
  const value = report?.photo_url ?? report?.photo_path

  if (!value) {
    return null
  }

  if (/^https?:\/\//i.test(value)) {
    return value
  }

  const apiBase = (
    import.meta.env.VITE_API_BASE_URL ||
    'http://localhost:8000'
  )
    .trim()
    .replace(/\/+$/, '')
    .replace(/\/api$/, '')

  const cleanPath = String(value)
    .replace(/^\/+/, '')
    .replace(/^storage\//, '')

  return `${apiBase}/storage/${cleanPath}`
}

function getMapUrl(report) {
  const latitude = report?.latitude
  const longitude = report?.longitude

  if (latitude === null || latitude === undefined) {
    return null
  }

  if (longitude === null || longitude === undefined) {
    return null
  }

  return `https://www.openstreetmap.org/?mlat=${encodeURIComponent(
    latitude,
  )}&mlon=${encodeURIComponent(
    longitude,
  )}#map=16/${encodeURIComponent(
    latitude,
  )}/${encodeURIComponent(longitude)}`
}

function statusLabel(status) {
  return (
    statusOptions.find(
      (option) => option.value === status,
    )?.label ?? status
  )
}

function statusClass(status) {
  return `status-${String(status).toLowerCase()}`
}

function extractError(error) {
  const errors = error?.response?.data?.errors

  if (errors && typeof errors === 'object') {
    const firstError = Object.values(errors)
      .flat()
      .find(Boolean)

    if (firstError) {
      return firstError
    }
  }

  return (
    error?.response?.data?.message ??
    error?.message ??
    'Something went wrong.'
  )
}

async function redirectIfUnauthenticated(error) {
  if (error?.response?.status !== 401) {
    return false
  }

  await router.replace('/niat/login')
  return true
}

function selectReport(report) {
  selectedReport.value = report
  reviewerNotes.value = report?.operator_notes ?? ''
  errorMessage.value = ''
  successMessage.value = ''
}

function selectStatus(status) {
  activeStatus.value = status

  const firstReport = reports.value.find(
    (report) => getStatus(report) === status,
  )

  selectReport(firstReport ?? null)
}

async function loadReports() {
  loading.value = true
  errorMessage.value = ''

  try {
    // Load once and calculate the three card totals locally.
    // Every request uses the same authenticated Axios instance.
    reports.value = await getPublicReports()

    const selectedId = selectedReport.value?.id
    const refreshedSelection = reports.value.find(
      (report) => report.id === selectedId,
    )
    const defaultSelection = reports.value.find(
      (report) => getStatus(report) === activeStatus.value,
    )

    selectReport(
      refreshedSelection ?? defaultSelection ?? null,
    )
  } catch (error) {
    if (await redirectIfUnauthenticated(error)) {
      return
    }

    reports.value = []
    selectedReport.value = null
    errorMessage.value = extractError(error)
  } finally {
    loading.value = false
  }
}

async function changeStatus(status) {
  if (!selectedReport.value?.id) {
    return
  }

  if (
    status === 'NEEDS_VERIFY' &&
    !reviewerNotes.value.trim()
  ) {
    errorMessage.value =
      'Please enter reviewer notes before requesting verification.'
    return
  }

  if (
    !window.confirm(
      `Change this report to ${statusLabel(status)}?`,
    )
  ) {
    return
  }

  updating.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await updatePublicReportStatus(
      selectedReport.value.id,
      status,
      reviewerNotes.value,
    )

    activeStatus.value = status
    await loadReports()

    successMessage.value =
      status === 'VERIFIED'
        ? 'Report verified and submitted to BRAVE.'
        : status === 'REJECTED'
          ? 'Report rejected.'
          : 'Report marked as needing verification.'
  } catch (error) {
    if (await redirectIfUnauthenticated(error)) {
      return
    }

    const message = extractError(error)

    if (error?.response?.status === 502) {
      await loadReports()
    }

    errorMessage.value = message
  } finally {
    updating.value = false
  }
}

async function handleLogout() {
  signingOut.value = true
  errorMessage.value = ''

  try {
    await logout()
    await router.replace('/niat/login')
  } catch (error) {
    errorMessage.value = extractError(error)
  } finally {
    signingOut.value = false
  }
}

onMounted(async () => {
  try {
    user.value = await getCurrentUser()
  } catch (error) {
    await redirectIfUnauthenticated(error)
    return
  }

  await loadReports()
})
</script>

<template>
  <main class="reviewer-page">
    <header class="portal-header">
      <div>
        <p class="eyebrow">BRAVE Operations</p>
        <h1>NIAT Reviewer Portal</h1>
        <p>
          Review public incident reports before publication to BRAVE.
        </p>
      </div>

      <div class="user-area">
        <div class="user-details">
          <strong>{{ user?.name || 'NIAT Reviewer' }}</strong>
          <span>{{ user?.role || 'reviewer' }}</span>
        </div>

        <button
          class="secondary-button"
          :disabled="signingOut"
          @click="handleLogout"
        >
          {{ signingOut ? 'Signing out…' : 'Sign out' }}
        </button>
      </div>
    </header>

    <section class="status-grid">
      <button
        v-for="option in statusOptions"
        :key="option.value"
        class="status-card"
        :class="[
          statusClass(option.value),
          { active: activeStatus === option.value },
        ]"
        @click="selectStatus(option.value)"
      >
        <span class="status-count">
          {{ getCount(option.value) }}
        </span>
        <strong>{{ option.label }}</strong>
        <small>{{ option.description }}</small>
      </button>
    </section>

    <p v-if="errorMessage" class="message error-message">
      {{ errorMessage }}
    </p>

    <p v-if="successMessage" class="message success-message">
      {{ successMessage }}
    </p>

    <section class="dashboard">
      <aside class="report-list-panel">
        <div class="panel-heading">
          <div>
            <h2>{{ statusLabel(activeStatus) }} Reports</h2>
            <span>{{ filteredReports.length }} report(s)</span>
          </div>

          <button
            class="secondary-button"
            :disabled="loading"
            @click="loadReports"
          >
            Refresh
          </button>
        </div>

        <input
          v-model="searchText"
          class="search-input"
          type="search"
          placeholder="Search report, type, location or reporter"
        />

        <div v-if="loading" class="empty-state">
          Loading public reports…
        </div>

        <div
          v-else-if="!filteredReports.length"
          class="empty-state"
        >
          No {{ statusLabel(activeStatus).toLowerCase() }} reports.
        </div>

        <div v-else class="report-list">
          <button
            v-for="report in filteredReports"
            :key="report.id"
            class="report-card"
            :class="{
              selected: selectedReport?.id === report.id,
            }"
            @click="selectReport(report)"
          >
            <div class="report-card-top">
              <strong>{{ getCategory(report) }}</strong>
              <span
                class="status-pill"
                :class="statusClass(getStatus(report))"
              >
                {{ statusLabel(getStatus(report)) }}
              </span>
            </div>

            <span>{{ getReference(report) }}</span>
            <span>{{ getLocation(report) }}</span>
            <time>{{ formatDate(getReportedDate(report)) }}</time>
          </button>
        </div>
      </aside>

      <article class="report-details-panel">
        <div
          v-if="!selectedReport"
          class="empty-state detail-empty"
        >
          Select a report to view its details.
        </div>

        <template v-else>
          <div class="details-heading">
            <div>
              <p class="eyebrow">Incident report</p>
              <h2>{{ getCategory(selectedReport) }}</h2>
              <span>{{ getReference(selectedReport) }}</span>
            </div>

            <span
              class="status-pill large"
              :class="statusClass(getStatus(selectedReport))"
            >
              {{ statusLabel(getStatus(selectedReport)) }}
            </span>
          </div>

          <div class="details-grid">
            <div>
              <span>Reported</span>
              <strong>
                {{ formatDate(getReportedDate(selectedReport)) }}
              </strong>
            </div>

            <div>
              <span>District</span>
              <strong>{{ getLocation(selectedReport) }}</strong>
            </div>

            <div>
              <span>Reporter</span>
              <strong>
                {{
                  selectedReport.reporter_full_name ||
                  'Not provided'
                }}
              </strong>
            </div>

            <div>
              <span>Reporter IC</span>
              <strong>
                {{ selectedReport.reporter_ic_no || 'Not provided' }}
              </strong>
            </div>
          </div>

          <section class="details-section">
            <h3>Report details</h3>
            <p>{{ getDetails(selectedReport) }}</p>
          </section>

          <section
            v-if="getPhotoUrl(selectedReport)"
            class="details-section"
          >
            <h3>Submitted photo</h3>
            <a
              :href="getPhotoUrl(selectedReport)"
              target="_blank"
              rel="noopener noreferrer"
            >
              <img
                class="report-photo"
                :src="getPhotoUrl(selectedReport)"
                alt="Submitted incident"
              />
            </a>
          </section>

          <section
            v-if="getMapUrl(selectedReport)"
            class="details-section"
          >
            <h3>Incident location</h3>
            <a
              class="map-link"
              :href="getMapUrl(selectedReport)"
              target="_blank"
              rel="noopener noreferrer"
            >
              Open reported location on map
            </a>
          </section>

          <section class="details-section">
            <h3>BRAVE submission</h3>
            <div class="submission-status">
              <span>
                {{
                  selectedReport.submitted_to_brave
                    ? 'Submitted'
                    : 'Not submitted'
                }}
              </span>
              <span v-if="selectedReport.brave_incident_objectid">
                ArcGIS Object ID:
                {{ selectedReport.brave_incident_objectid }}
              </span>
            </div>
          </section>

          <section
            v-if="getStatus(selectedReport) !== 'VERIFIED'"
            class="review-section"
          >
            <label for="reviewer-notes">Reviewer notes</label>
            <textarea
              id="reviewer-notes"
              v-model="reviewerNotes"
              rows="5"
              placeholder="Add notes or explain what needs verification."
            />

            <p class="notes-help">
              Notes are required when requesting further verification.
            </p>

            <div class="action-buttons">
              <button
                class="approve-button"
                :disabled="updating"
                @click="changeStatus('VERIFIED')"
              >
                {{
                  updating
                    ? 'Updating…'
                    : 'Approve and verify'
                }}
              </button>

              <button
                class="verify-button"
                :disabled="updating"
                @click="changeStatus('NEEDS_VERIFY')"
              >
                Needs verification
              </button>

              <button
                class="reject-button"
                :disabled="updating"
                @click="changeStatus('REJECTED')"
              >
                Reject report
              </button>
            </div>
          </section>
        </template>
      </article>
    </section>
  </main>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.reviewer-page {
  min-height: 100vh;
  padding: 32px;
  color: #172033;
  background: #f3f6fa;
}

.portal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  padding: 26px 30px;
  color: #ffffff;
  background: #0c2038;
  border-radius: 18px;
}

.portal-header h1,
.panel-heading h2,
.details-heading h2 {
  margin: 0;
}

.portal-header > div > p:last-child {
  margin: 10px 0 0;
  color: #cbd5e1;
}

.eyebrow {
  margin: 0 0 6px;
  color: #e8b931;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.user-area {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-details {
  display: flex;
  flex-direction: column;
  text-align: right;
}

.user-details span {
  color: #cbd5e1;
  font-size: 13px;
  text-transform: capitalize;
}

.secondary-button {
  padding: 10px 15px;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  cursor: pointer;
  background: #ffffff;
  color: #172033;
  font-weight: 700;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin: 22px 0;
}

.status-card {
  display: grid;
  gap: 5px;
  padding: 20px;
  text-align: left;
  cursor: pointer;
  background: #ffffff;
  border: 2px solid transparent;
  border-radius: 14px;
  box-shadow: 0 7px 22px rgb(15 23 42 / 7%);
}

.status-card.active {
  border-color: currentColor;
}

.status-card small,
.report-card span,
.report-card time,
.details-heading > div > span {
  color: #64748b;
}

.status-count {
  font-size: 28px;
  font-weight: 800;
}

.status-pending {
  color: #a16207;
}

.status-needs_verify {
  color: #1d4ed8;
}

.status-verified {
  color: #15803d;
}

.status-rejected {
  color: #b91c1c;
}

.message {
  padding: 13px 16px;
  border-radius: 10px;
}

.error-message {
  color: #991b1b;
  background: #fee2e2;
}

.success-message {
  color: #166534;
  background: #dcfce7;
}

.dashboard {
  display: grid;
  grid-template-columns: minmax(310px, 0.8fr) minmax(0, 1.5fr);
  gap: 20px;
}

.report-list-panel,
.report-details-panel {
  min-height: 600px;
  padding: 22px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 7px 22px rgb(15 23 42 / 7%);
}

.panel-heading,
.details-heading,
.report-card-top {
  display: flex;
  justify-content: space-between;
  gap: 16px;
}

.panel-heading {
  align-items: center;
}

.search-input,
textarea {
  width: 100%;
  margin-top: 18px;
  padding: 12px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  font: inherit;
}

.report-list {
  display: grid;
  gap: 10px;
  max-height: 690px;
  margin-top: 16px;
  overflow-y: auto;
}

.report-card {
  display: grid;
  gap: 8px;
  padding: 16px;
  text-align: left;
  cursor: pointer;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 11px;
}

.report-card:hover,
.report-card.selected {
  border-color: #1d4ed8;
  background: #eff6ff;
}

.status-pill {
  width: fit-content;
  padding: 5px 9px;
  background: #f1f5f9;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
}

.status-pill.large {
  padding: 8px 12px;
  font-size: 12px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 24px;
}

.details-grid > div {
  display: grid;
  gap: 5px;
  padding: 14px;
  background: #f8fafc;
  border-radius: 10px;
}

.details-grid span {
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
}

.details-section,
.review-section {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.details-section p {
  white-space: pre-wrap;
}

.report-photo {
  width: 100%;
  max-height: 380px;
  object-fit: contain;
  background: #f1f5f9;
  border-radius: 12px;
}

.map-link {
  color: #1d4ed8;
  font-weight: 700;
}

.submission-status {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.submission-status span {
  padding: 8px 11px;
  background: #f1f5f9;
  border-radius: 8px;
  font-size: 13px;
}

.review-section label {
  font-weight: 800;
}

.notes-help {
  margin: 7px 0 0;
  color: #64748b;
  font-size: 12px;
}

.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 16px;
}

.action-buttons button {
  padding: 11px 16px;
  color: #ffffff;
  border: 0;
  border-radius: 9px;
  cursor: pointer;
  font-weight: 800;
}

.approve-button {
  background: #15803d;
}

.verify-button {
  background: #1d4ed8;
}

.reject-button {
  background: #b91c1c;
}

button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.empty-state {
  padding: 50px 20px;
  color: #64748b;
  text-align: center;
}

.detail-empty {
  display: grid;
  min-height: 520px;
  place-items: center;
}

@media (max-width: 960px) {
  .status-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dashboard {
    grid-template-columns: 1fr;
  }

  .portal-header {
    flex-direction: column;
  }

  .user-details {
    text-align: left;
  }
}

@media (max-width: 650px) {
  .reviewer-page {
    padding: 16px;
  }

  .status-grid,
  .details-grid {
    grid-template-columns: 1fr;
  }

  .user-area {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
