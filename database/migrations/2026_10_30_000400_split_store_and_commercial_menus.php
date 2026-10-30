<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ADMIN_SECTION = 'Administração';

    public function up(): void
    {
        $store = DB::table('menus')
            ->where('name', 'Loja e vendas')
            ->where('type', 'dropdown')
            ->first()
            ?? DB::table('menus')
                ->where('name', 'Loja')
                ->where('type', 'dropdown')
                ->first();

        $adminId = $store?->sub ?: DB::table('menus')
            ->where('name', self::ADMIN_SECTION)
            ->where('type', 'section')
            ->value('id');

        if (! $adminId) {
            return;
        }

        if ($store) {
            DB::table('menus')->where('id', $store->id)->update([
                'name' => 'Loja',
                'type' => 'dropdown',
                'link' => null,
                'sub' => (string) $adminId,
                'icon' => 'store',
                'sort_order' => 3,
                'updated_at' => now(),
            ]);

            $storeId = (int) $store->id;
        } else {
            $storeId = (int) DB::table('menus')->insertGetId([
                'name' => 'Loja',
                'type' => 'dropdown',
                'link' => null,
                'sub' => (string) $adminId,
                'icon' => 'store',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $commercial = DB::table('menus')
            ->where('name', 'Comercial')
            ->where('type', 'dropdown')
            ->first();

        if ($commercial) {
            DB::table('menus')->where('id', $commercial->id)->update([
                'name' => 'Comercial',
                'type' => 'dropdown',
                'link' => null,
                'sub' => (string) $adminId,
                'icon' => 'sellers',
                'sort_order' => 4,
                'updated_at' => now(),
            ]);

            $commercialId = (int) $commercial->id;
        } else {
            $commercialId = (int) DB::table('menus')->insertGetId([
                'name' => 'Comercial',
                'type' => 'dropdown',
                'link' => null,
                'sub' => (string) $adminId,
                'icon' => 'sellers',
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')
            ->whereIn('link', ['/admin/vendedores', '/admin/comissoes'])
            ->update([
                'sub' => (string) $commercialId,
                'updated_at' => now(),
            ]);

        DB::table('menus')->where('link', '/admin/vendedores')->update([
            'sort_order' => 1,
            'updated_at' => now(),
        ]);

        DB::table('menus')->where('link', '/admin/comissoes')->update([
            'sort_order' => 2,
            'updated_at' => now(),
        ]);

        DB::table('menus')->where('link', '/admin/loja')->update([
            'name' => 'Vitrine',
            'updated_at' => now(),
        ]);

        $this->sortGroup('Financeiro', 5);
        $this->sortGroup('Sistema', 6);

        DB::table('menus')
            ->where('name', 'Conta')
            ->where('type', 'section')
            ->update(['sort_order' => 7, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $store = DB::table('menus')
            ->where('name', 'Loja')
            ->where('type', 'dropdown')
            ->first();

        $commercial = DB::table('menus')
            ->where('name', 'Comercial')
            ->where('type', 'dropdown')
            ->first();

        if ($store) {
            DB::table('menus')->where('id', $store->id)->update([
                'name' => 'Loja e vendas',
                'sort_order' => 3,
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->where('link', '/admin/loja')->update([
            'name' => 'Loja',
            'updated_at' => now(),
        ]);

        if ($commercial && $store) {
            DB::table('menus')
                ->whereIn('link', ['/admin/vendedores', '/admin/comissoes'])
                ->update([
                    'sub' => (string) $store->id,
                    'updated_at' => now(),
                ]);

            DB::table('menus')->where('link', '/admin/vendedores')->update([
                'sort_order' => 4,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('link', '/admin/comissoes')->update([
                'sort_order' => 5,
                'updated_at' => now(),
            ]);

            DB::table('menus')->where('id', $commercial->id)->delete();
        }

        $this->sortGroup('Financeiro', 4);
        $this->sortGroup('Sistema', 5);

        DB::table('menus')
            ->where('name', 'Conta')
            ->where('type', 'section')
            ->update(['sort_order' => 6, 'updated_at' => now()]);
    }

    private function sortGroup(string $name, int $sortOrder): void
    {
        DB::table('menus')
            ->where('name', $name)
            ->where('type', 'dropdown')
            ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
    }
};
