<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Http\Controllers\Api\ClassifyController;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnonUsage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Anon usage';

    protected static ?string $title = 'Anon usage';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.anon-usage';

    public function table(Table $table): Table
    {
        $cap = (int) config('services.anthropic.per_anon_monthly_cap', 50);
        $month = now()->format('Y-m');

        return $table
            ->query($this->getTableQuery())
            ->defaultSort('last_seen', 'desc')
            ->columns([
                TextColumn::make('anon_id')
                    ->label('Anon')
                    ->searchable()
                    ->copyable()
                    ->tooltip(fn (Event $record): string => (string) $record->anon_id),
                TextColumn::make('total_classifications')
                    ->label('Total classifications')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_seen')
                    ->label('Last seen')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('current_month_usage')
                    ->label("Used this month ({$month})")
                    ->state(fn (Event $record): string => $this->currentMonthUsage($record->anon_id).' / '.$cap)
                    ->badge()
                    ->color(fn (Event $record) => $this->currentMonthUsage($record->anon_id) >= $cap ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('resetAnonUsage')
                    ->label('Reset usage')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset monthly quota for this anon?')
                    ->modalDescription(fn (Event $record): string => "Clears the current-month classification counter for anon `{$record->anon_id}`. They'll be able to classify again immediately.")
                    ->modalSubmitActionLabel('Reset quota')
                    ->action(function (Event $record): void {
                        $month = now()->format('Y-m');
                        $key = ClassifyController::ANON_MONTHLY_SPEND_PREFIX.$record->anon_id.':'.$month;

                        Cache::forget($key);

                        Log::info('admin reset anon usage', [
                            'admin' => Auth::user()?->email,
                            'anon_id' => $record->anon_id,
                            'month' => $month,
                        ]);

                        Notification::make()
                            ->title('Quota reset')
                            ->body("Cleared {$month} counter for {$record->anon_id}.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return Event::query()
            ->selectRaw('MIN(id) as id, anon_id, COUNT(*) as total_classifications, MAX(created_at) as last_seen')
            ->where('name', 'classify.completed')
            ->whereNotNull('anon_id')
            ->groupBy('anon_id');
    }

    private function currentMonthUsage(string $anonId): int
    {
        $key = ClassifyController::ANON_MONTHLY_SPEND_PREFIX.$anonId.':'.now()->format('Y-m');

        return (int) Cache::get($key, 0);
    }
}
