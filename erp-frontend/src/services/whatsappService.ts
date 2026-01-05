import api from './api'

export type WhatsAppConversaResumo = {
  numero: string
  cliente?: { id: number; nome: string } | null
  ultima_mensagem?: string | null
  ultima_direcao?: string | null
  ultima_em?: string | null
}

export type WhatsAppMensagem = {
  id: number
  direcao?: 'saida' | 'entrada' | string | null
  tipo?: string | null
  mensagem: string
  status: string
  created_at: string
}

const whatsappService = {
  listConversas: () => api.get('/whatsapp/conversas'),
  getConversa: (numero: string) => api.get(`/whatsapp/conversas/${encodeURIComponent(numero)}`),
  enviarMensagem: (numero: string, mensagem: string) => api.post(`/whatsapp/conversas/${encodeURIComponent(numero)}/mensagens`, { mensagem }),
  enviarPix: (financeiroLancamentoId: number | string) =>
    api.post('/whatsapp/enviar-pix', { financeiro_lancamento_id: financeiroLancamentoId }),
}

export default whatsappService
