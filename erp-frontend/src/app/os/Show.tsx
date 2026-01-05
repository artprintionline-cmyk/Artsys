import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import Button from '../../components/Button'
import ordensServicoService from '../../services/ordensServicoService'
import { useToast } from '../toast/ToastProvider'

type OSItem = {
  id: number
  quantidade: number | string
  valor_unitario: number | string
  valor_total: number | string
  produto?: { id: number; nome: string } | null
}

type OS = {
  id: number
  numero_os: string
  cliente?: { id: number; nome: string; telefone?: string } | null
  status: string
  observacoes?: string | null
  valor_total: number | string
  created_at: string
  itens: OSItem[]
}

function money(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

const statusOptions = [
  { value: 'aberta', label: 'Aberta' },
  { value: 'em_andamento', label: 'Em andamento' },
  { value: 'finalizada', label: 'Finalizada' },
  { value: 'cancelada', label: 'Cancelada' },
]

export default function OrdemServicoShow() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [os, setOs] = useState<OS | null>(null)
  const [status, setStatus] = useState('')

  const load = async () => {
    if (!id) return
    setLoading(true)
    try {
      const res = await ordensServicoService.get(id)
      const payload = res.data?.data ?? res.data
      setOs(payload)
      setStatus(payload?.status ?? 'aberta')
    } catch {
      showToast('Erro ao carregar OS', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [id])

  const canSaveStatus = useMemo(() => {
    if (!os) return false
    return status && status !== os.status
  }, [os, status])

  const saveStatus = async () => {
    if (!id) return
    setSaving(true)
    try {
      await ordensServicoService.update(id, { status })
      showToast('Status atualizado com sucesso')
      await load()
    } catch {
      showToast('Erro ao atualizar status', 'error')
    } finally {
      setSaving(false)
    }
  }

  const cancelar = async () => {
    if (!id) return
    const ok = window.confirm('Deseja cancelar esta OS?')
    if (!ok) return

    try {
      await ordensServicoService.cancel(id)
      showToast('OS cancelada')
      navigate('/ordens-servico')
    } catch {
      showToast('Erro ao cancelar OS', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Visualizar OS">
        {loading || !os ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="text-sm">
                <div className="text-gray-600">Nº OS</div>
                <div className="text-black font-semibold">{os.numero_os}</div>
              </div>
              <div className="text-sm">
                <div className="text-gray-600">Cliente</div>
                <div className="text-black font-semibold">{os.cliente?.nome ?? '-'}</div>
              </div>
              <div className="text-sm">
                <div className="text-gray-600">Status</div>
                <div className="text-black font-semibold">{os.status}</div>
              </div>
              <div className="text-sm">
                <div className="text-gray-600">Valor total</div>
                <div className="text-black font-semibold">{money(os.valor_total)}</div>
              </div>
            </div>

            {os.observacoes ? (
              <div className="text-sm">
                <div className="text-gray-600">Observações</div>
                <div className="text-black">{os.observacoes}</div>
              </div>
            ) : null}

            <div className="overflow-auto border border-gray-200 rounded-lg">
              <table className="w-full text-sm">
                <thead className="bg-gray-100 text-black">
                  <tr>
                    <th className="p-3 text-left">Produto</th>
                    <th className="p-3 text-left">Qtd</th>
                    <th className="p-3 text-left">Vlr. unit.</th>
                    <th className="p-3 text-left">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {os.itens.map((it) => (
                    <tr key={it.id} className="border-t">
                      <td className="p-3 text-black">{it.produto?.nome ?? '-'}</td>
                      <td className="p-3 text-gray-800">{it.quantidade}</td>
                      <td className="p-3 text-gray-800">{money(it.valor_unitario)}</td>
                      <td className="p-3 text-gray-800">{money(it.valor_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label className="block">
                <div className="mb-2 text-sm font-medium text-black">Alterar status</div>
                <select
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                >
                  {statusOptions.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </select>
              </label>

              <div className="flex items-end gap-3">
                <Button type="button" fullWidth={false} loading={saving} onClick={saveStatus} className={!canSaveStatus ? 'opacity-60 cursor-not-allowed' : ''}>
                  Salvar status
                </Button>
                <Button type="button" variant="secondary" fullWidth={false} onClick={() => navigate('/ordens-servico')}>
                  Voltar
                </Button>
              </div>
            </div>

            <div>
              <Button type="button" variant="secondary" fullWidth={false} onClick={cancelar}>
                Cancelar OS
              </Button>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
