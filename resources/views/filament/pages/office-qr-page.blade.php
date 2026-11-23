<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center p-8 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Escanea para registrar asistencia</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Oficina Central - Codexasis</p>
        
        <div class="p-4 bg-white rounded-lg border-2 border-dashed border-gray-300">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=HQ-OFICINA-CENTRAL-2026&margin=20" 
                 alt="QR Oficina" 
                 class="w-64 h-64 object-contain">
        </div>
        
        <p class="text-xs text-gray-400 mt-4">Usa el botón "Descargar QR para Imprimir" en la parte superior para obtener la versión en alta resolución (800x800).</p>
    </div>
</x-filament-panels::page>