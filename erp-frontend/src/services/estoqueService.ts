import api from './api'

export type EstoqueAjustePayload = {
  produto_id: number
  tipo: 'entrada' | 'saida'
  quantidade: number
}

const base = '/estoque'

export default {
  list() {
    return api.get(base)
  },
  ajuste(payload: EstoqueAjustePayload) {
    return api.post(`${base}/ajuste`, payload)
  },
}
