import axios from 'axios'

function resolveBaseURL() {
  const envURL = (import.meta as any)?.env?.VITE_API_URL
  if (envURL && typeof envURL === 'string' && envURL.trim() !== '') {
    return envURL.trim().replace(/\/$/, '')
  }

  // Dev: talk directly to local Laravel server
  if ((import.meta as any)?.env?.DEV) {
    return 'http://127.0.0.1:8000/api/v1'
  }

  // Prod/build: SPA served by the same Laravel server
  return '/api/v1'
}

// Use relative base URL in build, absolute in dev
const api = axios.create({
  baseURL: resolveBaseURL(),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor: centralize 401/403 handling and detect HTML redirects
api.interceptors.response.use(
  (res) => {
    // If the server returned HTML (possible redirect to login), treat as error
    const contentType = res.headers['content-type'] || ''
    if (contentType.includes('text/html')) {
      return Promise.reject(new Error('Resposta inesperada do servidor (HTML). Provável redirect do backend'))
    }
    return res
  },
  (err) => {
    const status = err?.response?.status
    // Clear token on unauthorized to avoid redirect loops
    if (status === 401) {
      localStorage.removeItem('token')
      // Emit a logout event so the app can react and clear auth state
      try {
        window.dispatchEvent(new CustomEvent('erp:logout'))
      } catch (e) {
        // fallback: no-op
      }
    }
    if (status === 403) {
      // tenant or forbidden
      // keep token but inform user; do not redirect automatically
    }

    return Promise.reject(err)
  }
)

export default api
