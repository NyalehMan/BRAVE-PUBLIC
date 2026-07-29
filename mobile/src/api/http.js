import axios from 'axios'
import { Preferences } from '@capacitor/preferences'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  withCredentials: false,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

http.interceptors.request.use(async (config) => {
  const { value } = await Preferences.get({ key: 'auth_token' })

  if (value) {
    config.headers.Authorization = `Bearer ${value}`
  }

  return config
})

export default http
