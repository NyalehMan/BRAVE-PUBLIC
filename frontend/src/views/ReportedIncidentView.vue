<template>
  <div class="reported-incidents-page">
    <div class="page-header">
      <div>
        <h2>Public Reported Incidents</h2>
        <p>Review citizen-submitted BRAVE incident reports before publishing to dashboard.</p>
      </div>

      <div class="header-actions">
        <button class="btn btn-phoenix-secondary" type="button" @click="loadReports">
          <FeatherIcon icon="refresh-cw" />
          Refresh
        </button>
      </div>
    </div>

    <div class="table-card incidents-table-card">
      <div class="incidents-table-header">
        <div>
          <small>Pending public incident reports from BRAVE Mobile App</small>
        </div>

        <div class="incident-header-actions">
          <div class="incident-search">
            <FeatherIcon icon="search" />
            <input v-model="searchKeyword" type="text" placeholder="Search reports..." />
          </div>

          <select v-model="statusFilter" class="status-filter">
            <option value="">All Status</option>
            <option value="PENDING">Pending</option>
            <option value="VERIFIED">Verified</option>
            <option value="NEEDS_VERIFY">Needs Verify</option>
            <option value="REJECTED">Rejected</option>
          </select>

          <span class="incident-count">{{ filteredReports.length }} records</span>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner-border text-warning" role="status"></div>
        <span>Loading public reports...</span>
      </div>

      <div v-else class="table-responsive custom-table">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Reporter</th>
              <th>IC Number</th>
              <th>District</th>
              <th>Incident Type</th>
              <th>Status</th>
              <th>Submitted Time</th>
              <th>Description</th>
              <th class="text-center">Photo</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="paginatedReports.length === 0">
              <td colspan="9" class="text-center text-body-secondary py-4">
                No public incident reports found.
              </td>
            </tr>

            <tr v-for="report in paginatedReports" :key="report.id">
              <td>
                <div class="reporter-cell">
                  <div class="reporter-avatar">
                    {{ getInitials(report.reporter_full_name) }}
                  </div>
                  <div>
                    <strong>{{ report.reporter_full_name }}</strong>
                    <small>Citizen report</small>
                  </div>
                </div>
              </td>

              <td class="fw-bold">{{ report.reporter_ic_no }}</td>

              <td>
                <div class="location-cell">
                  <FeatherIcon icon="map-pin" />
                  <span>{{ report.district }}</span>
                </div>
              </td>

              <td>
                <span class="incident-pill">{{ report.incident_type }}</span>
              </td>

              <td>
                <span class="status-pill" :class="`status-${String(report.status).toLowerCase()}`">
                  {{ formatStatus(report.status) }}
                </span>
              </td>

              <td class="reported-time">{{ formatDate(report.created_at) }}</td>

              <td class="remarks-cell">
                {{ report.description }}
              </td>

              <td class="text-center">
                <button
                  v-if="report.photo_url || report.photo_path"
                  class="photo-btn"
                  type="button"
                  @click="openPhoto(report)"
                >
                  <FeatherIcon icon="image" />
                </button>

                <span v-else class="text-body-secondary">-</span>
              </td>

              <td>
                <div class="table-actions">
                  <button
                    class="action-btn view"
                    type="button"
                    title="View Details"
                    @click="openDetails(report)"
                  >
                    <FeatherIcon icon="eye" />
                  </button>

                  <button
                    class="action-btn verify"
                    type="button"
                    title="Verify Report"
                    :disabled="report.status === 'VERIFIED'"
                    @click="openActionConfirm(report, 'VERIFIED')"
                  >
                    <FeatherIcon icon="check" />
                  </button>

                  <button
                    class="action-btn warning"
                    type="button"
                    title="Needs Verification"
                    :disabled="report.status === 'NEEDS_VERIFY'"
                    @click="openActionConfirm(report, 'NEEDS_VERIFY')"
                  >
                    <FeatherIcon icon="alert-triangle" />
                  </button>

                  <button
                    class="action-btn reject"
                    type="button"
                    title="Reject Report"
                    :disabled="report.status === 'REJECTED'"
                    @click="openActionConfirm(report, 'REJECTED')"
                  >
                    <FeatherIcon icon="x" />
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

    <transition name="modal-fade">
      <div v-if="detailsModal.show" class="incident-modal-overlay" @click.self="closeDetails">
        <div class="incident-modal-card">
          <div class="incident-modal-header">
            <div>
              <h5>Public Report Details</h5>
              <small>Review submitted citizen incident information</small>
            </div>

            <button class="incident-modal-close" type="button" @click="closeDetails">×</button>
          </div>

          <div class="details-body">
            <div class="details-grid">
              <div class="detail-box">
                <span>Reporter Name</span>
                <strong>{{ selectedReport?.reporter_full_name }}</strong>
              </div>

              <div class="detail-box">
                <span>IC Number</span>
                <strong>{{ selectedReport?.reporter_ic_no }}</strong>
              </div>

              <div class="detail-box">
                <span>District</span>
                <strong>{{ selectedReport?.district }}</strong>
              </div>

              <div class="detail-box">
                <span>Incident Type</span>
                <strong>{{ selectedReport?.incident_type }}</strong>
              </div>

              <div class="detail-box">
                <span>Latitude</span>
                <strong>{{ selectedReport?.latitude || '-' }}</strong>
              </div>

              <div class="detail-box">
                <span>Longitude</span>
                <strong>{{ selectedReport?.longitude || '-' }}</strong>
              </div>

              <div class="detail-box full">
                <span>Description</span>
                <strong>{{ selectedReport?.description }}</strong>
              </div>

              <div class="detail-box full">
                <span>Operator Notes</span>
                <textarea
                  v-model="operatorNotes"
                  placeholder="Add verification notes..."
                ></textarea>
              </div>

              <img
                v-if="selectedReportPhotoUrl"
                :src="selectedReportPhotoUrl"
                class="report-photo-preview"
              />
            </div>
          </div>

          <div class="incident-modal-footer">
            <button class="btn btn-phoenix-secondary" type="button" @click="closeDetails">
              Close
            </button>

            <button
              class="btn btn-warning"
              type="button"
              @click="openActionConfirm(selectedReport, 'NEEDS_VERIFY')"
            >
              Needs Verify
            </button>

            <button
              class="btn btn-danger"
              type="button"
              @click="openActionConfirm(selectedReport, 'REJECTED')"
            >
              Reject
            </button>

            <button
              class="btn btn-success"
              type="button"
              @click="openActionConfirm(selectedReport, 'VERIFIED')"
            >
              Verify Report
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="modal-fade">
      <div v-if="photoModal.show" class="incident-modal-overlay" @click.self="closePhoto">
        <div class="photo-modal-card">
          <button class="photo-close" type="button" @click="closePhoto">×</button>
          <img :src="photoModal.url" alt="Report photo" />
        </div>
      </div>
    </transition>

    <transition name="toast-fade">
      <div v-if="confirmToast.show" class="toast-wrap confirm-toast-wrap">
        <div class="toast-card confirm-toast-card">
          <div class="toast-title">{{ confirmToast.title }}</div>
          <div class="toast-message">{{ confirmToast.message }}</div>

          <textarea
            v-model="confirmToast.operatorNotes"
            class="confirm-notes"
            placeholder="Operator notes optional..."
          ></textarea>

          <div class="toast-actions">
            <button type="button" class="toast-btn cancel-btn" @click="cancelAction">Cancel</button>

            <button
              type="button"
              class="toast-btn action-confirm-btn"
              :disabled="updatingId === confirmToast.report?.id"
              @click="confirmAction"
            >
              {{ updatingId === confirmToast.report?.id ? 'Updating...' : confirmToast.buttonText }}
            </button>
          </div>
        </div>
      </div>
    </transition>

    <transition name="toast-fade">
      <div v-if="toast.show" class="toast-wrap">
        <div class="toast-card" :class="toast.type">
          <div class="toast-title">{{ toast.title }}</div>
          <div class="toast-message">{{ toast.message }}</div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import FeatherIcon from '@/components/FeatherIcon.vue'
