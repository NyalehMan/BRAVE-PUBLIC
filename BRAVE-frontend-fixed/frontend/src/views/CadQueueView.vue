<template>
  <div class="cad-queue-page">
    <div class="page-header">
      <div>
        <h3>CAD Queue</h3>
        <p>Review, validate, duplicate-check, and submit CAD intakes to BRAVE.</p>
      </div>

      <button class="refresh-btn" @click="loadQueue" :disabled="loading">Refresh</button>
    </div>

    <div v-if="message" class="queue-message" :class="messageType">
      {{ message }}
    </div>

    <div class="queue-card">
      <div class="queue-toolbar">
        <input v-model="search" placeholder="Search caller, category, district..." />

        <select v-model="statusFilter">
          <option value="">All Status</option>
          <option value="PENDING">Pending</option>
          <option value="VALID">Valid</option>
          <option value="NEEDS_VERIFY">Needs Verify</option>
          <option value="SUSPICIOUS">Suspicious</option>
          <option value="REJECTED">Rejected</option>
        </select>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Call Ref</th>
              <th>Caller</th>
              <th>Phone</th>
              <th>Category</th>
              <th>Severity</th>
              <th>District</th>
              <th>Status</th>
              <th>BRAVE</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="empty">Loading CAD queue...</td>
            </tr>

            <tr v-else-if="filteredItems.length === 0">
              <td colspan="9" class="empty">No CAD intakes found.</td>
            </tr>

            <tr v-for="item in filteredItems" :key="item.id">
              <td>
                <strong>{{ item.call?.call_ref || '-' }}</strong>
              </td>

              <td>{{ item.call?.caller_name || '-' }}</td>
              <td>{{ item.call?.caller_phone || '-' }}</td>
              <td>{{ item.incident_category || '-' }}</td>

              <td>
                <span class="badge" :class="severityClass(item.severity_level)">
                  {{ item.severity_level || '-' }}
                </span>
              </td>

              <td>{{ item.district || '-' }}</td>

              <td>
                <span class="badge" :class="statusClass(item.validation_status)">
                  {{ item.validation_status || 'PENDING' }}
                </span>
              </td>

              <td>
                <span
                  class="badge"
                  :class="item.submitted_to_brave ? 'submitted' : 'not-submitted'"
                >
                  {{ item.submitted_to_brave ? 'Submitted' : 'Not Submitted' }}
                </span>
              </td>

              <td>
                <div class="action-row">
                  <button @click="openDetails(item)">View</button>

                  <button @click="validateItem(item)" :disabled="actionLoadingId === item.id">
                    Validate
                  </button>

                  <button @click="checkDuplicate(item)" :disabled="actionLoadingId === item.id">
                    Duplicate
                  </button>

                  <button
                    class="submit"
                    @click="submitToBrave(item)"
                    :disabled="
                      actionLoadingId === item.id ||
                      item.validation_status !== 'VALID' ||
                      Number(item.submitted_to_brave) === 1
                    "
                  >
                    Submit
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="selectedItem" class="drawer-backdrop" @click.self="selectedItem = null">
      <aside class="details-drawer">
        <div class="drawer-header">
          <div>
            <h5>CAD Intake Details</h5>
            <small>{{ selectedItem.call?.call_ref || '-' }}</small>
          </div>

          <button @click="selectedItem = null">×</button>
        </div>

        <div class="drawer-section">
          <h6>Caller</h6>
          <p><span>Name:</span> {{ selectedItem.call?.caller_name || '-' }}</p>
          <p><span>Phone:</span> {{ selectedItem.call?.caller_phone || '-' }}</p>
          <p>
            <span>ID:</span> {{ selectedItem.call?.caller_id_type || '-' }}
            {{ selectedItem.call?.caller_id_no || '' }}
          </p>
        </div>

        <div class="drawer-section">
          <h6>Incident</h6>
          <p><span>Category:</span> {{ selectedItem.incident_category || '-' }}</p>
          <p><span>Severity:</span> {{ selectedItem.severity_level || '-' }}</p>
          <p><span>District:</span> {{ selectedItem.district || '-' }}</p>
          <p><span>Location:</span> {{ selectedItem.location_description || '-' }}</p>
          <p><span>Details:</span> {{ selectedItem.more_details || '-' }}</p>
          <p>
            <span>Coordinates:</span> {{ selectedItem.latitude || '-' }},
            {{ selectedItem.longitude || '-' }}
          </p>
        </div>

        <div class="drawer-section">
          <h6>Status</h6>
          <p><span>Validation:</span> {{ selectedItem.validation_status || 'PENDING' }}</p>
          <p><span>Submitted:</span> {{ selectedItem.submitted_to_brave ? 'Yes' : 'No' }}</p>
          <p><span>ArcGIS OBJECTID:</span> {{ selectedItem.brave_incident_objectid || '-' }}</p>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import http from '@/api/http'

const loading = ref(false)
const actionLoadingId = ref(null)
const items = ref([])
const search = ref('')
const statusFilter = ref('')
const message = ref('')
const messageType = ref('info')
const selectedItem = ref(null)

onMounted(() => {
  loadQueue()
})

const filteredItems = computed(() => {
  const keyword = search.value.toLowerCase().trim()

  return items.value.filter((item) => {
    const matchesSearch = !keyword || JSON.stringify(item).toLowerCase().includes(keyword)

    const matchesStatus = !statusFilter.value || item.validation_status === statusFilter.value

    return matchesSearch && matchesStatus
  })
})

async function loadQueue() {
  loading.value = true
  message.value = ''

  try {
    const res = await http.get('/api/cad/queue')
    items.value = res.data || []
  } catch (error) {
    console.error(error)
    showMessage('Failed to load CAD queue.', 'error')
  } finally {
    loading.value = false
  }
}

