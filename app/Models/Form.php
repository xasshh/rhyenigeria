<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `fields` is an optional list of field definitions, for example:
 *
 *   [{"name": "turnout", "label": "Turnout", "type": "number", "required": true}]
 *
 * Only `name` and `required` are enforced by the API; everything else is for
 * the client that renders the form.
 */
#[Fillable(['key', 'name', 'description', 'fields', 'is_active'])]
class Form extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** @return list<string> */
    public function requiredFieldNames(): array
    {
        return collect($this->fields ?? [])
            ->filter(fn ($field) => is_array($field) && ! empty($field['required']) && isset($field['name']))
            ->pluck('name')
            ->values()
            ->all();
    }
}
