<x-filament-widgets::widget>
    <x-filament::section>
        <div 
            x-data="{
                calendar: null,
                events: @js($this->getEvents()),
                init() {
                    this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                        initialView: 'dayGridMonth',
                        locale: 'es',
                        nowIndicator: true, // Línea indicadora de hora actual
                        navLinks: true,     // Clic en los días para ir a la vista diaria
                        editable: true,     // Permite Drag & Drop
                        selectable: true,
                        
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                        },
                        
                        buttonText: {
                            today: 'Hoy',
                            month: 'Mes',
                            week: 'Semana',
                            day: 'Día',
                            list: 'Agenda'
                        },
                        
                        events: this.events,
                        height: 'auto',

                        // Modal al hacer clic en un evento
                        eventClick: (info) => {
                            $wire.mountAction('editEvent', { id: info.event.id });
                        },

                        // Modal al hacer clic en un día libre
                        dateClick: (info) => {
                            $wire.mountAction('createEvent', { date: info.dateStr });
                        },

                        // Guardar nueva fecha al arrastrar el evento
                        eventDrop: (info) => {
                            $wire.updateEventDate(
                                info.event.id, 
                                info.event.startStr, 
                                info.event.endStr
                            );
                        },

                        // Guardar nueva duración al cambiar tamaño
                        eventResize: (info) => {
                            $wire.updateEventDate(
                                info.event.id, 
                                info.event.startStr, 
                                info.event.endStr
                            );
                        }
                    });
                    
                    this.calendar.render();
                }
            }"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            wire:ignore
        >
            <div x-ref="calendar" class="fc-custom-styled"></div>
        </div>

        <x-filament-actions::modals />
    </x-filament::section>

    <!-- Estilos CSS para animaciones y tarjetas modernas -->
    <style>
        .fc-event {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border-radius: 6px !important;
            padding: 2px 5px !important;
            border: none !important;
            cursor: pointer;
        }
        .fc-event:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }
        .fc-daygrid-day:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }
        .fc-highlight {
            background-color: rgba(59, 130, 246, 0.15) !important;
        }
    </style>
</x-filament-widgets::widget>