import React from 'react'

type Props = {
  children: React.ReactNode
  onClick?: () => void
  type?: 'button' | 'submit' | 'reset'
  className?: string
  loading?: boolean
}

export default function Button({ children, onClick, type = 'button', className = '', loading = false }: Props) {
  return (
    <button
      type={type}
      onClick={onClick}
      disabled={loading}
      className={`w-full h-12 rounded-lg flex items-center justify-center bg-primary text-dark font-semibold shadow-sm hover:opacity-95 transition ${loading ? 'opacity-70 cursor-not-allowed' : ''} ${className}`}
    >
      {loading ? 'Carregando...' : children}
    </button>
  )
}
