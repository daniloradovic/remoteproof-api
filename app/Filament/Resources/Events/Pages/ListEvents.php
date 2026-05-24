<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Http\Controllers\Api\ClassifyController;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearUrlCache')
                ->label('Clear cache for URL')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->modalHeading('Clear cached classification for a URL')
                ->modalDescription('Removes the 24h cached verdict so the next request re-classifies. Useful after prompt tweaks.')
                ->modalSubmitActionLabel('Clear cache')
                ->schema([
                    TextInput::make('url')
                        ->label('Job URL')
                        ->url()
                        ->required()
                        ->placeholder('https://www.linkedin.com/jobs/view/...'),
                ])
                ->action(function (array $data): void {
                    $url = $data['url'];
                    $key = ClassifyController::CACHE_KEY_PREFIX.sha1($url);

                    $existed = Cache::pull($key) !== null;

                    Log::info('admin clear url cache', [
                        'admin' => Auth::user()?->email,
                        'url' => $url,
                        'hit' => $existed,
                    ]);

                    Notification::make()
                        ->title($existed ? 'Cache cleared' : 'No cache entry found')
                        ->body($existed ? 'Next request for that URL will re-classify.' : 'That URL had no cached verdict (already expired or never classified).')
                        ->success()
                        ->send();
                }),
        ];
    }
}
