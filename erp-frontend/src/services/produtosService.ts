import api from './api'

export interface ProdutoPayload {
  nome: string
  sku?: string
  ativo?: boolean

  // Produto é sempre vendável no backend; manter opcional apenas por compat.
  vendavel?: boolean

  forma_calculo: 'unitario' | 'metro_linear' | 'metro_quadrado'
  custo_base: number
  preco_base: number

  // Materiais (Produto Vivo)
  materiais?: Array<{
    material_id?: number
    material_produto_id?: number
    quantidade_base?: number
    quantidade?: number
  }>

  // Insumos (Produto Vivo)
  insumos?: Array<{
    insumo_id?: number
    quantidade_base?: number
  }>

  // Processos produtivos (estimativa)
  processos_produtivos?: Array<{
    processo_produtivo_id?: number
    quantidade_base?: number
  }>

  // Mão de obra (estimativa)
  mao_obra?: Array<{
    custo_mao_obra_id?: number
    minutos_base?: number
  }>

  // Equipamentos (estimativa)
  equipamentos?: Array<{
    equipamento_id?: number
    quantidade_base?: number
  }>
}

const base = '/produtos'

export default {
  list() {
    return api.get(base)
  },
  get(id: number | string) {
    return api.get(`${base}/${id}`)
  },
  create(payload: ProdutoPayload) {
    return api.post(base, payload)
  },
  update(id: number | string, payload: Partial<ProdutoPayload>) {
    return api.put(`${base}/${id}`, payload)
  },
  remove(id: number | string) {
    return api.delete(`${base}/${id}`)
  },
}
