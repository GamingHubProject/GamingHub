<?php

namespace App\Experience\Blocks;

use App\Contracts\BlockContract;
use Filament\Forms;
use Illuminate\Contracts\View\View;

class HeroBlock implements BlockContract
{
    public static function id(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'Hero';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\TextInput::make('heading')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('subheading')
                ->maxLength(500),
            Forms\Components\TextInput::make('background_image')
                ->label('Background image URL')
                ->url(),
            Forms\Components\TextInput::make('cta_label')
                ->label('Button label'),
            Forms\Components\TextInput::make('cta_url')
                ->label('Button link')
                ->url(),
        ];
    }

    public function render(array $config): View
    {
        return view('experience.blocks.hero', [
            'heading' => $config['heading'] ?? '',
            'subheading' => $config['subheading'] ?? null,
            'backgroundImage' => $config['background_image'] ?? null,
            'ctaLabel' => $config['cta_label'] ?? null,
            'ctaUrl' => $config['cta_url'] ?? null,
        ]);
    }
}
