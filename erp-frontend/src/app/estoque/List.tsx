import { useEffect, useState } from 'react'
import PageContainer from '../../components/PageContainer'
import Button from '../../components/Button'
import Input from '../../components/Input'
import produtosService from '../../services/produtosService'
import estoqueService from '../../services/estoqueService'
import { useToast } from '../toast/ToastProvider'

type Produto = { id: number; nome: string }

type EstoqueRow = {
  id: number
  produto_id: number
  produto?: Produto | null
  quantidade_atual: number | string
  estoque_minimo: number | string
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

export default function EstoqueList() {
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  const [produtos, setProdutos] = useState<Produto[]>([])
  const [rows, setRows] = useState<EstoqueRow[]>([])

  const [produtoId, setProdutoId] = useState('')
  const [tipo, setTipo] = useState<'entrada' | 'saida'>('entrada')
  const [quantidade, setQuantidade] = useState('')

  const load = async () => {
    setLoading(true)
    try {
      const [pRes, eRes] = await Promise.all([produtosService.list(), estoqueService.list()])
      setProdutos(asArray(pRes.data) as Produto[])
      setRows(asArray(eRes.data) as EstoqueRow[])
    } catch {
      setProdutos([])
      setRows([])
      showToast('Erro ao carregar estoque', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!produtoId) {
      showToast('Selecione um produto', 'error')
      return
    }

    const q = toNumber(quantidade)
    if (!q || q <= 0) {
      showToast('Informe a quantidade', 'error')
      return
    }

    setSaving(true)
    try {
      await estoqueService.ajuste({ produto_id: Number(produtoId), tipo, quantidade: q })
      showToast('Ajuste aplicado')
      setQuantidade('')
      await load()
    } catch (e: any) {
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao aplicar ajuste', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Estoque">
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="space-y-6">
            <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
              <label className="block">
                <div className="mb-2 text-sm font-medium text-black">Produto</div>
                <select
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                  value={produtoId}
                  onChange={(e) => setProdutoId(e.target.value)}
                >
                  <option value="">Selecione</option>
                  {produtos.map((p) => (
                    <option key={p.id} value={String(p.id)}>
                      {p.nome}
                    </option>
                  ))}
                </select>
              </label>

              <label className="block">
                <div className="mb-2 text-sm font-medium text-black">Tipo</div>
                <select
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                  value={tipo}
                  onChange={(e) => setTipo(e.target.value as any)}
                >
                  <option value="entrada">Entrada</option>
                  <option value="saida">Saída</option>
                </select>
              </label>

              <Input label="Quantidade" value={quantidade} onChange={setQuantidade} type="number" placeholder="1" />

              <Button type="submit" loading={saving} fullWidth={false}>
                Aplicar
              </Button>
            </form>

            <div className="overflow-auto border border-gray-200 rounded-lg">
              <table className="w-full text-sm">
                <thead className="bg-gray-100 text-black">
                  <tr>
                    <th className="p-3 text-left">Produto</th>
                    <th className="p-3 text-left">Saldo</th>
                    <th className="p-3 text-left">Mínimo</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.id} className="border-t hover:bg-gray-50">
                      <td className="p-3 text-black">{r.produto?.nome ?? '-'}</td>
                      <td className="p-3 text-gray-800">{Number(r.quantidade_atual).toFixed(2)}</td>
                      <td className="p-3 text-gray-800">{Number(r.estoque_minimo).toFixed(2)}</td>
                    </tr>
                  ))}
                  {rows.length === 0 ? (
                    <tr className="border-t">
                      <td className="p-4 text-gray-700" colSpan={3}>
                        Nenhum saldo de estoque registrado ainda.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
