<template>
  <div class="cad-page">
    <!-- Header -->
    <div class="cad-hero">
      <div>
        <span class="eyebrow">BRAVE CAD MODULE</span>
        <h3>CAD Intake Console</h3>
        <p>
          Capture caller details, validate incident information, and submit verified cases to BRAVE.
        </p>
      </div>

      <div class="status-stack">
        <span class="status-pill" :class="validationBadgeClass">
          {{ validationStatus || 'Draft Intake' }}
        </span>
        <small v-if="intakeId">Intake ID: #{{ intakeId }}</small>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon">📞</div>
        <div>
          <small>Caller</small>
          <strong>{{ form.caller_phone || 'Not captured' }}</strong>
        </div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">🔥</div>
        <div>
          <small>Incident Type</small>
          <strong>{{ form.incident_category || 'Not selected' }}</strong>
        </div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">⚠️</div>
        <div>
          <small>Severity</small>
          <strong>{{ form.severity_level || 'Not set' }}</strong>
        </div>
      </div>

      <div class="summary-card">
        <div class="summary-icon">✅</div>
        <div>
          <small>Risk Score</small>
          <strong>{{ totalRiskScore }}</strong>
        </div>
      </div>
    </div>

    <div class="cad-grid">
      <!-- Caller Details -->
      <section class="cad-card">
        <div class="card-title">
          <div>
            <h5>Caller Details</h5>
            <small>Basic caller identity and operator record</small>
          </div>
        </div>

        <div class="form-grid">
          <div class="field full">
            <label>Caller Phone</label>
            <input v-model="form.caller_phone" placeholder="e.g. +673 8123456" />
          </div>

          <div class="field full">
            <label>Caller Name</label>
            <input v-model="form.caller_name" placeholder="Enter caller name" />
          </div>

          <div class="field">
            <label>ID Type</label>
            <select v-model="form.caller_id_type">
              <option value="">Select ID Type</option>
              <option value="IC">IC</option>
              <option value="Passport">Passport</option>
            </select>
          </div>

          <!-- IC -->
          <div v-if="form.caller_id_type === 'IC'" class="field">
            <label>IC Number</label>

            <div class="ic-input-group" :class="icColorClass">
              <span class="ic-prefix">{{ detectedIcPrefix || '--' }}</span>

              <input
                :value="form.caller_id_no"
                @beforeinput="preventInvalidIcInput"
                @input="formatIcInput"
                inputmode="numeric"
                autocomplete="off"
                placeholder="00-123456"
                maxlength="9"
              />
            </div>

            <small class="ic-helper">
              {{ icHelperText }}
            </small>
          </div>

          <!-- Passport -->
          <div v-else-if="form.caller_id_type === 'Passport'" class="field">
            <label>Passport Number</label>

            <input v-model="form.caller_id_no" placeholder="Enter passport number" />
          </div>
        </div>

        <div class="field full">
          <label>Operator Name</label>
          <input v-model="form.operator_name" placeholder="CAD operator name" />
        </div>
      </section>

      <!-- Incident Details -->
      <section class="cad-card">
        <div class="card-title">
          <div>
            <h5>Incident Details</h5>
            <small>Location, severity, and incident description</small>
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>Incident Category</label>
            <select v-model="form.incident_category">
              <option value="">Select Category</option>
              <option value="Building Fire">Building Fire</option>
              <option value="Forest Fire">Forest Fire</option>
              <option value="Vehicle Fire">Vehicle Fire</option>
              <option value="WildFire">Wild Fire</option>
            </select>
          </div>

          <div class="field">
            <label>Severity</label>
            <select v-model="form.severity_level">
              <option value="">Select Severity</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>
          </div>

          <div class="field">
            <label>District</label>
            <select v-model="form.district">
              <option value="">Select District</option>
              <option value="Brunei Muara">Brunei Muara</option>
              <option value="Tutong">Tutong</option>
              <option value="Belait">Belait</option>
              <option value="Temburong">Temburong</option>
            </select>
          </div>

          <div class="field">
            <label>Latitude</label>
            <input v-model="form.latitude" readonly />
          </div>

          <div class="field">
            <label>Longitude</label>
            <input v-model="form.longitude" readonly />
          </div>

          <div class="field full">
            <label>Location Description</label>
            <textarea
              v-model="form.location_description"
              placeholder="Auto-filled from map selection"
            ></textarea>
          </div>

          <div class="field full">
            <label>Pick Incident Location</label>
            <div ref="cadMapDiv" class="cad-map"></div>
          </div>

          <div class="field full">
            <label>More Details</label>
            <textarea
              v-model="form.more_details"
              placeholder="Additional information from caller"
            ></textarea>
          </div>
        </div>
      </section>

      <!-- Validation Questions -->
      <section class="cad-card full">
        <div class="card-title">
          <div>
            <h5>Validation Questions</h5>
            <small>Use answers to confirm incident validity before submitting to BRAVE</small>
          </div>

          <span class="risk-pill">Score: {{ totalRiskScore }}</span>
        </div>

        <div class="question-list">
          <div v-for="(q, index) in questions" :key="index" class="question-row">
            <div class="question-left">
              <span class="question-number">{{ index + 1 }}</span>
              <div>
                <strong>{{ q.text }}</strong>
                <small>Risk weight: {{ q.risk_score }}</small>
              </div>
            </div>

            <select v-model="q.answer">
              <option value="">Select</option>
              <option value="Yes">Yes</option>
              <option value="No">No</option>
              <option value="Unknown">Unknown</option>
            </select>
          </div>
        </div>
      </section>
    </div>

    <!-- Actions -->
    <div class="cad-actions-card">
      <div>
        <h5>CAD Workflow</h5>
        <p>
          Save the intake first, validate the report, then submit only valid incidents to BRAVE.
        </p>
      </div>

      <div class="cad-actions">
        <button class="btn-soft" @click="saveIntake" :disabled="loading">
          {{ loading ? 'Processing...' : 'Save Intake' }}
        </button>

        <button class="btn-dark" @click="validateIntake" :disabled="!intakeId || loading">
          Validate
        </button>
        <button @click="checkDuplicate" :disabled="!intakeId || loading">Check Duplicate</button>
        <button
          class="btn-yellow"
          @click="submitToBrave"
          :disabled="validationStatus !== 'VALID' || loading"
        >
          Submit to BRAVE
        </button>
      </div>
    </div>

    <div v-if="message" class="cad-message" :class="messageClass">
      {{ message }}
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue'
import http from '@/api/http'

