import api from './api'

export type PixGerado = {
  payment_id: string | null
  status: 'pendente' | 'pago' | 'cancelado'
  qr_code_base64: string | null
  qr_code_text: string | null
}

const mercadoPagoService = {
  gerarPix: (financeiro_lancamento_id: number | string) =>
    api.post('/mercado-pago/gerar-pix', { financeiro_lancamento_id }),
}

export default mercadoPagoService
