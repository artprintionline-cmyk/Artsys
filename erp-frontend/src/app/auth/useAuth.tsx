import React, { createContext, useContext, useEffect, useMemo, useState } from 'react'
import api from '../../services/api'

type User = {
  id?: number
  name?: string
  email?: string
  empresa_id?: number
  status?: boolean
  perfil?: { id: number; nome: string } | null
  permissoes?: string[]
}

type RequiredPerm = string | string[]

type AuthContextType = {
  token: string | null
  user: User | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  hasPerm: (perm: RequiredPerm) => boolean
  isAuthenticated: boolean
  isAuthReady: boolean
}

const AuthContext = createContext<AuthContextType>({
  token: null,
  user: null,
  login: async () => {},
  logout: () => {},
  hasPerm: () => false,
  isAuthenticated: false,
  isAuthReady: false,
})

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'))
  const [user, setUser] = useState<User | null>(null)
  const [isAuthReady, setIsAuthReady] = useState<boolean>(() => (localStorage.getItem('token') ? false : true))

  const hasPerm = useMemo(() => {
    return (perm: RequiredPerm) => {
      const perms = user?.permissoes ?? []
      if (perms.includes('*')) return true
      // Para arrays, exigir TODAS as permissões listadas.
      // (Evita liberar páginas que chamam múltiplos endpoints protegidos.)
      if (Array.isArray(perm)) return perm.every((p) => perms.includes(p))
      return perms.includes(perm)
    }
  }, [user?.permissoes])

  useEffect(() => {
    // keep localStorage in sync
    if (token) {
      localStorage.setItem('token', token)
    } else {
      localStorage.removeItem('token')
      setUser(null)
    }
  }, [token])

  async function fetchUser() {
    try {
      const res = await api.get('/me')
      const payload = res.data?.data ?? res.data
      setUser(payload)
    } catch (err) {
      // Regra Desktop: não deslogar por erro transitório.
      // Só limpar o token quando o backend responder 401 (token inválido/expirado/revogado).
      const status = (err as any)?.response?.status
      if (status === 401) {
        setToken(null)
        setUser(null)
      } else {
        // Mantém o token para não pedir login novamente ao reabrir o app.
        // O restante da UI vai lidar com falhas de API (ex.: servidor indisponível).
        setUser(null)
      }
    } finally {
      setIsAuthReady(true)
    }
  }

  useEffect(() => {
    // on mount, if token exists validate it
    if (token) {
      fetchUser()
    }
  }, [])

  useEffect(() => {
    // listen to logout events emitted by api interceptor
    const handler = () => {
      setToken(null)
      setUser(null)
      setIsAuthReady(true)
    }

    window.addEventListener('erp:logout', handler as EventListener)
    return () => window.removeEventListener('erp:logout', handler as EventListener)
  }, [])

  async function login(email: string, password: string) {
    try {
      const res = await api.post('/auth/login', { email, password })
      const t = res.data.token ?? res.data.access_token ?? null
      if (!t) throw new Error('Token not returned from API')

      // store token immediately so api interceptor can use it
      localStorage.setItem('token', t)
      setToken(t)

      // fetch user after login
      try {
        const me = await api.get('/me')
        const payload = me.data?.data ?? me.data
        setUser(payload)
      } catch (err) {
        // if fetching user fails, consider logout
        setToken(null)
        localStorage.removeItem('token')
        throw new Error('Falha ao validar usuário')
      }

      setIsAuthReady(true)
    } catch (err: any) {
      // Network / server unavailable
      if (!err.response) {
        throw new Error('Servidor indisponível')
      }
      // Unauthorized
      if (err.response.status === 401) {
        throw new Error(
          'Credenciais inválidas. Verifique email e senha. Se você resetou o banco em ambiente local, rode php artisan db:seed no erp-api.'
        )
      }
      // Other API error
      const msg = err.response?.data?.message ?? 'Erro inesperado'
      throw new Error(msg)
    }
  }

  function logout() {
    setToken(null)
    setUser(null)
    setIsAuthReady(true)
    try {
      api.post('/auth/logout').catch(() => {})
    } catch (e) {}
  }

  return (
    <AuthContext.Provider value={{ token, user, login, logout, hasPerm, isAuthenticated: !!token, isAuthReady }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
