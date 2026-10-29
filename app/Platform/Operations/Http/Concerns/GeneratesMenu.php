<?php

namespace App\Platform\Operations\Http\Concerns;

/**
 * Flattens a registered navigation tree into the plain arrays the SPA renders.
 */
trait GeneratesMenu
{
    /**
     * Read one registered menu and drop every entry this user may not see.
     *
     * Visibility itself is decided by the user model; this only asks.
     */
    public function generateMenu($key, $user)
    {
        $navigation = \Menu::get($key);

        if (! $navigation) {
            return [];
        }

        $visible = [];

        foreach ($navigation->items->toArray() as $entry) {
            if ($user->checkAccess($entry)) {
                $visible[] = $this->describeMenuEntry($entry);
            }
        }

        return $visible;
    }

    /**
     * One navigation entry in wire shape. Grouping label and ordering weight
     * are optional in the menu definition, so they fall back here.
     */
    private function describeMenuEntry(object $entry): array
    {
        $meta = $entry->data;

        return [
            'title' => $entry->title,
            'link' => $entry->link->path['url'],
            'icon' => $meta['icon'],
            'name' => $meta['name'],
            'group' => $meta['group'],
            'group_label' => $meta['group_label'] ?? '',
            'priority' => $meta['priority'] ?? 100,
        ];
    }
}