import Map from '@arcgis/core/Map'
import MapView from '@arcgis/core/views/MapView'
import Graphic from '@arcgis/core/Graphic'
import Point from '@arcgis/core/geometry/Point'
import * as locator from '@arcgis/core/rest/locator'

const loading = ref(false)
const message = ref('')
const callId = ref(null)
const intakeId = ref(null)
const validationStatus = ref('')
const cadMapDiv = ref(null)
const duplicateResult = ref(null)

let cadMapView = null
let incidentMarker = null

const form = reactive({
  caller_phone: '',
  caller_name: '',
  caller_id_type: '',
  caller_id_no: '',
  operator_name: '',
  incident_category: '',
  severity_level: '',
  district: '',
  latitude: '',
  longitude: '',
  location_description: '',
  more_details: '',
})

watch(
  () => form.caller_id_type,
  () => {
    form.caller_id_no = ''
  },
)

const questions = reactive([
  { text: 'Is there visible fire or smoke?', answer: '', risk_score: 3 },
  { text: 'Is anyone trapped or injured?', answer: '', risk_score: 3 },
  { text: 'Is the location confirmed?', answer: '', risk_score: 2 },
  { text: 'Is the incident still ongoing?', answer: '', risk_score: 2 },
  { text: 'Is access blocked?', answer: '', risk_score: 1 },
])

const totalRiskScore = computed(() => questions.reduce((total, q) => total + answerScore(q), 0))

const validationBadgeClass = computed(() => {
  if (validationStatus.value === 'VALID') return 'valid'
  if (validationStatus.value === 'INVALID') return 'invalid'
  return 'draft'
})

const messageClass = computed(() => {
  if (message.value.toLowerCase().includes('failed')) return 'error'
  if (message.value.toLowerCase().includes('submitted')) return 'success'
  if (message.value.toLowerCase().includes('saved')) return 'success'
  return 'info'
})

const detectedIcPrefix = computed(() => {
  if (form.caller_id_type !== 'IC') return ''

  const match = form.caller_id_no.match(/^(\d{2})/)
  return match ? match[1] : ''
})

