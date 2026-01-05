import { useEffect, useMemo, useState } from 'react'
import PageContainer from '../../components/PageContainer'
import ordensServicoService from '../../services/ordensServicoService'
import { useToast } from '../toast/ToastProvider'
import { useAuth } from '../auth/useAuth'
import Button from '../../components/Button'
import { MessageCircle, Send, QrCode, X } from 'lucide-react'

type KanbanOS = {
  id: number
  numero_os: string
  cliente?: { id: number; nome: string } | null
  status: string
  valor_total: number | string
  itens_count?: number
  produtos?: string[]
}

type WAMensagem = {
  id: number
  direcao?: string | null
  tipo?: string | null
  mensagem: string
  status?: string | null
  created_at: string
}

type ColKey = 'aberta' | 'em_producao' | 'aguardando_pagamento' | 'finalizada' | 'cancelada'

const columns: { key: ColKey; title: string }[] = [
  { key: 'aberta', title: 'Aberta' },
  { key: 'em_producao', title: 'Em produção' },
  { key: 'aguardando_pagamento', title: 'Aguardando pagamento' },
  { key: 'finalizada', title: 'Finalizada' },
  { key: 'cancelada', title: 'Cancelada' },
]

function money(v: any) {
  const n = typeof v === 'string' ? Number(v) : v
  if (Number.isFinite(n)) {
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
  }
  return String(v ?? '')
}

