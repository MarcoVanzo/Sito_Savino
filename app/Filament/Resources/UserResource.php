<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Rules\NotAPreviousPassword;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Utente / Amministratore';

    protected static ?string $pluralModelLabel = 'Utenti / Amministratori';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Amministrazione';

    protected static ?string $navigationLabel = 'Utenti';

    protected static ?int $navigationSort = 1;

    /**
     * Il ruolo (enum o valore stringa proveniente dallo stato del form)
     * ha accesso al pannello di amministrazione?
     */
    protected static function roleCanAccessPanel(UserRole|string|null $role): bool
    {
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role);

        return $role?->canAccessPanel() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dati Utente')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->dehydrated(fn ($state) => filled($state))
                            // Anche le password impostate dal pannello devono
                            // rispettare la policy: robustezza minima e divieto
                            // di riuso delle ultime N già utilizzate.
                            ->rule(PasswordRule::defaults())
                            ->rule(fn (?User $record): NotAPreviousPassword => new NotAPreviousPassword($record))
                            ->helperText('Lascia vuoto per non modificare la password attuale.'),
                    ])->columns(2),
                Forms\Components\Section::make('Permessi')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Ruolo')
                            ->options(UserRole::class)
                            ->required()
                            ->default(UserRole::User)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                // Selezionando un ruolo con accesso al pannello, il cambio
                                // password al primo accesso è obbligatorio: forza il toggle.
                                if (self::roleCanAccessPanel($state)) {
                                    $set('must_change_password', true);
                                }
                            })
                            ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Attivo (Abilitato all\'accesso)')
                            ->default(false)
                            ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                        Forms\Components\Toggle::make('must_change_password')
                            ->label('Forza cambio password al primo login')
                            ->helperText('Obbligatorio per gli account con accesso al pannello (admin).')
                            ->default(fn (string $context): bool => $context === 'create')
                            ->dehydrated()
                            ->disabled(function (Forms\Get $get, $record): bool {
                                if ($record && $record->id === auth()->id()) {
                                    return true;
                                }

                                // Per i ruoli admin è forzato (non disattivabile).
                                return self::roleCanAccessPanel($get('role'));
                            }),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->getLabel())
                    ->color(fn (UserRole $state): string => $state->getColor())
                    ->icon(fn (UserRole $state): string => $state->getIcon()),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data Registrazione')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options(UserRole::class),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Attivo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn ($record) => $record->id === auth()->id()),
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
            RelationManagers\OrdersRelationManager::class,
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
