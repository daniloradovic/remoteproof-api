<?php

declare(strict_types=1);

namespace App\Filament\Resources\Events\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ]);
    }
}
