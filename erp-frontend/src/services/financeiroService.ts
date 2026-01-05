import api from './api'

export type FinanceiroLancamento = {
  id: number
  cliente?: { id: number; nome: string } | any
  ordem_servico?: { id: number; numero?: string; numero_os?: string } | any
  tipo: 'receber' | 'pagar'
  descricao: string
  valor: number | string
  status: 'pendente' | 'pago' | 'cancelado'
  data_vencimento: string
  data_pagamento?: string | null
  created_at?: string
}

const financeiroService = {
  list: () => api.get('/financeiro'),
  get: (id: number | string) => api.get(`/financeiro/${id}`),
  marcarComoPago: (id: number | string) => api.put(`/financeiro/${id}`, { status: 'pago' }),
}

export default financeiroService
