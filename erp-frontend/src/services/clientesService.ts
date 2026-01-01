import api from './api'

export interface ClientePayload {
  nome: string
  telefone?: string
  email?: string
  documento?: string
  observacoes?: string
}

const base = '/clientes'

export default {
  list() {
    return api.get(base)
  },
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
  create(payload: ClientePayload) {
    return api.post(base, payload)
  },
  update(id: number | string, payload: ClientePayload) {
    return api.put(`${base}/${id}`, payload)
  },
  remove(id: number | string) {
    return api.delete(`${base}/${id}`)
  },
}
