import { Dispatch, SetStateAction, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import Input from '../../components/Input'
import Button from '../../components/Button'
import produtosService from '../../services/produtosService'
import comprasItensService, { CompraItemTipo } from '../../services/comprasItensService'
import { useToast } from '../toast/ToastProvider'

type FormaCalculo = 'unitario' | 'metro_linear' | 'metro_quadrado'

type QtyMap = Record<string, string>

type CompraItemOption = {
  id: number
  tipo: CompraItemTipo
  nome: string
  ativo?: boolean | null
  unidade_compra?: string | null
  preco_medio?: number | string | null
}

type ProdutoApi = {
  id: number
  nome: string
  sku?: string | null
  ativo?: boolean | null
  status?: string | null
  forma_calculo?: FormaCalculo | null
  custo_base?: number | string | null
  preco_base?: number | string | null
  custo_total?: number | string | null
  lucro?: number | string | null
  margem_percentual?: number | string | null

  comprasItensPivot?: any[]
  compras_itens_pivot?: any[]
  compras_itens?: any[]
}

type FormState = {
  nome: string
  forma_calculo: FormaCalculo
  preco_base: string
}

function parseNumber(value: string) {
  const normalized = value.replace(',', '.').trim()
  if (!normalized) return null
  const n = Number(normalized)
  return Number.isNaN(n) ? null : n
}

function formatMoney(value: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

function fmtPercent(value: number) {
  return `${new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(value)}%`
}

function labelPor(forma: FormaCalculo) {
  if (forma === 'metro_linear') return 'por metro'
  if (forma === 'metro_quadrado') return 'por m²'
  return 'por unidade'
}

function labelUnidade(forma: FormaCalculo) {
  if (forma === 'metro_linear') return 'metro'
  if (forma === 'metro_quadrado') return 'm²'
  return 'unidade'
}

function extractList<T>(res: any): T[] {
  const payload = res?.data
  if (Array.isArray(payload)) return payload as T[]
  if (Array.isArray(payload?.data)) return payload.data as T[]
  return []
}

function usedCount(map: QtyMap) {
  let count = 0
  for (const v of Object.values(map)) {
    const n = parseNumber(String(v)) ?? 0
    if (n > 0) count += 1
  }
  return count
}

function hasInvalidQty(map: QtyMap) {
  for (const v of Object.values(map)) {
    const trimmed = String(v ?? '').trim()
    if (!trimmed) continue
    const n = parseNumber(trimmed)
    if (n == null || n < 0) return true
  }
  return false
}

function getQty(map: QtyMap, id: number) {
  return map[String(id)] ?? '0'
}

function setQtyRaw(setter: Dispatch<SetStateAction<QtyMap>>, id: number, value: string) {
  const key = String(id)
  setter((prev) => ({ ...prev, [key]: value }))
}

function normalizeQty(setter: Dispatch<SetStateAction<QtyMap>>, id: number) {
  const key = String(id)
  setter((prev) => {
    const raw = prev[key] ?? ''
    const trimmed = String(raw).trim()

    if (!trimmed) {
      const next = { ...prev }
      delete next[key]
      return next
    }

    const n = parseNumber(trimmed)
    if (n == null || n <= 0) {
      const next = { ...prev }
      delete next[key]
      return next
    }

    return { ...prev, [key]: String(n) }
  })
}

function ColunaPadrao(props: {
  titulo: string
  usado: number
  busca: string
  onBusca: (v: string) => void
  placeholder: string
  itens: Array<{ id: number; nome: string }>
  qtyMap: QtyMap
  setQtyMap: Dispatch<SetStateAction<QtyMap>>
}) {
  const { titulo, usado, busca, onBusca, placeholder, itens, qtyMap, setQtyMap } = props
  const search = busca.trim().toLowerCase()

  const rowRefs = useRef(new Map<number, HTMLDivElement>())
  const lastPositionsRef = useRef<Map<number, DOMRect>>(new Map())

  const displayedItems = useMemo(() => {
    const withMeta = itens.map((i) => {
      const itemId = Number(i.id)
      const qtyStr = getQty(qtyMap, itemId)
      const qty = parseNumber(qtyStr) ?? 0
      const isUsed = qty > 0
      const matchesSearch = search ? String(i.nome ?? '').toLowerCase().includes(search) : true

      return {
        ...i,
        itemId,
        qtyStr,
        qty,
        isUsed,
        matchesSearch,
      }
    })

    // Busca filtra, mas itens usados nunca podem ser ocultados.
    const filtered = withMeta.filter((i) => i.isUsed || i.matchesSearch)

    // Ordenação obrigatória: usados (q>0) no topo, depois não usados.
    // Dentro dos grupos, mantém previsível por nome.
    filtered.sort((a, b) => {
      if (a.isUsed !== b.isUsed) return a.isUsed ? -1 : 1
      return String(a.nome ?? '').localeCompare(String(b.nome ?? ''), 'pt-BR')
    })

    return filtered
  }, [itens, qtyMap, search])

  // Animação suave de reordenação (FLIP)
  useLayoutEffect(() => {
    const lastPositions = lastPositionsRef.current
    const newPositions = new Map<number, DOMRect>()

    for (const item of displayedItems) {
      const el = rowRefs.current.get(item.itemId)
      if (!el) continue
      newPositions.set(item.itemId, el.getBoundingClientRect())
    }

    for (const item of displayedItems) {
      const el = rowRefs.current.get(item.itemId)
      if (!el) continue
      const prev = lastPositions.get(item.itemId)
      const next = newPositions.get(item.itemId)
      if (!prev || !next) continue

      const dx = prev.left - next.left
      const dy = prev.top - next.top
      if (dx === 0 && dy === 0) continue

      el.style.transform = `translate(${dx}px, ${dy}px)`
      el.style.transition = 'transform 0s'

      requestAnimationFrame(() => {
        el.style.transform = ''
        el.style.transition = 'transform 180ms ease'
      })
    }

    lastPositionsRef.current = newPositions
  }, [displayedItems])

  return (
    <div className="border border-gray-200 rounded-lg p-2 bg-white min-w-0 flex flex-col h-[60vh] min-h-[420px]">
      <div className="text-xs font-semibold text-black">
        {titulo}{' '}
        <span className={usado > 0 ? 'text-yellow-700 font-semibold' : 'text-gray-400 font-normal'}>({usado})</span>
      </div>
      <input
        className="mt-2 w-full px-3 py-2 h-8 text-xs border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
        placeholder={placeholder}
        value={busca}
        onChange={(e) => onBusca(e.target.value)}
      />
      <div className="mt-2 flex-1 overflow-y-auto">
        <div className="space-y-1">
          {displayedItems.map((i) => {
            return (
              <div
                key={i.itemId}
                ref={(el) => {
                  if (!el) {
                    rowRefs.current.delete(i.itemId)
                    return
                  }
                  rowRefs.current.set(i.itemId, el)
                }}
                className={`border rounded-md px-2 py-1 will-change-transform ${
                  i.isUsed ? 'bg-yellow-50 border-yellow-200' : 'bg-white border-gray-200'
                }`}
              >
                <div className="flex items-center justify-between gap-2">
                  <div className={`text-xs truncate ${i.isUsed ? 'text-black font-medium' : 'text-gray-500'}`}>
                    <span>{i.nome}</span>
                  </div>
                  <input
                    className={`w-20 h-7 text-xs px-2 border rounded-md text-right bg-white focus:outline-none focus:ring-2 focus:ring-yellow-300 ${
                      i.isUsed ? 'border-yellow-200 text-black' : 'border-gray-200 text-gray-600'
                    }`}
                    type="number"
                    inputMode="decimal"
                    value={i.qtyStr}
                    onChange={(e) => setQtyRaw(setQtyMap, i.itemId, e.target.value)}
                    onBlur={() => normalizeQty(setQtyMap, i.itemId)}
                  />
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}

export default function ProdutoForm() {
  const navigate = useNavigate()
  const { id } = useParams()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(false)
  const [loadingData, setLoadingData] = useState(false)

  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof FormState, string>>>({})

  const [comprasOptions, setComprasOptions] = useState<CompraItemOption[]>([])

  const [materiaisQtd, setMateriaisQtd] = useState<QtyMap>({})
  const [insumosQtd, setInsumosQtd] = useState<QtyMap>({})
  const [equipamentosQtd, setEquipamentosQtd] = useState<QtyMap>({})

  const [searchMateriais, setSearchMateriais] = useState('')
  const [searchInsumos, setSearchInsumos] = useState('')
  const [searchEquipamentos, setSearchEquipamentos] = useState('')

  const [form, setForm] = useState<FormState>({
    nome: '',
    forma_calculo: 'unitario',
    preco_base: '',
  })

  const [lastSaved, setLastSaved] = useState<{ margem_percentual?: number } | null>(null)

  useEffect(() => {
    const load = async () => {
      setLoadingData(true)
      try {
        const [comprasRes, prodRes] = await Promise.all([
          comprasItensService.listPlanejamento(),
          id ? produtosService.get(id) : Promise.resolve(null as any),
        ])

        const comprasAll = extractList<CompraItemOption>(comprasRes).filter((i) => i?.id)
        setComprasOptions(comprasAll)

        const tipoById = new Map<number, CompraItemTipo>()
        for (const it of comprasAll) {
          if (!it?.id) continue
          tipoById.set(Number(it.id), it.tipo)
        }

        if (id) {
          const payload = (prodRes?.data?.data ?? prodRes?.data) as ProdutoApi
          const forma = (payload?.forma_calculo ?? 'unitario') as FormaCalculo

          // Itens planejados (todos vêm de compras_itens)
          const comprasPivot = payload?.comprasItensPivot ?? payload?.compras_itens_pivot ?? payload?.compras_itens ?? []
          const materiaisMap: QtyMap = {}
          const insumosMap: QtyMap = {}
          const equipamentosMap: QtyMap = {}
          if (Array.isArray(comprasPivot)) {
            for (const r of comprasPivot) {
              const compraItemId = r?.compra_item_id ?? r?.compraItem?.id ?? r?.item?.id
              if (compraItemId == null) continue
              const q = r?.quantidade_base ?? r?.quantidade

              const tipo = (r?.compraItem?.tipo ?? r?.item?.tipo ?? tipoById.get(Number(compraItemId))) as CompraItemTipo | undefined
              const qtyStr = q != null ? String(q) : '1'
              if (tipo === 'material') materiaisMap[String(compraItemId)] = qtyStr
              else if (tipo === 'equipamento') equipamentosMap[String(compraItemId)] = qtyStr
              else insumosMap[String(compraItemId)] = qtyStr
            }
          }


          setForm({
            nome: payload?.nome ?? '',
            forma_calculo: forma,
            preco_base: payload?.preco_base != null ? String(payload.preco_base) : '',
          })

          setMateriaisQtd(materiaisMap)
          setInsumosQtd(insumosMap)
          setEquipamentosQtd(equipamentosMap)

          const mp = payload?.margem_percentual != null ? Number(payload.margem_percentual) : undefined
          setLastSaved({
            ...(mp != null && !Number.isNaN(mp) ? { margem_percentual: mp } : {}),
          })
        } else {
          setLastSaved(null)
          setMateriaisQtd({})
          setInsumosQtd({})
          setEquipamentosQtd({})
        }
      } catch {
        showToast('Erro ao carregar produto', 'error')
      } finally {
        setLoadingData(false)
      }
    }

    load()
  }, [id])

  const comprasList = useMemo(() => {
    return [...comprasOptions].filter((i) => i?.id).sort((a, b) => String(a.nome ?? '').localeCompare(String(b.nome ?? ''), 'pt-BR'))
  }, [comprasOptions])

  const usedMateriais = useMemo(() => usedCount(materiaisQtd), [materiaisQtd])
  const usedInsumos = useMemo(() => usedCount(insumosQtd), [insumosQtd])
  const usedEquipamentos = useMemo(() => usedCount(equipamentosQtd), [equipamentosQtd])

  const simulacao = useMemo(() => {
    const precoBase = parseNumber(form.preco_base) ?? 0

    // Produto apenas planeja e precifica: campos abaixo são SIMULAÇÃO (estimativa por preço médio de compras).
    // Não consumimos estoque e não geramos custo real aqui.
    const custoUnitById = new Map<number, number>()
    for (const i of comprasOptions) {
      if (!i?.id) continue
      const custo = i.preco_medio != null ? Number(i.preco_medio) : 0
      custoUnitById.set(Number(i.id), Number.isNaN(custo) ? 0 : custo)
    }

    const sum = (map: QtyMap) => {
      let total = 0
      for (const [idStr, qtyStr] of Object.entries(map)) {
        const cid = Number(idStr)
        const q = parseNumber(String(qtyStr)) ?? 0
        if (!cid || q <= 0) continue
        total += (custoUnitById.get(cid) ?? 0) * q
      }
      return total
    }

    const custo_estimado = sum(materiaisQtd) + sum(insumosQtd) + sum(equipamentosQtd)
    const lucro_estimado = precoBase - custo_estimado
    const margem_percentual = precoBase > 0 ? (lucro_estimado / precoBase) * 100 : 0

    return {
      precoBase,
      custo_estimado,
      lucro_estimado,
      margem_percentual,
    }
  }, [form.preco_base, comprasOptions, materiaisQtd, insumosQtd, equipamentosQtd])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFieldErrors({})

    const nomeTrim = form.nome.trim()
    const precoBaseN = parseNumber(form.preco_base)

    const errors: Partial<Record<keyof FormState, string>> = {}
    if (!nomeTrim) errors.nome = 'Nome é obrigatório'
    if (!form.forma_calculo) errors.forma_calculo = 'Forma de cálculo é obrigatória'
    if (precoBaseN == null) errors.preco_base = 'Preço base é obrigatório'
    else if (precoBaseN < 0) errors.preco_base = 'Preço base não pode ser negativo'

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      showToast('Corrija os campos obrigatórios', 'error')
      return
    }

    if (hasInvalidQty(materiaisQtd)) return showToast('Corrija as quantidades em Materiais', 'error')
    if (hasInvalidQty(insumosQtd)) return showToast('Corrija as quantidades em Insumos', 'error')
    if (hasInvalidQty(equipamentosQtd)) return showToast('Corrija as quantidades em Equipamentos', 'error')

    setLoading(true)
    try {
      const custoEstimadoN = simulacao.custo_estimado
      const payload: any = {
        nome: nomeTrim,
        forma_calculo: form.forma_calculo,
        // Regra: Produto não consome/baixa estoque. Aqui é estimativa para simulação comercial.
        custo_base: custoEstimadoN > 0 ? custoEstimadoN : 0,
        preco_base: precoBaseN as number,
        compras_itens: [
          ...Object.entries(materiaisQtd),
          ...Object.entries(insumosQtd),
          ...Object.entries(equipamentosQtd),
        ]
          .map(([idStr, qtyStr]) => ({ compra_item_id: Number(idStr), quantidade_base: parseNumber(String(qtyStr)) }))
          .filter((m) => m.compra_item_id > 0 && m.quantidade_base != null && (m.quantidade_base as number) > 0),
      }

      const res = id ? await produtosService.update(id, payload) : await produtosService.create(payload)
      const saved = (res as any)?.data?.data ?? (res as any)?.data

      showToast(`Produto salvo (${labelUnidade(form.forma_calculo)})`)

      setLastSaved({
        ...(saved?.margem_percentual != null ? { margem_percentual: Number(saved.margem_percentual) } : {}),
      })

      navigate('/produtos')
    } catch (err: any) {
      const msg = err?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao salvar produto', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-5xl mx-auto">
      <PageContainer title={id ? 'Editar Produto' : 'Novo Produto'}>
        {loadingData ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div className="border border-gray-200 rounded-lg p-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Input
                  label="Nome do produto"
                  required
                  value={form.nome}
                  onChange={(v) => setForm((prev) => ({ ...prev, nome: v }))}
                  placeholder="Nome do produto"
                  error={fieldErrors.nome}
                  dense
                />

                <div className="border border-gray-200 rounded-lg p-3">
                  <div className="text-xs font-medium text-black">Tipo de venda</div>
                  <div className="mt-2 grid grid-cols-3 gap-2">
                    <label className="flex items-center gap-2 text-sm text-black">
                      <input
                        type="radio"
                        name="forma_calculo"
                        checked={form.forma_calculo === 'unitario'}
                        onChange={() => setForm((prev) => ({ ...prev, forma_calculo: 'unitario' }))}
                      />
                      Unidade
                    </label>
                    <label className="flex items-center gap-2 text-sm text-black">
                      <input
                        type="radio"
                        name="forma_calculo"
                        checked={form.forma_calculo === 'metro_linear'}
                        onChange={() => setForm((prev) => ({ ...prev, forma_calculo: 'metro_linear' }))}
                      />
                      Metro
                    </label>
                    <label className="flex items-center gap-2 text-sm text-black">
                      <input
                        type="radio"
                        name="forma_calculo"
                        checked={form.forma_calculo === 'metro_quadrado'}
                        onChange={() => setForm((prev) => ({ ...prev, forma_calculo: 'metro_quadrado' }))}
                      />
                      m²
                    </label>
                  </div>
                  {fieldErrors.forma_calculo ? <div className="mt-2 text-sm text-red-600">{fieldErrors.forma_calculo}</div> : null}
                </div>

                <Input
                  label={`Preço de venda ${labelPor(form.forma_calculo)}`}
                  required
                  type="number"
                  value={form.preco_base}
                  onChange={(v) => setForm((prev) => ({ ...prev, preco_base: v }))}
                  placeholder="0,00"
                  error={fieldErrors.preco_base}
                  dense
                />

                <div className="border border-gray-200 rounded-lg p-3">
                  <div className="text-xs text-gray-600">Custo estimado</div>
                  <div className="text-sm font-semibold text-black mt-1">{formatMoney(simulacao.custo_estimado)}</div>
                  <div className="text-xs text-gray-600 mt-2">Lucro estimado</div>
                  <div className={`text-sm font-semibold mt-1 ${simulacao.lucro_estimado < 0 ? 'text-red-700' : 'text-black'}`}>
                    {formatMoney(simulacao.lucro_estimado)}
                  </div>
                  <div className="text-xs text-gray-600 mt-2">Margem (%)</div>
                  <div className="text-sm font-semibold text-black mt-1">{fmtPercent(simulacao.margem_percentual)}</div>
                  {lastSaved?.margem_percentual != null ? (
                    <div className="text-xs text-gray-600 mt-1">Última margem salva: {fmtPercent(lastSaved.margem_percentual)}</div>
                  ) : null}
                </div>
              </div>
            </div>

            <div className="border border-gray-200 rounded-lg p-3">
              <div>
                <div className="text-sm font-semibold text-black">Como este produto é produzido</div>
                <div className="text-xs text-gray-600">Busque e informe quantidade. Quantidade &gt; 0 ativa o item.</div>
              </div>

              <div className="mt-4 overflow-x-hidden">
                <div className="grid grid-cols-3 gap-2">
                  <ColunaPadrao
                    titulo="Materiais"
                    usado={usedMateriais}
                    busca={searchMateriais}
                    onBusca={setSearchMateriais}
                    placeholder="Buscar material…"
                    itens={comprasList.filter((i) => i.tipo === 'material').map((i) => ({ id: i.id, nome: i.nome }))}
                    qtyMap={materiaisQtd}
                    setQtyMap={setMateriaisQtd}
                  />
                  <ColunaPadrao
                    titulo="Insumos"
                    usado={usedInsumos}
                    busca={searchInsumos}
                    onBusca={setSearchInsumos}
                    placeholder="Buscar insumo…"
                    itens={comprasList.filter((i) => i.tipo === 'insumo').map((i) => ({ id: i.id, nome: i.nome }))}
                    qtyMap={insumosQtd}
                    setQtyMap={setInsumosQtd}
                  />
                  <ColunaPadrao
                    titulo="Equipamentos"
                    usado={usedEquipamentos}
                    busca={searchEquipamentos}
                    onBusca={setSearchEquipamentos}
                    placeholder="Buscar equipamento…"
                    itens={comprasList.filter((i) => i.tipo === 'equipamento').map((i) => ({ id: i.id, nome: i.nome }))}
                    qtyMap={equipamentosQtd}
                    setQtyMap={setEquipamentosQtd}
                  />
                </div>
              </div>

              <div className="mt-4 bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div className="text-sm font-semibold text-black">Resumo</div>
                <div className="mt-2 text-sm text-black space-y-1">
                  <div className="flex justify-between">
                    <span>Materiais usados:</span>
                    <span>{usedMateriais}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Insumos usados:</span>
                    <span>{usedInsumos}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Equipamentos usados:</span>
                    <span>{usedEquipamentos}</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex gap-3">
              <Button type="submit" loading={loading} fullWidth={false} dense>
                Salvar
              </Button>
              <Button type="button" variant="secondary" fullWidth={false} onClick={() => navigate('/produtos')} dense>
                Cancelar
              </Button>
            </div>
          </form>
        )}
      </PageContainer>
    </div>
  )
}

