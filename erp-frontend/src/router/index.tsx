import { Routes, Route, Navigate, useParams } from 'react-router-dom'
import Login from '../app/auth/Login'
import Dashboard from '../app/dashboard/Dashboard'
import ClientesList from '../app/clientes/List'
import ClienteForm from '../app/clientes/Form'
import { useAuth } from '../app/auth/useAuth'
import AppLayout from '../components/AppLayout'
import Produtos from '../app/produtos/Produtos'
import ProdutoForm from '../app/produtos/Form'
import ComprasNova from '../app/compras/NovaCompra'
import Estoque from '../app/estoque/Estoque'
import OrdemServico from '../app/os/OrdemServico'
import OrdemServicoNew from '../app/os/New'
import OrdemServicoShow from '../app/os/Show'
import OrdemServicoKanban from '../app/os/Kanban'
import Financeiro from '../app/financeiro/Financeiro'
import FinanceiroShow from '../app/financeiro/Show'
import FinanceiroPix from '../app/financeiro/Pix'
import WhatsAppList from '../app/whatsapp/List'
import WhatsAppShow from '../app/whatsapp/Show'
import Relatorios from '../app/relatorios/Relatorios'
import Admin from '../app/admin/Admin'
import AssinaturaPage from '../app/saas/Assinatura'

function Forbidden() {
  return (
    <div className="max-w-3xl mx-auto">
      <div className="bg-white border border-gray-200 rounded-lg p-6">
        <div className="text-lg font-semibold text-black">Acesso negado</div>
        <div className="text-sm text-gray-700 mt-2">Você não tem permissão para acessar esta página.</div>
      </div>
    </div>
  )
}

function RequirePerm({ perm, children }: { perm: string | string[]; children: JSX.Element }) {
  const { hasPerm, isAuthReady } = useAuth()

  if (!isAuthReady) {
    return <div className="flex items-center justify-center h-screen">Carregando...</div>
  }

  if (!hasPerm(perm)) {
    return <Forbidden />
  }

  return children
}

function PrivateRoute({ children }: { children: JSX.Element }) {
  const { isAuthenticated, isAuthReady } = useAuth()

  // If auth state is still being validated, show a loading placeholder
  if (!isAuthReady) {
    return <div className="flex items-center justify-center h-screen">Carregando...</div>
  }

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
        element={
          <PrivateRoute>
            <AppLayout />
          </PrivateRoute>
        }
      >
        <Route
          path="/dashboard"
          element={
            <RequirePerm perm="dashboard.view">
              <Dashboard />
            </RequirePerm>
          }
        />

        <Route
          path="/clientes"
          element={
            <RequirePerm perm="clientes.view">
              <ClientesList />
            </RequirePerm>
          }
        />
        <Route
          path="/clientes/novo"
          element={
            <RequirePerm perm="clientes.create">
              <ClienteForm />
            </RequirePerm>
          }
        />
        <Route
          path="/clientes/:id/editar"
          element={
            <RequirePerm perm="clientes.edit">
              <ClienteForm />
            </RequirePerm>
          }
        />

        <Route
          path="/produtos"
          element={
            <RequirePerm perm="produtos.view">
              <Produtos />
            </RequirePerm>
          }
        />
        <Route
          path="/produtos/novo"
          element={
            <RequirePerm perm="produtos.create">
              <ProdutoForm />
            </RequirePerm>
          }
        />
        <Route
          path="/produtos/:id/editar"
          element={
            <RequirePerm perm="produtos.edit">
              <ProdutoForm />
            </RequirePerm>
          }
        />

        <Route
          path="/compras"
          element={<Navigate to="/compras/nova" replace />}
        />
        <Route
          path="/compras/nova"
          element={
            <RequirePerm perm="compras.compras.create">
              <ComprasNova />
            </RequirePerm>
          }
        />

        {/* Back-compat redirects (Custos removido; substituído por Compras) */}


        <Route
          path="/estoque"
          element={
            <RequirePerm perm="estoque.view">
              <Estoque />
            </RequirePerm>
          }
        />

        <Route
          path="/ordens-servico"
          element={
            <RequirePerm perm="os.view">
              <OrdemServico />
            </RequirePerm>
          }
        />
        <Route
          path="/ordens-servico/kanban"
          element={
            <RequirePerm perm="os.view">
              <OrdemServicoKanban />
            </RequirePerm>
          }
        />
        <Route
          path="/ordens-servico/nova"
          element={
            <RequirePerm perm="os.create">
              <OrdemServicoNew />
            </RequirePerm>
          }
        />
        <Route
          path="/ordens-servico/:id"
          element={
            <RequirePerm perm="os.view">
              <OrdemServicoShow />
            </RequirePerm>
          }
        />

        <Route
          path="/financeiro"
          element={
            <RequirePerm perm="financeiro.view">
              <Financeiro />
            </RequirePerm>
          }
        />
        <Route
          path="/financeiro/:id"
          element={
            <RequirePerm perm="financeiro.view">
              <FinanceiroShow />
            </RequirePerm>
          }
        />
        <Route
          path="/financeiro/:id/pix"
          element={
            <RequirePerm perm="financeiro.pay">
              <FinanceiroPix />
            </RequirePerm>
          }
        />

        <Route
          path="/relatorios"
          element={
            <RequirePerm perm="relatorios.view">
              <Relatorios />
            </RequirePerm>
          }
        />

        <Route
          path="/whatsapp"
          element={
            <RequirePerm perm="whatsapp.view">
              <WhatsAppList />
            </RequirePerm>
          }
        />
        <Route
          path="/whatsapp/:numero"
          element={
            <RequirePerm perm="whatsapp.view">
              <WhatsAppShow />
            </RequirePerm>
          }
        />

        <Route
          path="/admin"
          element={
            <RequirePerm perm="admin.users.manage">
              <Admin />
            </RequirePerm>
          }
        />

        <Route
          path="/assinatura"
          element={
            <RequirePerm perm="saas.view">
              <AssinaturaPage />
            </RequirePerm>
          }
        />
      </Route>

      {/* fallback */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}
