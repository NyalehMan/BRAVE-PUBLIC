<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getCurrentUser, logout } from '../api/authApi'
import { getPublicReports, updatePublicReportStatus } from '../api/operatorPublicReportsApi'

const router = useRouter()

const AUTO_REFRESH_INTERVAL_MS = 15000

let autoRefreshTimer = null
let searchDebounceTimer = null
let reportsRequestPromise = null
let reportsHaveLoaded = false
let knownPendingReportIds = new Set()
let originalDocumentTitle = ''

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
const statusCounts = ref({
  PENDING: 0,
  NEEDS_VERIFY: 0,
  VERIFIED: 0,
  REJECTED: 0,
})
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 25,
  total: 0,
})
const selectedReport = ref(null)
const activeStatus = ref('PENDING')
const searchText = ref('')
const reviewerNotes = ref('')
const loading = ref(true)
const checkingForUpdates = ref(false)
const updating = ref(false)
const signingOut = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const lastCheckedAt = ref(null)
const newPendingReportIds = ref([])

const lastCheckedLabel = computed(() => {
  if (!lastCheckedAt.value) {
    return 'Waiting for first check'
  }

  return `Last checked ${new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(lastCheckedAt.value)}`
})

const newReportMessage = computed(() => {
  const count = newPendingReportIds.value.length

  return count === 1
    ? '1 new public report is waiting for review.'
    : `${count} new public reports are waiting for review.`
})

const filteredReports = computed(() => {
  return reports.value
})

function getStatus(report) {
  return String(report?.status ?? 'PENDING').toUpperCase()
}

