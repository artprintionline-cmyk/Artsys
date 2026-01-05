<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\SaasController;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum','tenant');

    // Onboarding público de empresa (SaaS)
    Route::post('onboarding', [OnboardingController::class, 'store'])->middleware('throttle:10,1');

    // Mercado Pago Webhook (público)
    Route::post('mercado-pago/webhook', [App\Http\Controllers\Api\V1\MercadoPagoController::class, 'webhook'])->middleware('throttle:120,1');

    // WhatsApp Cloud API Webhook (público)
    Route::get('whatsapp/webhook', [App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1');
    Route::post('whatsapp/webhook', [App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'webhook'])->middleware('throttle:120,1');

    Route::middleware(['auth:sanctum','tenant','plano'])->group(function () {
        Route::get('me', function (Request $request) {
            $user = $request->user();
            if (! $user) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }

            $perfil = $user->perfil()->first();
            return response()->json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'empresa_id' => $user->empresa_id,
                'status' => (bool) $user->status,
                'perfil' => $perfil ? ['id' => $perfil->id, 'nome' => $perfil->nome] : null,
                'permissoes' => $user->permissoesChaves(),
            ]);
        });

        Route::get('teste-tenant', function (Request $request) {
            $user = $request->user();
            $empresaId = $request->attributes->get('empresa_id') ?? null;
            return response()->json([
                'user_id' => $user ? $user->id : null,
                'empresa_id' => $empresaId,
                'mensagem' => 'Tenant OK',
            ]);
        });

        // SaaS: plano e assinatura da empresa atual
        Route::get('saas/planos', [SaasController::class, 'planos'])->middleware('perm:saas.view');
        Route::get('saas/assinatura', [SaasController::class, 'assinatura'])->middleware('perm:saas.view');
        Route::post('saas/assinatura/simular-pagamento', [SaasController::class, 'simularPagamento'])->middleware('perm:saas.manage');
        Route::post('saas/assinatura/status', [SaasController::class, 'setStatus'])->middleware('perm:saas.manage');
        // Clientes CRUD (multi-tenant)
        Route::get('clientes', [App\Http\Controllers\Api\V1\ClienteController::class, 'index'])->middleware('perm:clientes.view');
        Route::post('clientes', [App\Http\Controllers\Api\V1\ClienteController::class, 'store'])->middleware('perm:clientes.create');
        Route::get('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'show'])->middleware('perm:clientes.view');
        Route::get('clientes/{id}/financeiro-resumo', [App\Http\Controllers\Api\V1\ClienteController::class, 'financeiroResumo'])->middleware('perm:clientes.view', 'perm:financeiro.view');
        Route::put('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'update'])->middleware('perm:clientes.edit');
        Route::delete('clientes/{id}', [App\Http\Controllers\Api\V1\ClienteController::class, 'destroy'])->middleware('perm:clientes.delete');

        // Produtos CRUD + composição e custo (multi-tenant)
        $produtoController = App\Http\Controllers\Api\V1\ProdutoController::class;

        Route::get('produtos', [$produtoController, 'index'])->middleware('perm:produtos.view');
        Route::post('produtos', [$produtoController, 'store'])->middleware('perm:produtos.create');
        Route::get('produtos/{id}', [$produtoController, 'show'])->middleware('perm:produtos.view');
        Route::put('produtos/{id}', [$produtoController, 'update'])->middleware('perm:produtos.edit');
        Route::delete('produtos/{id}', [$produtoController, 'destroy'])->middleware('perm:produtos.delete');

        Route::post('produtos/{id}/componentes', [$produtoController, 'adicionarComponente'])->middleware('perm:produtos.edit');
        Route::delete('produtos/{id}/componentes/{componenteId}', [$produtoController, 'removerComponente'])->middleware('perm:produtos.edit');
        Route::post('produtos/{id}/recalcular-custo', [$produtoController, 'recalcularCusto'])->middleware('perm:produtos.edit');

        // Insumos (materiais de consumo)
        $insumoController = App\Http\Controllers\Api\V1\InsumoController::class;
        Route::get('insumos', [$insumoController, 'index'])->middleware('perm:insumos.view');
        Route::post('insumos', [$insumoController, 'store'])->middleware('perm:insumos.create');
        Route::get('insumos/{id}', [$insumoController, 'show'])->middleware('perm:insumos.view');
        Route::put('insumos/{id}', [$insumoController, 'update'])->middleware('perm:insumos.edit');
        Route::delete('insumos/{id}', [$insumoController, 'destroy'])->middleware('perm:insumos.delete');

        // Compras (base de custos reais: preço médio ponderado)
        $compraItemController = App\Http\Controllers\Api\V1\CompraItemController::class;
        Route::get('compras/itens', [$compraItemController, 'index'])->middleware('perm:compras.itens.view');
        Route::get('compras/itens/{id}', [$compraItemController, 'show'])->middleware('perm:compras.itens.view');

        // Planejamento (Produto): listar itens vindos de compras sem exigir permissão de Compras
        Route::get('produtos/itens-planejamento', [$compraItemController, 'index'])->middleware('perm:produtos.view');

        // IMPORTANTE: Item nasce exclusivamente na compra (CompraController@store).
        // Não expor CRUD de itens fora da compra.

        $compraController = App\Http\Controllers\Api\V1\CompraController::class;
        Route::get('compras', [$compraController, 'index'])->middleware('perm:compras.compras.view');
        Route::post('compras', [$compraController, 'store'])->middleware('perm:compras.compras.create');
        Route::get('compras/{id}', [$compraController, 'show'])->middleware('perm:compras.compras.view');
        Route::delete('compras/{id}', [$compraController, 'destroy'])->middleware('perm:compras.compras.delete');

        // Custos V1 removido: custos reais centralizados em Compras.

        // Produtos Compostos
        $produtoCompostoController = App\Http\Controllers\Api\V1\ProdutoCompostoController::class;
        Route::get('produtos-compostos', [$produtoCompostoController, 'index'])->middleware('perm:produtos.view');
        Route::post('produtos-compostos', [$produtoCompostoController, 'store'])->middleware('perm:produtos.create');
        Route::get('produtos-compostos/{id}', [$produtoCompostoController, 'show'])->middleware('perm:produtos.view');
        Route::put('produtos-compostos/{id}', [$produtoCompostoController, 'update'])->middleware('perm:produtos.edit');
        Route::delete('produtos-compostos/{id}', [$produtoCompostoController, 'destroy'])->middleware('perm:produtos.delete');

        // Ordens de Serviço (OS) CRUD + itens e status
        $osController = App\Http\Controllers\Api\V1\OrdemServicoController::class;

        Route::get('ordens-servico', [$osController, 'index'])->middleware('perm:os.view');
        Route::post('ordens-servico', [$osController, 'store'])->middleware('perm:os.create', 'plano.limite:os_mes');
        Route::get('ordens-servico/{id}', [$osController, 'show'])->middleware('perm:os.view');
        Route::put('ordens-servico/{id}', [$osController, 'update'])->middleware('perm:os.edit');

        Route::post('ordens-servico/{id}/itens', [$osController, 'adicionarItem'])->middleware('perm:os.edit');
        Route::delete('ordens-servico/{id}/itens/{itemId}', [$osController, 'removerItem'])->middleware('perm:os.edit');

        Route::post('ordens-servico/{id}/status', [$osController, 'atualizarStatus'])->middleware('perm:os.status');
        Route::put('ordens-servico/{id}/status', [$osController, 'atualizarStatusDestino'])->middleware('perm:os.status', 'throttle:60,1');
        Route::delete('ordens-servico/{id}', [$osController, 'destroy'])->middleware('perm:os.cancel');

        // WhatsApp contextual por OS (Kanban)
        $osWhatsAppController = App\Http\Controllers\Api\V1\OrdemServicoWhatsAppController::class;
        Route::get('ordens-servico/{id}/whatsapp', [$osWhatsAppController, 'historico'])->middleware('perm:os.view', 'perm:whatsapp.view', 'plano.feature:whatsapp', 'throttle:whatsapp-os');
        Route::post('ordens-servico/{id}/whatsapp/enviar', [$osWhatsAppController, 'enviar'])->middleware('perm:os.view', 'perm:whatsapp.send', 'plano.feature:whatsapp', 'throttle:whatsapp-os');

        // Financeiro (contas a receber)
        $financeiroController = App\Http\Controllers\Api\V1\FinanceiroController::class;

        Route::get('financeiro', [$financeiroController, 'index'])->middleware('perm:financeiro.view');
        Route::post('financeiro', [$financeiroController, 'store'])->middleware('perm:financeiro.create');
        Route::get('financeiro/{id}', [$financeiroController, 'show'])->middleware('perm:financeiro.view');
        Route::put('financeiro/{id}', [$financeiroController, 'update'])->middleware('perm:financeiro.pay');
        Route::delete('financeiro/{id}', [$financeiroController, 'destroy'])->middleware('perm:financeiro.delete');
        Route::post('financeiro/{ordemServicoId}/gerar', [$financeiroController, 'gerar'])->middleware('perm:financeiro.create');

        // Mercado Pago (PIX)
        Route::post('mercado-pago/gerar-pix', [App\Http\Controllers\Api\V1\MercadoPagoController::class, 'gerarPix'])->middleware('perm:financeiro.pay');

        // Estoque
        $estoqueController = App\Http\Controllers\Api\V1\EstoqueController::class;
        Route::get('estoque', [$estoqueController, 'index'])->middleware('perm:estoque.view');
        Route::post('estoque/ajuste', [$estoqueController, 'ajuste'])->middleware('perm:estoque.adjust');

        // Relatórios
        $relatoriosController = App\Http\Controllers\Api\V1\RelatoriosController::class;
        Route::get('relatorios/ordens-servico', [$relatoriosController, 'ordensServico'])->middleware('perm:relatorios.view');
        Route::get('relatorios/producao', [$relatoriosController, 'producao'])->middleware('perm:relatorios.view');
        Route::get('relatorios/produtos-mais-usados', [$relatoriosController, 'produtosMaisUsados'])->middleware('perm:relatorios.view');
        Route::get('relatorios/financeiro', [$relatoriosController, 'financeiro'])->middleware('perm:relatorios.view');
        Route::get('relatorios/faturamento', [$relatoriosController, 'faturamento'])->middleware('perm:relatorios.view');
        Route::get('relatorios/inadimplencia', [$relatoriosController, 'inadimplencia'])->middleware('perm:relatorios.view');

        // WhatsApp (conversas)
        Route::get('whatsapp/conversas', [App\Http\Controllers\Api\V1\WhatsAppController::class, 'conversas'])->middleware('perm:whatsapp.view', 'plano.feature:whatsapp');
        Route::get('whatsapp/conversas/{numero}', [App\Http\Controllers\Api\V1\WhatsAppController::class, 'conversa'])->middleware('perm:whatsapp.view', 'plano.feature:whatsapp');
        Route::post('whatsapp/conversas/{numero}/mensagens', [App\Http\Controllers\Api\V1\WhatsAppController::class, 'enviarMensagem'])->middleware('perm:whatsapp.send', 'plano.feature:whatsapp');
        Route::post('whatsapp/enviar-pix', [App\Http\Controllers\Api\V1\WhatsAppController::class, 'enviarPix'])->middleware('perm:whatsapp.send', 'plano.feature:whatsapp');
        
        // Dashboard summary
        Route::get('dashboard/summary', [App\Http\Controllers\Api\V1\DashboardController::class, 'summary'])->middleware('perm:dashboard.view');

        // Dashboard executivo
        Route::get('dashboard/resumo', [App\Http\Controllers\Api\V1\DashboardController::class, 'resumo'])->middleware('perm:dashboard.view');
        Route::get('dashboard/operacional', [App\Http\Controllers\Api\V1\DashboardController::class, 'operacional'])->middleware('perm:dashboard.view');
        Route::get('dashboard/financeiro', [App\Http\Controllers\Api\V1\DashboardController::class, 'financeiro'])->middleware('perm:dashboard.view');

        // Admin: perfis e usuários
        $perfisController = App\Http\Controllers\Api\V1\PerfisController::class;
        Route::get('perfis', [$perfisController, 'index'])->middleware('perm:admin.users.manage');
        Route::put('perfis/{id}/permissoes', [$perfisController, 'atualizarPermissoes'])->middleware('perm:admin.users.manage');

        $usuariosController = App\Http\Controllers\Api\V1\UsuariosController::class;
        Route::get('usuarios', [$usuariosController, 'index'])->middleware('perm:admin.users.manage');
        Route::put('usuarios/{id}', [$usuariosController, 'update'])->middleware('perm:admin.users.manage', 'plano.limite:usuarios');
    });
});
