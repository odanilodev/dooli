<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessTokenSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@cursos.test')->firstOrFail();
        $student = User::query()->where('email', 'aluno@cursos.test')->firstOrFail();

        // The plaintext token is never stored, so this demo record is not usable.
        $admin->tokens()->updateOrCreate(
            ['name' => 'demo-admin-token'],
            [
                'token' => hash('sha256', Str::random(40)),
                'abilities' => ['admin'],
                'expires_at' => now()->addYear(),
            ],
        );

        // Holds a hash of an unknown value, so it cannot be used to reset a password.
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $student->email],
            [
                'token' => bcrypt(Str::random(60)),
                'created_at' => now(),
            ],
        );
    }
}
