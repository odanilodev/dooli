<?php

namespace App\Services;

use App\Models\Datalog;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Auth;

/**
 * Centraliza auditoria de operações críticas (datalog).
 * Nunca registra senhas, tokens ou credenciais.
 */
class DatalogService
{
    public function __construct(private readonly CompanyContext $context) {}

    public function log(
        string $module,
        string $action,
        string $item,
        ?string $description = null,
        ?int $userId = null,
    ): void {
        $companyId = $this->context->id() ?? Auth::user()?->company_id;

        if (! $companyId) {
            return;
        }

        Datalog::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId ?? Auth::id(),
            'module' => $module,
            'action' => $action,
            'item' => $item,
            'description' => $description,
        ]);
    }
}
