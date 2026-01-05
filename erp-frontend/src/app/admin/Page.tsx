import { useEffect, useMemo, useState } from 'react'
import PageContainer from '../../components/PageContainer'
import Button from '../../components/Button'
import Textarea from '../../components/Textarea'
import adminService, { type PerfilDto, type UsuarioDto } from '../../services/adminService'
import { useToast } from '../toast/ToastProvider'

function asArray(payload: any): any[] {
  if (Array.isArray(payload)) return payload
  if (payload && Array.isArray(payload.data)) return payload.data
  return []
}

function uniqSorted(xs: string[]) {
  return Array.from(new Set(xs.map((x) => String(x).trim()).filter(Boolean))).sort()
}

export default function AdminPage() {
  const { showToast } = useToast()

  const [loading, setLoading] = useState(true)
  const [savingPerfil, setSavingPerfil] = useState(false)

  const [usuarios, setUsuarios] = useState<UsuarioDto[]>([])
  const [perfis, setPerfis] = useState<PerfilDto[]>([])

  const [perfilId, setPerfilId] = useState<string>('')
  const selectedPerfil = useMemo(
    () => (perfilId ? perfis.find((p) => String(p.id) === perfilId) ?? null : null),
    [perfilId, perfis]
  )

  const [permissoesText, setPermissoesText] = useState('')

  const loadAll = async () => {
    setLoading(true)
    try {
      const [uRes, pRes] = await Promise.all([adminService.listUsuarios(), adminService.listPerfis()])
      const u = asArray(uRes.data) as UsuarioDto[]
      const p = asArray(pRes.data) as PerfilDto[]
      setUsuarios(u)
      setPerfis(p)

      const first = p[0]
      if (first && !perfilId) {
        setPerfilId(String(first.id))
      }
    } catch {
      setUsuarios([])
      setPerfis([])
      showToast('Erro ao carregar dados de administração', 'error')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadAll()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!selectedPerfil) {
      setPermissoesText('')
      return
    }
    setPermissoesText((selectedPerfil.permissoes ?? []).join('\n'))
  }, [selectedPerfil?.id])

  const salvarPermissoes = async () => {
    if (!selectedPerfil) return

    const permissoes = uniqSorted(
      permissoesText
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean)
    )

    setSavingPerfil(true)
    try {
      const res = await adminService.updatePerfilPermissoes(selectedPerfil.id, permissoes)
      const updated = (res.data?.data ?? res.data) as PerfilDto

      setPerfis((prev) => prev.map((p) => (p.id === updated.id ? { ...p, permissoes: asArray(updated.permissoes) as any } : p)))
      showToast('Permissões atualizadas')
    } catch (e: any) {
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao salvar permissões', 'error')
    } finally {
      setSavingPerfil(false)
    }
  }

  const atualizarUsuario = async (id: number, payload: { perfil_id?: number | null; status?: boolean }) => {
    try {
      const res = await adminService.updateUsuario(id, payload)
      const updated = (res.data?.data ?? res.data) as UsuarioDto
      setUsuarios((prev) => prev.map((u) => (u.id === updated.id ? { ...u, ...updated } : u)))
      showToast('Usuário atualizado')
    } catch (e: any) {
      const msg = e?.response?.data?.message
      showToast(msg ? String(msg) : 'Erro ao atualizar usuário', 'error')
    }
  }

  return (
    <div className="max-w-6xl mx-auto">
      <PageContainer title="Administração">
        {loading ? (
          <div className="text-sm text-gray-700">Carregando...</div>
        ) : (
          <div className="space-y-8">
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <div className="text-sm font-semibold text-black mb-4">Usuários</div>

              <div className="overflow-auto border border-gray-200 rounded-lg">
                <table className="w-full text-sm">
                  <thead className="bg-gray-100 text-black">
                    <tr>
                      <th className="p-3 text-left">Nome</th>
                      <th className="p-3 text-left">Email</th>
                      <th className="p-3 text-left">Perfil</th>
                      <th className="p-3 text-left">Status</th>
                      <th className="p-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {usuarios.map((u) => (
                      <tr key={u.id} className="border-t hover:bg-gray-50">
                        <td className="p-3 text-black">{u.name}</td>
                        <td className="p-3 text-gray-800">{u.email}</td>
                        <td className="p-3">
                          <select
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                            value={u.perfil?.id ? String(u.perfil.id) : ''}
                            onChange={(e) => {
                              const raw = e.target.value
                              const next = raw ? Number(raw) : null
                              atualizarUsuario(u.id, { perfil_id: next })
                            }}
                          >
                            <option value="">(sem perfil)</option>
                            {perfis.map((p) => (
                              <option key={p.id} value={String(p.id)}>
                                {p.nome}
                              </option>
                            ))}
                          </select>
                        </td>
                        <td className="p-3 text-gray-800">{u.status ? 'Ativo' : 'Inativo'}</td>
                        <td className="p-3 text-right">
                          <button
                            className="text-black hover:underline"
                            onClick={() => atualizarUsuario(u.id, { status: !u.status })}
                          >
                            {u.status ? 'Desativar' : 'Ativar'}
                          </button>
                        </td>
                      </tr>
                    ))}
                    {usuarios.length === 0 ? (
                      <tr className="border-t">
                        <td className="p-4 text-gray-700" colSpan={5}>
                          Nenhum usuário encontrado.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </div>

            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <div className="text-sm font-semibold text-black mb-4">Perfis e permissões</div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                <label className="block">
                  <div className="mb-2 text-sm font-medium text-black">Perfil</div>
                  <select
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-black focus:outline-none focus:ring-2 focus:ring-yellow-300"
                    value={perfilId}
                    onChange={(e) => setPerfilId(e.target.value)}
                  >
                    {perfis.map((p) => (
                      <option key={p.id} value={String(p.id)}>
                        {p.nome}
                      </option>
                    ))}
                  </select>
                </label>

                <div className="text-sm text-gray-700">
                  <div className="font-semibold text-black">Dica</div>
                  <div>Uma permissão por linha (ex.: <span className="font-mono">clientes.view</span>).</div>
                  <div>O perfil <span className="font-mono">admin</span> no backend faz bypass com <span className="font-mono">*</span>.</div>
                </div>
              </div>

              <div className="mt-4">
                <Textarea
                  label="Permissões"
                  value={permissoesText}
                  onChange={setPermissoesText}
                  placeholder="clientes.view\nprodutos.view\n..."
                  rows={10}
                />
              </div>

              <div className="mt-4 flex gap-3">
                <Button type="button" loading={savingPerfil} fullWidth={false} onClick={salvarPermissoes}>
                  Salvar permissões
                </Button>
                <Button type="button" variant="secondary" fullWidth={false} onClick={loadAll}>
                  Recarregar
                </Button>
              </div>
            </div>
          </div>
        )}
      </PageContainer>
    </div>
  )
}
