<?php

declare(strict_types=1);

namespace App\Filament\Resources\Waitlist\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaitlistSignupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->label('Signed up'),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('plan_interest')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('host_referrer')
                    ->limit(32)
                    ->tooltip(fn ($record): ?string => $record->host_referrer)
                    ->placeholder('—'),
                TextColumn::make('anon_id')
                    ->limit(12)
                    ->tooltip(fn ($record): ?string => $record->anon_id)
                    ->placeholder('—')
                    ->label('Anon'),
            ])
            ->filters([
                SelectFilter::make('plan_interest')
                    ->options([
                        'pro' => 'Pro',
                    ]),
                SelectFilter::make('source')
                    ->options([
                        'landing_pricing' => 'Landing pricing',
                        'quota_429' => 'Quota 429',
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
