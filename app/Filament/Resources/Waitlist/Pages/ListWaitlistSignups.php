<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Pages;

use App\Filament\Resources\Waitlist\WaitlistSignupResource;
use Filament\Resources\Pages\ListRecords;

class ListWaitlistSignups extends ListRecords
{
    protected static string $resource = WaitlistSignupResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
