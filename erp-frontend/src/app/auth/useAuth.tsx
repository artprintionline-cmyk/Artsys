import React, { createContext, useContext, useEffect, useState } from 'react'
import api from '../../services/api'

type User = { id?: number; name?: string; email?: string }

type AuthContextType = {
  token: string | null
  user: User | null
  login: (email: string, password: string) => Promise<void>
  logout: () => void
  isAuthenticated: boolean
  isAuthReady: boolean
}

const AuthContext = createContext<AuthContextType>({
  token: null,
  user: null,
  login: async () => {},
  logout: () => {},
  isAuthenticated: false,
  isAuthReady: false,
})

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'))
  const [user, setUser] = useState<User | null>(null)
  const [isAuthReady, setIsAuthReady] = useState<boolean>(() => (localStorage.getItem('token') ? false : true))

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
      setUser(res.data)
    } catch (err) {
      // invalid token or server error -> clear token
      setToken(null)
      setUser(null)
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
        setUser(me.data)
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
        throw new Error('Credenciais inválidas')
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
    <AuthContext.Provider value={{ token, user, login, logout, isAuthenticated: !!token, isAuthReady }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => useContext(AuthContext)
