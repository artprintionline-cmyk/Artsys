import axios from 'axios'

// Use relative base URL so built SPA is served by the same Laravel server
const api = axios.create({
  baseURL: '/api/v1',
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
      // optional: trigger a full reload to let app auth detect logged-out state
      try {
        window.location.replace('/login')
      } catch (e) {}
    }
    if (status === 403) {
      // tenant or forbidden
      // keep token but inform user; do not redirect automatically
    }

    return Promise.reject(err)
  }
)

export default api
