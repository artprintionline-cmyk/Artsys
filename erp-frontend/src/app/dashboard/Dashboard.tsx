import React, { useEffect, useState } from 'react'
import {
  Layers,
  Play,
  CheckCircle,
  DollarSign,
  Clock,
  AlertTriangle,
  ArrowUp,
  ArrowDown,
  Minus,
} from 'lucide-react'
import {
  DashboardPeriodo,
  getDashboardFinanceiro,
  getDashboardOperacional,
  getDashboardResumo,
} from '../../services/dashboardService'
import PageContainer from '../../components/PageContainer'

function formatMoney(value: number) {
  try {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0)
  } catch {
    return `R$ ${(value || 0).toFixed(2)}`
  }
}

function formatMinutes(min: number) {
  const m = Math.max(0, Math.floor(min || 0))
  if (m < 60) return `${m} min`
  const h = Math.floor(m / 60)
  const mm = m % 60
  return `${h}h ${mm}m`
}

function statusLabel(status: string | null | undefined) {
  switch (status) {
    case 'aberta':
      return 'Aberta'
    case 'em_producao':
      return 'Em produção'
    case 'aguardando_pagamento':
      return 'Aguardando pagamento'
    case 'finalizada':
      return 'Finalizada'
    case 'cancelada':
      return 'Cancelada'
    default:
      return status || '-'
  }
}

function TrendIcon({ dir }: { dir: 'up' | 'down' | 'flat' }) {
  if (dir === 'up') return <ArrowUp className="w-4 h-4" />
  if (dir === 'down') return <ArrowDown className="w-4 h-4" />
  return <Minus className="w-4 h-4" />
}

function KpiCard({ title, value, Icon }: { title: string; value: React.ReactNode; Icon: any }) {
  return (
    <div className="p-6 bg-white rounded-xl shadow-sm flex items-center justify-between">
      <div>
        <div className="text-sm text-gray-800">{title}</div>
        <div className="text-2xl font-semibold text-black mt-2">{value}</div>
      </div>
      <div className="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center text-yellow-500">
        <Icon className="w-6 h-6" />
      </div>
    </div>
  )
}

