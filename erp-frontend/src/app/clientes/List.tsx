import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import clientesService from '../../services/clientesService'
import PageContainer from '../../components/PageContainer'

export default function ClientesList() {
  const navigate = useNavigate()
  const [loading, setLoading] = useState(true)
  const [clientes, setClientes] = useState<any[]>([])

  useEffect(() => {
    fetchList()
  }, [])

  async function fetchList() {
    setLoading(true)
    try {
      const res = await clientesService.list()
      const payload = res?.data
      const list = Array.isArray(payload) ? payload : (payload?.data ?? [])
      setClientes(Array.isArray(list) ? list : [])
    } catch (err) {
      alert('Erro ao carregar clientes')
    } finally {
      setLoading(false)
    }
  }

  async function handleDelete(id: number) {
    if (!confirm('Deseja inativar este cliente?')) return
    try {
      await clientesService.remove(id)
      setClientes((c) => c.filter((x) => x.id !== id))
    } catch (err) {
      alert('Erro ao inativar cliente')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Clientes" actionLabel="Novo Cliente" onAction={() => navigate('/clientes/novo')}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Nome</th>
                  <th className="p-3 text-left">Telefone</th>
                  <th className="p-3 text-left">Email</th>
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {clientes.map((c) => (
                  <tr key={c.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{c.nome}</td>
                    <td className="p-3 text-gray-800">{c.telefone}</td>
                    <td className="p-3 text-gray-800">{c.email}</td>
                    <td className="p-3 text-gray-800">{c.status || 'ativo'}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`/clientes/${c.id}/editar`} className="text-black hover:underline mr-4">
                        Editar
                      </Link>
                      <button onClick={() => handleDelete(c.id)} className="text-black hover:underline">
                        Desativar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
