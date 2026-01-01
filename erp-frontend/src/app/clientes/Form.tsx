import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import clientesService from '../../services/clientesService'

export default function ClienteForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [form, setForm] = useState({ nome: '', telefone: '', email: '', documento: '', observacoes: '' })

  useEffect(() => {
    if (id) fetchCliente(id)
  }, [id])

  async function fetchCliente(id: string) {
    try {
      const res = await clientesService.get(id)
      setForm(res.data)
    } catch (err) {
      alert('Erro ao carregar cliente')
    }
  }

  async function handleSubmit(e: any) {
    e.preventDefault()
    if (!form.nome) return alert('Nome é obrigatório')
    setLoading(true)
    try {
      if (id) await clientesService.update(id, form)
      else await clientesService.create(form)
      navigate('/clientes')
    } catch (err) {
      alert('Erro ao salvar cliente')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h2 className="text-2xl font-semibold mb-4">{id ? 'Editar' : 'Novo'} Cliente</h2>

      <form onSubmit={handleSubmit} className="space-y-4 max-w-lg">
        <div>
          <label className="block mb-1">Nome</label>
          <input value={form.nome} onChange={(e) => setForm({ ...form, nome: e.target.value })} className="w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label className="block mb-1">Telefone</label>
          <input value={form.telefone} onChange={(e) => setForm({ ...form, telefone: e.target.value })} className="w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label className="block mb-1">Email</label>
          <input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label className="block mb-1">Documento</label>
          <input value={form.documento} onChange={(e) => setForm({ ...form, documento: e.target.value })} className="w-full border rounded px-3 py-2" />
        </div>

        <div>
          <label className="block mb-1">Observações</label>
          <textarea value={form.observacoes} onChange={(e) => setForm({ ...form, observacoes: e.target.value })} className="w-full border rounded px-3 py-2" />
        </div>

        <div>
          <button disabled={loading} className="bg-yellow-400 text-black px-4 py-2 rounded">Salvar</button>
        </div>
      </form>
    </div>
  )
}
