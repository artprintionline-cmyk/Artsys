import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import produtosService from '../../services/produtosService'
import { useToast } from '../toast/ToastProvider'

type Produto = {
  id: number
  nome: string
  sku?: string | null
  preco?: number | string | null
  status?: string | null
}

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function formatPreco(v: any) {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : 0
  if (Number.isNaN(n)) return 'R$ 0,00'
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

export default function ProdutosList() {
  const navigate = useNavigate()
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [produtos, setProdutos] = useState<Produto[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await produtosService.list()
      setProdutos(asArray(res.data) as Produto[])
    } catch (e: any) {
      setProdutos([])
      const status = e?.response?.status
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : status ? `Erro ao carregar produtos (${status})` : 'Erro ao carregar produtos', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleDelete = async (id: number) => {
    const ok = window.confirm('Deseja desativar este produto?')
    if (!ok) return

    try {
      await produtosService.remove(id)
      showToast('Produto desativado com sucesso')
      await load()
    } catch (e: any) {
      showToast('Erro ao desativar produto', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Produtos" actionLabel="Novo Produto" onAction={() => navigate('/produtos/novo')}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Nome</th>
                  <th className="p-3 text-left">SKU</th>
                  <th className="p-3 text-left">Preço</th>
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {produtos.map((p) => (
                  <tr key={p.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{p.nome}</td>
                    <td className="p-3 text-gray-800">{p.sku || '-'}</td>
                    <td className="p-3 text-gray-800">{formatPreco(p.preco)}</td>
                    <td className="p-3 text-gray-800">{p.status || 'ativo'}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`/produtos/${p.id}/editar`} className="text-black hover:underline mr-4">
                        Editar
                      </Link>
                      <button onClick={() => handleDelete(p.id)} className="text-black hover:underline">
                        Desativar
                      </button>
                    </td>
                  </tr>
                ))}
                {produtos.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={5}>
                      Nenhum produto cadastrado.
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
