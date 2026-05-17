<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist;

use App\Filament\Resources\Waitlist\Pages\ListWaitlistSignups;
use App\Filament\Resources\Waitlist\Tables\WaitlistSignupsTable;
use App\Models\WaitlistSignup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WaitlistSignupResource extends Resource
{
    protected static ?string $model = WaitlistSignup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?string $navigationLabel = 'Waitlist';

    public static function table(Table $table): Table
    {
        return WaitlistSignupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlistSignups::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
