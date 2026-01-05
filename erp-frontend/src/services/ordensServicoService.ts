import api from './api'

export type OsItemPayload = {
  quantidade: number
  produto_id: number
}

export type OrdemServicoCreatePayload = {
  cliente_id: number
  observacoes?: string
  itens: OsItemPayload[]
}

export type OrdemServicoUpdatePayload = {
  status?: string
  observacoes?: string
  itens?: OsItemPayload[]
}

const base = '/ordens-servico'

export default {
  list() {
    return api.get(base)
  },
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
  create(payload: OrdemServicoCreatePayload) {
    return api.post(base, payload)
  },
  update(id: number | string, payload: OrdemServicoUpdatePayload) {
    return api.put(`${base}/${id}`, payload)
  },
  cancel(id: number | string) {
    return api.delete(`${base}/${id}`)
  },

  updateStatusDestino(id: number | string, status_destino: string) {
    return api.put(`${base}/${id}/status`, { status_destino })
  },

  getWhatsAppHistorico(id: number | string) {
    return api.get(`${base}/${id}/whatsapp`)
  },

  enviarWhatsApp(id: number | string, payload: { tipo: 'texto' | 'pix_qr'; mensagem?: string | null }) {
    return api.post(`${base}/${id}/whatsapp/enviar`, payload)
  },
}
