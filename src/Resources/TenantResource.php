<?php

namespace AngelitoSystems\FilamentTenancy\Resources;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Resources\TenantResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'create') {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Select::make('plan')
                            ->options([
                                'basic' => 'Basic',
                                'premium' => 'Premium',
                                'enterprise' => 'Enterprise',
                            ])
                            ->placeholder('Select a plan'),

                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->placeholder('Never expires'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Domain Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('domain')
                            ->label('Custom Domain')
                            ->placeholder('example.com')
                            ->unique(ignoreRecord: true)
                            ->rules(['nullable', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.[a-zA-Z]{2,}$/'])
                            ->helperText('Full domain name (e.g., clinic.com)'),

                        Forms\Components\TextInput::make('subdomain')
                            ->label('Subdomain')
                            ->placeholder('clinic')
                            ->unique(ignoreRecord: true)
                            ->rules(['nullable', 'alpha_dash'])
                            ->helperText('Subdomain prefix (e.g., clinic.yourdomain.com)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Database Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('database_name')
                            ->label('Database Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Will be auto-generated if left empty'),

                        Forms\Components\TextInput::make('database_host')
                            ->label('Database Host')
                            ->placeholder('127.0.0.1')
                            ->helperText('Leave empty to use default from template'),

                        Forms\Components\TextInput::make('database_port')
                            ->label('Database Port')
                            ->numeric()
                            ->placeholder('3306')
                            ->helperText('Leave empty to use default from template'),

                        Forms\Components\TextInput::make('database_username')
                            ->label('Database Username')
                            ->placeholder('root')
                            ->helperText('Leave empty to use default from template'),

                        Forms\Components\TextInput::make('database_password')
                            ->label('Database Password')
                            ->password()
                            ->helperText('Leave empty to use default from template'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Data')
                    ->schema([
                        Forms\Components\KeyValue::make('data')
                            ->label('Custom Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Store additional tenant-specific configuration'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('subdomain')
                    ->searchable()
                    ->placeholder('N/A'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'basic' => 'gray',
                        'premium' => 'warning',
                        'enterprise' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('No plan'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\SelectFilter::make('plan')
                    ->options([
                        'basic' => 'Basic',
                        'premium' => 'Premium',
                        'enterprise' => 'Enterprise',
                    ]),

                Tables\Filters\Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('expires_at', '<=', now()))
                    ->label('Expired'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}