const detectedIcColor = computed(() => {
  if (!detectedIcPrefix.value) return ''

  const prefix = Number(detectedIcPrefix.value)

  if (prefix === 0 || (prefix >= 1 && prefix <= 29)) return 'Yellow'
  if (prefix === 30 || (prefix >= 31 && prefix <= 49)) return 'Purple'
  if (prefix >= 50) return 'Green'

  return ''
})

const icColorClass = computed(() => {
  if (!detectedIcColor.value) return 'ic-default'

  if (detectedIcColor.value === 'Yellow') return 'ic-yellow'
  if (detectedIcColor.value === 'Purple') return 'ic-purple'
  if (detectedIcColor.value === 'Green') return 'ic-green'

  return 'ic-default'
})

const icHelperText = computed(() => {
  if (!form.caller_id_no) {
    return 'Enter IC number, example: 00-123456'
  }

  if (detectedIcColor.value === 'Yellow') {
    return 'Citizen IC detected'
  }

  if (detectedIcColor.value === 'Purple') {
    return 'Permanent Resident IC detected'
  }

  if (detectedIcColor.value === 'Green') {
    return 'Temporary Resident IC detected'
  }

  return 'Unknown IC format'
})

async function initCadMap() {
  const map = new Map({
    basemap: 'hybrid',
  })

  cadMapView = new MapView({
    container: cadMapDiv.value,
    map,
    center: [114.9398, 4.9031],
    zoom: 10,
  })

  cadMapView.on('click', async (event) => {
    const point = event.mapPoint

    form.latitude = point.latitude.toFixed(6)
    form.longitude = point.longitude.toFixed(6)

    if (incidentMarker) {
      cadMapView.graphics.remove(incidentMarker)
    }

    incidentMarker = new Graphic({
      geometry: point,
      symbol: {
        type: 'simple-marker',
        color: '#ef4444',
        size: 14,
        outline: {
          color: '#ffffff',
          width: 2,
        },
      },
    })

    cadMapView.graphics.add(incidentMarker)

    try {
      const result = await locator.locationToAddress(
        'https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer',
        {
          location: point,
        },
      )

      form.location_description = result.address
    } catch {
      form.location_description = 'Selected map location'
    }
  })
}

function answerScore(q) {
  if (q.answer === 'Yes') return q.risk_score
  if (q.answer === 'Unknown') return 1
  return 0
}

function preventInvalidIcInput(event) {
  // allow delete/backspace/etc
  if (event.inputType?.includes('delete') || event.inputType === 'insertFromPaste') {
    return
  }

  // block non-numeric typing
  if (event.data && !/^\d$/.test(event.data)) {
    event.preventDefault()
  }
}

function formatIcInput(event) {
  let value = event.target.value

  // remove everything except digits
  value = value.replace(/[^\d]/g, '')

  // limit to 8 digits total
  value = value.slice(0, 8)

  // auto add dash after first 2 digits
  if (value.length > 2) {
    value = value.slice(0, 2) + '-' + value.slice(2)
  }

  form.caller_id_no = value
}

async function saveIntake() {
  loading.value = true
  message.value = ''

  try {
    const callRes = await http.post('/api/cad/calls', {
      caller_phone: form.caller_phone,
      caller_name: form.caller_name,
      caller_id_type: form.caller_id_type,
      caller_id_no: form.caller_id_no,
      operator_name: form.operator_name,
    })

    callId.value = callRes.data.id

    const intakeRes = await http.post('/api/cad/intakes', {
      call_id: callId.value,
      incident_category: form.incident_category,
      severity_level: form.severity_level,
      district: form.district,
      latitude: form.latitude,
      longitude: form.longitude,
      location_description: form.location_description,
      more_details: form.more_details,
      answers: questions.map((q) => ({
        question_text: q.text,
        answer: q.answer,
        risk_score: answerScore(q),
      })),
    })

    intakeId.value = intakeRes.data.id
    validationStatus.value = ''
    message.value = 'CAD intake saved.'
  } catch (error) {
    console.error(error)
    message.value = 'Failed to save intake.'
  } finally {
    loading.value = false
  }
}

