<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerLayoutWidget extends Model
{
    protected $fillable = [
        'server_layout_id',
        'widget_type',
        'config',
        'position_x',
        'position_y',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(ServerLayout::class, 'server_layout_id');
    }
}