export default function OrdemServicoKanban() {
  const { showToast } = useToast()
  const { hasPerm } = useAuth()

  const [loading, setLoading] = useState(true)
  const [movingId, setMovingId] = useState<number | null>(null)
  const [ordens, setOrdens] = useState<KanbanOS[]>([])

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selected, setSelected] = useState<KanbanOS | null>(null)
  const [waLoading, setWaLoading] = useState(false)
  const [waSending, setWaSending] = useState(false)
  const [waSendingPix, setWaSendingPix] = useState(false)
  const [waCliente, setWaCliente] = useState<{ id: number; nome: string; telefone: string } | null>(null)
  const [waMensagens, setWaMensagens] = useState<WAMensagem[]>([])
  const [waTexto, setWaTexto] = useState('')

  const canMove = hasPerm('os.status')
  const canWhatsAppView = hasPerm('whatsapp.view')
  const canWhatsAppSend = hasPerm('whatsapp.send')

  const load = async () => {
    setLoading(true)
    try {
      const res = await ordensServicoService.list()
      const payload = res.data?.data ?? res.data
      const arr = Array.isArray(payload) ? payload : Array.isArray(payload?.data) ? payload.data : []
      setOrdens(arr as KanbanOS[])
    } catch {
      setOrdens([])
      showToast('Erro ao carregar ordens de serviço', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const openWhatsApp = async (os: KanbanOS) => {
    if (!canWhatsAppView) {
      showToast('Você não tem permissão para ver WhatsApp', 'error')
      return
    }

    setDrawerOpen(true)
    setSelected(os)
    setWaMensagens([])
    setWaCliente(null)
    setWaTexto('')
    setWaLoading(true)

    try {
      const res = await ordensServicoService.getWhatsAppHistorico(os.id)
      const payload = res.data?.data ?? res.data
      const data = payload?.data ?? payload
      setWaCliente(data?.cliente ?? null)
      setWaMensagens((data?.mensagens ?? []) as WAMensagem[])
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Erro ao carregar histórico do WhatsApp'
      showToast(msg, 'error')
    } finally {
      setWaLoading(false)
    }
  }

  const sendTexto = async () => {
    if (!selected) return
    if (!canWhatsAppSend) {
      showToast('Você não tem permissão para enviar WhatsApp', 'error')
      return
    }

    if (!waTexto.trim()) return

    setWaSending(true)
    try {
      await ordensServicoService.enviarWhatsApp(selected.id, { tipo: 'texto', mensagem: waTexto })
      showToast('Envio enfileirado')
      setWaTexto('')
      // recarrega histórico (pode demorar se estiver em fila)
      const res = await ordensServicoService.getWhatsAppHistorico(selected.id)
      const payload = res.data?.data ?? res.data
      const data = payload?.data ?? payload
      setWaMensagens((data?.mensagens ?? []) as WAMensagem[])
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Erro ao enviar mensagem'
      showToast(msg, 'error')
    } finally {
      setWaSending(false)
    }
  }

  const sendPix = async () => {
    if (!selected) return
    if (!canWhatsAppSend) {
      showToast('Você não tem permissão para enviar WhatsApp', 'error')
      return
    }

    setWaSendingPix(true)
    try {
      await ordensServicoService.enviarWhatsApp(selected.id, { tipo: 'pix_qr' })
      showToast('Envio de PIX enfileirado')
      const res = await ordensServicoService.getWhatsAppHistorico(selected.id)
      const payload = res.data?.data ?? res.data
      const data = payload?.data ?? payload
      setWaMensagens((data?.mensagens ?? []) as WAMensagem[])
    } catch (err: any) {
      const msg = err?.response?.data?.message ?? 'Erro ao enviar PIX'
      showToast(msg, 'error')
    } finally {
      setWaSendingPix(false)
    }
  }

  const byColumn = useMemo(() => {
    const base: Record<ColKey, KanbanOS[]> = {
      aberta: [],
      em_producao: [],
      aguardando_pagamento: [],
      finalizada: [],
      cancelada: [],
    }

    for (const os of ordens) {
      const key = (os.status as ColKey) in base ? (os.status as ColKey) : 'aberta'
      base[key].push(os)
    }

    return base
  }, [ordens])

  const onDragStart = (e: React.DragEvent, osId: number) => {
    if (!canMove) return
    e.dataTransfer.setData('text/plain', String(osId))
    e.dataTransfer.effectAllowed = 'move'
  }

  const onDrop = async (e: React.DragEvent, destino: ColKey) => {
    e.preventDefault()
    if (!canMove) {
      showToast('Você não tem permissão para mover OS', 'error')
      return
    }

    const raw = e.dataTransfer.getData('text/plain')
    const osId = Number(raw)
    if (!Number.isFinite(osId)) return

    const atual = ordens.find((o) => o.id === osId)
    if (!atual) return

    if (atual.status === destino) return

    const prev = ordens
    setMovingId(osId)

    setOrdens((curr) =>
      curr.map((o) => {
        if (o.id !== osId) return o
        return { ...o, status: destino }
      }),
    )

    try {
      await ordensServicoService.updateStatusDestino(osId, destino)
      showToast('Status atualizado')
    } catch (err: any) {
      setOrdens(prev)
      const msg = err?.response?.data?.message ?? 'Erro ao atualizar status'
      showToast(msg, 'error')
    } finally {
      setMovingId(null)
    }
  }

  return (
    <div className="max-w-7xl mx-auto">
      <PageContainer title="Kanban - Ordens de Serviço">
        {loading ? <div className="text-sm text-gray-700">Carregando...</div> : null}

        {!loading ? (
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            {columns.map((col) => (
              <div
                key={col.key}
                className="bg-white border border-gray-200 rounded-lg p-3"
                onDragOver={(e) => {
                  if (!canMove) return
                  e.preventDefault()
                }}
                onDrop={(e) => onDrop(e, col.key)}
              >
                <div className="text-sm font-semibold text-black mb-3">{col.title}</div>

                <div className="space-y-3">
                  {byColumn[col.key].map((os) => (
                    <div
                      key={os.id}
                      className={`border border-gray-200 rounded-lg p-3 bg-white ${canMove ? 'cursor-move' : 'cursor-default'} ${movingId === os.id ? 'opacity-60' : ''}`}
                      draggable={canMove && movingId === null}
                      onDragStart={(e) => onDragStart(e, os.id)}
                      title={canMove ? 'Arraste para mudar o status' : 'Sem permissão para mover'}
                    >
                      <div className="flex items-start justify-between gap-2">
                        <div className="text-sm font-semibold text-black">{os.numero_os}</div>
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            className={`p-1 rounded hover:bg-gray-100 ${canWhatsAppView ? '' : 'opacity-50 cursor-not-allowed'}`}
                            onClick={() => openWhatsApp(os)}
                            disabled={!canWhatsAppView}
                            title="WhatsApp"
                          >
                            <MessageCircle className="w-4 h-4 text-black" />
                          </button>
                        </div>
                      </div>
                      <div className="text-xs text-gray-700 mt-1">{os.cliente?.nome ?? '-'}</div>

                      <div className="text-xs text-gray-700 mt-2">Valor: {money(os.valor_total)}</div>

                      {Array.isArray(os.produtos) && os.produtos.length > 0 ? (
                        <div className="text-xs text-gray-700 mt-2">Produtos: {os.produtos.join(', ')}</div>
                      ) : os.itens_count ? (
                        <div className="text-xs text-gray-700 mt-2">Itens: {os.itens_count}</div>
                      ) : null}

                      {movingId === os.id ? <div className="text-xs text-gray-600 mt-2">Atualizando...</div> : null}
                    </div>
                  ))}

                  {byColumn[col.key].length === 0 ? <div className="text-xs text-gray-600">Sem OS</div> : null}
                </div>
              </div>
            ))}
          </div>
        ) : null}

        {drawerOpen ? (
          <div className="fixed inset-0 z-50">
            <div className="absolute inset-0 bg-black/30" onClick={() => setDrawerOpen(false)} />
            <div className="absolute top-0 right-0 h-full w-full max-w-md bg-white border-l border-gray-200 p-4 overflow-auto">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="text-lg font-semibold text-black">WhatsApp</div>
                  <div className="text-xs text-gray-700 mt-1">
                    {selected ? (
                      <>
                        OS: <span className="font-semibold">{selected.numero_os}</span> · Status: <span className="font-semibold">{selected.status}</span>
                      </>
                    ) : null}
                  </div>
                </div>
                <button type="button" className="p-2 rounded hover:bg-gray-100" onClick={() => setDrawerOpen(false)} title="Fechar">
                  <X className="w-5 h-5 text-black" />
                </button>
              </div>

              <div className="mt-4">
                <div className="text-sm font-semibold text-black">Cliente</div>
                <div className="text-sm text-gray-800 mt-1">{waCliente?.nome ?? selected?.cliente?.nome ?? '-'}</div>
                <div className="text-xs text-gray-700">{waCliente?.telefone ?? '-'}</div>
              </div>

              <div className="mt-4">
                <div className="text-sm font-semibold text-black">Histórico</div>
                {waLoading ? <div className="text-sm text-gray-700 mt-2">Carregando...</div> : null}

                {!waLoading ? (
                  <div className="mt-2 space-y-2">
                    {waMensagens.map((m) => (
                      <div key={m.id} className={`text-sm p-3 rounded-lg border ${m.direcao === 'saida' ? 'bg-gray-50 border-gray-200' : 'bg-white border-gray-200'}`}>
                        <div className="flex items-center justify-between gap-2">
                          <div className="text-xs text-gray-700">
                            {m.direcao === 'saida' ? 'Enviado' : 'Recebido'} {m.tipo ? `· ${m.tipo}` : ''}
                          </div>
                          <div className="text-xs text-gray-600">{m.created_at}</div>
                        </div>
                        <div className="text-black mt-1 whitespace-pre-wrap">{m.mensagem}</div>
                        {m.status ? <div className="text-xs text-gray-600 mt-1">Status: {m.status}</div> : null}
                      </div>
                    ))}
                    {waMensagens.length === 0 ? <div className="text-sm text-gray-700">Sem mensagens para esta OS.</div> : null}
                  </div>
                ) : null}
              </div>

              <div className="mt-4 border-t border-gray-200 pt-4">
                <div className="text-sm font-semibold text-black">Enviar</div>

                <textarea
                  className="w-full mt-2 px-3 py-2 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                  rows={3}
                  value={waTexto}
                  onChange={(e) => setWaTexto(e.target.value)}
                  placeholder={canWhatsAppSend ? 'Mensagem rápida...' : 'Sem permissão para enviar'}
                  disabled={!canWhatsAppSend}
                />

                <div className="flex gap-2 mt-2">
                  <Button type="button" fullWidth={false} loading={waSending} onClick={sendTexto} disabled={!canWhatsAppSend || !waTexto.trim()}>
                    <span className="flex items-center gap-2">
                      <Send className="w-4 h-4" />
                      Enviar
                    </span>
                  </Button>

                  <Button type="button" variant="secondary" fullWidth={false} loading={waSendingPix} onClick={sendPix} disabled={!canWhatsAppSend}>
                    <span className="flex items-center gap-2">
                      <QrCode className="w-4 h-4" />
                      Enviar PIX
                    </span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </PageContainer>
    </div>
  )
}
