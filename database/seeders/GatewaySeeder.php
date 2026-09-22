<?php

namespace Database\Seeders;

use App\Enums\GatewayCode;
use App\Enums\GeneralStatus;
use App\Models\Company;
use App\Models\Gateway;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->findOrFail(1);

        $gateways = [
            [
                'name' => 'Mercado Pago',
                'code' => GatewayCode::MercadoPago->value,
                'credentials' => [
                    'access_token' => 'TEST-DEMO-ACCESS-TOKEN',
                    'public_key' => 'TEST-DEMO-PUBLIC-KEY',
                    'webhook_secret' => 'demo-mercadopago-secret',
                ],
                'settings' => ['sandbox' => true],
            ],
            [
                'name' => 'Pagamento Manual',
                'code' => GatewayCode::Manual->value,
                'credentials' => [
                    'webhook_secret' => 'demo-manual-secret',
                ],
                'settings' => ['instructions' => 'Envie o comprovante para suporte@cursos.test'],
            ],
        ];

        foreach ($gateways as $gateway) {
            Gateway::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $gateway['code']],
                [
                    'name' => $gateway['name'],
                    'status' => GeneralStatus::Active->value,
                    'credentials' => $gateway['credentials'],
                    'settings' => $gateway['settings'],
                ],
            );
        }
    }
}
