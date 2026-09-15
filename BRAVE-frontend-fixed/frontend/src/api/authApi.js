import axios from 'axios'

const baseURL = (
  import.meta.env.VITE_API_BASE_URL ||
  'http://localhost:8000'
)
  .trim()
  .replace(/\/+$/, '')
  .replace(/\/api$/, '')

const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

function extractUser(data) {
  return data?.user ?? data
}

export async function login(credentials) {
  // This is the only place that should request a new CSRF cookie.
  // Calling it again after login can replace the authenticated session.
  await api.get('/sanctum/csrf-cookie')

  const response = await api.post('/login', {
    email: credentials.email,
    password: credentials.password,
  })

  return extractUser(response.data)
}

export async function getCurrentUser() {
  const response = await api.get('/api/niat/me')

  return extractUser(response.data)
}

export async function logout() {
  const response = await api.post('/logout')

  return response.data
}

export default api
