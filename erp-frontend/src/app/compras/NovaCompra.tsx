import { useMemo, useState } from 'react'
import PageContainer from '../../components/PageContainer'
import Input from '../../components/Input'
import Button from '../../components/Button'
import comprasService from '../../services/comprasService'
import { useToast } from '../toast/ToastProvider'

type ItemTipo = 'material' | 'insumo' | 'equipamento'

type CompraItemDraft = {
  id: string
  nome: string
  tipo: ItemTipo
  unidade_compra: string
  quantidade: number
  valor_total: number
}

function todayISO() {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function toNumber(v: any): number {
  const n = Number(String(v ?? '').replace(',', '.'))
  return Number.isFinite(n) ? n : 0
}

function formatMoney(v: any): string {
  const n = Number(v)
  if (!Number.isFinite(n)) return 'R$ 0,00'
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function normalizeText(s: any) {
  return String(s ?? '').trim().replace(/\s+/g, ' ')
}

function newId() {
  return `${Date.now()}_${Math.random().toString(16).slice(2)}`
}

function TipoLabel({ tipo }: { tipo: ItemTipo }) {
  if (tipo === 'material') return <>Material</>
  if (tipo === 'insumo') return <>Insumo</>
  return <>Equipamento</>
}

export default function NovaCompra() {
  const { showToast } = useToast()

  const [saving, setSaving] = useState(false)

  const [data, setData] = useState<string>(todayISO())
  const [fornecedor, setFornecedor] = useState<string>('')
  const [observacoes, setObservacoes] = useState<string>('')

  const [itens, setItens] = useState<CompraItemDraft[]>([])

  const [newNome, setNewNome] = useState('')
  const [newTipo, setNewTipo] = useState<ItemTipo>('material')
  const [newUnidade, setNewUnidade] = useState('')
  const [newQuantidade, setNewQuantidade] = useState('')
  const [newValorTotal, setNewValorTotal] = useState('')

  const custoUnitarioPreview = useMemo(() => {
    const qtd = toNumber(newQuantidade)
    const total = toNumber(newValorTotal)
    if (qtd <= 0 || total <= 0) return 0
    return total / qtd
  }, [newQuantidade, newValorTotal])

  const grouped = useMemo(() => {
    const g: Record<ItemTipo, CompraItemDraft[]> = { material: [], insumo: [], equipamento: [] }
    for (const it of itens) g[it.tipo].push(it)
    return g
  }, [itens])

  const addItem = () => {
    const nome = normalizeText(newNome)
    const unidade_compra = normalizeText(newUnidade)
    const quantidade = toNumber(newQuantidade)
    const valor_total = toNumber(newValorTotal)

    if (!nome || !newTipo || !unidade_compra || quantidade <= 0 || valor_total <= 0) {
      showToast('Informe nome, tipo, unidade, quantidade e valor total', 'error')
      return
    }

    setItens((prev) => [
      ...prev,
      {
        id: newId(),
        nome,
        tipo: newTipo,
        unidade_compra,
        quantidade,
        valor_total,
      },
    ])

    setNewNome('')
    setNewUnidade('')
    setNewQuantidade('')
    setNewValorTotal('')
  }

  const removeItem = (id: string) => {
    setItens((prev) => prev.filter((i) => i.id !== id))
  }

  const updateItem = (id: string, patch: Partial<CompraItemDraft>) => {
    setItens((prev) => prev.map((i) => (i.id === id ? { ...i, ...patch } : i)))
  }

  const [editingId, setEditingId] = useState<string | null>(null)

  const save = async () => {
    if (!data) {
      showToast('Informe a data da compra', 'error')
      return
    }
    if (itens.length === 0) {
      showToast('Adicione ao menos 1 item na compra', 'error')
      return
    }

    setSaving(true)
    try {
      await comprasService.create({
        data,
        fornecedor: normalizeText(fornecedor) ? normalizeText(fornecedor) : null,
        observacoes: normalizeText(observacoes) ? normalizeText(observacoes) : null,
        itens: itens.map((i) => ({
          nome: i.nome,
          tipo: i.tipo,
          unidade_compra: i.unidade_compra,
          quantidade: i.quantidade,
          valor_total: i.valor_total,
        })),
      })

      showToast('Compra salva com sucesso')
      setItens([])
      setFornecedor('')
      setObservacoes('')
      setNewNome('')
      setNewUnidade('')
      setNewQuantidade('')
      setNewValorTotal('')
      setEditingId(null)
    } catch (e: any) {
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao salvar compra', 'error')
    } finally {
      setSaving(false)
    }
  }

  const ItemCard = ({ item }: { item: CompraItemDraft }) => {
    const custo_unitario = item.quantidade > 0 ? item.valor_total / item.quantidade : 0
    const isEditing = editingId === item.id

    return (
      <div className="border border-gray-200 rounded-lg p-4 bg-white">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="text-sm font-semibold text-black truncate">{item.nome}</div>
            <div className="text-xs text-gray-600 mt-1">
              <TipoLabel tipo={item.tipo} /> • unidade: {item.unidade_compra}
            </div>
          </div>

          <div className="flex gap-2">
            {isEditing ? (
              <>
                <Button type="button" onClick={() => setEditingId(null)} variant="secondary" dense fullWidth={false}>
                  Cancelar
                </Button>
                <Button type="button" onClick={() => setEditingId(null)} dense fullWidth={false}>
                  OK
                </Button>
              </>
            ) : (
              <>
                <Button type="button" onClick={() => setEditingId(item.id)} variant="secondary" dense fullWidth={false}>
                  Editar
                </Button>
                <Button
                  type="button"
                  onClick={() => removeItem(item.id)}
                  dense
                  fullWidth={false}
                  className="bg-black text-white hover:bg-gray-900"
                >
                  Remover
                </Button>
              </>
            )}
          </div>
        </div>

        {isEditing ? (
          <div className="grid grid-cols-1 md:grid-cols-5 gap-3 mt-4">
            <div className="md:col-span-2">
              <label className="text-xs text-gray-600">Nome</label>
              <input
                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                value={item.nome}
                onChange={(e) => updateItem(item.id, { nome: normalizeText(e.target.value) })}
              />
            </div>
            <div>
              <label className="text-xs text-gray-600">Tipo</label>
              <select
                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                value={item.tipo}
                onChange={(e) => updateItem(item.id, { tipo: e.target.value as ItemTipo })}
              >
                <option value="material">Material</option>
                <option value="insumo">Insumo</option>
                <option value="equipamento">Equipamento</option>
              </select>
            </div>
            <div>
              <label className="text-xs text-gray-600">Unidade</label>
              <input
                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                value={item.unidade_compra}
                onChange={(e) => updateItem(item.id, { unidade_compra: normalizeText(e.target.value) })}
              />
            </div>
            <div>
              <label className="text-xs text-gray-600">Quantidade</label>
              <input
                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                value={String(item.quantidade)}
                onChange={(e) => updateItem(item.id, { quantidade: toNumber(e.target.value) })}
              />
            </div>
            <div>
              <label className="text-xs text-gray-600">Valor total</label>
              <input
                className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                value={String(item.valor_total)}
                onChange={(e) => updateItem(item.id, { valor_total: toNumber(e.target.value) })}
              />
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
            <div>
              <div className="text-xs text-gray-600">Quantidade</div>
              <div className="text-sm text-black">{item.quantidade}</div>
            </div>
            <div>
              <div className="text-xs text-gray-600">Valor total</div>
              <div className="text-sm text-black">{formatMoney(item.valor_total)}</div>
            </div>
            <div>
              <div className="text-xs text-gray-600">Custo unitário</div>
              <div className="text-sm text-black">{formatMoney(custo_unitario)}</div>
            </div>
            <div>
              <div className="text-xs text-gray-600">Tipo</div>
              <div className="text-sm text-black">
                <TipoLabel tipo={item.tipo} />
              </div>
            </div>
          </div>
        )}
      </div>
    )
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Compras - Nova Compra">
        <div className="space-y-8">
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="text-sm font-semibold text-black mb-3">Cabeçalho da compra</div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="text-sm text-gray-700">Data</label>
                <input
                  type="date"
                  className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  value={data}
                  onChange={(e) => setData(e.target.value)}
                />
              </div>
              <div className="md:col-span-2">
                <label className="text-sm text-gray-700">Fornecedor (opcional)</label>
                <input
                  className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  value={fornecedor}
                  onChange={(e) => setFornecedor(e.target.value)}
                />
              </div>
              <div className="md:col-span-3">
                <label className="text-sm text-gray-700">Observações</label>
                <textarea
                  className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  value={observacoes}
                  onChange={(e) => setObservacoes(e.target.value)}
                  rows={3}
                />
              </div>
            </div>
          </div>

          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="text-sm font-semibold text-black mb-3">Adicionar item</div>
            <div className="grid grid-cols-1 md:grid-cols-6 gap-3">
              <div className="md:col-span-2">
                <Input
                  label="Nome do item"
                  value={newNome}
                  onChange={setNewNome}
                  placeholder="Ex.: Papel Sulfite 75g – 500 folhas"
                />
              </div>
              <div>
                <label className="text-sm text-gray-700">Tipo do item</label>
                <select
                  className="w-full mt-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  value={newTipo}
                  onChange={(e) => setNewTipo(e.target.value as ItemTipo)}
                >
                  <option value="material">Material</option>
                  <option value="insumo">Insumo</option>
                  <option value="equipamento">Equipamento</option>
                </select>
              </div>
              <div>
                <Input label="Unidade de compra" value={newUnidade} onChange={setNewUnidade} placeholder="un, pacote, kg, ml..." />
              </div>
              <div>
                <Input label="Quantidade" value={newQuantidade} onChange={setNewQuantidade} placeholder="0" />
              </div>
              <div>
                <Input label="Valor total" value={newValorTotal} onChange={setNewValorTotal} placeholder="0" />
              </div>
            </div>

            <div className="flex items-end justify-between gap-3 mt-3">
              <div className="text-sm text-gray-700">
                <div>Custo unitário (prévia): {formatMoney(custoUnitarioPreview)}</div>
              </div>
              <Button type="button" onClick={addItem}>
                Adicionar item
              </Button>
            </div>
          </div>

          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="text-sm font-semibold text-black">Itens da compra</div>
            <div className="text-xs text-gray-600 mt-1">Itens são criados/reutilizados somente ao salvar a compra.</div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
              <div>
                <div className="text-sm font-semibold text-black mb-2">Materiais</div>
                <div className="space-y-3">
                  {grouped.material.map((it) => (
                    <ItemCard key={it.id} item={it} />
                  ))}
                  {grouped.material.length === 0 ? <div className="text-xs text-gray-600">Nenhum material adicionado.</div> : null}
                </div>
              </div>
              <div>
                <div className="text-sm font-semibold text-black mb-2">Insumos</div>
                <div className="space-y-3">
                  {grouped.insumo.map((it) => (
                    <ItemCard key={it.id} item={it} />
                  ))}
                  {grouped.insumo.length === 0 ? <div className="text-xs text-gray-600">Nenhum insumo adicionado.</div> : null}
                </div>
              </div>
              <div>
                <div className="text-sm font-semibold text-black mb-2">Equipamentos</div>
                <div className="space-y-3">
                  {grouped.equipamento.map((it) => (
                    <ItemCard key={it.id} item={it} />
                  ))}
                  {grouped.equipamento.length === 0 ? <div className="text-xs text-gray-600">Nenhum equipamento adicionado.</div> : null}
                </div>
              </div>
            </div>

            <div className="flex justify-end mt-6">
              <Button type="button" onClick={save} loading={saving} fullWidth={false}>
                Salvar compra
              </Button>
            </div>
          </div>
        </div>
      </PageContainer>
    </div>
  )
}
