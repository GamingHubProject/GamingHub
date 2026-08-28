<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageLayoutWidget extends Model
{
    protected $fillable = [
        'page_layout_id',
        'group_widget_id',
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
        return $this->belongsTo(PageLayout::class, 'page_layout_id');
    }

    /**
     * Only ever set on a member of a 'group' widget — see the
     * group_widget_id migration's docblock. A 'group' row itself always
     * has this null (enforced in PageLayoutWidgetController), so there's
     * no risk of walking this more than one level deep.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PageLayoutWidget::class, 'group_widget_id');
    }

    /** Only meaningful when widget_type === 'group'. */
    public function children(): HasMany
    {
        return $this->hasMany(PageLayoutWidget::class, 'group_widget_id');
    }
}
