<?php

namespace Database\Seeders;

use App\Enums\ActionCode;
use App\Enums\GeneralStatus;
use App\Models\Action;
use Illuminate\Database\Seeder;

class ActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            ActionCode::Started->value => 'Iniciou',
            ActionCode::Viewed->value => 'Visualizou',
            ActionCode::Completed->value => 'Concluiu',
        ];

        foreach ($actions as $code => $name) {
            Action::query()->updateOrCreate(
                ['company_id' => 1, 'code' => $code],
                ['name' => $name, 'status' => GeneralStatus::Active->value],
            );
        }
    }
}
