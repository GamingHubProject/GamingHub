<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CapabilityBinding extends Model
{
    protected $fillable = [
        'capability',
        'subject_type',
        'subject_id',
        'provider',
        'value',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
