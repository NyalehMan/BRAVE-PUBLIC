import axios from 'axios'

const api = axios.create({
  baseURL:
    import.meta.env.VITE_API_BASE_URL ||
    'http://127.0.0.1:8000',

  withCredentials: true,
  withXSRFToken: true,

  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export async function login(credentials) {
  await api.get('/sanctum/csrf-cookie')

  const response = await api.post('/login', {
    email: credentials.email,
    password: credentials.password,
  })

  return response.data
}

export async function getCurrentUser() {
  const response = await api.get('/api/user')

  return response.data
}

export async function logout() {
  const response = await api.post('/logout')

  return response.data
}

export default api