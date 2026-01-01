import React from 'react'
import { NavLink } from 'react-router-dom'
import { Home, Users, Box, ClipboardList, CreditCard } from 'lucide-react'

const items = [
  { to: '/dashboard', label: 'Dashboard', icon: Home },
  { to: '/clientes', label: 'Clientes', icon: Users },
  { to: '/produtos', label: 'Produtos', icon: Box },
  { to: '/ordens-servico', label: 'Ordem de Serviço', icon: ClipboardList },
  { to: '/financeiro', label: 'Financeiro', icon: CreditCard },
]

export default function Sidebar() {
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
        {items.map((it) => (
          <NavLink
            to={it.to}
            key={it.to}
            className={({ isActive }) =>
              `flex items-center gap-3 px-4 py-3 rounded-lg mb-2 text-sm transition-colors duration-150 ${isActive ? 'bg-yellow-200 text-black font-semibold' : 'text-black hover:bg-gray-100'}`
            }
          >
            <span className="text-black">
              <it.icon className="w-5 h-5" />
            </span>
            <span className="flex-1">{it.label}</span>
          </NavLink>
        ))}
      </nav>
      <div className="absolute bottom-6 left-6 right-6">
        <div className="text-xs text-gray-500">v1.0.0</div>
      </div>
    </aside>
  )
}
