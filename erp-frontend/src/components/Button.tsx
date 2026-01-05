import React from 'react'

type Props = {
  children: React.ReactNode
  onClick?: () => void
  type?: 'button' | 'submit' | 'reset'
  className?: string
  loading?: boolean
  fullWidth?: boolean
  variant?: 'primary' | 'secondary'
  dense?: boolean
}

export default function Button({
  children,
  onClick,
  type = 'button',
  className = '',
  loading = false,
  fullWidth = true,
  variant = 'primary',
  dense = false,
}: Props) {
  const baseSize = dense ? 'h-9 text-sm px-3' : 'h-11 px-4'
  const base = `${baseSize} rounded-lg inline-flex items-center justify-center font-medium transition ${
    fullWidth ? 'w-full' : 'w-auto'
  } ${loading ? 'opacity-70 cursor-not-allowed' : ''}`

  const styles =
    variant === 'secondary'
      ? 'border border-gray-300 text-black bg-white hover:bg-gray-50'
      : 'bg-yellow-400 text-black hover:bg-yellow-500'

  return (
    <button
      type={type}
      onClick={onClick}
      disabled={loading}
      className={`${base} ${styles} ${className}`}
    >
      {loading ? 'Carregando...' : children}
    </button>
  )
}
