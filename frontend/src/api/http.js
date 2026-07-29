import axios from 'axios'

const configuredBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ||
  'http://127.0.0.1:8000'
)
  .trim()
  .replace(/\/+$/, '')
  .replace(/\/api$/, '')

const http = axios.create({
  baseURL: configuredBaseUrl,
  timeout: 45000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default http