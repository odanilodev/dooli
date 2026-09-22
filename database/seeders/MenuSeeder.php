<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            ['name' => 'Dashboard', 'link' => '/dashboard', 'icon' => 'home', 'sort_order' => 1],
            ['name' => 'Cursos', 'link' => '/courses', 'icon' => 'book', 'sort_order' => 2],
            ['name' => 'Turmas', 'link' => '/teams', 'icon' => 'users', 'sort_order' => 3],
            ['name' => 'Alunos', 'link' => '/students', 'icon' => 'user', 'sort_order' => 4],
            ['name' => 'Pedidos', 'link' => '/orders', 'icon' => 'shopping-cart', 'sort_order' => 5],
            ['name' => 'Cupons', 'link' => '/coupons', 'icon' => 'tag', 'sort_order' => 6],
            ['name' => 'Pagamentos', 'link' => '/payments', 'icon' => 'credit-card', 'sort_order' => 7],
            ['name' => 'Certificados', 'link' => '/certificates', 'icon' => 'award', 'sort_order' => 8],
            ['name' => 'Configurações', 'link' => '/settings', 'icon' => 'settings', 'sort_order' => 9],
        ];

        foreach ($menus as $menu) {
            Menu::query()->updateOrCreate(['name' => $menu['name']], $menu);
        }
    }
}
