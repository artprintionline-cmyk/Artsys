import api from './api'

export type DashboardSummary = {
  total_os?: number
  em_producao?: number
  faturado?: number
  pendencias?: number
}

export async function getDashboardSummary(): Promise<DashboardSummary> {
  try {
    const res = await api.get('/dashboard/summary')
    return res.data || {}
  } catch (err) {
    // If the backend endpoint isn't available, fail gracefully
    return {}
  }
}
