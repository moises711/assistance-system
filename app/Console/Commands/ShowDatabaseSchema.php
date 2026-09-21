<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ShowDatabaseSchema extends Command
{
    protected $signature = 'db:show-schema';
    protected $description = 'Muestra todas las tablas y sus respectivos campos en la consola.';

    public function handle()
    {
        $tables = Schema::getTableListing();

        if (empty($tables)) {
            $this->warn('No se encontraron tablas en la base de datos.');
            return Command::FAILURE;
        }

        $this->info("=== ESTRUCTURA DE LA BASE DE DATOS ===");

        foreach ($tables as $table) {
            // Remueve el prefijo del nombre de la base de datos si viene incluido
            $tableName = last(explode('.', $table));

            $this->line('');
            $this->info("Tabla: <comment>{$tableName}</comment>");

            // Obtiene directamente los nombres de las columnas usando Schema
            $columns = Schema::getColumnListing($tableName);

            $headers = ['Campo', 'Tipo de Dato'];
            $data = [];

            foreach ($columns as $column) {
                // Obtiene el tipo de dato de cada columna
                $type = Schema::getColumnType($tableName, $column);
                $data[] = [$column, $type];
            }

            $this->table($headers, $data);
        }

        return Command::SUCCESS;
    }
}