<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

class MenuService
{
    /**
     * Menus visible to the given user.
     *
     * An empty permission_menu means "all menus". Otherwise only the permitted
     * menus plus their ancestor chain are returned, so sections/dropdowns that
     * hold a permitted item do not disappear.
     *
     * @return Collection<int, Menu>
     */
    public function visibleFor(User $user): Collection
    {
        $menus = Menu::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $allowed = array_filter((array) ($user->userType?->permission_menu ?? []));

        if (empty($allowed)) {
            return $menus;
        }

        $byId = $menus->keyBy('id');
        $visible = [];

        foreach ($allowed as $id) {
            $menu = $byId->get((int) $id);

            while ($menu) {
                $visible[(int) $menu->id] = true;

                $parentId = $menu->sub !== null ? (int) $menu->sub : null;
                $menu = $parentId !== null ? $byId->get($parentId) : null;
            }
        }

        return $menus
            ->filter(fn (Menu $menu) => isset($visible[(int) $menu->id]))
            ->values();
    }
}
