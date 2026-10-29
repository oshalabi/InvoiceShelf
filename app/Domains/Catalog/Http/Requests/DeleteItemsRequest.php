<?php

namespace App\Domains\Catalog\Http\Requests;

use App\Domains\Catalog\Models\Item;
use App\Rules\RelationNotExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Incoming payload for the bulk removal of catalog items.
 *
 * The guard against destroying referenced data sits here rather than in the
 * controller, so a blocked removal comes back as a field error on the id that
 * caused it instead of as a conflict on the request as a whole.
 */
class DeleteItemsRequest extends FormRequest
{
    /**
     * Relations that pin an item in place: while any one of them holds a row,
     * the item may not be removed.
     *
     * Quirk kept as is: the last entry is the item's own default taxes, which
     * are part of the item rather than a use of it. A taxed item therefore
     * cannot be removed until its tax list has been emptied by an update.
     *
     * @var array<int, string>
     */
    private const BLOCKING_RELATIONS = [
        'invoiceItems',
        'estimateItems',
        'taxes',
    ];

    /**
     * Access is settled by the standalone bulk-delete ability in the
     * controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every submitted id has to name a real item and be free of the relations
     * above.
     *
     * Quirk kept as is: existence is checked against the whole table, not
     * within the acting company, so an id owned by another company passes
     * validation here and is then dropped by the company-scoped deletion.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unreferenced = array_map(
            fn (string $relation) => new RelationNotExist(Item::class, $relation),
            self::BLOCKING_RELATIONS,
        );

        return [
            'ids' => ['required'],
            'ids.*' => array_merge(
                ['required', Rule::exists('items', 'id')],
                $unreferenced,
            ),
        ];
    }
}
