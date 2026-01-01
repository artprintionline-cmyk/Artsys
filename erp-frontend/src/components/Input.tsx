import React from 'react'

type Props = {
  label?: string
  value: string
  onChange: (v: string) => void
  type?: string
  placeholder?: string
}

export default function Input({ label, value, onChange, type = 'text', placeholder }: Props) {
  return (
    <label className="block mb-4">
      {label && <div className="mb-2 text-sm font-medium text-gray-700">{label}</div>}
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary transition"
      />
    </label>
  )
}
