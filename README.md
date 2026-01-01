 # ERP SaaS - Backend (esqueleto)

 Esta pasta contém um esqueleto para um backend Laravel API preparado para um ERP SaaS modular (Laravel + PostgreSQL).

 Objetivo: fornecer a fundação (módulos, middleware tenant, autenticação básica) e instruções para instalar e rodar localmente.

 Instalação mínima

 1. Criar projeto Laravel (se ainda não houver):

 ```bash
 composer create-project laravel/laravel backend --prefer-dist
 cd backend
 ```

 2. Copiar os arquivos deste esqueleto para o diretório do projeto (substituir quando solicitado).

 3. Ajustar `.env` para PostgreSQL (exemplo em `.env.example`) e gerar a chave:

 ```bash
 cp .env.example .env
 php artisan key:generate
 ```

 4. Instalar Sanctum (opcional, recomendado):

 ```bash
 composer require laravel/sanctum
 php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"
 php artisan migrate
 ```

 5. Rodar migrations e seeders:

 ```bash
 php artisan migrate
 php artisan db:seed
 ```

 Endpoints principais
 - `POST /api/v1/auth/login` — autenticação (retorna token)
 - `POST /api/v1/auth/logout` — logout

 Notas
 - Este repositório contém apenas o esqueleto. Após copiar para um projeto Laravel real, execute os comandos acima para instalar dependências e migrar o banco.

Passos detalhados (Windows PowerShell)

1) Criar novo projeto Laravel (se ainda não criado):

```powershell
composer create-project laravel/laravel backend --prefer-dist
cd backend
```

2) Instalar e configurar Sanctum:

```powershell
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

3) Configurar `.env` para PostgreSQL — edite as variáveis `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

```powershell
copy .env.example .env
php artisan key:generate
```

4) Criar estrutura de módulos:

```powershell
mkdir -p app/Modules/Core app/Modules/Clientes app/Modules/Produtos app/Modules/OS app/Modules/Financeiro
```

5) Registrar o `TenantMiddleware` em `app/Http/Kernel.php` — adicione a entrada no array `$routeMiddleware`:

```php
protected $routeMiddleware = [
	// ... outros middlewares
	'tenant' => \App\Http\Middleware\TenantMiddleware::class,
];
```

6) Registrar o `ModuleServiceProvider` em `config/app.php` dentro do array `providers`:

```php
'providers' => [
	// Outros providers do framework...
	App\Providers\ModuleServiceProvider::class,
],
```

7) Verifique/adicione as rotas versionadas em `routes/api.php` (o esqueleto já fornece `/api/v1`).

8) Rodar migrations e iniciar o servidor:

```powershell
php artisan migrate
php artisan serve
```

9) Testar login (exemplo usando `httpie` ou `curl`):

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login -H "Content-Type: application/json" -d '{"email":"user@example.com","password":"secret"}'
```

Observações e recomendações
- Modelos que devem ser filtrados por `empresa_id` precisam estender `App\Models\BaseModel`.
- Use `php artisan make:seeder` para popular dados iniciais (criar uma `Empresa` e um `User`).
- Se optar por JWT em vez de Sanctum eu posso adaptar os controllers e configuração.

Testes rápidos do CORE

1) Rodar seeders:

```powershell
cd backend # ou a pasta do seu projeto
php artisan db:seed
```

2) Login (exemplo):

Endpoint: `POST /api/v1/auth/login`
Payload JSON:
```json
{"email":"tester@example.com","password":"password"}
```
Resposta esperada (exemplo):
```json
{
	"token": "<SANCTUM_TOKEN>",
	"user": {"id":1,"name":"Tester","email":"tester@example.com",...},
	"empresa_id": 1
}
```

3) Teste rota protegida `GET /api/v1/teste-tenant`:

- Sem token: deve retornar 401 (não autorizado).
- Com token válido (Bearer): deve retornar:
```json
{
	"user_id": 1,
	"empresa_id": 1,
	"mensagem": "Tenant OK"
}
```
- Se a `empresa` estiver inativa (`status=false`), o `TenantMiddleware` deve retornar 403 com mensagem de bloqueio.

4) Como testar rapidamente com `curl`:

```bash
# 1) login
curl -X POST http://127.0.0.1:8000/api/v1/auth/login -H "Content-Type: application/json" -d '{"email":"tester@example.com","password":"password"}'

# 2) usar token retornado (substitua <TOKEN>)
curl -H "Authorization: Bearer <TOKEN>" http://127.0.0.1:8000/api/v1/teste-tenant
```

Se quiser, eu posso alterar os nomes/credenciais usadas pelos seeders. Por padrão o seeder cria `tester@example.com` / `password`.