import http from '@/api/http'

const loading = ref(false)
const updatingId = ref(null)
const reports = ref([])
const searchKeyword = ref('')
const statusFilter = ref('')
const currentPage = ref(1)
const perPage = 50

const selectedReport = ref(null)
const operatorNotes = ref('')

const detailsModal = ref({
  show: false,
})

const photoModal = ref({
  show: false,
  url: '',
})

const toast = ref({
  show: false,
  type: 'success',
  title: '',
  message: '',
})

const confirmToast = ref({
  show: false,
  title: '',
  message: '',
  buttonText: '',
  report: null,
  action: '',
  operatorNotes: '',
})

const filteredReports = computed(() => {
  const keyword = searchKeyword.value.toLowerCase().trim()

  return reports.value.filter((report) => {
    const matchesKeyword = !keyword || JSON.stringify(report).toLowerCase().includes(keyword)
    const matchesStatus = !statusFilter.value || report.status === statusFilter.value

    return matchesKeyword && matchesStatus
  })
})

const totalPages = computed(() => {
  return Math.ceil(filteredReports.value.length / perPage)
})

const paginatedReports = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredReports.value.slice(start, start + perPage)
})

const selectedReportPhotoUrl = computed(() => {
  if (!selectedReport.value) return ''

  return buildPhotoUrl(selectedReport.value)
})

