import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import acabamentosService from '../../services/acabamentosService'
import { useToast } from '../toast/ToastProvider'

type Acabamento = {
  id: number
  nome: string
  unidade_consumo?: string | null
  custo_unitario?: number | string | null
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
}

export default function AcabamentosList({ basePath = '/acabamentos', title = 'Acabamentos' }: Props) {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [acabamentos, setAcabamentos] = useState<Acabamento[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await acabamentosService.list()
      setAcabamentos(asArray(res.data) as Acabamento[])
    } catch (e: any) {
      setAcabamentos([])
      showToast('Erro ao carregar acabamentos', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleDelete = async (id: number) => {
    const ok = window.confirm('Deseja desativar este acabamento?')
    if (!ok) return

    try {
      await acabamentosService.remove(id)
      showToast('Acabamento desativado com sucesso')
      await load()
    } catch (e: any) {
      showToast('Erro ao desativar acabamento', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title={title} actionLabel="Novo Acabamento" onAction={() => navigate(`${basePath}/novo`)}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Nome</th>
                  <th className="p-3 text-left">Unidade</th>
                  <th className="p-3 text-left">Custo unitário</th>
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {acabamentos.map((a) => (
                  <tr key={a.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{a.nome}</td>
                    <td className="p-3 text-gray-800">{a.unidade_consumo || 'un'}</td>
                    <td className="p-3 text-gray-800">{formatMoney(a.custo_unitario)}</td>
                    <td className="p-3 text-gray-800">{a.ativo === false ? 'inativo' : 'ativo'}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`${basePath}/${a.id}/editar`} className="text-black hover:underline mr-4">
                        Editar
                      </Link>
                      <button onClick={() => handleDelete(a.id)} className="text-black hover:underline">
                        Desativar
                      </button>
                    </td>
                  </tr>
                ))}
                {acabamentos.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={5}>
                      Nenhum acabamento cadastrado.
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
