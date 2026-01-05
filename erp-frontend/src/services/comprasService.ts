import api from './api'

export type ItemTipo = 'material' | 'insumo' | 'equipamento'

export type CompraItemPayload = {
  nome: string
  tipo: ItemTipo
  unidade_compra: string
  quantidade: number
  valor_total: number
}

export interface CompraPayload {
  data: string // YYYY-MM-DD
  fornecedor?: string | null
  observacoes?: string | null
  itens: CompraItemPayload[]
}

const base = '/compras'

export default {
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
  create(payload: CompraPayload) {
    return api.post(base, payload)
  },
  remove(id: number | string) {
    return api.delete(`${base}/${id}`)
  },
}
