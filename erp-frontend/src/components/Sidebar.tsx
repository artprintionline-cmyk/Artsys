import React from 'react'
import { NavLink } from 'react-router-dom'
import { Home, Users, Box, ClipboardList, CreditCard, MessageCircle, FileText } from 'lucide-react'
import { useAuth } from '../app/auth/useAuth'

type NavItem =
  | { type: 'section'; label: string }
  | { type: 'link'; to: string; label: string; icon: any; perm?: string | string[]; indent?: boolean }

const items: NavItem[] = [
  { type: 'link', to: '/dashboard', label: 'Dashboard', icon: Home, perm: 'dashboard.view' },
  { type: 'link', to: '/clientes', label: 'Clientes', icon: Users, perm: 'clientes.view' },
  { type: 'link', to: '/produtos', label: 'Produtos', icon: Box, perm: 'produtos.view' },

  {
    type: 'link',
    to: '/compras/nova',
    label: 'Compras',
    icon: Box,
    perm: ['compras.compras.create'],
  },

  { type: 'link', to: '/estoque', label: 'Estoque', icon: Box, perm: 'estoque.view' },
  { type: 'link', to: '/ordens-servico', label: 'Ordem de Serviço', icon: ClipboardList, perm: 'os.view' },
  { type: 'link', to: '/ordens-servico/kanban', label: 'Kanban OS', icon: ClipboardList, perm: 'os.view' },
  { type: 'link', to: '/financeiro', label: 'Financeiro', icon: CreditCard, perm: 'financeiro.view' },
  { type: 'link', to: '/relatorios', label: 'Relatórios', icon: FileText, perm: 'relatorios.view' },
  { type: 'link', to: '/whatsapp', label: 'WhatsApp', icon: MessageCircle, perm: 'whatsapp.view' },
  { type: 'link', to: '/assinatura', label: 'Plano', icon: CreditCard, perm: 'saas.view' },
  { type: 'link', to: '/admin', label: 'Admin', icon: Users, perm: 'admin.users.manage' },
]

export default function Sidebar() {
  const { hasPerm } = useAuth()

  const isVisibleLink = (it: NavItem) => {
    if (it.type !== 'link') return false
    if (!it.perm) return true
    return hasPerm(it.perm)
  }

  const visible: NavItem[] = []
  for (let i = 0; i < items.length; i++) {
    const it = items[i]
    if (it.type === 'link') {
      if (isVisibleLink(it)) visible.push(it)
      continue
    }

    // section: only show if it has at least one visible link until next section
    let hasAny = false
    for (let j = i + 1; j < items.length; j++) {
      const next = items[j]
      if (next.type === 'section') break
      if (isVisibleLink(next)) {
        hasAny = true
        break
      }
    }
    if (hasAny) visible.push(it)
  }

  return (
    <aside className="w-72 bg-white text-black h-screen fixed left-0 top-0 bottom-0 shadow-sm">
      <div className="p-6 border-b border-gray-100 flex items-center gap-3">
        <div className="w-10 h-10 rounded-md bg-gray-100 flex items-center justify-center text-black font-bold">E</div>
        <div>
          <div className="text-lg font-bold text-black">ERP Sistema</div>
          <div className="text-xs text-gray-600">Painel administrativo</div>
        </div>
      </div>
      <nav className="p-4 mt-4">
        {visible.map((it) => {
          if (it.type === 'section') {
            return (
              <div key={`section:${it.label}`} className="px-4 pt-4 pb-2 text-xs font-semibold text-gray-700">
                {it.label}
              </div>
            )
          }

          return (
            <NavLink
              to={it.to}
              key={it.to}
              className={({ isActive }) =>
                `flex items-center gap-3 ${it.indent ? 'pl-8 pr-4' : 'px-4'} py-3 rounded-lg mb-2 text-sm transition-colors duration-150 ${
                  isActive ? 'bg-yellow-200 text-black font-semibold' : 'text-black hover:bg-gray-100'
                }`
              }
            >
              <span className="text-black">
                <it.icon className="w-5 h-5" />
              </span>
              <span className="flex-1">{it.label}</span>
            </NavLink>
          )
        })}
      </nav>
      <div className="absolute bottom-6 left-6 right-6">
        <div className="text-xs text-gray-500">v1.0.0</div>
      </div>
    </aside>
  )
}