watch([searchKeyword, statusFilter], () => {
  currentPage.value = 1
})

onMounted(() => {
  loadReports()
})

async function loadReports() {
  loading.value = true

  try {
    const response = await http.get('/api/operator/public-reports')

    reports.value = response.data?.data || []
    currentPage.value = 1
  } catch (error) {
    console.error(error)
    showToast('error', 'Load failed', error?.response?.data?.message || 'Unable to load reports.')
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
}

function openDetails(report) {
  selectedReport.value = report
  operatorNotes.value = report.operator_notes || ''
  detailsModal.value.show = true
}

function closeDetails() {
  detailsModal.value.show = false
  selectedReport.value = null
  operatorNotes.value = ''
}

function openPhoto(report) {
  const url = buildPhotoUrl(report)

  if (!url) return

  photoModal.value = {
    show: true,
    url,
  }
}

function closePhoto() {
  photoModal.value = {
    show: false,
    url: '',
  }
}

function openActionConfirm(report, action) {
  if (!report) return

  const actionText = formatStatus(action)

  confirmToast.value = {
    show: true,
    title: `${actionText} Report`,
    message:
      action === 'VERIFIED'
        ? 'Verify this public report and submit it into the BRAVE dashboard?'
        : `Mark this public report as ${actionText}?`,
    buttonText: action === 'VERIFIED' ? 'Verify & Submit' : actionText,
    report,
    action,
    operatorNotes: operatorNotes.value || report.operator_notes || '',
  }
}

function cancelAction() {
  confirmToast.value = {
    show: false,
    title: '',
    message: '',
    buttonText: '',
    report: null,
    action: '',
    operatorNotes: '',
  }
}

async function confirmAction() {
  const report = confirmToast.value.report
  const action = confirmToast.value.action

  if (!report || !action) return

  updatingId.value = report.id

  try {
    await http.post(`/api/operator/public-reports/${report.id}/status`, {
      status: action,
      operator_notes: confirmToast.value.operatorNotes,
    })

    showToast(
      'success',
      'Report Updated',
      action === 'VERIFIED'
        ? 'Report verified and submitted to BRAVE dashboard.'
        : `Report marked as ${formatStatus(action)}.`,
    )

    cancelAction()
    closeDetails()
    await loadReports()
  } catch (error) {
    console.error(error)

    showToast(
      'error',
      'Update failed',
      error?.response?.data?.message || 'Unable to update report.',
    )
  } finally {
    updatingId.value = null
  }
}

function buildPhotoUrl(report) {
  if (report.photo_url) return report.photo_url

  if (!report.photo_path) return ''

  const baseURL = import.meta.env.VITE_API_BASE_URL.replace('/api', '')

  return `${baseURL}/storage/${report.photo_path}`
}

function formatStatus(status) {
  const value = String(status || '')
    .replace('_', ' ')
    .toLowerCase()

  return value.replace(/\b\w/g, (char) => char.toUpperCase())
}

function getInitials(name) {
  if (!name) return 'BR'

  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function formatDate(value) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return value

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
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
  }, 2800)
}
</script>

<style scoped>
.reported-incidents-page {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.page-header h2 {
  font-size: clamp(1.7rem, 2.2vw, 2.25rem);
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
  flex-wrap: nowrap;
}

.header-actions .btn {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border-radius: 12px;
  font-weight: 800;
}

.table-card {
  width: 100%;
  max-width: 100%;
  background: var(--phoenix-card-bg);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.15);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
}

.incidents-table-card {
  margin-bottom: 2.5rem;
}

.incidents-table-header {
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  padding: 1.15rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  border-bottom: 1px solid var(--phoenix-border-color);
}

.incidents-table-header small {
  color: var(--phoenix-secondary-color);
  font-size: 0.9rem;
}

