import { useEffect, useMemo, useState } from 'react'
import PageContainer from '../../components/PageContainer'
import Button from '../../components/Button'
import clientesService from '../../services/clientesService'
import relatoriosService from '../../services/relatoriosService'
import { useToast } from '../toast/ToastProvider'

type Cliente = { id: number; nome: string }

type OsRow = {
  id: number
  numero_os: string
  cliente: { id: number; nome: string }
  status: string
  valor_total: number
  data_criacao: string
  data_finalizacao: string | null
}

type ProducaoRow = {
  produto: { id: number; nome: string }
  quantidade_utilizada: number
  origem: { tipo: 'os'; ordem_servico_id: number; numero_os: string; data_finalizacao: string }
}

type MaisUsadosRow = {
  produto: { id: number; nome: string }
  quantidade_total_utilizada: number
}

type FinanceiroRow = {
  id: number
  cliente: { id: number; nome: string }
  ordem_servico: { id: number; numero_os: string }
  valor: number
  vencimento: string
  status: string
}

type InadimplenciaRow = {
  id: number
  cliente: { id: number; nome: string }
  ordem_servico: { id: number; numero_os: string }
  valor_pendente: number
  vencimento: string
  dias_em_atraso: number
}

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function money(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number.isNaN(n) ? 0 : n)
}

function formatDate(iso: string | null | undefined) {
  if (!iso) return '-'
  try {
    const d = new Date(iso)
    if (Number.isNaN(d.getTime())) return String(iso)
    return d.toLocaleDateString('pt-BR')
  } catch {
    return String(iso)
  }
}

