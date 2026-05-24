<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Tables;

use App\Http\Controllers\Api\ClassifyController;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->label('Time'),
                TextColumn::make('name')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('verdict')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'WORLDWIDE' => 'success',
                        'RESTRICTED' => 'danger',
                        'UNCLEAR' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('host')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('cached')
                    ->boolean(),
                TextColumn::make('latency_ms')
                    ->numeric()
                    ->suffix(' ms')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('anon_id')
                    ->limit(12)
                    ->tooltip(fn ($record): ?string => $record->anon_id)
                    ->label('Anon'),
            ])
            ->filters([
                SelectFilter::make('verdict')
                    ->options([
                        'WORLDWIDE' => 'Worldwide',
                        'RESTRICTED' => 'Restricted',
                        'UNCLEAR' => 'Unclear',
                    ]),
                SelectFilter::make('name')
                    ->options(fn (): array => [
                        'classify.completed' => 'classify.completed',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
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
}
