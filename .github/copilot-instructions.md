 # Instruções Copilot / Agente IA

 Propósito
 - Fornecer contexto acionável e direto para agentes IA atuarem com produtividade imediata neste repositório.

 Resumo da varredura do repositório
 - Não foram encontrados arquivos de orientação para IA (ex.: `AGENT.md`, `copilot-instructions.md`, `README.md`). O repositório está vazio ou não contém artefatos detectáveis.

 O que fazer primeiro (etapas de descoberta de alto valor)
 1. Abrir arquivos de topo se existirem: `README--choco install -y php composer
# Feche e reabra o terminal e verifique:
php -v
composer --versionicável.

 Mesclagem (se este arquivo já existir)
 - Preserve trechos úteis sob títulos já presentes (por exemplo **Project-specific** ou **Known Commands**).
 - Acrescente uma seção "Repository scan" com achados concretos e comandos verificados.
 - Não remova anotações de mantenedor; prefira adicionar uma subseção "Notas do agente IA".

 Ao editar código
 - Faça alterações mínimas e específicas; siga o estilo existente do código.
 - Adicione testes só quando pequenos, diretos e executáveis localmente.

 Exemplos concretos a incluir quando encontrados
 - Entrypoint: `src/server.js` (inicia Express usando `process.env.PORT`).
 - Banco de dados: `migrations/` com Knex — comando: `npx knex migrate:latest`.
 - Integração: `api/` (REST) e `worker/` (fila Bull) comunicando via `REDIS_URL`.

 Limitações
 - Documente apenas fatos verificáveis a partir dos arquivos; não especule sobre provedores de deploy ou segredos.

 Próximos passos para o revisor humano
 - Se o repositório não estiver vazio, execute a checklist de descoberta acima e cole os resultados na seção "Repository scan".
 - Informe quais comandos funcionaram ou falharam para que o arquivo seja refinado.

 Observação
 - Este modelo é um ponto de partida; posso executar a varredura automaticamente e preencher os achados se desejar.

 Favor revisar e indicar se quer que eu execute a varredura e preencha os detalhes encontrados.

 **Projeto específico**
 - **Stack:** Laravel (API) + React + Tailwind + PostgreSQL.
 - **Multiempresa:** o sistema é multiempresa — todas as tabelas devem conter `empresa_id` e todo acesso a dados precisa ser filtrado pelo contexto da empresa atual. Nunca conflitar empresa_id vindo do cliente sem validação/associação pelo backend.
 - **Arquitetura modular:** cada módulo deve ter responsabilidades bem separadas (rotas, controllers, models, migrations, testes). Evitar misturar funcionalidades de um módulo dentro de outro.
 - **Boas práticas Laravel:** usar `FormRequest` para validação, `Resource`/`ResourceCollection` para respostas API, `Policy`/`Gate` para autorização e middlewares para scoping (ex.: vincular `empresa_id` ao usuário autenticado).
 - **Padrões recomendados:** manter lógica de negócio em services ou domain classes; controladores finos; usar migrations para schema; factory + seeders para dados de desenvolvimento.
 - **Permissões:** validar sempre no servidor — use `Policies` e checagens antes de qualquer operação crítica. Cobre também as APIs consumidas pelo frontend (checagem dupla não depende só do cliente).
 - **Frontend (React + Tailwind):** organizar componentes por módulo (ex.: `resources/js/modules/<Modulo>/`), usar hooks para chamadas à API e manter a UI desacoplada das regras de negócio.
 - **Fluxo de desenvolvimento / comandos úteis:**
	 - Instalar dependências backend: `composer install`
	 - Rodar migrations: `php artisan migrate`
	 - Rodar seeders: `php artisan db:seed`
	 - Rodar testes: `php artisan test` (ou `vendor/bin/phpunit`)
	 - Frontend: `npm install` e `npm run dev` (ou `npm run build` para produção)
	 - Worker/queues: `php artisan queue:work` quando usar filas
 - **Banco de dados (Postgres):** sempre criar índice em `empresa_id` quando presente; definir FK/constraints apropriados.
 - **Segurança de configuração:** manter `.env` fora do repositório e fornecer `.env.example` com variáveis necessárias.
 - **Testes:** criar testes de integração/feature que validem scoping por `empresa_id` e regras de autorização.

 Se quiser, aplico essas regras diretamente ao `copilot-instructions.md` (feito) e depois posso:
 - executar a varredura automática do repositório para popular a seção "Repository scan"; ou
 - começar a implementar uma tarefa específica se você descrever o que precisa (substitua `[DESCREVA A TAREFA AQUI]`).

**Regra de Interação do Agente**
- **Responda apenas em Português:** Todas as respostas do agente devem ser fornecidas exclusivamente em Português (pt-BR). Não incluir versões em outros idiomas, exceto quando o usuário solicitar explicitamente.
