<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Enums\ScheduleType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->gap(true)
            ->components([
                TextEntry::make('name'),
                TextEntry::make('last_name')
                    ->placeholder('-'),
                TextEntry::make('dni'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('schedule.name')
                    ->label('Horario')
                    ->placeholder('-'),
                TextEntry::make('schedule.type')
                    ->label('Tipo de horario')
                    ->formatStateUsing(fn (ScheduleType|string|null $state): string => $state instanceof ScheduleType
                        ? $state->label()
                        : ScheduleType::tryFrom((string) $state)?->label() ?? (string) ($state ?? '-'))
                    ->placeholder('-'),
                TextEntry::make('schedule.start_time')
                    ->label('Entrada')
                    ->time('H:i')
                    ->placeholder('-'),
                TextEntry::make('schedule.end_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->placeholder('-'),
                TextEntry::make('schedule.working_days')
                    ->label('Días laborables')
                    ->formatStateUsing(fn (array|string|null $state): string => is_array($state)
                        ? implode(', ', array_map(static fn (int|string $day): string => [
                            1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
                            5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
                        ][(int) $day] ?? (string) $day, $state))
                        : (string) ($state ?? '-'))
                    ->placeholder('-'),
                TextEntry::make('schedule.tolerance_minutes')
                    ->label('Tolerancia')
                    ->suffix(' minutos')
                    ->placeholder('-'),
                TextEntry::make('qr_code')
                    ->label('Código QR')
                    ->copyable()
                    ->placeholder('-'),
                TextEntry::make('qr_code_image')
                    ->label('Imagen QR')
                    ->state(fn (User $record): ?HtmlString => $record->qrCodeDataUri()
                        ? new HtmlString(sprintf(
                            '<img src="%s" alt="Código QR de %s" width="240" height="240">',
                            $record->qrCodeDataUri(),
                            e($record->name),
                        ))
                        : null)
                    ->html()
                    ->placeholder('-'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
