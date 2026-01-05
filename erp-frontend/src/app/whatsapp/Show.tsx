import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import whatsappService, { type WhatsAppMensagem } from '../../services/whatsappService'
import { useToast } from '../toast/ToastProvider'

export default function WhatsAppShow() {
  const { numero } = useParams()
  const navigate = useNavigate()
  const { showToast } = useToast()

  const numeroDecoded = useMemo(() => {
    try {
      return numero ? decodeURIComponent(numero) : ''
    } catch {
      return numero ?? ''
    }
  }, [numero])

  const [loading, setLoading] = useState(true)
  const [items, setItems] = useState<WhatsAppMensagem[]>([])
  const [text, setText] = useState('')

  const load = async () => {
    if (!numeroDecoded) return
    setLoading(true)
    try {
      const res = await whatsappService.getConversa(numeroDecoded)
      const data = res.data?.data ?? []
      setItems(data as WhatsAppMensagem[])
    } catch {
      setItems([])
      showToast('Erro ao carregar conversa', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [numeroDecoded])

  const enviar = async () => {
    const msg = text.trim()
    if (!numeroDecoded || msg === '') return

    try {
      await whatsappService.enviarMensagem(numeroDecoded, msg)
      setText('')
      showToast('Mensagem enfileirada')
      await load()
    } catch {
      showToast('Erro ao enviar mensagem', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="WhatsApp" actionLabel="Voltar" onAction={() => navigate('/whatsapp')}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="space-y-4">
            <div className="text-sm text-gray-800">
              <span className="font-semibold text-black">Número:</span> {numeroDecoded}
            </div>

            <div className="border rounded-md bg-white p-3 h-[420px] overflow-auto">
              {items.length === 0 ? (
                <div className="text-sm text-gray-700">Nenhuma mensagem.</div>
              ) : (
                <div className="space-y-2">
                  {items.map((m) => (
                    <div key={m.id} className={`flex ${m.direcao === 'saida' ? 'justify-end' : 'justify-start'}`}>
                      <div
                        className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                          m.direcao === 'saida' ? 'bg-yellow-200 text-black' : 'bg-gray-100 text-black'
                        }`}
                      >
                        <div className="whitespace-pre-wrap">{m.mensagem}</div>
                        <div className="mt-1 text-[11px] text-gray-600 flex gap-2">
                          <span>{new Date(m.created_at).toLocaleString('pt-BR')}</span>
                          <span>{m.status}</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div className="flex gap-2">
              <input
                value={text}
                onChange={(e) => setText(e.target.value)}
                placeholder="Digite uma mensagem..."
                className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm"
              />
              <button
                onClick={enviar}
                className="bg-yellow-400 hover:bg-yellow-500 text-black px-4 py-2 rounded-md text-sm"
              >
                Enviar
              </button>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
