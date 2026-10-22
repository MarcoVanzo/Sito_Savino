<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use App\Rules\NotAPreviousPassword;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Attributo usato per il titolo nei risultati di ricerca globale
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Utente / Amministratore';

    protected static ?string $pluralModelLabel = 'Utenti / Amministratori';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati Utente')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
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
                Section::make('Permessi')
                    ->schema([
                        Select::make('role')
                            ->label('Ruolo')
                            ->options(UserRole::class)
                            ->required()
                            ->default(UserRole::User)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                // Selezionando un ruolo con accesso al pannello, il cambio
                                // password al primo accesso è obbligatorio: forza il toggle.
                                if (self::roleCanAccessPanel($state)) {
                                    $set('must_change_password', true);
                                }
                            })
                            ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                        Toggle::make('is_active')
                            ->label('Attivo (Abilitato all\'accesso)')
                            ->default(false)
                            ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                        Toggle::make('must_change_password')
                            ->label('Forza cambio password al primo login')
                            ->helperText('Obbligatorio per gli account con accesso al pannello (admin).')
                            ->default(fn (string $context): bool => $context === 'create')
                            ->dehydrated()
                            ->disabled(function (Get $get, $record): bool {
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
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->getLabel())
                    ->color(fn (UserRole $state): string => $state->getColor())
                    ->icon(fn (UserRole $state): string => $state->getIcon()),
                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->disabled(fn ($record) => $record && $record->id === auth()->id()),
                TextColumn::make('created_at')
                    ->label('Data Registrazione')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options(UserRole::class),
                TernaryFilter::make('is_active')
                    ->label('Attivo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn ($record) => $record->id === auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Ogni record passa da UserPolicy::delete(): la selezione
                    // può includere sé stessi o l'ultimo super admin, che
                    // vengono saltati invece di essere cancellati.
                    // Il controllo è fatto record per record e al momento della
                    // cancellazione, così l'ultimo super admin si riconosce anche
                    // dopo aver cancellato gli altri della stessa selezione.
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            foreach ($records as $record) {
                                if (Gate::allows('delete', $record)) {
                                    $record->delete();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
