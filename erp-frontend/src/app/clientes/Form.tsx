import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import clientesService from '../../services/clientesService'
import PageContainer from '../../components/PageContainer'
import Input from '../../components/Input'
import Textarea from '../../components/Textarea'
import Button from '../../components/Button'

export default function ClienteForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [loading, setLoading] = useState(false)
  const [form, setForm] = useState({ nome: '', telefone: '', email: '', documento: '', observacoes: '' })
  const [financeiroResumo, setFinanceiroResumo] = useState<any | null>(null)

  useEffect(() => {
    if (id) {
      fetchCliente(id)
      fetchFinanceiroResumo(id)
    }
  }, [id])

  async function fetchCliente(id: string) {
    try {
      const res = await clientesService.get(id)
      const payload = res?.data
      const data = payload?.data ?? payload
      setForm(data)
    } catch (err) {
      alert('Erro ao carregar cliente')
    }
  }

  async function fetchFinanceiroResumo(id: string) {
    try {
      const res = await clientesService.financeiroResumo(id)
      const payload = res?.data
      const data = payload?.data ?? payload
      setFinanceiroResumo(data)
    } catch (err) {
      // best-effort: pode falhar por permissão (financeiro.view) ou ausência de dados
      setFinanceiroResumo(null)
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
    <div className="max-w-4xl mx-auto">
      <PageContainer title={id ? 'Editar Cliente' : 'Novo Cliente'}>
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input
              label="Nome"
              required
              value={form.nome}
              onChange={(v) => setForm({ ...form, nome: v })}
              placeholder="Nome do cliente"
            />
            <Input
              label="Telefone"
              value={form.telefone}
              onChange={(v) => setForm({ ...form, telefone: v })}
              placeholder="(00) 00000-0000"
            />
            <Input
              label="Email"
              type="email"
              value={form.email}
              onChange={(v) => setForm({ ...form, email: v })}
              placeholder="email@exemplo.com"
            />
            <Input
              label="Documento"
              value={form.documento}
              onChange={(v) => setForm({ ...form, documento: v })}
              placeholder="CPF/CNPJ"
            />

            <div className="md:col-span-2">
              <Textarea
                label="Observações"
                value={form.observacoes}
                onChange={(v) => setForm({ ...form, observacoes: v })}
                placeholder="Observações sobre o cliente"
              />
            </div>
          </div>

          <div className="flex gap-3">
            <Button type="submit" loading={loading} fullWidth={false}>
              Salvar
            </Button>
            <Button type="button" variant="secondary" fullWidth={false} onClick={() => navigate('/clientes')}>
              Cancelar
            </Button>
          </div>
        </form>

        {id && financeiroResumo ? (
          <div className="mt-8 bg-white border border-gray-200 rounded-lg p-4">
            <div className="text-lg font-semibold text-black">Resumo Financeiro</div>
            <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div className="border border-gray-200 rounded-md p-3">
                <div className="text-gray-700">Pendente</div>
                <div className="text-black font-bold">R$ {Number(financeiroResumo?.totais?.pendente ?? 0).toFixed(2)}</div>
              </div>
              <div className="border border-gray-200 rounded-md p-3">
                <div className="text-gray-700">Pago</div>
                <div className="text-black font-bold">R$ {Number(financeiroResumo?.totais?.pago ?? 0).toFixed(2)}</div>
              </div>
              <div className="border border-gray-200 rounded-md p-3">
                <div className="text-gray-700">Cancelado</div>
                <div className="text-black font-bold">R$ {Number(financeiroResumo?.totais?.cancelado ?? 0).toFixed(2)}</div>
              </div>
            </div>

            <div className="mt-6">
              <div className="text-sm font-semibold text-black mb-2">Últimos lançamentos</div>
              {Array.isArray(financeiroResumo?.ultimos) && financeiroResumo.ultimos.length > 0 ? (
                <div className="overflow-auto border border-gray-200 rounded-md">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 text-gray-700">
                      <tr>
                        <th className="text-left p-2">ID</th>
                        <th className="text-left p-2">Descrição</th>
                        <th className="text-left p-2">OS</th>
                        <th className="text-left p-2">Valor</th>
                        <th className="text-left p-2">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {financeiroResumo.ultimos.map((l: any) => (
                        <tr key={l.id} className="border-t border-gray-200">
                          <td className="p-2">{l.id}</td>
                          <td className="p-2">{l.descricao}</td>
                          <td className="p-2">{l?.ordem_servico?.numero ?? '-'}</td>
                          <td className="p-2">R$ {Number(l.valor ?? 0).toFixed(2)}</td>
                          <td className="p-2">{l.status}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-sm text-gray-700">Sem lançamentos.</div>
              )}
            </div>
          </div>
        ) : null}
      </PageContainer>
    </div>
  )
}