async function validateIntake() {
  loading.value = true
  message.value = ''

  try {
    const res = await http.post(`/api/cad/intakes/${intakeId.value}/validate`)
    validationStatus.value = res.data.validation_status
    message.value = `Validation result: ${validationStatus.value}`
  } catch (error) {
    console.error(error)
    message.value = 'Failed to validate intake.'
  } finally {
    loading.value = false
  }
}

async function submitToBrave() {
  loading.value = true
  message.value = ''

  try {
    await http.post(`/api/cad/intakes/${intakeId.value}/submit-to-brave`)
    message.value = 'Incident submitted to BRAVE.'
  } catch (error) {
    console.error(error)
    message.value = error.response?.data?.message || 'Failed to submit to BRAVE.'
  } finally {
    loading.value = false
  }
}
async function checkDuplicate() {
  if (!intakeId.value) return

  const res = await http.post(`/api/cad/intakes/${intakeId.value}/duplicate-check`)
  duplicateResult.value = res.data

  if (res.data.duplicate) {
    message.value = `Possible duplicate found: ${res.data.count} nearby active incident(s).`
  } else {
    message.value = 'No duplicate incident found nearby.'
  }
}

onMounted(() => {
  initCadMap()
})
</script>

<style scoped>
.cad-page {
  min-height: 100vh;
  padding: 24px;
  color: var(--phoenix-body-color);
  background:
    radial-gradient(circle at top left, rgba(246, 192, 0, 0.12), transparent 35%),
    var(--phoenix-body-bg);
  transition:
    background 0.25s ease,
    color 0.25s ease;
}

.cad-hero {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: center;
  padding: 24px;
  margin-bottom: 18px;
  border-radius: 22px;
  position: relative;
  overflow: hidden;
  color: white;
  background: linear-gradient(135deg, rgba(17, 24, 39, 0.95), rgba(30, 41, 59, 0.95));
}

.cad-hero::after {
  content: '';
  position: absolute;
  right: -60px;
  top: -60px;
  width: 220px;
  height: 220px;
  border-radius: 999px;
  background: rgba(246, 192, 0, 0.22);
}

.eyebrow {
  display: inline-flex;
  margin-bottom: 8px;
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  color: #f6c000;
}

.cad-hero h3 {
  margin: 0;
  font-size: 1.7rem;
  font-weight: 900;
  color: #cbd5e1;
}

.cad-hero p {
  margin: 6px 0 0;
  color: #cbd5e1;
  max-width: 720px;
}

.status-stack {
  position: relative;
  z-index: 1;
  text-align: right;
}

.status-stack small {
  display: block;
  margin-top: 8px;
  color: #cbd5e1;
}

.status-pill,
.risk-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 8px 13px;
  font-size: 0.75rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-pill.draft {
  background: rgba(148, 163, 184, 0.18);
  color: #e2e8f0;
  border: 1px solid rgba(226, 232, 240, 0.25);
}

.status-pill.valid {
  background: rgba(34, 197, 94, 0.16);
  color: #86efac;
  border: 1px solid rgba(134, 239, 172, 0.3);
}

.status-pill.invalid {
  background: rgba(239, 68, 68, 0.16);
  color: #fca5a5;
  border: 1px solid rgba(252, 165, 165, 0.3);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 18px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-radius: 18px;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  transition: all 0.2s ease;
}

.summary-icon {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  border-radius: 14px;
  background: #fff8db;
  font-size: 1.15rem;
}

.summary-card small {
  display: block;
  color: var(--phoenix-secondary-color);
  font-weight: 700;
}

.summary-card strong {
  display: block;
  margin-top: 2px;
  font-size: 0.95rem;
  color: #0f172a;
}

.cad-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.cad-card {
  padding: 20px;
  border-radius: 22px;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  transition: all 0.2s ease;
}

.cad-card.full {
  grid-column: 1 / -1;
}

.card-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}

.card-title h5 {
  margin: 0;
  font-weight: 900;
  color: #0f172a;
}

.card-title small {
  color: var(--phoenix-secondary-color);
  font-weight: 600;
}

.risk-pill {
  background: #fff8db;
  color: #9a6a00;
  border: 1px solid #f8df7b;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.field.full {
  grid-column: 1 / -1;
}

.field label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--phoenix-secondary-color);
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 14px;
  padding: 11px 13px;
  background: var(--phoenix-body-bg);
  color: var(--phoenix-body-color);
  font-size: 0.92rem;
  outline: none;
  transition: 0.18s ease;
}

