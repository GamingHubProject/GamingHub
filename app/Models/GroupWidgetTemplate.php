<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable Group snapshot — see the group_widget_templates migration's
 * docblock for why `snapshot` is JSON rather than relational rows.
 */
class GroupWidgetTemplate extends Model
{
    protected $fillable = [
        'name',
        'created_by',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
