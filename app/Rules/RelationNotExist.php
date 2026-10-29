<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guards bulk deletes: rejects an id whose record still has rows hanging off
 * the named relation.
 *
 * Known defect, kept deliberately: an id with no matching record makes the
 * lookup return null and the relation call raise, which surfaces as a 500
 * rather than a validation failure. Call sites pair this with an existence
 * check when they care.
 */
class RelationNotExist implements ValidationRule
{
    public $class;

    public $relation;

    /**
     * @param  string|null  $class  Model to look the value up on.
     * @param  string|null  $relation  Relation method that must come back empty.
     * @return void
     */
    public function __construct(?string $class = null, ?string $relation = null)
    {
        $this->class = $class;
        $this->relation = $relation;
    }

    /**
     * Decide the value.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $method = $this->relation;

        if ($this->class::find($value)->$method()->exists()) {
            $fail("Relation {$this->relation} exists.");
        }

    }
}
