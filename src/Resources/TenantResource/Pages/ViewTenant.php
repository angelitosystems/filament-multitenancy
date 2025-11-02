<?php

namespace AngelitoSystems\FilamentTenancy\Resources\TenantResource\Pages;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Resources\TenantResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('visit_tenant')
                ->label('Visit Tenant')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => $this->record->getUrl())
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->isActive()),
            Actions\Action::make('run_migrations')
                ->label('Run Migrations')
                ->icon('heroicon-o-cog-6-tooth')
                ->action(function () {
                    try {
                        Tenancy::database()->runTenantMigrations($this->record);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Migrations completed')
                            ->body('Tenant migrations have been run successfully.')
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Migration failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalDescription('This will run all pending migrations for this tenant.'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Infolists\Components\Section::make('Basic Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('ID'),
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('plan')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'basic' => 'gray',
                                'premium' => 'warning',
                                'enterprise' => 'success',
                                default => 'gray',
                            })
                            ->placeholder('No plan'),
                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expires')
                            ->date()
                            ->placeholder('Never'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Domain Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('domain')
                            ->label('Custom Domain')
                            ->placeholder('Not set'),
                        Infolists\Components\TextEntry::make('subdomain')
                            ->label('Subdomain')
                            ->placeholder('Not set'),
                        Infolists\Components\TextEntry::make('full_domain')
                            ->label('Full URL')
                            ->state(fn () => $this->record->getUrl())
                            ->url(fn () => $this->record->getUrl())
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Database Configuration')
                    ->schema([
                        Infolists\Components\TextEntry::make('database_name')
                            ->label('Database Name'),
                        Infolists\Components\TextEntry::make('database_host')
                            ->label('Database Host')
                            ->placeholder('Default'),
                        Infolists\Components\TextEntry::make('database_port')
                            ->label('Database Port')
                            ->placeholder('Default'),
                        Infolists\Components\TextEntry::make('database_username')
                            ->label('Database Username')
                            ->placeholder('Default'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->label('Deleted')
                            ->dateTime()
                            ->placeholder('Not deleted'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Additional Data')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('data')
                            ->label('Custom Data')
                            ->placeholder('No additional data'),
                    ])
                    ->visible(fn () => !empty($this->record->data)),
            ]);
    }
}