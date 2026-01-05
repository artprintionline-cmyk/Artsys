import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import whatsappService, { type WhatsAppConversaResumo } from '../../services/whatsappService'
import { useToast } from '../toast/ToastProvider'

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function formatDateTime(iso?: string | null) {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleString('pt-BR')
}

export default function WhatsAppList() {
  const { showToast } = useToast()
  const [loading, setLoading] = useState(true)
  const [items, setItems] = useState<WhatsAppConversaResumo[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await whatsappService.listConversas()
      setItems(asArray(res.data) as WhatsAppConversaResumo[])
    } catch {
      setItems([])
      showToast('Erro ao carregar conversas', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="WhatsApp">
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Cliente</th>
                  <th className="p-3 text-left">Número</th>
                  <th className="p-3 text-left">Última mensagem</th>
                  <th className="p-3 text-left">Quando</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {items.map((c) => (
                  <tr key={c.numero} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{c.cliente?.nome ?? '-'}</td>
                    <td className="p-3 text-gray-800">{c.numero}</td>
                    <td className="p-3 text-gray-800">{c.ultima_mensagem ?? '-'}</td>
                    <td className="p-3 text-gray-800">{formatDateTime(c.ultima_em)}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`/whatsapp/${encodeURIComponent(c.numero)}`} className="text-black hover:underline">
                        Abrir
                      </Link>
                    </td>
                  </tr>
                ))}
                {items.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={5}>
                      Nenhuma conversa encontrada.
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
