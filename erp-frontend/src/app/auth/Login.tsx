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
  const [fieldErrors, setFieldErrors] = useState<{ email?: string; password?: string }>({})

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setFieldErrors({})

    // client-side validation
    const emailTrim = email.trim()
    const passwordTrim = password
    const errors: { email?: string; password?: string } = {}
    if (!emailTrim) {
      errors.email = 'Email é obrigatório'
    } else {
      // simple email regex
      const re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\\.,;:\s@\"]+\.)+[^<>()[\]\\.,;:\s@\"]{2,})$/i
      if (!re.test(emailTrim)) errors.email = 'Email inválido'
    }
    if (!passwordTrim) errors.password = 'Senha é obrigatória'

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      setLoading(false)
      return
    }
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

        {error && <div role="alert" className="mb-4 text-sm text-red-600">{error}</div>}

        <Input
          label={t('login.email')}
          value={email}
          onChange={(v: string) => setEmail(v)}
          type="email"
        />
        {fieldErrors.email && <div className="text-sm text-red-600 mb-2">{fieldErrors.email}</div>}

        <Input
          label={t('login.password')}
          value={password}
          onChange={(v: string) => setPassword(v)}
          type="password"
        />
        {fieldErrors.password && <div className="text-sm text-red-600 mb-2">{fieldErrors.password}</div>}

        <div className="mt-6">
          <Button type="submit" loading={loading}>
            {t('login.submit')}
          </Button>
        </div>
      </form>
    </div>
  )
}
