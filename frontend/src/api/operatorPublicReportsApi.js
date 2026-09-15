import api from './authApi'

function extractReports(data) {
  const reports = data?.reports?.data ?? data?.reports ?? data?.data?.data ?? data?.data ?? data

  return Array.isArray(reports) ? reports : []
}

export async function getPublicReports({
  status = 'PENDING',
  search = '',
  page = 1,
  perPage = 25,
} = {}) {
  const response = await api.get('/api/operator/public-reports', {
    params: {
      status,
      search: search.trim() || undefined,
      page,
      per_page: perPage,
    },
  })

  const data = response.data ?? {}

  return {
    reports: extractReports(data),
    pagination: {
      currentPage: Number(data.pagination?.current_page) || 1,
      lastPage: Number(data.pagination?.last_page) || 1,
      perPage: Number(data.pagination?.per_page) || perPage,
      total: Number(data.pagination?.total) || 0,
    },
    statusCounts: data.status_counts ?? {},
    pendingReportIds: Array.isArray(data.pending_report_ids)
      ? data.pending_report_ids.map(String)
      : [],
  }
}

export async function updatePublicReportStatus(id, status, notes = '') {
  // Do not request /sanctum/csrf-cookie here. The authenticated
  // brave-session created during login must be reused.
  const response = await api.post(`/api/operator/public-reports/${id}/status`, {
    status,
    operator_notes: notes.trim() || null,
  })

  return response.data?.report ?? response.data?.data ?? response.data
}
