import axios from 'axios'

const productionBaseUrl = typeof window === 'undefined' ? '' : window.location.origin

const configuredBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ||
  (import.meta.env.DEV ? 'http://127.0.0.1:8000' : productionBaseUrl)
)
  .trim()
  .replace(/\/+$/, '')
  .replace(/\/api$/, '')

const http = axios.create({
  baseURL: configuredBaseUrl,
  timeout: 45000,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default http
