import React from 'react'

type Props = {
  label?: string
  value: string
  onChange: (v: string) => void
  placeholder?: string
  error?: string
  rows?: number
}

export default function Textarea({ label, value, onChange, placeholder, error, rows = 4 }: Props) {
  return (
    <label className="block">
      {label && <div className="mb-2 text-sm font-medium text-black">{label}</div>}
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        rows={rows}
        className={`w-full px-4 py-3 border rounded-lg bg-white text-black placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-yellow-300 transition ${
          error ? 'border-red-300' : 'border-gray-300'
        }`}
      />
      {error ? <div className="mt-2 text-sm text-red-600">{error}</div> : null}
    </label>
  )
}