async function validateItem(item) {
  actionLoadingId.value = item.id

  try {
    const res = await http.post(`/api/cad/intakes/${item.id}/validate`)
    showMessage(`Validation result: ${res.data.validation_status}`, 'success')
    await loadQueue()
  } catch (error) {
    console.error(error)
    showMessage('Failed to validate intake.', 'error')
  } finally {
    actionLoadingId.value = null
  }
}

async function checkDuplicate(item) {
  actionLoadingId.value = item.id

  try {
    const res = await http.post(`/api/cad/intakes/${item.id}/duplicate-check`)

    if (res.data.duplicate) {
      showMessage(`Possible duplicate found: ${res.data.count} nearby incident(s).`, 'warning')
    } else {
      showMessage('No duplicate incident found nearby.', 'success')
    }
  } catch (error) {
    console.error(error)
    showMessage('Failed to check duplicate.', 'error')
  } finally {
    actionLoadingId.value = null
  }
}

async function submitToBrave(item) {
  actionLoadingId.value = item.id

  try {
    const res = await http.post(`/api/cad/intakes/${item.id}/submit-to-brave`)
    showMessage(res.data.message || 'Submitted to BRAVE.', 'success')
    await loadQueue()
  } catch (error) {
    console.error(error)
    showMessage(error.response?.data?.message || 'Failed to submit to BRAVE.', 'error')
  } finally {
    actionLoadingId.value = null
  }
}

function openDetails(item) {
  selectedItem.value = item
}

function showMessage(text, type = 'info') {
  message.value = text
  messageType.value = type

  setTimeout(() => {
    message.value = ''
  }, 3500)
}

function statusClass(status) {
  const value = String(status || 'PENDING').toLowerCase()

  if (value === 'valid') return 'valid'
  if (value === 'needs_verify') return 'verify'
  if (value === 'suspicious') return 'suspicious'
  if (value === 'rejected') return 'rejected'

  return 'pending'
}

function severityClass(status) {
  const value = String(status || '').toLowerCase()

  if (value === 'critical') return 'critical'
  if (value === 'high') return 'high'
  if (value === 'medium') return 'medium'
  if (value === 'low') return 'low'

  return 'pending'
}
</script>

<style scoped>
.cad-queue-page {
  padding: 24px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.page-header h3 {
  margin: 0;
  font-weight: 800;
}

.page-header p {
  margin: 6px 0 0;
  color: #64748b;
}

.refresh-btn,
.action-row button {
  border: 0;
  border-radius: 10px;
  padding: 8px 12px;
  font-weight: 800;
  background: #e5e7eb;
  color: #111827;
  cursor: pointer;
}

.action-row button.submit {
  background: #f6c000;
}

button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.queue-message {
  padding: 12px 14px;
  border-radius: 14px;
  margin-bottom: 16px;
  font-weight: 700;
}

.queue-message.success {
  background: #dcfce7;
  color: #166534;
}

.queue-message.error {
  background: #fee2e2;
  color: #991b1b;
}

.queue-message.warning {
  background: #fef3c7;
  color: #92400e;
}

.queue-message.info {
  background: #dbeafe;
  color: #1d4ed8;
}

.queue-card {
  background: #fff;
  border-radius: 20px;
  padding: 18px;
  box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
}

.queue-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.queue-toolbar input,
.queue-toolbar select {
  border: 1px solid #dbe3ef;
  border-radius: 12px;
  padding: 10px 12px;
}

.queue-toolbar input {
  flex: 1;
}

.table-responsive {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

th {
  text-align: left;
  color: #64748b;
  font-size: 12px;
  text-transform: uppercase;
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
}

td {
  padding: 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.empty {
  text-align: center;
  color: #64748b;
  padding: 28px;
}

.badge {
  display: inline-block;
  border-radius: 999px;
  padding: 5px 10px;
  font-weight: 800;
  font-size: 11px;
  white-space: nowrap;
}

.badge.valid,
.badge.submitted,
.badge.low {
  background: #dcfce7;
  color: #15803d;
}

.badge.verify,
.badge.medium {
  background: #fef3c7;
  color: #b45309;
}

.badge.suspicious,
.badge.high {
  background: #ffedd5;
  color: #c2410c;
}

.badge.rejected,
.badge.critical {
  background: #fee2e2;
  color: #b91c1c;
}

.badge.pending,
.badge.not-submitted {
  background: #e5e7eb;
  color: #374151;
}

.action-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  z-index: 9999;
  display: flex;
  justify-content: flex-end;
}

.details-drawer {
  width: min(480px, 100%);
  height: 100%;
  background: #fff;
  padding: 22px;
  overflow-y: auto;
  box-shadow: -20px 0 45px rgba(15, 23, 42, 0.18);
}

.drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid #e5e7eb;
  padding-bottom: 14px;
  margin-bottom: 16px;
}

.drawer-header h5 {
  margin: 0;
  font-weight: 800;
}

.drawer-header small {
  color: #64748b;
}

.drawer-header button {
  border: 0;
  background: #e5e7eb;
  border-radius: 10px;
  width: 34px;
  height: 34px;
  font-size: 22px;
  cursor: pointer;
}

.drawer-section {
  margin-bottom: 20px;
}

.drawer-section h6 {
  font-weight: 800;
  margin-bottom: 10px;
}

.drawer-section p {
  margin: 8px 0;
  color: #334155;
}

.drawer-section span {
  font-weight: 800;
  color: #111827;
}

@media (max-width: 768px) {
  .cad-queue-page {
    padding: 14px;
  }

  .page-header,
  .queue-toolbar {
    flex-direction: column;
  }

  .queue-toolbar input,
  .queue-toolbar select {
    width: 100%;
  }
}
</style>
