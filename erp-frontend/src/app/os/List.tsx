import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import PageContainer from '../../components/PageContainer'
import ordensServicoService from '../../services/ordensServicoService'
import { useToast } from '../toast/ToastProvider'

type OS = {
  id: number
  numero_os: string
  cliente?: { id: number; nome: string } | null
  status: string
  valor_total: number | string
  created_at: string
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

function formatDate(iso: string) {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('pt-BR')
}

export default function OrdemServicoList() {
  const navigate = useNavigate()
  const { showToast } = useToast()
  const [loading, setLoading] = useState(true)
  const [ordens, setOrdens] = useState<OS[]>([])

  const load = async () => {
    setLoading(true)
    try {
      const res = await ordensServicoService.list()
      setOrdens(asArray(res.data) as OS[])
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

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Ordens de Serviço" actionLabel="Nova OS" onAction={() => navigate('/ordens-servico/nova')}>
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-100 text-black">
                <tr>
                  <th className="p-3 text-left">Nº OS</th>
                  <th className="p-3 text-left">Cliente</th>
                  <th className="p-3 text-left">Status</th>
                  <th className="p-3 text-left">Valor Total</th>
                  <th className="p-3 text-left">Data</th>
                  <th className="p-3 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {ordens.map((os) => (
                  <tr key={os.id} className="border-t hover:bg-gray-50">
                    <td className="p-3 text-black">{os.numero_os}</td>
                    <td className="p-3 text-gray-800">{os.cliente?.nome ?? '-'}</td>
                    <td className="p-3 text-gray-800">{os.status}</td>
                    <td className="p-3 text-gray-800">{formatMoney(os.valor_total)}</td>
                    <td className="p-3 text-gray-800">{formatDate(os.created_at)}</td>
                    <td className="p-3 text-right whitespace-nowrap">
                      <Link to={`/ordens-servico/${os.id}`} className="text-black hover:underline mr-4">
                        Visualizar
                      </Link>
                      <Link to={`/ordens-servico/${os.id}`} className="text-black hover:underline">
                        Editar Status
                      </Link>
                    </td>
                  </tr>
                ))}
                {ordens.length === 0 ? (
                  <tr className="border-t">
                    <td className="p-4 text-gray-700" colSpan={6}>
                      Nenhuma OS cadastrada.
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
