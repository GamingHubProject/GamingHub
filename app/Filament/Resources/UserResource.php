<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\Asset;
use App\Models\User;
use App\Profiles\RichText;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                // Public presentation, separate from the login identity
                // above. Uniqueness is case-insensitive in the database
                // (a functional index over lower(display_name), so "Rose"
                // and "rose" cannot both exist); Filament's own rule is
                // case-sensitive, hence the explicit callback — without it
                // a collision differing only in case reaches Postgres and
                // comes back as a 500 instead of a form error.
                Forms\Components\TextInput::make('display_name')
                    ->label('Display name')
                    ->helperText('What their profile is titled with, and their /@name link. Blank falls back to the account name.')
                    ->maxLength(50)
                    ->rules([
                        fn (?User $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                            if (blank($value)) {
                                return;
                            }

                            $taken = User::query()
                                ->whereRaw('lower(display_name) = lower(?)', [$value])
                                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail('That display name is already taken.');
                            }
                        },
                    ]),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    // No bcrypt() here: User casts `password` as 'hashed',
                    // so hashing already happens once on the way into the
                    // column. Hashing again in the form was redundant while
                    // both used bcrypt, and would break outright the day the
                    // app's hashing driver changed — the cast rejects a hash
                    // whose algorithm doesn't match its configuration.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\Select::make('avatar_asset_id')
                    ->label('Avatar')
                    ->native(false)
                    ->helperText('People choose their own in the profile editor; this is here for moderation. Raster images only.')
                    ->options(fn () => Asset::query()
                        ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
                        ->latest()
                        ->limit(200)
                        ->get()
                        ->mapWithKeys(fn (Asset $asset) => [$asset->id => $asset->alt_text ?: basename($asset->disk_path)]))
                    ->searchable()
                    ->nullable(),
                Forms\Components\Toggle::make('profile_public')
                    ->label('Public profile')
                    ->helperText('Off means only they and admins can see it.'),
                // Markdown, stored as typed — the same value the person's
                // own editor writes, through the same normaliser. Nothing
                // is sanitised on the way in because nothing renders raw
                // HTML on the way out; see App\Profiles\RichText.
                Forms\Components\Textarea::make('bio')
                    ->helperText('Markdown. Headings, bold, italic, strikethrough, lists, quotes, code and links render; anything else is ignored.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => RichText::normalize($state))
                    ->rows(6)
                    ->columnSpanFull(),
                // One field per allowed preference, built from the same
                // User::PREFERENCES allowlist the API validates against —
                // replacing a raw KeyValue editor, which let an admin write
                // any key with any value straight past that list. Anything
                // already stored outside the list is dropped on save; see
                // User::sanitizePreferences().
                Forms\Components\Fieldset::make('Preferences')
                    ->schema(self::preferenceFields())
                    ->columns(1),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Select>
     */
    private static function preferenceFields(): array
    {
        return collect(User::PREFERENCES)
            ->map(fn (array $allowed, string $key) => Forms\Components\Select::make("preferences.{$key}")
                ->label(Str::headline($key))
                ->options(collect($allowed)->mapWithKeys(fn (string $value) => [$value => Str::headline($value)])->all())
                // Nothing stored means "follow the theme" — the same idiom
                // the per-page font override uses, rather than a separate
                // "is overridden" flag that can disagree with the value.
                ->placeholder('Follow the theme'))
            ->values()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display name')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\IconColumn::make('profile_public')
                    ->label('Public')
                    ->boolean(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
