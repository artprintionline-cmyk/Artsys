import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import financeiroService, { type FinanceiroLancamento } from '../../services/financeiroService'
import mercadoPagoService from '../../services/mercadoPagoService'
import whatsappService from '../../services/whatsappService'
import { useToast } from '../toast/ToastProvider'

type PixState = {
  status: 'pendente' | 'pago' | 'cancelado'
  qr_code_base64: string | null
  qr_code_text: string | null
  payment_id: string | null
}

function formatMoney(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

function statusLabel(s: PixState['status']) {
  if (s === 'pago') return 'Pago'
  if (s === 'cancelado') return 'Cancelado'
  return 'Aguardando pagamento'
}

export default function FinanceiroPix() {
  const { id } = useParams()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [lancamento, setLancamento] = useState<FinanceiroLancamento | null>(null)
  const [pix, setPix] = useState<PixState | null>(null)

  const valor = useMemo(() => (lancamento ? formatMoney(lancamento.valor) : 'R$ 0,00'), [lancamento])

  const loadLancamento = async () => {
    if (!id) return
    const res = await financeiroService.get(id)
    const data = res.data?.data ?? res.data
    setLancamento(data as FinanceiroLancamento)
  }

  const gerarOuAtualizarPix = async () => {
    if (!id) return
    const res = await mercadoPagoService.gerarPix(id)
    const data = res.data?.data ?? res.data
    setPix({
      payment_id: data.payment_id ?? null,
      status: data.status,
      qr_code_base64: data.qr_code_base64 ?? null,
      qr_code_text: data.qr_code_text ?? null,
    })
  }

  const loadAll = async () => {
    setLoading(true)
    try {
      await loadLancamento()
      await gerarOuAtualizarPix()
    } catch (e: any) {
      showToast(e?.message ?? 'Erro ao gerar PIX', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadAll()
  }, [id])

  useEffect(() => {
    if (!pix || pix.status !== 'pendente') return

    const t = window.setInterval(() => {
      gerarOuAtualizarPix().catch(() => {})
    }, 8000)

    return () => window.clearInterval(t)
  }, [pix?.status, id])

  const jaPaguei = () => {
    showToast('Ok! Aguardando confirmação do pagamento.')
  }

  const reenviarWhatsApp = async () => {
    if (!lancamento) return
    try {
      await whatsappService.enviarPix(lancamento.id)
      showToast('Reenvio do PIX para WhatsApp agendado.')
    } catch (e: any) {
      const msg = e?.response?.data?.message ?? e?.message ?? 'Erro ao reenviar PIX'
      showToast(msg, 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Pagamento PIX">
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : !lancamento ? (
          <div className="text-sm text-gray-700">Lançamento não encontrado.</div>
        ) : (
          <div className="space-y-6">
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <div className="text-sm text-gray-800">
                <div>
                  <span className="font-semibold text-black">Cliente:</span> {lancamento.cliente?.nome ?? '-'}
                </div>
                <div>
                  <span className="font-semibold text-black">OS:</span> {lancamento.ordem_servico?.numero ?? lancamento.ordem_servico?.numero_os ?? '-'}
                </div>
                <div className="mt-3 text-lg font-bold text-black">Valor: {valor}</div>
              </div>
            </div>

            {!pix ? (
              <div className="text-sm text-gray-700">Gerando PIX...</div>
            ) : (
              <div className="bg-white border border-gray-200 rounded-lg p-6">
                <div className="text-center">
                  <div className="text-sm text-gray-700 mb-2">Status</div>
                  <div className="text-lg font-bold text-black mb-6">{statusLabel(pix.status)}</div>

                  {pix.qr_code_base64 ? (
                    <img
                      src={`data:image/png;base64,${pix.qr_code_base64}`}
                      alt="QR Code PIX"
                      className="mx-auto w-72 h-72 border border-gray-200 rounded-lg"
                    />
                  ) : (
                    <div className="text-sm text-gray-700">QR Code indisponível.</div>
                  )}

                  <div className="mt-6 text-sm text-gray-700">Código PIX (copia e cola)</div>
                  <textarea
                    className="w-full mt-2 border border-gray-200 rounded-md p-3 text-sm text-black"
                    rows={4}
                    readOnly
                    value={pix.qr_code_text ?? ''}
                  />

                  <div className="mt-6 flex items-center justify-center gap-4">
                    <button
                      type="button"
                      onClick={jaPaguei}
                      className="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded-md text-sm"
                      disabled={pix.status !== 'pendente'}
                    >
                      Já paguei
                    </button>

                    <button
                      type="button"
                      onClick={reenviarWhatsApp}
                      className="bg-white border border-gray-300 hover:bg-gray-50 text-black px-4 py-2 rounded-md text-sm"
                      disabled={pix.status !== 'pendente'}
                    >
                      Reenviar no WhatsApp
                    </button>

                    <Link to={`/financeiro/${id}`} className="text-black hover:underline text-sm">
                      Voltar
                    </Link>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </PageContainer>
    </div>
  )
}
