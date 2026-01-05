import React from 'react'

type Props = {
  label?: string
  value: string
  onChange: (v: string) => void
  type?: string
  placeholder?: string
  error?: string
  required?: boolean
  readOnly?: boolean
  disabled?: boolean
  dense?: boolean
  className?: string
}

export default function Input({
  label,
  value,
  onChange,
  type = 'text',
  placeholder,
  error,
  required,
  readOnly = false,
  disabled = false,
  dense = false,
  className = '',
}: Props) {
  const labelClass = dense ? 'mb-1 text-xs font-medium text-black' : 'mb-2 text-sm font-medium text-black'
  const inputSizing = dense ? 'h-9 px-3 py-2 text-sm' : 'px-4 py-3'
  const inputBg = readOnly || disabled ? 'bg-gray-50' : 'bg-white'

  return (
    <label className={`block ${className}`}>
      {label && (
        <div className={labelClass}>
          {label} {required ? <span className="text-red-600">*</span> : null}
        </div>
      )}
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        readOnly={readOnly}
        disabled={disabled}
        className={`w-full ${inputSizing} border rounded-lg ${inputBg} text-black placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-yellow-300 transition ${
          error ? 'border-red-300' : 'border-gray-300'
        }`}
      />
      {error ? <div className="mt-2 text-sm text-red-600">{error}</div> : null}
    </label>
  )
}
