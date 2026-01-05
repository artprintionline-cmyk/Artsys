import api from './api'

export type CompraItemTipo = 'material' | 'insumo' | 'equipamento'

export interface CompraItemPayload {
  tipo: CompraItemTipo
  nome: string
  unidade_compra?: string | null
  ativo?: boolean
}

const base = '/compras/itens'

// Produto (planejamento) precisa listar itens mesmo sem permissão de Compras
const basePlanejamento = '/produtos/itens-planejamento'

export default {
  list(params?: { tipo?: CompraItemTipo }) {
    return api.get(base, { params })
  },
  listPlanejamento(params?: { tipo?: CompraItemTipo }) {
    return api.get(basePlanejamento, { params })
  },
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
}
