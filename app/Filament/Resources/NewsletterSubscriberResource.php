<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsletterSubscriberResource\Pages;
use App\Filament\Resources\NewsletterSubscriberResource\RelationManagers;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                Forms\Components\TextInput::make('first_name')->maxLength(255),
                Forms\Components\TextInput::make('last_name')->maxLength(255),
                Forms\Components\Select::make('status')->options([
                    'active' => 'Attivo',
                    'unsubscribed' => 'Disiscritto',
                ])->default('active')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('first_name')->searchable(),
                Tables\Columns\TextColumn::make('last_name')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'unsubscribed' => 'danger',
                }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNewsletterSubscribers::route('/'),
        ];
    }
}
