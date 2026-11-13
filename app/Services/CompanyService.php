<?php

namespace App\Services;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Enums\UserRegistrationSource;
use App\Enums\UserTypeCode;
use App\Models\Action;
use App\Models\Company;
use App\Models\Robot;
use App\Models\User;
use App\Models\UserType;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    public function __construct(private readonly DatalogService $audit) {}

    /**
     * Creates a company and bootstraps its default user types plus the first
     * admin account. Companies are the tenant root, so their rows have an
     * explicit company_id that must survive the current request context.
     *
     * @param  array{name: string, slug?: string|null, domain?: string|null, status?: string, admin_name: string, admin_username: string, admin_email: string, admin_password: string}  $data
     * @return array{company: Company, admin: User}
     */
    public function create(array $data): array
    {
        $context = app(CompanyContext::class);
        $previous = $context->id();

        // The new company's rows belong to a company that is not the current
        // tenant; releasing the context keeps their explicit company_id intact.
        $context->forget();

        try {
            [$company, $admin] = DB::transaction(function () use ($data) {
                $company = Company::query()->create([
                    'name' => $data['name'],
                    'slug' => $data['slug'] ?? null,
                    'domain' => $data['domain'] ?? null,
                    'status' => $data['status'] ?? GeneralStatus::Active->value,
                ]);

                $adminType = $this->createUserType($company->id, UserTypeCode::Admin->value, 'Administrador');
                $this->createUserType($company->id, UserTypeCode::Instructor->value, 'Instrutor');
                $this->createUserType($company->id, UserTypeCode::Student->value, 'Aluno');
                $this->createDefaultActions($company->id);
                $this->createDefaultRobots($company->id);

                $admin = User::query()->create([
                    'company_id' => $company->id,
                    'user_type_id' => $adminType->id,
                    'name' => $data['admin_name'],
                    'username' => $data['admin_username'],
                    'email' => $data['admin_email'],
                    'email_verified_at' => now(),
                    'password' => $data['admin_password'],
                    'status' => GeneralStatus::Active->value,
                    'registration_source' => UserRegistrationSource::Manual->value,
                ]);

                return [$company, $admin];
            });
        } finally {
            $previous === null ? $context->forget() : $context->set($previous);
        }

        $this->audit->log('company', 'create', 'company', "Empresa criada: {$company->name}");

        return ['company' => $company, 'admin' => $admin];
    }

    public function update(Company $company, array $data): Company
    {
        $company->fill($data)->save();

        $this->audit->log('company', 'update', 'company', "Empresa atualizada: {$company->name}");

        return $company->refresh();
    }

    /**
     * Ações de progresso do aluno (iniciou, visualizou, concluiu). Sem elas a empresa não
     * consegue registrar conclusão de aula ("Ação de conclusão não configurada").
     */
    private function createDefaultActions(int $companyId): void
    {
        $actions = [
            ActionCode::Started->value => 'Iniciou',
            ActionCode::Viewed->value => 'Visualizou',
            ActionCode::Completed->value => 'Concluiu',
        ];

        foreach ($actions as $code => $name) {
            Action::query()->create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'status' => GeneralStatus::Active->value,
            ]);
        }
    }

    /**
     * Ferramentas (robôs) do catálogo: a empresa já nasce com elas, para o admin poder liberá-las
     * por turma sem depender de migration, seeder ou SQL.
     */
    private function createDefaultRobots(int $companyId): void
    {
        Robot::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'slug' => 'delta-screener'],
            [
                'name' => 'DeltaStats',
                'description' => 'Triagem estatística de setups de entrada por limiar fixo na B3, mercado americano e cripto.',
                'market' => 'b3,us,crypto',
                'icon' => 'chart',
                'status' => GeneralStatus::Active->value,
                'sort_order' => 1,
            ],
        );
    }

    private function createUserType(int $companyId, string $code, string $name): UserType
    {
        return UserType::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'status' => GeneralStatus::Active->value,
            'permission_menu' => [],
        ]);
    }
}
