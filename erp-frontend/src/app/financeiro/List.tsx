import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import financeiroService, { type FinanceiroLancamento } from '../../services/financeiroService'
import { useToast } from '../toast/ToastProvider'

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function formatMoney(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

function formatDate(iso: string) {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('pt-BR')
}

export default function FinanceiroList() {
  const { showToast } = useToast()
  const [loading, setLoading] = useState(true)
  const [items, setItems] = useState<FinanceiroLancamento[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await financeiroService.list()
      setItems(asArray(res.data) as FinanceiroLancamento[])
    } catch {
      setItems([])
      showToast('Erro ao carregar lançamentos', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const marcarPago = async (id: number) => {
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
      <PageContainer title="Financeiro">
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Cliente</th>
                  <th className="p-3 text-left">OS</th>
                  <th className="p-3 text-left">Tipo</th>
                  <th className="p-3 text-left">Valor</th>
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-left">Vencimento</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {items.map((l) => (
                  <tr key={l.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{l.cliente?.nome ?? '-'}</td>
                    <td className="p-3 text-gray-800">{l.ordem_servico?.numero ?? l.ordem_servico?.numero_os ?? '-'}</td>
                    <td className="p-3 text-gray-800">{l.tipo}</td>
                    <td className="p-3 text-gray-800">{formatMoney(l.valor)}</td>
                    <td className="p-3 text-gray-800">{l.status}</td>
                    <td className="p-3 text-gray-800">{formatDate(l.data_vencimento)}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`/financeiro/${l.id}`} className="text-black hover:underline mr-4">
                        Visualizar
                      </Link>
                      <button
                        onClick={() => marcarPago(l.id)}
                        className="text-black hover:underline disabled:opacity-60"
                        disabled={l.status === 'pago' || l.status === 'cancelado'}
                      >
                        Marcar como Pago
                      </button>
                    </td>
                  </tr>
                ))}
                {items.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={7}>
                      Nenhum lançamento encontrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
