import api from './authApi'

function extractReports(data) {
  const reports =
    data?.reports?.data ??
    data?.reports ??
    data?.data?.data ??
    data?.data ??
    data

  return Array.isArray(reports) ? reports : []
}

export async function getPublicReports(status = null) {
  const response = await api.get(
    '/api/operator/public-reports',
    {
      params: status ? { status } : {},
    },
  )

  return extractReports(response.data)
}

export async function updatePublicReportStatus(
  id,
  status,
  notes = '',
) {
  // Do not request /sanctum/csrf-cookie here. The authenticated
  // brave-session created during login must be reused.
  const response = await api.post(
    `/api/operator/public-reports/${id}/status`,
    {
      status,
      operator_notes: notes.trim() || null,
    },
  )

  return (
    response.data?.report ??
    response.data?.data ??
    response.data
  )
}
