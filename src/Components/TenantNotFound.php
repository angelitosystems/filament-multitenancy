<?php

namespace AngelitoSystems\FilamentTenancy\Components;

use Livewire\Component;

class TenantNotFound extends Component
{
    public string $host;
    public ?string $resolver;
    public ?string $appDomain;

    public function mount()
    {
        $this->host = request()->getHost();
        $this->resolver = config('filament-tenancy.resolver', 'domain');
        $this->appDomain = env('APP_DOMAIN');
    }

    public function render()
    {
        return view('filament-tenancy::errors.tenant-not-found', [
            'host' => $this->host,
            'resolver' => $this->resolver,
            'appDomain' => $this->appDomain,
        ]);
    }
}

