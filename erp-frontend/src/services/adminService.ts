import api from './api'

export type PerfilDto = {
  id: number
  nome: string
  permissoes: string[]
}

export type UsuarioDto = {
  id: number
  name: string
  email: string
  status: boolean
  perfil?: { id: number; nome: string } | null
}

const adminService = {
  listPerfis: () => api.get('/perfis'),
  updatePerfilPermissoes: (id: number | string, permissoes: string[]) =>
    api.put(`/perfis/${id}/permissoes`, { permissoes }),

  listUsuarios: () => api.get('/usuarios'),
  updateUsuario: (id: number | string, payload: { perfil_id?: number | null; status?: boolean }) =>
    api.put(`/usuarios/${id}`, payload),
}

export default adminService
