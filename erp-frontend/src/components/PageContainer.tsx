import React from 'react'
import Button from './Button'

type Props = {
  title: string
  actionLabel?: string
  onAction?: () => void
  children: React.ReactNode
}

export default function PageContainer({ title, actionLabel, onAction, children }: Props) {
  return (
    <div className="min-h-full">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-black">{title}</h1>
        {actionLabel && onAction ? (
          <Button fullWidth={false} onClick={onAction}>
            {actionLabel}
          </Button>
        ) : null}
      </div>

      <div className="bg-white rounded-xl shadow-sm p-6">{children}</div>
    </div>
  )
}
