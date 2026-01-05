import { useEffect, useMemo, useState } from 'react'
import { useAuth } from '../auth/useAuth'
import { getPlanos, getSaasAssinatura, Plano, SaasAssinaturaStatus, setAssinaturaStatus, simularPagamento } from '../../services/saasService'

function formatDate(iso: string | null) {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString()
}

export default function AssinaturaPage() {
  const { hasPerm } = useAuth()
  const canManage = hasPerm('saas.manage')

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [assinatura, setAssinatura] = useState<SaasAssinaturaStatus | null>(null)
  const [planos, setPlanos] = useState<Plano[]>([])

  const limitesList = useMemo(() => {
    const lim = assinatura?.limites ?? {}
    const entries = Object.entries(lim)
    return entries
  }, [assinatura?.limites])

  const [planoId, setPlanoId] = useState<number | ''>('')
  const [meses, setMeses] = useState<number>(1)
  const [referencia, setReferencia] = useState<string>('')

  async function refresh() {
    setLoading(true)
    setError(null)
    try {
      const [a, p] = await Promise.all([getSaasAssinatura(), getPlanos()])
      setAssinatura(a)
      setPlanos(p)
      if (a?.plano?.id) setPlanoId(a.plano.id)
    } catch (e: any) {
      setError(e?.response?.data?.message ?? e?.message ?? 'Erro ao carregar assinatura')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    refresh()
  }, [])

  async function onSimularPagamento() {
    if (!planoId) {
      setError('Selecione um plano')
      return
    }
    try {
      await simularPagamento(Number(planoId), Number(meses), referencia || undefined)
      await refresh()
    } catch (e: any) {
      setError(e?.response?.data?.message ?? e?.message ?? 'Falha ao simular pagamento')
    }
  }

  async function onSetStatus(status: 'trial' | 'ativa' | 'suspensa' | 'cancelada') {
    try {
      await setAssinaturaStatus(status)
      await refresh()
    } catch (e: any) {
      setError(e?.response?.data?.message ?? e?.message ?? 'Falha ao alterar status')
    }
  }

  if (loading) {
    return <div className="text-sm text-gray-700">Carregando assinatura...</div>
  }

  return (
    <div className="max-w-4xl mx-auto space-y-4">
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <div className="text-lg font-semibold text-black">Plano e Assinatura</div>
        <div className="text-sm text-gray-700 mt-1">Status comercial e limites do seu plano.</div>

        {error && <div className="mt-4 text-sm text-red-700">{error}</div>}

        {assinatura?.read_only && (
          <div className="mt-4 bg-yellow-50 border border-yellow-200 rounded p-4">
            <div className="text-sm font-semibold text-black">Acesso somente leitura</div>
            <div className="text-sm text-gray-700 mt-1">{assinatura.motivo ?? 'Sua assinatura está em modo leitura.'}</div>
          </div>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="text-xs text-gray-500">Plano atual</div>
            <div className="text-base font-semibold text-black mt-1">{assinatura?.plano?.nome ?? '—'}</div>
            <div className="text-sm text-gray-700 mt-1">Preço: R$ {assinatura?.plano?.preco?.toFixed?.(2) ?? '0.00'}</div>
          </div>

          <div className="border border-gray-200 rounded-lg p-4">
            <div className="text-xs text-gray-500">Assinatura</div>
            <div className="text-base font-semibold text-black mt-1">{assinatura?.status ?? '—'}</div>
            <div className="text-sm text-gray-700 mt-1">Expira em: {formatDate(assinatura?.expires_at ?? null)}</div>
          </div>
        </div>
      </div>

      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <div className="text-lg font-semibold text-black">Limites</div>
        <div className="text-sm text-gray-700 mt-1">Os limites podem bloquear criações quando atingidos.</div>

        {limitesList.length === 0 ? (
          <div className="text-sm text-gray-600 mt-4">Nenhum limite configurado para este plano.</div>
        ) : (
          <div className="mt-4 overflow-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="text-left text-gray-600">
                  <th className="py-2 pr-4">Chave</th>
                  <th className="py-2">Valor</th>
                </tr>
              </thead>
              <tbody>
                {limitesList.map(([k, v]) => (
                  <tr key={k} className="border-t border-gray-100">
                    <td className="py-2 pr-4 text-gray-800">{k}</td>
                    <td className="py-2 text-gray-800">{String(v)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {canManage && (
        <div className="bg-white border border-gray-200 rounded-lg p-6">
          <div className="text-lg font-semibold text-black">Administração (manual/simulada)</div>
          <div className="text-sm text-gray-700 mt-1">Sem cobrança automática nesta fase.</div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
            <div>
              <label className="block text-xs text-gray-600">Plano</label>
              <select
                className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
                value={planoId}
                onChange={(e) => setPlanoId(e.target.value ? Number(e.target.value) : '')}
              >
                <option value="">Selecione</option>
                {planos.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.nome} — R$ {p.preco.toFixed(2)}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs text-gray-600">Meses</label>
              <input
                className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
                type="number"
                min={1}
                max={24}
                value={meses}
                onChange={(e) => setMeses(Number(e.target.value))}
              />
            </div>

            <div>
              <label className="block text-xs text-gray-600">Referência (opcional)</label>
              <input
                className="mt-1 w-full border border-gray-300 rounded px-3 py-2 text-sm"
                value={referencia}
                onChange={(e) => setReferencia(e.target.value)}
                placeholder="ex: boleto #123"
              />
            </div>
          </div>

          <div className="flex flex-wrap gap-2 mt-4">
            <button
              onClick={onSimularPagamento}
              className="px-4 py-2 rounded bg-black text-white text-sm hover:bg-gray-800"
            >
              Simular pagamento (ativar)
            </button>
            <button
              onClick={() => onSetStatus('trial')}
              className="px-4 py-2 rounded border border-gray-300 text-sm hover:bg-gray-50"
            >
              Marcar como trial
            </button>
            <button
              onClick={() => onSetStatus('suspensa')}
              className="px-4 py-2 rounded border border-gray-300 text-sm hover:bg-gray-50"
            >
              Suspender
            </button>
            <button
              onClick={() => onSetStatus('cancelada')}
              className="px-4 py-2 rounded border border-gray-300 text-sm hover:bg-gray-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
