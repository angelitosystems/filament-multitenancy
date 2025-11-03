<?php

namespace AngelitoSystems\FilamentTenancy\Resources\Tenant;

use AngelitoSystems\FilamentTenancy\Models\Role;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationLabel = 'Roles';
    
    protected static ?string $modelLabel = 'Rol';
    
    protected static ?string $pluralModelLabel = 'Roles';
    
    protected static ?string $breadcrumb = 'Roles';

    protected static  string|\UnitEnum|null $navigationGroup = 'Gestión de Usuarios';
    
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
               Section::make('Role Information')
                    ->schema([
                       TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state,Set $set) {
                                $set('slug', \Illuminate\Support\Str::slug($state));
                            }),

                       TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->readonly(),

                       Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->placeholder('Describe the role and its responsibilities'),

                       ColorPicker::make('color')
                            ->label('Color')
                            ->helperText('Color for badges and UI elements'),
                    ])
                    ->columns(2),

               Section::make('Permissions')
                    ->schema([
                       CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->helperText('Select the permissions that this role should have'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_users')
                    ->query(fn ($query) => $query->has('users'))
                    ->label('Has Users'),

                Tables\Filters\Filter::make('no_users')
                    ->query(fn ($query) => $query->doesntHave('users'))
                    ->label('No Users'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->before(function (Role $record) {
                        if ($record->users()->count() > 0) {
                            throw new \Exception('Cannot delete role with assigned users. Please reassign users first.');
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->users()->count() > 0) {
                                    throw new \Exception("Cannot delete role '{$record->name}' with assigned users. Please reassign users first.");
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
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
            'index' => \AngelitoSystems\FilamentTenancy\Resources\Tenant\RoleResource\Pages\ListRoles::route('/'),
            'create' => \AngelitoSystems\FilamentTenancy\Resources\Tenant\RoleResource\Pages\CreateRole::route('/create'),
            'view' => \AngelitoSystems\FilamentTenancy\Resources\Tenant\RoleResource\Pages\ViewRole::route('/{record}'),
            'edit' => \AngelitoSystems\FilamentTenancy\Resources\Tenant\RoleResource\Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
