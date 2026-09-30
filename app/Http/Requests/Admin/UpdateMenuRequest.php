<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuType;
use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menu = $this->route('menu');
        $menuId = $menu instanceof Menu ? $menu->id : $menu;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(MenuType::values())],
            'sub' => array_filter([
                'nullable',
                'integer',
                'exists:menus,id',
                $menuId ? Rule::notIn($this->blockedParentIds((int) $menuId)) : null,
            ]),
            'link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A menu cannot be its own parent nor descend from itself.
     *
     * @return list<int>
     */
    private function blockedParentIds(int $menuId): array
    {
        $blocked = [$menuId];
        $queue = [(string) $menuId];

        while ($queue) {
            $children = Menu::query()
                ->whereIn('sub', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $blocked));

            if (empty($children)) {
                break;
            }

            $blocked = array_merge($blocked, $children);
            $queue = array_map('strval', $children);
        }

        return $blocked;
    }
}
