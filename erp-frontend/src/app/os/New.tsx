import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import Button from '../../components/Button'
import Input from '../../components/Input'
import Textarea from '../../components/Textarea'
import clientesService from '../../services/clientesService'
import produtosService from '../../services/produtosService'
import ordensServicoService from '../../services/ordensServicoService'
import { useToast } from '../toast/ToastProvider'

type Cliente = { id: number; nome: string }

type Produto = {
  id: number
  nome: string
  preco?: number | string | null
}

type ItemState = {
  produto_id: string
  quantidade: string
}

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function toNumber(value: string) {
  const normalized = value.replace(',', '.')
  const n = Number(normalized)
  return Number.isNaN(n) ? null : n
}

function produtoPreco(prod: Produto | undefined) {
  const v = prod?.preco
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  return Number.isNaN(n) ? 0 : n
}

function parseProdutoId(raw: string): number | null {
  if (!raw) return null
  const id = Number(raw)
  if (!Number.isNaN(id) && id > 0) return id
  return null
}

function money(v: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
}

export default function OrdemServicoNew() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(false)
  const [loadingData, setLoadingData] = useState(true)

  const [clientes, setClientes] = useState<Cliente[]>([])
  const [produtos, setProdutos] = useState<Produto[]>([])

  const [clienteId, setClienteId] = useState('')
  const [observacoes, setObservacoes] = useState('')
  const [itens, setItens] = useState<ItemState[]>([{ produto_id: '', quantidade: '1' }])

  useEffect(() => {
    const load = async () => {
      setLoadingData(true)
      try {
        const [cRes, pRes] = await Promise.all([clientesService.list(), produtosService.list()])
        setClientes(asArray(cRes.data) as Cliente[])
        setProdutos(asArray(pRes.data) as Produto[])
      } catch {
        showToast('Erro ao carregar clientes/produtos', 'error')
      } finally {
        setLoadingData(false)
      }
    }
    load()
  }, [])

  const computed = useMemo(() => {
    const rows = itens.map((it) => {
      const pid = parseProdutoId(it.produto_id)
      const produto = pid ? produtos.find((p) => p.id === pid) : undefined
      const q = toNumber(it.quantidade) ?? 0
      const unit = produtoPreco(produto)
      const total = q * unit
      return { produto, q, unit, total }
    })

    const totalGeral = rows.reduce((acc, r) => acc + r.total, 0)

    return { rows, totalGeral }
  }, [itens, produtos])

  const addItem = () => setItens((prev) => [...prev, { produto_id: '', quantidade: '1' }])

  const removeItem = (idx: number) => {
    setItens((prev) => prev.filter((_, i) => i !== idx))
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!clienteId) {
      showToast('Selecione um cliente', 'error')
      return
    }

    const payloadItens = itens
      .map((it) => {
        const pid = parseProdutoId(it.produto_id)
        const quantidade = toNumber(it.quantidade)
        if (!pid) return null
        if (!quantidade || quantidade <= 0) return null
        return { produto_id: pid, quantidade }
      })
      .filter(Boolean)

    if (payloadItens.length === 0) {
      showToast('Adicione ao menos 1 item', 'error')
      return
    }

    setLoading(true)
    try {
      const res = await ordensServicoService.create({
        cliente_id: Number(clienteId),
        observacoes: observacoes.trim() ? observacoes.trim() : undefined,
        itens: payloadItens as any,
      })

      const id = res.data?.data?.id
      showToast('OS criada com sucesso')
      if (id) {
        navigate(`/ordens-servico/${id}`)
      } else {
        navigate('/ordens-servico')
      }
    } catch {
      showToast('Erro ao criar OS', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Nova OS">
        {loadingData ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label className="block">
                <div className="mb-2 text-sm font-medium text-black">Cliente <span className="text-red-600">*</span></div>
                <select
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                  value={clienteId}
                  onChange={(e) => setClienteId(e.target.value)}
                >
                  <option value="">Selecione</option>
                  {clientes.map((c) => (
                    <option key={c.id} value={String(c.id)}>
                      {c.nome}
                    </option>
                  ))}
                </select>
              </label>

              <div className="md:col-span-2">
                <Textarea
                  label="Observações"
                  value={observacoes}
                  onChange={setObservacoes}
                  placeholder="Observações da OS"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="text-sm font-medium text-black">Itens</div>
                <Button type="button" variant="secondary" fullWidth={false} onClick={addItem}>
                  Adicionar item
                </Button>
              </div>

              <div className="overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Produto</th>
                      <th className="p-3 text-left">Quantidade</th>
                      <th className="p-3 text-left">Valor unitário</th>
                      <th className="p-3 text-left">Total</th>
                      <th className="p-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {itens.map((it, idx) => {
                      const row = computed.rows[idx]
                      return (
                        <tr key={idx} className="border-t">
                          <td className="p-3">
                            <select
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                              value={it.produto_id}
                              onChange={(e) =>
                                setItens((prev) => prev.map((p, i) => (i === idx ? { ...p, produto_id: e.target.value } : p)))
                              }
                            >
                              <option value="">Selecione</option>
                              {produtos.map((p) => (
                                <option key={p.id} value={String(p.id)}>
                                  {p.nome}
                                </option>
                              ))}
                            </select>
                          </td>
                          <td className="p-3 w-40">
                            <Input
                              value={it.quantidade}
                              onChange={(v) => setItens((prev) => prev.map((p, i) => (i === idx ? { ...p, quantidade: v } : p)))}
                              type="number"
                              placeholder="1"
                            />
                          </td>
                          <td className="p-3 text-gray-800">{money(row?.unit ?? 0)}</td>
                          <td className="p-3 text-gray-800">{money(row?.total ?? 0)}</td>
                          <td className="p-3 text-right">
                            <button type="button" className="text-black hover:underline" onClick={() => removeItem(idx)}>
                              Remover
                            </button>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              <div className="mt-4 text-right text-black font-semibold">Total: {money(computed.totalGeral)}</div>
            </div>

            <div className="flex gap-3">
              <Button type="submit" loading={loading} fullWidth={false}>
                Salvar
              </Button>
              <Button type="button" variant="secondary" fullWidth={false} onClick={() => navigate('/ordens-servico')}>
                Cancelar
              </Button>
            </div>
          </form>
        )}
      </PageContainer>
    </div>
  )
}
