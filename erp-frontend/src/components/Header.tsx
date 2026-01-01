import React from 'react'
import { useAuth } from '../app/auth/useAuth'

export default function Header() {
  const { logout, user } = useAuth()
  return (
    <header className="h-14 flex items-center justify-between px-6 bg-white border-b border-gray-100">
      <div className="flex items-center gap-4">
        <div className="text-xl font-bold text-black">ERP Sistema</div>
        <div className="text-sm text-gray-600">Painel</div>
      </div>
      <div className="flex items-center gap-4">
        <div className="text-sm text-black">{user?.name ?? 'Usuário'}</div>
        <button
          onClick={() => logout()}
          className="px-3 py-1 rounded-md bg-yellow-200 text-black font-medium hover:bg-yellow-300 transition"
        >
          Sair
        </button>
      </div>
    </header>
  )
}
