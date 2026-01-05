import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import insumosService from '../../services/insumosService'
import { useToast } from '../toast/ToastProvider'

type Insumo = {
  id: number
  nome: string
  sku?: string | null
  unidade_medida?: string | null
  custo_unitario?: number | string | null
  estoque_atual?: number | string | null
  controla_estoque?: boolean | null
  ativo?: boolean | null
}

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function formatMoney(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

type Props = {
  basePath?: string
  title?: string
  showEstoque?: boolean
}

export default function InsumosList({ basePath = '/insumos', title = 'Insumos', showEstoque = true }: Props) {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [insumos, setInsumos] = useState<Insumo[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await insumosService.list()
      setInsumos(asArray(res.data) as Insumo[])
    } catch (e: any) {
      setInsumos([])
      showToast('Erro ao carregar insumos', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleDelete = async (id: number) => {
    const ok = window.confirm('Deseja desativar este insumo?')
    if (!ok) return

    try {
      await insumosService.remove(id)
      showToast('Insumo desativado com sucesso')
      await load()
    } catch (e: any) {
      showToast('Erro ao desativar insumo', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title={title} actionLabel="Novo" onAction={() => navigate(`${basePath}/novo`)}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Nome</th>
                  <th className="p-3 text-left">SKU</th>
                  <th className="p-3 text-left">Unidade</th>
                  <th className="p-3 text-left">Custo unitário</th>
                  {showEstoque ? <th className="p-3 text-left">Estoque</th> : null}
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {insumos.map((i) => (
                  <tr key={i.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{i.nome}</td>
                    <td className="p-3 text-gray-800">{i.sku || '-'}</td>
                    <td className="p-3 text-gray-800">{i.unidade_medida || 'un'}</td>
                    <td className="p-3 text-gray-800">{formatMoney(i.custo_unitario)}</td>
                    {showEstoque ? <td className="p-3 text-gray-800">{i.controla_estoque ? (i.estoque_atual ?? 0) : '-'}</td> : null}
                    <td className="p-3 text-gray-800">{i.ativo === false ? 'inativo' : 'ativo'}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`${basePath}/${i.id}/editar`} className="text-black hover:underline mr-4">
                        Editar
                      </Link>
                      <button onClick={() => handleDelete(i.id)} className="text-black hover:underline">
                        Desativar
                      </button>
                    </td>
                  </tr>
                ))}
                {insumos.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={showEstoque ? 7 : 6}>
                      Nenhum insumo cadastrado.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
