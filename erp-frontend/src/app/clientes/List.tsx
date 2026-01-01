import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import clientesService from '../../services/clientesService'

export default function ClientesList() {
  const [loading, setLoading] = useState(true)
  const [clientes, setClientes] = useState<any[]>([])

  useEffect(() => {
    fetchList()
  }, [])

  async function fetchList() {
    setLoading(true)
    try {
      const res = await clientesService.list()
      setClientes(res.data || [])
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
    <div>
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-2xl font-semibold">Clientes</h2>
        <Link to="/clientes/novo" className="bg-yellow-400 text-black px-3 py-1 rounded">Novo Cliente</Link>
      </div>

      {loading ? (
        <div>Carregando...</div>
      ) : (
        <div className="overflow-auto bg-white rounded shadow">
          <table className="min-w-full table-auto">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-2 text-left">Nome</th>
                <th className="px-4 py-2 text-left">Telefone</th>
                <th className="px-4 py-2 text-left">Email</th>
                <th className="px-4 py-2 text-left">Status</th>
                <th className="px-4 py-2">Ações</th>
              </tr>
            </thead>
            <tbody>
              {clientes.map((c) => (
                <tr key={c.id} className="border-t">
                  <td className="px-4 py-2">{c.nome}</td>
                  <td className="px-4 py-2">{c.telefone}</td>
                  <td className="px-4 py-2">{c.email}</td>
                  <td className="px-4 py-2">{c.status || 'ativo'}</td>
                  <td className="px-4 py-2 text-center">
                    <Link to={`/clientes/${c.id}/editar`} className="text-blue-600 mr-3">Editar</Link>
                    <button onClick={() => handleDelete(c.id)} className="text-red-600">Inativar</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
