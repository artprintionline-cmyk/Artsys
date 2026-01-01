import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from './useAuth'
import Button from '../../components/Button'
import Input from '../../components/Input'
import { t } from '../../i18n'

export default function Login() {
  const navigate = useNavigate()
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    try {
      await login(email, password)
      navigate('/dashboard')
    } catch (err: any) {
      const message = err?.message ?? 'Erro inesperado'
      setError(message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <form onSubmit={submit} className="w-full max-w-md p-8 bg-white rounded-lg shadow">
        <div className="flex items-center gap-3 mb-6">
          <div className="w-10 h-10 bg-primary rounded-full" />
          <h2 className="text-2xl font-semibold">{t('app.title')}</h2>
        </div>

        {error && <div className="mb-4 text-sm text-red-600">{error}</div>}

        <Input label={t('login.email')} value={email} onChange={(v) => setEmail(v)} type="email" />
        <Input label={t('login.password')} value={password} onChange={(v) => setPassword(v)} type="password" />

        <div className="mt-6">
          <Button type="submit" loading={loading}>{t('login.submit')}</Button>
        </div>
      </form>
    </div>
  )
}