function getCount(status) {
  return Number(statusCounts.value[status]) || 0
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
  return report?.description || 'No additional information was provided.'
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
  return report?.photo_url ?? null
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
  )}&mlon=${encodeURIComponent(longitude)}#map=16/${encodeURIComponent(
    latitude,
  )}/${encodeURIComponent(longitude)}`
}

function statusLabel(status) {
  return statusOptions.find((option) => option.value === status)?.label ?? status
}

function statusClass(status) {
  return `status-${String(status).toLowerCase()}`
}

function extractError(error) {
  const response = error?.response?.data
  const errors = response?.errors

  if (errors && typeof errors === 'object') {
    const firstError = Object.values(errors).flat().find(Boolean)

    if (firstError) {
      return firstError
    }
  }

  return (
    response?.error ||
    response?.details?.error?.message ||
    response?.message ||
    error?.message ||
    'Something went wrong.'
  )
}

async function redirectIfUnauthenticated(error) {
  const status = error?.response?.status

  if (status !== 401 && status !== 403) {
    return false
  }

  stopAutoRefresh()

  if (status === 403) {
    await logout().catch(() => null)
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

async function selectStatus(status) {
  if (activeStatus.value === status) {
    return
  }

  activeStatus.value = status
  pagination.value.currentPage = 1
  await loadReports()
}

function updateDocumentTitle() {
  if (typeof document === 'undefined') {
    return
  }

  const pendingCount = getCount('PENDING')
  const baseTitle = originalDocumentTitle || 'BRAVE Reviewer Portal'

  document.title = pendingCount ? `(${pendingCount}) ${baseTitle}` : baseTitle
}

function showDetectedReports(ids) {
  if (!ids.length) {
    return
  }

  newPendingReportIds.value = [...new Set([...newPendingReportIds.value, ...ids])]
}

function dismissNewReportNotice() {
  newPendingReportIds.value = []
}

async function reviewNewReports() {
  const newIds = new Set(newPendingReportIds.value)
  activeStatus.value = 'PENDING'
  pagination.value.currentPage = 1

  await loadReports()

  const firstNewReport = reports.value.find(
    (report) => getStatus(report) === 'PENDING' && newIds.has(String(report.id)),
  )

  const firstPendingReport = reports.value.find((report) => getStatus(report) === 'PENDING')

  selectReport(firstNewReport ?? firstPendingReport ?? null)
  dismissNewReportNotice()
}

function applyReportSelection(items, silent) {
  const selectedId = selectedReport.value?.id
  const refreshedSelection = items.find(
    (report) => report.id === selectedId && getStatus(report) === activeStatus.value,
  )
  const defaultSelection = items.find((report) => getStatus(report) === activeStatus.value)

  if (silent && refreshedSelection) {
    // Keep notes currently being typed while refreshing report data.
    selectedReport.value = refreshedSelection
    return
  }

  if (silent) {
    selectedReport.value = defaultSelection ?? null
    reviewerNotes.value = defaultSelection?.operator_notes ?? ''
    return
  }

  selectReport(refreshedSelection ?? defaultSelection ?? null)
}

async function loadReports({ silent = false, detectNew = false } = {}) {
  if (reportsRequestPromise) {
    if (!silent) {
      await reportsRequestPromise.catch(() => null)

      return loadReports({
        silent: false,
        detectNew,
      })
    }

    return
  }

  if (silent && updating.value) {
    return
  }

  if (silent) {
    checkingForUpdates.value = true
  } else {
    loading.value = true
    errorMessage.value = ''
  }

  const currentRequest = getPublicReports({
    status: activeStatus.value,
    search: searchText.value,
    page: pagination.value.currentPage,
    perPage: pagination.value.perPage,
  })
  reportsRequestPromise = currentRequest

  try {
    // The API returns only the current page plus lightweight global counts.
    const result = await currentRequest
    const incomingReports = result.reports
    const pendingIds = new Set(result.pendingReportIds)

    if (detectNew && reportsHaveLoaded) {
      const newIds = [...pendingIds].filter((id) => !knownPendingReportIds.has(id))

      showDetectedReports(newIds)
    }

    knownPendingReportIds = pendingIds
    newPendingReportIds.value = newPendingReportIds.value.filter((id) => pendingIds.has(id))

    reports.value = incomingReports
    statusCounts.value = {
      ...statusCounts.value,
      ...result.statusCounts,
    }
    pagination.value = result.pagination
    reportsHaveLoaded = true
    lastCheckedAt.value = new Date()

    applyReportSelection(incomingReports, silent)
    updateDocumentTitle()
  } catch (error) {
    if (await redirectIfUnauthenticated(error)) {
      return
    }

    if (!silent) {
      reports.value = []
      selectedReport.value = null
      errorMessage.value = extractError(error)
    }
  } finally {
    if (reportsRequestPromise === currentRequest) {
      reportsRequestPromise = null
    }

    loading.value = false
    checkingForUpdates.value = false
  }
}

function goToPage(page) {
  const targetPage = Math.min(Math.max(Number(page) || 1, 1), pagination.value.lastPage)

  if (targetPage === pagination.value.currentPage) {
    return
  }

  pagination.value.currentPage = targetPage
  void loadReports()
}

function checkForNewReports() {
  void loadReports({
    silent: true,
    detectNew: true,
  })
}

function startAutoRefresh() {
  stopAutoRefresh()

  autoRefreshTimer = window.setInterval(checkForNewReports, AUTO_REFRESH_INTERVAL_MS)
}

function stopAutoRefresh() {
  if (autoRefreshTimer !== null) {
    window.clearInterval(autoRefreshTimer)
    autoRefreshTimer = null
  }
}

function handleVisibilityChange() {
  if (document.visibilityState === 'visible') {
    checkForNewReports()
  }
}

async function changeStatus(status) {
  if (!selectedReport.value?.id) {
    return
  }

  if (status === 'NEEDS_VERIFY' && !reviewerNotes.value.trim()) {
    errorMessage.value = 'Please enter reviewer notes before requesting verification.'
    return
  }

  if (!window.confirm(`Change this report to ${statusLabel(status)}?`)) {
    return
  }

  updating.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await updatePublicReportStatus(selectedReport.value.id, status, reviewerNotes.value)

    activeStatus.value = status
    pagination.value.currentPage = 1
    await loadReports()

    successMessage.value = {
      VERIFIED: 'Report verified and submitted to BRAVE.',
      NEEDS_VERIFY: 'Report marked as needing verification.',
      REJECTED: 'Report rejected.',
    }[status]
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
    stopAutoRefresh()
    await logout()
    await router.replace('/niat/login')
  } catch (error) {
    errorMessage.value = extractError(error)
  } finally {
    signingOut.value = false
  }
}

watch(searchText, () => {
  if (searchDebounceTimer !== null) {
    window.clearTimeout(searchDebounceTimer)
  }

  searchDebounceTimer = window.setTimeout(() => {
    pagination.value.currentPage = 1
    void loadReports()
  }, 350)
})

onMounted(async () => {
  originalDocumentTitle = document.title

  try {
    user.value = await getCurrentUser()
  } catch (error) {
    await redirectIfUnauthenticated(error)
    return
  }

  await loadReports()
  startAutoRefresh()
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onBeforeUnmount(() => {
  stopAutoRefresh()

  if (searchDebounceTimer !== null) {
    window.clearTimeout(searchDebounceTimer)
    searchDebounceTimer = null
  }
  document.removeEventListener('visibilitychange', handleVisibilityChange)

  if (originalDocumentTitle) {
    document.title = originalDocumentTitle
  }
})
</script>

<template>
  <main class="reviewer-page">
    <header class="portal-header">
      <div>
        <p class="eyebrow">BRAVE Operations</p>
        <h1>NIAT Reviewer Portal</h1>
        <p>Review public incident reports before publication to BRAVE.</p>
      </div>

      <div class="user-area">
        <div class="user-details">
          <strong>{{ user?.name || 'NIAT Reviewer' }}</strong>
          <span>{{ user?.role || 'reviewer' }}</span>
        </div>

        <button class="secondary-button" :disabled="signingOut" @click="handleLogout">
          {{ signingOut ? 'Signing out…' : 'Sign out' }}
        </button>
      </div>
    </header>

    <Transition name="new-report-notice">
      <section
        v-if="newPendingReportIds.length"
        class="new-report-notice"
        role="status"
        aria-live="polite"
      >
        <div class="new-report-notice-content">
          <span class="new-report-badge">New</span>

          <div>
            <strong>{{ newReportMessage }}</strong>
            <p>The pending report list has been updated automatically.</p>
          </div>
        </div>

        <div class="new-report-notice-actions">
          <button type="button" class="review-now-button" @click="reviewNewReports">
            Review now
          </button>

          <button
            type="button"
            class="dismiss-notice-button"
            aria-label="Dismiss new report notification"
            @click="dismissNewReportNotice"
          >
            Dismiss
          </button>
        </div>
      </section>
    </Transition>

    <section class="status-grid">
      <button
        v-for="option in statusOptions"
        :key="option.value"
        class="status-card"
        :class="[statusClass(option.value), { active: activeStatus === option.value }]"
        @click="selectStatus(option.value)"
      >
        <span class="status-count">
          {{ getCount(option.value) }}
        </span>
        <span
          v-if="option.value === 'PENDING' && newPendingReportIds.length"
          class="new-count-badge"
        >
          +{{ newPendingReportIds.length }} new
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
            <span>{{ pagination.total }} report(s)</span>
          </div>

          <div class="refresh-controls">
            <span class="auto-refresh-status">
              <span class="live-dot" :class="{ checking: checkingForUpdates }" />
              <span>
                {{ checkingForUpdates ? 'Checking for new reports…' : lastCheckedLabel }}
              </span>
            </span>

            <button
              class="secondary-button"
              :disabled="loading || checkingForUpdates"
              @click="loadReports()"
            >
              Refresh
            </button>
          </div>
        </div>

        <input
          v-model="searchText"
          class="search-input"
          type="search"
          placeholder="Search report, type, location or reporter"
        />

        <div v-if="loading" class="empty-state">Loading public reports…</div>

        <div v-else-if="!filteredReports.length" class="empty-state">
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
              <span class="status-pill" :class="statusClass(getStatus(report))">
                {{ statusLabel(getStatus(report)) }}
              </span>
            </div>

            <span>{{ getReference(report) }}</span>
            <span>{{ getLocation(report) }}</span>
            <time>{{ formatDate(getReportedDate(report)) }}</time>
          </button>
        </div>

        <nav
          v-if="!loading && pagination.lastPage > 1"
          class="pagination-controls"
          aria-label="Report pages"
        >
          <button
            type="button"
            class="secondary-button"
            :disabled="pagination.currentPage <= 1"
            @click="goToPage(pagination.currentPage - 1)"
          >
            Previous
          </button>

          <span> Page {{ pagination.currentPage }} of {{ pagination.lastPage }} </span>

          <button
            type="button"
            class="secondary-button"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="goToPage(pagination.currentPage + 1)"
          >
            Next
          </button>
        </nav>
      </aside>

      <article class="report-details-panel">
        <div v-if="!selectedReport" class="empty-state detail-empty">
          Select a report to view its details.
        </div>

        <template v-else>
          <div class="details-heading">
            <div>
              <p class="eyebrow">Incident report</p>
              <h2>{{ getCategory(selectedReport) }}</h2>
              <span>{{ getReference(selectedReport) }}</span>
            </div>

            <span class="status-pill large" :class="statusClass(getStatus(selectedReport))">
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
                {{ selectedReport.reporter_full_name || 'Not provided' }}
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

          <section v-if="getPhotoUrl(selectedReport)" class="details-section">
            <h3>Submitted photo</h3>
            <a :href="getPhotoUrl(selectedReport)" target="_blank" rel="noopener noreferrer">
              <img
                class="report-photo"
                :src="getPhotoUrl(selectedReport)"
                alt="Submitted incident"
              />
            </a>
          </section>

          <section v-if="getMapUrl(selectedReport)" class="details-section">
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
                {{ selectedReport.submitted_to_brave ? 'Submitted' : 'Not submitted' }}
              </span>
              <span v-if="selectedReport.brave_incident_objectid">
                ArcGIS Object ID:
                {{ selectedReport.brave_incident_objectid }}
              </span>
            </div>
          </section>

          <section v-if="getStatus(selectedReport) !== 'VERIFIED'" class="review-section">
            <label for="reviewer-notes">Reviewer notes</label>
            <textarea
              id="reviewer-notes"
              v-model="reviewerNotes"
              rows="5"
              placeholder="Add notes or explain what needs verification."
            />

            <p class="notes-help">Notes are required when requesting further verification.</p>

            <div class="action-buttons">
              <button class="approve-button" :disabled="updating" @click="changeStatus('VERIFIED')">
                {{ updating ? 'Updating…' : 'Approve and verify' }}
              </button>

              <button
                class="verify-button"
                :disabled="updating || getStatus(selectedReport) === 'NEEDS_VERIFY'"
                @click="changeStatus('NEEDS_VERIFY')"
              >
                Needs verification
              </button>

              <button
                class="reject-button"
                :disabled="updating || getStatus(selectedReport) === 'REJECTED'"
                @click="changeStatus('REJECTED')"
              >
                Reject report
              </button>
            </div>
          </section>

          <section v-else class="review-section review-complete">
            <h3>Review completed</h3>
            <p>This report is read-only because it has been submitted to BRAVE.</p>
            <p v-if="selectedReport.operator_notes">
              <strong>Reviewer notes:</strong>
              {{ selectedReport.operator_notes }}
            </p>
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

.new-report-notice {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-top: 18px;
  padding: 16px 18px;
  color: #78350f;
  background: #fffbeb;
  border: 1px solid #fbbf24;
  border-radius: 13px;
  box-shadow: 0 10px 28px rgb(146 64 14 / 12%);
}

.new-report-notice-content,
.new-report-notice-actions,
.refresh-controls,
.auto-refresh-status {
  display: flex;
  align-items: center;
}

.new-report-notice-content {
  gap: 12px;
}

.new-report-notice-content p {
  margin: 3px 0 0;
  color: #92400e;
  font-size: 13px;
}

.new-report-badge,
.new-count-badge {
  width: fit-content;
  color: #ffffff;
  background: #d97706;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.new-report-badge {
  padding: 7px 10px;
}

.new-report-notice-actions {
  flex: 0 0 auto;
  gap: 8px;
}

.review-now-button,
.dismiss-notice-button {
  min-height: 38px;
  padding: 8px 13px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 800;
}

.review-now-button {
  color: #ffffff;
  background: #b45309;
  border: 1px solid #b45309;
}

.dismiss-notice-button {
  color: #78350f;
  background: transparent;
  border: 1px solid #f59e0b;
}

.new-report-notice-enter-active,
.new-report-notice-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.new-report-notice-enter-from,
.new-report-notice-leave-to {
  opacity: 0;
  transform: translateY(-8px);
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
  position: relative;
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

.new-count-badge {
  position: absolute;
  top: 16px;
  right: 16px;
  padding: 5px 8px;
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

.refresh-controls {
  justify-content: flex-end;
  gap: 12px;
}

.auto-refresh-status {
  gap: 7px;
  color: #64748b;
  font-size: 11px;
  white-space: nowrap;
}

.live-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 auto;
  background: #16a34a;
  border-radius: 50%;
  box-shadow: 0 0 0 3px rgb(22 163 74 / 13%);
}

.live-dot.checking {
  animation: auto-refresh-pulse 0.9s ease-in-out infinite;
}

@keyframes auto-refresh-pulse {
  50% {
    opacity: 0.35;
    transform: scale(0.78);
  }
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

.pagination-controls {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
  color: #64748b;
  font-size: 12px;
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

.review-complete {
  padding: 18px;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  background: #f0fdf4;
  color: #166534;
}

.review-complete h3,
.review-complete p {
  margin: 0;
}

.review-complete p + p {
  margin-top: 10px;
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

  .new-report-notice {
    align-items: flex-start;
    flex-direction: column;
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

  .panel-heading,
  .refresh-controls {
    align-items: flex-start;
    flex-direction: column;
  }

  .new-report-notice-actions {
    width: 100%;
  }

  .new-report-notice-actions button {
    flex: 1;
  }
}
</style>
