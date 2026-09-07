<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
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
                Forms\Components\TextInput::make('avatar')
                    ->maxLength(255),
                // Stored as plain text, and kept that way here on purpose.
                // A textarea an admin can put markup into writes straight
                // onto a page other people load; when bios become rich text
                // this dehydrate step becomes the same server-side
                // sanitiser that endpoint will use, rather than a second
                // path around it.
                Forms\Components\Textarea::make('bio')
                    ->helperText('Plain text — any HTML is stripped when saved.')
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::plainText($state))
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

    /** Tag-free text, or null when nothing is left worth storing. */
    private static function plainText(?string $value): ?string
    {
        $stripped = trim(strip_tags((string) $value));

        return $stripped === '' ? null : $stripped;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
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
