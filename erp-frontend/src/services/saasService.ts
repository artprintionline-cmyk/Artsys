import api from './api'

export type SaasAssinaturaStatus = {
  read_only: boolean
  motivo: string | null
  status: string | null
  trial_expired: boolean
  expires_at: string | null
  plano: { id: number; nome: string; preco: number; ativo: boolean } | null
  limites: Record<string, any>
}

export type Plano = {
  id: number
  nome: string
  preco: number
  limites: Record<string, any>
}

export async function getSaasAssinatura(): Promise<SaasAssinaturaStatus> {
  const res = await api.get('/saas/assinatura')
  return res.data?.data ?? res.data
}

export async function getPlanos(): Promise<Plano[]> {
  const res = await api.get('/saas/planos')
  return res.data?.data ?? []
}

export async function simularPagamento(plano_id: number, meses: number, referencia?: string) {
  const res = await api.post('/saas/assinatura/simular-pagamento', { plano_id, meses, referencia })
  return res.data?.data ?? res.data
}

export async function setAssinaturaStatus(status: 'trial' | 'ativa' | 'suspensa' | 'cancelada') {
  const res = await api.post('/saas/assinatura/status', { status })
  return res.data?.data ?? res.data
}
