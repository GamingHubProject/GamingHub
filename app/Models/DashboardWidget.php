<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    protected $fillable = [
        'dashboard_page_id',
        'widget_type',
        'config',
        'order',
        'position_x',
        'position_y',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'order' => 'integer',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(DashboardPage::class, 'dashboard_page_id');
    }
}
