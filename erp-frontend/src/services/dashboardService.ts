import api from './api'

export type DashboardSummary = {
  total_os?: number
  em_producao?: number
  faturado?: number
  pendencias?: number
}

export type DashboardPeriodo = 'hoje' | 'semana' | 'mes'

export type DashboardResumo = {
  periodo: DashboardPeriodo
  kpis: {
    os_abertas: number
    os_em_producao: number
    os_finalizadas_periodo: number
    faturamento_pago_periodo: number
    valor_pendente: number
    inadimplencia_total: number
  }
}

export type DashboardOperacional = {
  periodo: DashboardPeriodo
  os_por_status: Record<string, number>
  tempo_medio_producao_min: number
  os_paradas_por_coluna: Record<string, number>
  gargalos: {
    coluna_com_mais_os: string | null
    os_parada_mais_tempo: null | {
      id: number
      numero: string
      status: string
      dias_parada: number
    }
  }
}

export type DashboardFinanceiro = {
  periodo: DashboardPeriodo
  totais: {
    pago: number
    pendente: number
    cancelado: number
    ticket_medio_os: number
  }
  tendencia: {
    faturado_pago: { atual: number; anterior: number; direcao: 'up' | 'down' | 'flat' }
    ticket_medio_os: { atual: number; anterior: number; direcao: 'up' | 'down' | 'flat' }
  }
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

export async function getDashboardResumo(periodo: DashboardPeriodo): Promise<DashboardResumo | null> {
  try {
    const res = await api.get('/dashboard/resumo', { params: { periodo } })
    return res.data?.data || null
  } catch {
    return null
  }
}

export async function getDashboardOperacional(periodo: DashboardPeriodo): Promise<DashboardOperacional | null> {
  try {
    const res = await api.get('/dashboard/operacional', { params: { periodo } })
    return res.data?.data || null
  } catch {
    return null
  }
}

export async function getDashboardFinanceiro(periodo: DashboardPeriodo): Promise<DashboardFinanceiro | null> {
  try {
    const res = await api.get('/dashboard/financeiro', { params: { periodo } })
    return res.data?.data || null
  } catch {
    return null
  }
}
