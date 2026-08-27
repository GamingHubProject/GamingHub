<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\SiteOption;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * A single settings form, not a Resource — there's exactly one row to
 * edit (SiteOption::current()), so list/create/delete routes would be
 * dead weight. save() writes straight to the singleton row; applying
 * site_name/timezone to the running app's config happens in
 * AppServiceProvider::boot(), not here — this page only owns the form.
 */
class SiteOptions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Basic Settings';

    protected static ?string $navigationLabel = 'Options';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.site-options';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteOption::current()->values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('site_name')
                    ->label('Site name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('site_description')
                    ->label('Site description')
                    ->helperText('Used as the meta description for SEO.')
                    ->maxLength(1000),
                Forms\Components\TextInput::make('site_url')
                    ->label('Site URL')
                    ->url()
                    ->helperText('Base URL used to build absolute links, e.g. https://example.com'),
                Forms\Components\Select::make('timezone')
                    ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('admin_email')
                    ->label('Admin email')
                    ->email()
                    ->helperText('For alerts — not used yet.'),
                Forms\Components\TextInput::make('discord_webhook')
                    ->label('Discord webhook')
                    ->helperText('Optional — for future news/alerts. Not validated or tested here.'),
                Forms\Components\Select::make('font_asset_id')
                    ->label('Platform default font')
                    ->helperText('Upload a .woff/.woff2 file into the Fonts folder from the Asset Library first (Admin > Assets), then pick it here. A page can override this — see that page\'s "Edit layout" controls.')
                    ->options(fn () => Asset::query()
                        ->whereHas('folder', fn ($q) => $q->where('slug', 'fonts'))
                        ->get()
                        ->mapWithKeys(fn (Asset $asset) => [$asset->id => $asset->alt_text ?: basename($asset->disk_path)]))
                    ->searchable()
                    ->nullable(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SiteOption::current()->update(['values' => $this->form->getState()]);

        Notification::make()
            ->title('Options saved')
            ->success()
            ->send();
    }
}
