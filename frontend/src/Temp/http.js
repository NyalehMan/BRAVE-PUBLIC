import axios from 'axios'

const http = axios.create({
  baseURL: 'http://127.0.0.1:8000',
  // baseURL: import.meta.env.VITE_API_BASE_URL,

  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default http