export default function Dashboard() {
  const [periodo, setPeriodo] = useState<DashboardPeriodo>('mes')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [resumo, setResumo] = useState<any>(null)
  const [operacional, setOperacional] = useState<any>(null)
  const [financeiro, setFinanceiro] = useState<any>(null)

  useEffect(() => {
    let mounted = true
    setLoading(true)

    Promise.all([getDashboardResumo(periodo), getDashboardOperacional(periodo), getDashboardFinanceiro(periodo)])
      .then(([r, o, f]) => {
        if (!mounted) return
        setResumo(r)
        setOperacional(o)
        setFinanceiro(f)

        if (!r || !o || !f) {
          setError('Não foi possível carregar o dashboard executivo')
        } else {
          setError(null)
        }
      })
      .catch(() => {
        if (!mounted) return
        setError('Não foi possível carregar o dashboard executivo')
      })
      .finally(() => mounted && setLoading(false))

    return () => {
      mounted = false
    }
  }, [periodo])

  const kpis = resumo?.kpis
  const osPorStatus = operacional?.os_por_status || {}
  const paradasPorColuna = operacional?.os_paradas_por_coluna || {}
  const gargalos = operacional?.gargalos
  const totais = financeiro?.totais
  const tendencia = financeiro?.tendencia

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Dashboard Executivo">
        <div className="flex items-center justify-between gap-4 mb-6">
          <div className="text-sm text-gray-700">Visão rápida por período</div>
          <select
            className="border rounded-lg px-3 py-2 text-sm bg-white"
            value={periodo}
            onChange={(e) => setPeriodo(e.target.value as DashboardPeriodo)}
          >
            <option value="hoje">Hoje</option>
            <option value="semana">Semana</option>
            <option value="mes">Mês</option>
          </select>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {loading ? (
            Array.from({ length: 6 }).map((_, idx) => (
              <div key={idx} className="bg-gray-50 rounded-xl animate-pulse h-28" />
            ))
          ) : (
            <>
              <KpiCard title="OS Abertas" value={kpis?.os_abertas ?? 0} Icon={Layers} />
              <KpiCard title="Em Produção" value={kpis?.os_em_producao ?? 0} Icon={Play} />
              <KpiCard title="Finalizadas (período)" value={kpis?.os_finalizadas_periodo ?? 0} Icon={CheckCircle} />
              <KpiCard title="Faturamento Pago (período)" value={formatMoney(kpis?.faturamento_pago_periodo ?? 0)} Icon={DollarSign} />
              <KpiCard title="Valor Pendente (período)" value={formatMoney(kpis?.valor_pendente ?? 0)} Icon={Clock} />
              <KpiCard title="Inadimplência (total no período)" value={formatMoney(kpis?.inadimplencia_total ?? 0)} Icon={AlertTriangle} />
            </>
          )}
        </div>

        {error && <div className="mt-4 text-sm text-red-600">{error}</div>}

        <div className="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-white rounded-xl shadow-sm p-6">
            <div className="text-lg font-semibold text-black">Operação</div>

            <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Tempo médio de produção</div>
                <div className="text-black font-semibold mt-1">{formatMinutes(operacional?.tempo_medio_producao_min ?? 0)}</div>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Coluna com mais OS</div>
                <div className="text-black font-semibold mt-1">{statusLabel(gargalos?.coluna_com_mais_os)}</div>
              </div>
            </div>

            <div className="mt-6">
              <div className="text-sm font-semibold text-black">OS por status</div>
              <div className="mt-2 text-sm text-gray-700 grid grid-cols-2 gap-2">
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Aberta</span>
                  <span className="font-semibold text-black">{osPorStatus.aberta ?? 0}</span>
                </div>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Em produção</span>
                  <span className="font-semibold text-black">{osPorStatus.em_producao ?? 0}</span>
                </div>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Aguardando pagamento</span>
                  <span className="font-semibold text-black">{osPorStatus.aguardando_pagamento ?? 0}</span>
                </div>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Finalizada</span>
                  <span className="font-semibold text-black">{osPorStatus.finalizada ?? 0}</span>
                </div>
              </div>
            </div>

            <div className="mt-6">
              <div className="text-sm font-semibold text-black">OS paradas por coluna</div>
              <div className="mt-2 text-sm text-gray-700 grid grid-cols-3 gap-2">
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Aberta</span>
                  <span className="font-semibold text-black">{paradasPorColuna.aberta ?? 0}</span>
                </div>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Produção</span>
                  <span className="font-semibold text-black">{paradasPorColuna.em_producao ?? 0}</span>
                </div>
                <div className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <span>Pagamento</span>
                  <span className="font-semibold text-black">{paradasPorColuna.aguardando_pagamento ?? 0}</span>
                </div>
              </div>
            </div>

            <div className="mt-6">
              <div className="text-sm font-semibold text-black">OS parada há mais tempo</div>
              <div className="mt-2 text-sm text-gray-700 bg-gray-50 rounded-lg px-3 py-3">
                {gargalos?.os_parada_mais_tempo ? (
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="text-black font-semibold">OS #{gargalos.os_parada_mais_tempo.numero}</div>
                      <div className="text-gray-700">Status: {statusLabel(gargalos.os_parada_mais_tempo.status)}</div>
                    </div>
                    <div className="text-black font-semibold">{gargalos.os_parada_mais_tempo.dias_parada}d</div>
                  </div>
                ) : (
                  <div>-</div>
                )}
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl shadow-sm p-6">
            <div className="text-lg font-semibold text-black">Financeiro</div>

            <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Pago (período)</div>
                <div className="text-black font-semibold mt-1">{formatMoney(totais?.pago ?? 0)}</div>
                {tendencia?.faturado_pago && (
                  <div className="mt-2 flex items-center gap-2 text-gray-700">
                    <TrendIcon dir={tendencia.faturado_pago.direcao} />
                    <span className="text-xs">
                      vs anterior: {formatMoney(tendencia.faturado_pago.anterior ?? 0)}
                    </span>
                  </div>
                )}
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Pendente (período)</div>
                <div className="text-black font-semibold mt-1">{formatMoney(totais?.pendente ?? 0)}</div>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Cancelado (período)</div>
                <div className="text-black font-semibold mt-1">{formatMoney(totais?.cancelado ?? 0)}</div>
              </div>
              <div className="bg-gray-50 rounded-lg p-4">
                <div className="text-gray-700">Ticket médio (OS pagas)</div>
                <div className="text-black font-semibold mt-1">{formatMoney(totais?.ticket_medio_os ?? 0)}</div>
                {tendencia?.ticket_medio_os && (
                  <div className="mt-2 flex items-center gap-2 text-gray-700">
                    <TrendIcon dir={tendencia.ticket_medio_os.direcao} />
                    <span className="text-xs">
                      vs anterior: {formatMoney(tendencia.ticket_medio_os.anterior ?? 0)}
                    </span>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </PageContainer>
    </div>
  )
}