.incident-search {
  height: 40px;
  width: 280px;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 999px;
  padding: 0 0.9rem;
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
  width: 16px;
  height: 16px;
  color: var(--phoenix-secondary-color);
}

.status-filter {
  height: 40px;
  min-width: 150px;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 999px;
  padding: 0 0.9rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  font-size: 0.85rem;
  font-weight: 800;
}

.incident-count {
  height: 40px;
  display: inline-flex;
  align-items: center;
  background: rgba(245, 159, 0, 0.14);
  color: #a05a00;
  border-radius: 999px;
  padding: 0 0.9rem;
  font-weight: 900;
  font-size: 0.8rem;
  white-space: nowrap;
}

.custom-table {
  width: 100%;
  overflow-x: hidden;
}

.custom-table table {
  width: 100%;
  table-layout: auto;
}

.table thead th {
  color: var(--phoenix-secondary-color);
  background: var(--phoenix-body-bg);
  font-size: 0.85rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 0.75rem 0.6rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  text-align: left;
  vertical-align: middle;
  white-space: nowrap;
}

.table thead th:nth-child(1) {
  width: 180px;
}

.table thead th:nth-child(2) {
  width: 110px;
  text-align: center;
}

.table thead th:nth-child(3) {
  width: 120px;
}

.table thead th:nth-child(4) {
  width: 140px;
  text-align: center;
}

.table thead th:nth-child(5) {
  width: 130px;
  text-align: center;
}

.table thead th:nth-child(6) {
  width: 150px;
  text-align: center;
}

.table thead th:nth-child(7) {
  width: 190px;
}

.table thead th:nth-child(8) {
  width: 90px;
  text-align: center;
}

.table thead th:nth-child(9) {
  width: 170px;
  text-align: center;
}

.table tbody tr:nth-child(odd) {
  background: #ffffff;
}

.table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.table tbody tr {
  transition: all 0.2s ease;
}

.table tbody tr:hover {
  background: #fff7d6 !important;
}

.table tbody td {
  padding: 1rem 0.85rem;
  vertical-align: middle;
  color: var(--phoenix-body-color);
  border-bottom: 1px solid var(--phoenix-border-color);
  font-size: 0.9rem;
}

.reporter-cell {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  text-align: left;
  min-width: 0;
  max-width: 180px;
}

.reporter-avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #d8ae00, #f59e0b);
  color: #111827;
  font-size: 0.78rem;
  font-weight: 950;
  flex: 0 0 auto;
}

.reporter-cell strong {
  display: block;
  color: var(--phoenix-heading-color);
  font-size: 0.88rem;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.reporter-cell small {
  display: block;
  color: var(--phoenix-secondary-color);
  font-size: 0.72rem;
}

.table tbody td:nth-child(2),
.reported-time {
  text-align: center;
  font-weight: 800;
  white-space: nowrap;
  font-size: 0.82rem;
  word-break: break-word;
}

.location-cell {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--phoenix-heading-color);
  font-weight: 900;
  justify-content: flex-start;
}

.location-cell svg {
  width: 16px;
  height: 16px;
  flex: 0 0 auto;
}

.incident-pill,
.status-pill {
  padding: 0.4rem 0.75rem;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 900;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 96px;
  white-space: nowrap;
}

.incident-pill {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
}

.status-pending {
  background: rgba(245, 158, 11, 0.15);
  color: #b45309;
}

.status-verified {
  background: rgba(34, 197, 94, 0.14);
  color: #16a34a;
}

.status-needs_verify {
  background: rgba(59, 130, 246, 0.13);
  color: #2563eb;
}

.status-rejected {
  background: rgba(239, 68, 68, 0.14);
  color: #dc2626;
}

.remarks-cell {
  color: var(--phoenix-secondary-color);
  text-align: left;
  line-height: 1.45;
  word-break: break-word;
}

