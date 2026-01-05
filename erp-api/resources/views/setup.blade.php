<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Setup — ERP SaaS</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f6f7f9; margin: 0; }
        .wrap { max-width: 860px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; }
        .h1 { font-size: 20px; font-weight: 700; margin: 0; }
        .muted { color: #6b7280; font-size: 13px; margin-top: 6px; }
        .grid { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
        label { font-size: 12px; color: #374151; display: block; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; }
        .full { grid-column: 1 / -1; }
        .btn { background: #111827; color: #fff; border: 0; padding: 10px 14px; border-radius: 10px; font-size: 14px; cursor: pointer; }
        .btn:hover { background: #0b1220; }
        .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }
        .section { margin-top: 18px; padding-top: 18px; border-top: 1px solid #f3f4f6; }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="h1">Setup inicial — ERP SaaS</div>
        <div class="muted">Versão do sistema: {{ $appVersion }}. Esta tela só aparece quando o sistema ainda não foi instalado.</div>

        @if ($errors->any())
            <div class="err">
                <div><strong>Verifique os campos:</strong></div>
                <ul>
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="/setup">
            @csrf

            <div class="section">
                <div class="h1" style="font-size:16px;">Banco de dados</div>
                <div class="muted">O setup grava as credenciais no arquivo .env e executa migrations.</div>

                <div class="grid" style="margin-top: 12px;">
                    <div>
                        <label>Driver</label>
                        <select name="db_connection" required>
                            <option value="pgsql" {{ old('db_connection', $defaultConnection) === 'pgsql' ? 'selected' : '' }}>PostgreSQL</option>
                            <option value="mysql" {{ old('db_connection', $defaultConnection) === 'mysql' ? 'selected' : '' }}>MySQL</option>
                        </select>
                    </div>
                    <div>
                        <label>Host</label>
                        <input name="db_host" required value="{{ old('db_host', '127.0.0.1') }}" />
                    </div>
                    <div>
                        <label>Porta</label>
                        <input name="db_port" type="number" required value="{{ old('db_port', 5432) }}" />
                    </div>
                    <div>
                        <label>Database</label>
                        <input name="db_database" required value="{{ old('db_database', '') }}" />
                    </div>
                    <div>
                        <label>Usuário</label>
                        <input name="db_username" required value="{{ old('db_username', '') }}" />
                    </div>
                    <div>
                        <label>Senha</label>
                        <input name="db_password" type="password" value="{{ old('db_password', '') }}" />
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="h1" style="font-size:16px;">Empresa inicial</div>
                <div class="grid" style="margin-top: 12px;">
                    <div class="full">
                        <label>Nome da empresa</label>
                        <input name="empresa_nome" required value="{{ old('empresa_nome', '') }}" />
                    </div>
                    <div>
                        <label>E-mail</label>
                        <input name="empresa_email" type="email" required value="{{ old('empresa_email', '') }}" />
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input name="empresa_telefone" value="{{ old('empresa_telefone', '') }}" />
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="h1" style="font-size:16px;">Usuário admin</div>
                <div class="grid" style="margin-top: 12px;">
                    <div class="full">
                        <label>Nome</label>
                        <input name="admin_nome" required value="{{ old('admin_nome', '') }}" />
                    </div>
                    <div>
                        <label>E-mail</label>
                        <input name="admin_email" type="email" required value="{{ old('admin_email', '') }}" />
                    </div>
                    <div>
                        <label>Senha</label>
                        <input name="admin_password" type="password" required />
                    </div>
                </div>
            </div>

            <div style="margin-top: 18px; display: flex; gap: 10px; justify-content: flex-end;">
                <button class="btn" type="submit">Instalar</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
