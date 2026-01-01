import { Routes, Route, Navigate } from 'react-router-dom'
import Login from '../app/auth/Login'
import Dashboard from '../app/dashboard/Dashboard'
import ClientesList from '../app/clientes/List'
import ClienteForm from '../app/clientes/Form'
import { useAuth } from '../app/auth/useAuth'
import Sidebar from '../components/Sidebar'
import Header from '../components/Header'

function PrivateRoute({ children }: { children: JSX.Element }) {
  const { isAuthenticated } = useAuth()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return children
}

export default function AppRouter() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route
        path="/dashboard"
        element={
          <PrivateRoute>
            <div className="flex h-screen bg-gray-100">
              <Sidebar />
              <div className="flex flex-col flex-1">
                <Header />
                <main className="flex-1 p-6 overflow-auto">
                  <Dashboard />
                </main>
              </div>
            </div>
          </PrivateRoute>
        }
      />

      <Route
        path="/clientes"
        element={
          <PrivateRoute>
            <div className="flex h-screen bg-gray-100">
              <Sidebar />
              <div className="flex flex-col flex-1">
                <Header />
                <main className="flex-1 p-6 overflow-auto">
                  <ClientesList />
                </main>
              </div>
            </div>
          </PrivateRoute>
        }
      />

      <Route
        path="/clientes/novo"
        element={
          <PrivateRoute>
            <div className="flex h-screen bg-gray-100">
              <Sidebar />
              <div className="flex flex-col flex-1">
                <Header />
                <main className="flex-1 p-6 overflow-auto">
                  <ClienteForm />
                </main>
              </div>
            </div>
          </PrivateRoute>
        }
      />

      <Route
        path="/clientes/:id/editar"
        element={
          <PrivateRoute>
            <div className="flex h-screen bg-gray-100">
              <Sidebar />
              <div className="flex flex-col flex-1">
                <Header />
                <main className="flex-1 p-6 overflow-auto">
                  <ClienteForm />
                </main>
              </div>
            </div>
          </PrivateRoute>
        }
      />

      {/* fallback */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}
