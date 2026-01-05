import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import financeiroService, { type FinanceiroLancamento } from '../../services/financeiroService'
import { useToast } from '../toast/ToastProvider'

function formatMoney(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

function formatDate(iso?: string | null) {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('pt-BR')
}

export default function FinanceiroShow() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [item, setItem] = useState<FinanceiroLancamento | null>(null)

  const load = async () => {
    if (!id) return
    setLoading(true)
    try {
      const res = await financeiroService.get(id)
      const data = res.data?.data ?? res.data
      setItem(data as FinanceiroLancamento)
    } catch {
      setItem(null)
      showToast('Erro ao carregar lançamento', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const marcarPago = async () => {
    if (!id) return
    const ok = window.confirm('Marcar este lançamento como pago?')
    if (!ok) return

    try {
      await financeiroService.marcarComoPago(id)
      showToast('Lançamento marcado como pago')
      await load()
    } catch {
      showToast('Erro ao marcar como pago', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Financeiro" actionLabel="Voltar" onAction={() => navigate('/financeiro')}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : !item ? (
          <div className="text-sm text-gray-700">Lançamento não encontrado.</div>
        ) : (
          <div className="space-y-4">
            <div className="text-sm text-gray-800">
              <div className="mb-2">
                <span className="font-semibold text-black">Cliente:</span> {item.cliente?.nome ?? '-'}
              </div>
              <div className="mb-2">
                <span className="font-semibold text-black">Ordem de Serviço:</span> {item.ordem_servico?.numero ?? item.ordem_servico?.numero_os ?? '-'}
              </div>
              <div className="mb-2">
                <span className="font-semibold text-black">Tipo:</span> {item.tipo}
              </div>
              <div className="mb-2">
                <span className="font-semibold text-black">Valor:</span> {formatMoney(item.valor)}
              </div>
              <div className="mb-2">
                <span className="font-semibold text-black">Status:</span> {item.status}
              </div>
              <div className="mb-2">
                <span className="font-semibold text-black">Vencimento:</span> {formatDate(item.data_vencimento)}
              </div>
              <div>
                <span className="font-semibold text-black">Pagamento:</span> {formatDate(item.data_pagamento)}
              </div>
            </div>

            <div className="flex gap-4">
              <Link to="/financeiro" className="text-black hover:underline">
                Voltar para lista
              </Link>
              <Link
                to={`/financeiro/${item.id}/pix`}
                className="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded-md text-sm"
              >
                Pagar com PIX
              </Link>
              <button
                onClick={marcarPago}
                className="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded-md text-sm disabled:opacity-60"
                disabled={item.status === 'pago' || item.status === 'cancelado'}
              >
                Marcar como Pago
              </button>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