.photo-btn {
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 12px;
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.photo-btn svg {
  width: 14px;
  height: 14px;
}

.table-actions {
  display: flex;
  justify-content: center;
  gap: 0.45rem;
  align-items: center;
  flex-wrap: nowrap;
}

.action-btn {
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  flex: 0 0 auto;
}

.action-btn svg {
  width: 14px;
  height: 14px;
}

.action-btn.view {
  background: rgba(59, 130, 246, 0.12);
  color: #2563eb;
}

.action-btn.verify {
  background: rgba(34, 197, 94, 0.14);
  color: #16a34a;
}

.action-btn.warning {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.action-btn.reject {
  background: rgba(239, 68, 68, 0.14);
  color: #dc2626;
}

.action-btn:hover,
.photo-btn:hover {
  transform: translateY(-1px);
  filter: brightness(0.96);
}

.action-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
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
  max-width: 860px;
  max-height: 92vh;
  overflow: auto;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  border-radius: 1.25rem;
  box-shadow: 0 2rem 4rem rgba(15, 23, 42, 0.25);
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
  font-size: 1.4rem;
}

.details-body {
  padding: 1.5rem;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.9rem;
}

.detail-box {
  padding: 0.9rem;
  border-radius: 14px;
  background: var(--phoenix-body-bg);
  border: 1px solid var(--phoenix-border-color);
}

.detail-box.full {
  grid-column: span 2;
}

.detail-box span {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.72rem;
  font-weight: 900;
  color: var(--phoenix-secondary-color);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.detail-box strong {
  display: block;
  color: var(--phoenix-heading-color);
  font-size: 0.92rem;
  word-break: break-word;
}

.detail-box textarea {
  width: 100%;
  min-height: 95px;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 12px;
  padding: 0.75rem;
  background: var(--phoenix-card-bg);
  color: var(--phoenix-body-color);
  resize: vertical;
}

.report-photo-preview {
  grid-column: span 2;
  width: 100%;
  max-height: 360px;
  object-fit: cover;
  border-radius: 16px;
  border: 1px solid var(--phoenix-border-color);
}

.incident-modal-footer {
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--phoenix-border-color);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.photo-modal-card {
  position: relative;
  width: min(94vw, 880px);
  max-height: 92vh;
}

.photo-modal-card img {
  width: 100%;
  max-height: 92vh;
  object-fit: contain;
  border-radius: 18px;
  background: #000;
}

.photo-close {
  position: absolute;
  top: -14px;
  right: -14px;
  width: 38px;
  height: 38px;
  border: 0;
  border-radius: 999px;
  background: #dc2626;
  color: white;
  font-size: 1.3rem;
  z-index: 2;
}

.toast-wrap {
  position: fixed;
  top: 24px;
  right: 24px;
  z-index: 30000;
}

.toast-card {
  min-width: 280px;
  max-width: 390px;
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
  font-weight: 800;
}

.toast-message {
  margin-top: 4px;
}

.confirm-toast-card {
  background: var(--phoenix-card-bg, #fff);
  border: 1px solid var(--phoenix-border-color);
  color: var(--phoenix-body-color);
}

.confirm-notes {
  width: 100%;
  min-height: 78px;
  margin-top: 0.85rem;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 12px;
  padding: 0.75rem;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  resize: vertical;
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
  font-weight: 700;
  cursor: pointer;
  border: 1px solid var(--phoenix-border-color);
}

.cancel-btn {
  background: #f8fafc;
  color: #475569;
}

.action-confirm-btn {
  background: #198754;
  color: #fff;
  border-color: #198754;
}

.toast-fade-enter-active,
.toast-fade-leave-active,
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: all 0.25s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to,
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

:global(html[data-bs-theme='dark']) .incidents-table-header {
  background: linear-gradient(180deg, #111827, #0f172a);
}

:global(html[data-bs-theme='dark']) .table tbody tr:nth-child(odd),
:global(html[data-bs-theme='dark']) .table tbody tr:nth-child(even) {
  background: #111827;
}

:global(html[data-bs-theme='dark']) .table tbody tr:hover {
  background: rgba(216, 174, 0, 0.12) !important;
}

@media (max-width: 1366px) {
  .custom-table table {
    min-width: 1220px;
  }

  .table thead th:nth-child(1) {
    width: 220px;
  }

  .table thead th:nth-child(6) {
    width: 170px;
  }

  .table thead th:nth-child(9) {
    width: 200px;
  }

  .action-btn,
  .photo-btn {
    width: 34px;
    height: 34px;
  }
}

@media (max-width: 992px) {
  .page-header,
  .incidents-table-header {
    flex-direction: column;
    align-items: stretch;
  }

  .incident-header-actions {
    width: 100%;
    flex-wrap: wrap;
  }

  .incident-search {
    width: 100%;
  }

  .status-filter,
  .incident-count {
    flex: 1;
  }
}

@media (max-width: 768px) {
  .details-grid {
    grid-template-columns: 1fr;
  }

  .detail-box.full,
  .report-photo-preview {
    grid-column: span 1;
  }
}
</style>
