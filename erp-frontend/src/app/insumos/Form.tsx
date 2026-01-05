import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import Input from '../../components/Input'
import Button from '../../components/Button'
import insumosService from '../../services/insumosService'
import { useToast } from '../toast/ToastProvider'

function parseNumber(value: string) {
  const normalized = value.replace(',', '.').trim()
  if (!normalized) return null
  const n = Number(normalized)
  return Number.isNaN(n) ? null : n
}

type Props = {
  basePath?: string
  title?: string
}

export default function InsumoForm({ basePath = '/insumos', title }: Props) {
  const { id } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(false)
  const [loadingData, setLoadingData] = useState(false)

  const [form, setForm] = useState({
    nome: '',
    sku: '',
    unidade_medida: 'un',
    custo_unitario: '',
    estoque_atual: '',
    controla_estoque: true,
    ativo: true,
  })

  useEffect(() => {
    const load = async () => {
      if (!id) return
      setLoadingData(true)
      try {
        const res = await insumosService.get(id)
        const payload = res?.data
        const data = payload?.data ?? payload
        setForm({
          nome: data?.nome ?? '',
          sku: data?.sku ?? '',
          unidade_medida: data?.unidade_medida ?? 'un',
          custo_unitario: data?.custo_unitario != null ? String(data.custo_unitario) : '',
          estoque_atual: data?.estoque_atual != null ? String(data.estoque_atual) : '',
          controla_estoque: data?.controla_estoque != null ? Boolean(data.controla_estoque) : true,
          ativo: data?.ativo != null ? Boolean(data.ativo) : true,
        })
      } catch (e: any) {
        showToast('Erro ao carregar insumo', 'error')
      } finally {
        setLoadingData(false)
      }
    }

    load()
  }, [id])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()

    const nomeTrim = form.nome.trim()
    const custoN = parseNumber(form.custo_unitario)
    const estoqueN = parseNumber(form.estoque_atual)

    if (!nomeTrim) {
      showToast('Nome é obrigatório', 'error')
      return
    }
    if (custoN == null || custoN < 0) {
      showToast('Custo unitário inválido', 'error')
      return
    }
    if (!form.unidade_medida.trim()) {
      showToast('Unidade de medida é obrigatória', 'error')
      return
    }
    if (form.controla_estoque && (estoqueN == null || estoqueN < 0)) {
      showToast('Estoque atual inválido', 'error')
      return
    }

    setLoading(true)
    try {
      const payload: any = {
        nome: nomeTrim,
        sku: form.sku?.trim() ? form.sku.trim() : null,
        unidade_medida: form.unidade_medida.trim(),
        custo_unitario: custoN,
        estoque_atual: form.controla_estoque ? estoqueN : null,
        controla_estoque: Boolean(form.controla_estoque),
        ativo: Boolean(form.ativo),
      }

      if (id) await insumosService.update(id, payload)
      else await insumosService.create(payload)

      showToast('Registro salvo com sucesso')
      navigate(basePath)
    } catch (e: any) {
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao salvar insumo', 'error')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="max-w-4xl mx-auto">
      <PageContainer title={title ?? (id ? 'Editar Insumo' : 'Novo Insumo')}>
        {loadingData ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div className="border border-gray-200 rounded-lg p-3">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Input label="Nome" required value={form.nome} onChange={(v) => setForm((p) => ({ ...p, nome: v }))} placeholder="Ex.: Tinta" dense />
                <Input label="SKU (opcional)" value={form.sku} onChange={(v) => setForm((p) => ({ ...p, sku: v }))} placeholder="Ex.: INS-001" dense />
                <Input
                  label="Unidade"
                  required
                  value={form.unidade_medida}
                  onChange={(v) => setForm((p) => ({ ...p, unidade_medida: v }))}
                  placeholder="un"
                  dense
                />
                <Input
                  label="Custo unitário"
                  required
                  type="number"
                  value={form.custo_unitario}
                  onChange={(v) => setForm((p) => ({ ...p, custo_unitario: v }))}
                  placeholder="0,00"
                  dense
                />

                <Input
                  label="Estoque atual"
                  type="number"
                  value={form.estoque_atual}
                  onChange={(v) => setForm((p) => ({ ...p, estoque_atual: v }))}
                  placeholder="0"
                  dense
                  disabled={!form.controla_estoque}
                />
                <label className="block">
                  <div className="mb-1 text-xs font-medium text-black">Controla estoque</div>
                  <select
                    className="w-full px-3 py-2 h-9 text-sm border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                    value={form.controla_estoque ? '1' : '0'}
                    onChange={(e) => setForm((p) => ({ ...p, controla_estoque: e.target.value === '1' }))}
                  >
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                  </select>
                </label>
                <label className="block">
                  <div className="mb-1 text-xs font-medium text-black">Ativo</div>
                  <select
                    className="w-full px-3 py-2 h-9 text-sm border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                    value={form.ativo ? '1' : '0'}
                    onChange={(e) => setForm((p) => ({ ...p, ativo: e.target.value === '1' }))}
                  >
                    <option value="1">Sim</option>
                    <option value="0">Não</option>
                  </select>
                </label>
              </div>
            </div>

            <div className="flex gap-3">
              <Button type="submit" loading={loading} fullWidth={false} dense>
                Salvar
              </Button>
              <Button type="button" variant="secondary" fullWidth={false} onClick={() => navigate(basePath)} dense>
                Cancelar
              </Button>
            </div>
          </form>
        )}
      </PageContainer>
    </div>
  )
}