export default function RelatoriosPage() {
  const { showToast } = useToast()

  const [tab, setTab] = useState<'operacional' | 'financeiro'>('operacional')

  const [clientes, setClientes] = useState<Cliente[]>([])
  const [loadingClientes, setLoadingClientes] = useState(true)

  // OS
  const [osFilters, setOsFilters] = useState({ data_inicio: '', data_fim: '', cliente_id: '', status: '' })
  const [osLoading, setOsLoading] = useState(false)
  const [osRows, setOsRows] = useState<OsRow[]>([])
  const [osTotais, setOsTotais] = useState<{ quantidade: number; valor_total: number } | null>(null)

  // Produção
  const [prodFilters, setProdFilters] = useState({ data_inicio: '', data_fim: '' })
  const [prodLoading, setProdLoading] = useState(false)
  const [prodRows, setProdRows] = useState<ProducaoRow[]>([])
  const [prodTotais, setProdTotais] = useState<{ quantidade_total_utilizada: number; linhas: number } | null>(null)

  // Mais usados
  const [maisFilters, setMaisFilters] = useState({ data_inicio: '', data_fim: '' })
  const [maisLoading, setMaisLoading] = useState(false)
  const [maisRows, setMaisRows] = useState<MaisUsadosRow[]>([])
  const [maisTotais, setMaisTotais] = useState<{ quantidade_total_utilizada: number; produtos: number } | null>(null)

  // Contas a receber (financeiro)
  const [finFilters, setFinFilters] = useState({ data_inicio: '', data_fim: '', cliente_id: '', status: '' })
  const [finLoading, setFinLoading] = useState(false)
  const [finRows, setFinRows] = useState<FinanceiroRow[]>([])
  const [finTotais, setFinTotais] = useState<any>(null)

  // Faturamento
  const [fatFilters, setFatFilters] = useState({ data_inicio: '', data_fim: '' })
  const [fatLoading, setFatLoading] = useState(false)
  const [fatData, setFatData] = useState<{ total_faturado: number; total_pendente: number; total_cancelado: number } | null>(null)

  // Inadimplência
  const [inadFilters, setInadFilters] = useState({ cliente_id: '' })
  const [inadLoading, setInadLoading] = useState(false)
  const [inadRows, setInadRows] = useState<InadimplenciaRow[]>([])
  const [inadTotais, setInadTotais] = useState<{ quantidade: number; valor_total_pendente: number } | null>(null)

  useEffect(() => {
    const load = async () => {
      setLoadingClientes(true)
      try {
        const res = await clientesService.list()
        setClientes(asArray(res.data) as Cliente[])
      } catch {
        setClientes([])
        showToast('Erro ao carregar clientes', 'error')
      } finally {
        setLoadingClientes(false)
      }
    }
    load()
  }, [])

  const osApply = async () => {
    setOsLoading(true)
    try {
      const res = await relatoriosService.ordensServico({
        data_inicio: osFilters.data_inicio || undefined,
        data_fim: osFilters.data_fim || undefined,
        cliente_id: osFilters.cliente_id || undefined,
        status: osFilters.status || undefined,
      })
      setOsRows(asArray(res.data) as OsRow[])
      setOsTotais(res.data?.totais ?? null)
    } catch {
      setOsRows([])
      setOsTotais(null)
      showToast('Erro ao carregar relatório de OS', 'error')
    } finally {
      setOsLoading(false)
    }
  }

  const prodApply = async () => {
    setProdLoading(true)
    try {
      const res = await relatoriosService.producao({
        data_inicio: prodFilters.data_inicio || undefined,
        data_fim: prodFilters.data_fim || undefined,
      })
      setProdRows(asArray(res.data) as ProducaoRow[])
      setProdTotais(res.data?.totais ?? null)
    } catch {
      setProdRows([])
      setProdTotais(null)
      showToast('Erro ao carregar relatório de produção/consumo', 'error')
    } finally {
      setProdLoading(false)
    }
  }

  const maisApply = async () => {
    setMaisLoading(true)
    try {
      const res = await relatoriosService.produtosMaisUsados({
        data_inicio: maisFilters.data_inicio || undefined,
        data_fim: maisFilters.data_fim || undefined,
      })
      setMaisRows(asArray(res.data) as MaisUsadosRow[])
      setMaisTotais(res.data?.totais ?? null)
    } catch {
      setMaisRows([])
      setMaisTotais(null)
      showToast('Erro ao carregar relatório de produtos mais usados', 'error')
    } finally {
      setMaisLoading(false)
    }
  }

  const finApply = async () => {
    setFinLoading(true)
    try {
      const res = await relatoriosService.financeiro({
        data_inicio: finFilters.data_inicio || undefined,
        data_fim: finFilters.data_fim || undefined,
        cliente_id: finFilters.cliente_id || undefined,
        status: finFilters.status || undefined,
      })
      setFinRows(asArray(res.data) as FinanceiroRow[])
      setFinTotais(res.data?.totais ?? null)
    } catch {
      setFinRows([])
      setFinTotais(null)
      showToast('Erro ao carregar contas a receber', 'error')
    } finally {
      setFinLoading(false)
    }
  }

  const fatApply = async () => {
    setFatLoading(true)
    try {
      const res = await relatoriosService.faturamento({
        data_inicio: fatFilters.data_inicio || undefined,
        data_fim: fatFilters.data_fim || undefined,
      })
      setFatData(res.data?.data ?? null)
    } catch {
      setFatData(null)
      showToast('Erro ao carregar faturamento', 'error')
    } finally {
      setFatLoading(false)
    }
  }

  const inadApply = async () => {
    setInadLoading(true)
    try {
      const res = await relatoriosService.inadimplencia({
        cliente_id: inadFilters.cliente_id || undefined,
      })
      setInadRows(asArray(res.data) as InadimplenciaRow[])
      setInadTotais(res.data?.totais ?? null)
    } catch {
      setInadRows([])
      setInadTotais(null)
      showToast('Erro ao carregar inadimplência', 'error')
    } finally {
      setInadLoading(false)
    }
  }

  const finTotalsLine = useMemo(() => {
    if (!finTotais) return null
    return {
      total: money(finTotais.valor_total ?? 0),
      pago: money(finTotais.total_pago ?? 0),
      pendente: money(finTotais.total_pendente ?? 0),
      cancelado: money(finTotais.total_cancelado ?? 0),
    }
  }, [finTotais])

  const sectionClass = 'bg-white border border-gray-200 rounded-lg p-4'

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Relatórios">
        <div className="flex gap-2 mb-6">
          <button
            type="button"
            onClick={() => setTab('operacional')}
            className={`px-4 py-2 rounded-md text-sm ${tab === 'operacional' ? 'bg-yellow-200 text-black font-semibold' : 'bg-gray-100 text-black'}`}
          >
            Operacional
          </button>
          <button
            type="button"
            onClick={() => setTab('financeiro')}
            className={`px-4 py-2 rounded-md text-sm ${tab === 'financeiro' ? 'bg-yellow-200 text-black font-semibold' : 'bg-gray-100 text-black'}`}
          >
            Financeiro
          </button>
        </div>

        {tab === 'operacional' ? (
          <div className="space-y-6">
            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">1) Relatório de Ordens de Serviço</div>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data inicial</div>
                  <input type="date" value={osFilters.data_inicio} onChange={(e) => setOsFilters((p) => ({ ...p, data_inicio: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data final</div>
                  <input type="date" value={osFilters.data_fim} onChange={(e) => setOsFilters((p) => ({ ...p, data_fim: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Cliente</div>
                  <select value={osFilters.cliente_id} onChange={(e) => setOsFilters((p) => ({ ...p, cliente_id: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black">
                    <option value="">Todos</option>
                    {clientes.map((c) => (
                      <option key={c.id} value={String(c.id)}>
                        {c.nome}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Status</div>
                  <select value={osFilters.status} onChange={(e) => setOsFilters((p) => ({ ...p, status: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black">
                    <option value="">Todos</option>
                    <option value="aberta">Aberta</option>
                    <option value="finalizada">Finalizada</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </label>
              </div>

              <div className="mt-4">
                <Button type="button" fullWidth={false} loading={osLoading} onClick={osApply}>
                  Aplicar
                </Button>
              </div>

              {osTotais ? (
                <div className="mt-4 text-sm text-black font-semibold">Totais: {osTotais.quantidade} OS | {money(osTotais.valor_total)}</div>
              ) : null}

              <div className="mt-4 overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Nº OS</th>
                      <th className="p-3 text-left">Cliente</th>
                      <th className="p-3 text-left">Status</th>
                      <th className="p-3 text-left">Valor</th>
                      <th className="p-3 text-left">Criação</th>
                      <th className="p-3 text-left">Finalização</th>
                    </tr>
                  </thead>
                  <tbody>
                    {osRows.map((r) => (
                      <tr key={r.id} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{r.numero_os}</td>
                        <td className="p-3 text-gray-800">{r.cliente?.nome ?? '-'}</td>
                        <td className="p-3 text-gray-800">{r.status}</td>
                        <td className="p-3 text-gray-800">{money(r.valor_total)}</td>
                        <td className="p-3 text-gray-800">{formatDate(r.data_criacao)}</td>
                        <td className="p-3 text-gray-800">{formatDate(r.data_finalizacao)}</td>
                      </tr>
                    ))}
                    {osRows.length === 0 && !osLoading ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={6}>
                          Nenhum resultado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>

            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">2) Relatório de Produção / Consumo</div>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data inicial</div>
                  <input type="date" value={prodFilters.data_inicio} onChange={(e) => setProdFilters((p) => ({ ...p, data_inicio: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data final</div>
                  <input type="date" value={prodFilters.data_fim} onChange={(e) => setProdFilters((p) => ({ ...p, data_fim: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <Button type="button" fullWidth={false} loading={prodLoading} onClick={prodApply}>
                  Aplicar
                </Button>
              </div>

              {prodTotais ? (
                <div className="mt-4 text-sm text-black font-semibold">Totais: {prodTotais.linhas} linhas | {prodTotais.quantidade_total_utilizada.toFixed(2)} unidades</div>
              ) : null}

              <div className="mt-4 overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Produto</th>
                      <th className="p-3 text-left">Quantidade utilizada</th>
                      <th className="p-3 text-left">Origem (OS)</th>
                      <th className="p-3 text-left">Finalização</th>
                    </tr>
                  </thead>
                  <tbody>
                    {prodRows.map((r, idx) => (
                      <tr key={idx} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{r.produto?.nome ?? '-'}</td>
                        <td className="p-3 text-gray-800">{r.quantidade_utilizada.toFixed(2)}</td>
                        <td className="p-3 text-gray-800">{r.origem?.numero_os ?? '-'}</td>
                        <td className="p-3 text-gray-800">{formatDate(r.origem?.data_finalizacao)}</td>
                      </tr>
                    ))}
                    {prodRows.length === 0 && !prodLoading ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={4}>
                          Nenhum resultado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>

            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">3) Relatório de Produtos Mais Utilizados</div>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data inicial</div>
                  <input type="date" value={maisFilters.data_inicio} onChange={(e) => setMaisFilters((p) => ({ ...p, data_inicio: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data final</div>
                  <input type="date" value={maisFilters.data_fim} onChange={(e) => setMaisFilters((p) => ({ ...p, data_fim: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <Button type="button" fullWidth={false} loading={maisLoading} onClick={maisApply}>
                  Aplicar
                </Button>
              </div>

              {maisTotais ? (
                <div className="mt-4 text-sm text-black font-semibold">Totais: {maisTotais.produtos} produtos | {maisTotais.quantidade_total_utilizada.toFixed(2)} unidades</div>
              ) : null}

              <div className="mt-4 overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Produto</th>
                      <th className="p-3 text-left">Quantidade total utilizada</th>
                    </tr>
                  </thead>
                  <tbody>
                    {maisRows.map((r, idx) => (
                      <tr key={idx} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{r.produto?.nome ?? '-'}</td>
                        <td className="p-3 text-gray-800">{r.quantidade_total_utilizada.toFixed(2)}</td>
                      </tr>
                    ))}
                    {maisRows.length === 0 && !maisLoading ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={2}>
                          Nenhum resultado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">4) Relatório de Contas a Receber</div>

              <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data inicial (vencimento)</div>
                  <input type="date" value={finFilters.data_inicio} onChange={(e) => setFinFilters((p) => ({ ...p, data_inicio: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data final (vencimento)</div>
                  <input type="date" value={finFilters.data_fim} onChange={(e) => setFinFilters((p) => ({ ...p, data_fim: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Cliente</div>
                  <select value={finFilters.cliente_id} onChange={(e) => setFinFilters((p) => ({ ...p, cliente_id: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" disabled={loadingClientes}>
                    <option value="">Todos</option>
                    {clientes.map((c) => (
                      <option key={c.id} value={String(c.id)}>
                        {c.nome}
                      </option>
                    ))}
                  </select>
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Status</div>
                  <select value={finFilters.status} onChange={(e) => setFinFilters((p) => ({ ...p, status: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="pago">Pago</option>
                    <option value="cancelado">Cancelado</option>
                  </select>
                </label>
              </div>

              <div className="mt-4">
                <Button type="button" fullWidth={false} loading={finLoading} onClick={finApply}>
                  Aplicar
                </Button>
              </div>

              {finTotalsLine ? (
                <div className="mt-4 text-sm text-black font-semibold">
                  Totais: {finTotalsLine.total} | Pago: {finTotalsLine.pago} | Pendente: {finTotalsLine.pendente} | Cancelado: {finTotalsLine.cancelado}
                </div>
              ) : null}

              <div className="mt-4 overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Cliente</th>
                      <th className="p-3 text-left">OS</th>
                      <th className="p-3 text-left">Valor</th>
                      <th className="p-3 text-left">Vencimento</th>
                      <th className="p-3 text-left">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {finRows.map((r) => (
                      <tr key={r.id} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{r.cliente?.nome ?? '-'}</td>
                        <td className="p-3 text-gray-800">{r.ordem_servico?.numero_os ?? '-'}</td>
                        <td className="p-3 text-gray-800">{money(r.valor)}</td>
                        <td className="p-3 text-gray-800">{formatDate(r.vencimento)}</td>
                        <td className="p-3 text-gray-800">{r.status}</td>
                      </tr>
                    ))}
                    {finRows.length === 0 && !finLoading ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={5}>
                          Nenhum resultado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>

            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">5) Relatório de Faturamento</div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data inicial (vencimento)</div>
                  <input type="date" value={fatFilters.data_inicio} onChange={(e) => setFatFilters((p) => ({ ...p, data_inicio: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Data final (vencimento)</div>
                  <input type="date" value={fatFilters.data_fim} onChange={(e) => setFatFilters((p) => ({ ...p, data_fim: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" />
                </label>
                <Button type="button" fullWidth={false} loading={fatLoading} onClick={fatApply}>
                  Aplicar
                </Button>
              </div>

              {fatData ? (
                <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="text-xs text-gray-600">Total faturado (pagos)</div>
                    <div className="text-lg font-semibold text-black">{money(fatData.total_faturado)}</div>
                  </div>
                  <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="text-xs text-gray-600">Total pendente</div>
                    <div className="text-lg font-semibold text-black">{money(fatData.total_pendente)}</div>
                  </div>
                  <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="text-xs text-gray-600">Total cancelado</div>
                    <div className="text-lg font-semibold text-black">{money(fatData.total_cancelado)}</div>
                  </div>
                </div>
              ) : null}
            </div>

            <div className={sectionClass}>
              <div className="text-sm font-semibold text-black mb-3">6) Relatório de Inadimplência</div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Cliente</div>
                  <select value={inadFilters.cliente_id} onChange={(e) => setInadFilters((p) => ({ ...p, cliente_id: e.target.value }))} className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black" disabled={loadingClientes}>
                    <option value="">Todos</option>
                    {clientes.map((c) => (
                      <option key={c.id} value={String(c.id)}>
                        {c.nome}
                      </option>
                    ))}
                  </select>
                </label>
                <div />
                <Button type="button" fullWidth={false} loading={inadLoading} onClick={inadApply}>
                  Aplicar
                </Button>
              </div>

              {inadTotais ? (
                <div className="mt-4 text-sm text-black font-semibold">Totais: {inadTotais.quantidade} em atraso | {money(inadTotais.valor_total_pendente)}</div>
              ) : null}

              <div className="mt-4 overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Cliente</th>
                      <th className="p-3 text-left">OS</th>
                      <th className="p-3 text-left">Valor pendente</th>
                      <th className="p-3 text-left">Vencimento</th>
                      <th className="p-3 text-left">Dias em atraso</th>
                    </tr>
                  </thead>
                  <tbody>
                    {inadRows.map((r) => (
                      <tr key={r.id} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{r.cliente?.nome ?? '-'}</td>
                        <td className="p-3 text-gray-800">{r.ordem_servico?.numero_os ?? '-'}</td>
                        <td className="p-3 text-gray-800">{money(r.valor_pendente)}</td>
                        <td className="p-3 text-gray-800">{formatDate(r.vencimento)}</td>
                        <td className="p-3 text-gray-800">{r.dias_em_atraso}</td>
                      </tr>
                    ))}
                    {inadRows.length === 0 && !inadLoading ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={5}>
                          Nenhum resultado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
