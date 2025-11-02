<?php

namespace AngelitoSystems\FilamentTenancy\Resources\TenantResource\Pages;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Resources\TenantResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            // Use the Tenancy facade to create the tenant with all the hooks
            $tenant = Tenancy::createTenant($data);

            Notification::make()
                ->title('Tenant created successfully')
                ->body("Tenant '{$tenant->name}' has been created with database '{$tenant->database_name}'.")
                ->success()
                ->send();

            return $tenant;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to create tenant')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}