<?php

namespace AngelitoSystems\FilamentTenancy\Resources;

use AngelitoSystems\FilamentTenancy\Models\Permission;
use AngelitoSystems\FilamentTenancy\Traits\HasSimpleTranslations;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PermissionResource extends Resource
{
    use HasSimpleTranslations;

    protected static ?string $model = Permission::class;

    protected static ?string $slug = 'permissions';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Override translation keys
     */
    public static function getNavigationKey(): string
    {
        return 'permissions';
    }

    public static function getModelKey(): string
    {
        return 'permission';
    }

    public static function getPluralModelKey(): string
    {
        return 'permissions';
    }

    public static function getBreadcrumbKey(): string
    {
        return 'permissions';
    }

    public static function getNavigationGroupKey(): ?string
    {
        return 'admin_management';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('tenancy.permission_information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('tenancy.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, Set $set) {
                                $set('slug', \Illuminate\Support\Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label(__('tenancy.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->readonly(),

                        Textarea::make('description')
                            ->label(__('tenancy.description'))
                            ->rows(3),

                        Select::make('group')
                            ->label(__('tenancy.group'))
                            ->options([
                                'users' => 'Users',
                                'roles' => 'Roles',
                                'tenants' => 'Tenants',
                                'subscriptions' => 'Subscriptions',
                                'billing' => 'Billing',
                                'reports' => 'Reports',
                                'settings' => 'Settings',
                                'system' => 'System',
                            ])
                            ->required()
                            ->default('users')
                            ->helperText(__('tenancy.group_helper')),

                        ColorPicker::make('color')
                            ->label(__('tenancy.color'))
                            ->helperText(__('tenancy.color_helper')),
                    ])
                    ->columns(2),

                Section::make(__('tenancy.additional_settings'))
                    ->schema([
                        Toggle::make('is_system')
                            ->label(__('tenancy.system_permission'))
                            ->default(false)
                            ->helperText(__('tenancy.system_permission_helper')),

                        Toggle::make('is_active')
                            ->label(__('tenancy.is_active'))
                            ->default(true)
                            ->helperText(__('tenancy.permission_active_helper')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),

                TextColumn::make('group')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'users' => 'blue',
                        'roles' => 'green',
                        'tenants' => 'purple',
                        'subscriptions' => 'yellow',
                        'billing' => 'orange',
                        'reports' => 'pink',
                        'settings' => 'gray',
                        'system' => 'red',
                    }),

                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),

                ColorColumn::make('color')
                    ->label('Color'),

                IconColumn::make('is_system')
                    ->boolean()
                    ->label('System'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->options([
                        'users' => 'Users',
                        'roles' => 'Roles',
                        'tenants' => 'Tenants',
                        'subscriptions' => 'Subscriptions',
                        'billing' => 'Billing',
                        'reports' => 'Reports',
                        'settings' => 'Settings',
                        'system' => 'System',
                    ]),

                TernaryFilter::make('is_system')
                    ->label('System')
                    ->placeholder('All permissions')
                    ->trueLabel('System permissions')
                    ->falseLabel('Custom permissions'),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All permissions')
                    ->trueLabel('Active permissions')
                    ->falseLabel('Inactive permissions'),

                Filter::make('has_roles')
                    ->query(fn($query) => $query->has('roles'))
                    ->label('Has Roles'),

                Filter::make('no_roles')
                    ->query(fn($query) => $query->doesntHave('roles'))
                    ->label('No Roles'),

                Filter::make('has_users')
                    ->query(fn($query) => $query->has('users'))
                    ->label('Has Users'),

                Filter::make('no_users')
                    ->query(fn($query) => $query->doesntHave('users'))
                    ->label('No Users'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->hidden(fn($record) => $record->is_system),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->hidden(fn($records) => $records->contains('is_system', true)),
                ]),
            ])
            ->defaultSort('group', 'asc')
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
            'index' => \AngelitoSystems\FilamentTenancy\Resources\PermissionResource\Pages\ListPermissions::route('/'),
            'create' => \AngelitoSystems\FilamentTenancy\Resources\PermissionResource\Pages\CreatePermission::route('/create'),
            'view' => \AngelitoSystems\FilamentTenancy\Resources\PermissionResource\Pages\ViewPermission::route('/{record}'),
            'edit' => \AngelitoSystems\FilamentTenancy\Resources\PermissionResource\Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
