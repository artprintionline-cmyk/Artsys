import React, { useEffect, useState } from 'react'
import Header from '../../components/Header'
import Sidebar from '../../components/Sidebar'
import { Layers, Play, CheckCircle, AlertCircle } from 'lucide-react'
import { getDashboardSummary } from '../../services/dashboardService'

const defaultCards = [
  { id: 1, title: 'Total de OS', key: 'total_os', value: 0, Icon: Layers },
  { id: 2, title: 'Em Produção', key: 'em_producao', value: 0, Icon: Play },
  { id: 3, title: 'Faturado', key: 'faturado', value: 0, Icon: CheckCircle },
  { id: 4, title: 'Pendências', key: 'pendencias', value: 0, Icon: AlertCircle },
]

function Card({ title, value, Icon }: { title: string; value: number; Icon: any }) {
  return (
    <div className="p-6 bg-white rounded-xl shadow-sm flex items-center justify-between">
      <div>
        <div className="text-sm text-gray-800">{title}</div>
        <div className="text-2xl font-semibold text-black mt-2">{value}</div>
      </div>
      <div className="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center text-yellow-500">
        <Icon className="w-6 h-6" />
      </div>
    </div>
  )
}

export default function Dashboard() {
  const [cards, setCards] = useState(defaultCards)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    setLoading(true)
    getDashboardSummary()
      .then((data) => {
        if (!mounted) return
        const updated = defaultCards.map((c) => ({ ...c, value: (data as any)[c.key] ?? c.value }))
        setCards(updated)
      })
      .catch((e) => {
        if (!mounted) return
        setError('Não foi possível carregar resumo')
      })
      .finally(() => mounted && setLoading(false))

    return () => {
      mounted = false
    }
  }, [])

  return (
    <div className="h-screen flex">
      <Sidebar />
      <div className="flex-1 flex flex-col ml-72">
        <Header />
        <main className="p-6 bg-gray-50 flex-1">
          <div className="max-w-6xl mx-auto">
            <h1 className="text-3xl font-bold mb-4">Dashboard</h1>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
              {loading
                ? defaultCards.map((c) => (
                    <div key={c.id} className="p-6 bg-white rounded-xl shadow-sm animate-pulse h-28" />
                  ))
                : cards.map((c) => <Card key={c.id} title={c.title} value={c.value} Icon={c.Icon} />)}
            </div>

            {error && <div className="mt-4 text-sm text-red-600">{error}</div>}

            <section className="mt-8">
              <div className="bg-white rounded-xl shadow-sm p-6">Bem-vindo ao painel. Implementar próximos widgets aqui.</div>
            </section>
          </div>
        </main>
      </div>
    </div>
  )
}
