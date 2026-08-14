<?php

namespace App\Experience\Blocks;

use App\Contracts\BlockContract;
use Filament\Forms;
use Illuminate\Contracts\View\View;

class RichTextBlock implements BlockContract
{
    public static function id(): string
    {
        return 'rich-text';
    }

    public static function label(): string
    {
        return 'Rich Text';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\RichEditor::make('content')
                ->label('Content')
                ->columnSpanFull(),
        ];
    }

    public function render(array $config): View
    {
        return view('experience.blocks.rich-text', [
            'content' => $config['content'] ?? '',
        ]);
    }
}
