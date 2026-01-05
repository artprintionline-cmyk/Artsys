export type CompraItemTipo = 'material' | 'insumo' | 'equipamento'

export type CompraItem = {
  id: number
  tipo: CompraItemTipo
  nome: string
  unidade_compra?: string | null
  ativo?: boolean | null
  preco_medio?: number | string | null
  preco_ultimo?: number | string | null
}

export type Compra = {
  id: number
  data: string
  fornecedor?: string | null
  quantidade?: number | string | null
  valor_total?: number | string | null
  custo_unitario?: number | string | null
  observacoes?: string | null
  item?: CompraItem | null
}

export function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

export function toNumber(v: any): number {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  return Number.isNaN(n) ? 0 : n
}

export function formatMoney(v: any): string {
  const n = toNumber(v)
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}