textarea {
  min-height: 86px;
  resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
  border-color: #f6c000;
  box-shadow: 0 0 0 4px rgba(246, 192, 0, 0.15);
}

.question-list {
  display: grid;
  gap: 10px;
}

.question-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 180px;
  gap: 14px;
  align-items: center;
  padding: 14px;
  border-radius: 16px;
  background: var(--phoenix-emphasis-bg);
  border: 1px solid var(--phoenix-border-color);
}

.question-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.question-number {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 12px;
  background: #111827;
  color: #f6c000;
  font-weight: 900;
}

.question-left strong {
  display: block;
  font-size: 0.92rem;
  color: #0f172a;
}

.question-left small {
  color: var(--phoenix-secondary-color);
  font-weight: 600;
}

.cad-actions-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 18px;
  padding: 18px 20px;
  border-radius: 22px;
  background: var(--phoenix-card-bg);
  border: 1px solid var(--phoenix-border-color);
  box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
  transition: all 0.2s ease;
}

.cad-actions-card h5 {
  margin: 0;
  font-weight: 900;
}

.cad-actions-card p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 0.9rem;
}

.cad-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.cad-actions button {
  border: 0;
  border-radius: 14px;
  padding: 11px 16px;
  font-weight: 900;
  cursor: pointer;
  transition: 0.18s ease;
}

.cad-actions button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.btn-soft {
  background: #22c55e;
  color: white;
  box-shadow: 0 10px 20px rgba(34, 197, 94, 0.28);
}

.btn-soft:hover:not(:disabled) {
  background: #16a34a;
  transform: translateY(-1px);
}

.btn-dark {
  background: #111827;
  color: #fff;
}

.btn-yellow {
  background: #f6c000;
  color: #111827;
  box-shadow: 0 10px 20px rgba(246, 192, 0, 0.28);
}

button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.cad-message {
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 16px;
  font-weight: 800;
}

.cad-message.success {
  background: #ecfdf5;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.cad-message.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.cad-message.info {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
}

.ic-input-group {
  display: grid;
  grid-template-columns: 70px 1fr;
  align-items: center;
  border: 1px solid var(--phoenix-border-color);
  border-radius: 14px;
  overflow: hidden;
  background: var(--phoenix-body-bg);
}

.ic-input-group input {
  border: 0;
  border-radius: 0;
  margin: 0;
}

.ic-input-group input:focus {
  box-shadow: none;
}

.ic-prefix {
  height: 100%;
  display: grid;
  place-items: center;
  font-weight: 900;
  border-right: 1px solid rgba(15, 23, 42, 0.08);
  transition: 0.2s ease;
}

/* DEFAULT GREY */
.ic-default .ic-prefix {
  background: var(--phoenix-emphasis-bg);
  color: var(--phoenix-secondary-color);
}

/* CITIZEN */
.ic-yellow .ic-prefix {
  background: #f6c000;
  color: #111827;
}

/* PR */
.ic-purple .ic-prefix {
  background: #7c3aed;
  color: white;
}

/* TEMP */
.ic-green .ic-prefix {
  background: #22c55e;
  color: white;
}

.ic-helper {
  display: block;
  margin-top: 6px;
  font-size: 0.76rem;
  color: #64748b;
  font-weight: 600;
}

.cad-map {
  width: 100%;
  height: 420px;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid #dbe3ef;
}

[data-bs-theme='dark'] .summary-card,
[data-bs-theme='dark'] .cad-card,
[data-bs-theme='dark'] .cad-actions-card {
  box-shadow: 0 18px 42px rgba(0, 0, 0, 0.45);
}
@media (max-width: 1100px) {
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .cad-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .cad-page {
    padding: 14px;
  }

  .cad-hero,
  .cad-actions-card {
    flex-direction: column;
    align-items: stretch;
  }

  .status-stack {
    text-align: left;
  }

  .summary-grid,
  .form-grid {
    grid-template-columns: 1fr;
  }

  .field.full {
    grid-column: auto;
  }

  .question-row {
    grid-template-columns: 1fr;
  }

  .cad-actions {
    flex-direction: column;
  }

  .cad-actions button {
    width: 100%;
  }
}
</style>
