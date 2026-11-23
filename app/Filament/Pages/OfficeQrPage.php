<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OfficeQrPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'QR de Oficina';

    protected static ?string $title = 'Cartel QR de Asistencia - Oficina';

    protected string $view = 'filament.pages.office-qr-page'; // <-- Sin 'static'

    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargarQr')
                ->label('Descargar QR para Imprimir (PNG)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $officeToken = 'HQ-OFICINA-CENTRAL-2026';
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=800x800&data={$officeToken}&margin=20";

                    $imageContent = @file_get_contents($qrUrl);

                    if ($imageContent === false) {
                        Notification::make()
                            ->title('Error')
                            ->body('No se pudo generar la imagen del QR.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    return response()->streamDownload(function () use ($imageContent) {
                        echo $imageContent;
                    }, 'qr-oficina-central.png', [
                        'Content-Type' => 'image/png',
                    ]);
                }),
        ];
    }
}
