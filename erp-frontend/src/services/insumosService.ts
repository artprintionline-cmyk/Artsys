import api from './api'

export interface InsumoPayload {
  nome: string
  sku?: string | null
  custo_unitario?: number
  unidade_medida?: string
  unidade_consumo?: string
  tipo_embalagem?: 'Pacote' | 'Caixa' | 'Unidade' | 'Kg' | 'Litro'
  valor_embalagem?: number
  quantidade_por_embalagem?: number
  rendimento_total?: number
  estoque_atual?: number | null
  controla_estoque?: boolean
  ativo?: boolean
}

const base = '/insumos'

export default {
  list() {
    return api.get(base)
  },
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
  create(payload: InsumoPayload) {
    return api.post(base, payload)
  },
  update(id: number | string, payload: Partial<InsumoPayload>) {
    return api.put(`${base}/${id}`, payload)
  },
  remove(id: number | string) {
    return api.delete(`${base}/${id}`)
  },
}
