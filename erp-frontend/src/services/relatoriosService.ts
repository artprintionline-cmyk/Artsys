import api from './api'

export type DateRange = {
  data_inicio?: string
  data_fim?: string
}

export type RelatorioOsParams = DateRange & {
  cliente_id?: string | number
  status?: string
}

export type RelatorioFinanceiroParams = DateRange & {
  cliente_id?: string | number
  status?: string
}

const base = '/relatorios'

export default {
  ordensServico(params: RelatorioOsParams) {
    return api.get(`${base}/ordens-servico`, { params })
  },
  producao(params: DateRange) {
    return api.get(`${base}/producao`, { params })
  },
  produtosMaisUsados(params: DateRange) {
    return api.get(`${base}/produtos-mais-usados`, { params })
  },
  financeiro(params: RelatorioFinanceiroParams) {
    return api.get(`${base}/financeiro`, { params })
  },
  faturamento(params: DateRange) {
    return api.get(`${base}/faturamento`, { params })
  },
  inadimplencia(params: { cliente_id?: string | number }) {
    return api.get(`${base}/inadimplencia`, { params })
  },
}
