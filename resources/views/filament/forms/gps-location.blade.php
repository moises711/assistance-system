<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{
        location: $wire.$entangle('{{ $getStatePath() }}'), busy: false, error: '',
        capture() {
            this.busy = true; this.error = ''; this.location = null;
            if (!navigator.geolocation) { this.error = 'El navegador no permite obtener ubicación.'; this.busy = false; return; }
            navigator.geolocation.getCurrentPosition(p => {
                this.busy = false;
                if (p.coords.accuracy > 30) { this.error = 'Ubicación imprecisa. Activa ubicación precisa y vuelve a capturar.'; return; }
                this.location = {latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy, captured_at: new Date(p.timestamp).toISOString(), is_mocked: false};
            }, () => { this.busy = false; this.error = 'No se obtuvo ubicación. Permite el acceso y activa el GPS. Se requiere HTTPS.'; },
            {enableHighAccuracy: true, maximumAge: 0, timeout: 20000});
        }
    }" @if (($field->getExtraAttributes()['data-auto-capture'] ?? false)) x-init="capture()" @endif>
        <x-filament::button type="button" x-on:click="capture()" x-bind:disabled="busy">Obtener ubicación GPS</x-filament::button>
        <p x-show="busy">Obteniendo ubicación…</p>
        <p x-show="error" x-text="error" role="alert"></p>
        <p x-show="location" x-text="location ? 'Ubicación obtenida. Precisión: ' + Math.round(location.accuracy) + ' metros.' : ''"></p>
        <p>Solo se captura al marcar o configurar la sede; no se rastrea en segundo plano.</p>
    </div>
</x-dynamic-component>
