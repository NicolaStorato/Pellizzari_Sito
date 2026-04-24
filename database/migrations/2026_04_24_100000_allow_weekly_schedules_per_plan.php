<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Questa migration è stata annullata.
 * up()  → no-op (non modifica nulla)
 * down() → ripristina il vincolo UNIQUE su therapy_plan_id (1 schedule per piano)
 *          così un migrate:rollback riporta il DB allo stato corretto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intenzionalmente vuoto — il codice applicativo è già stato ripristinato.
    }

    public function down(): void
    {
        // Elimina le righe duplicate tenendo solo quella con id minore per ogni piano
        DB::statement('
            DELETE tps1 FROM therapy_plan_schedules tps1
            INNER JOIN therapy_plan_schedules tps2
                ON tps1.therapy_plan_id = tps2.therapy_plan_id
                AND tps1.id > tps2.id
        ');

        Schema::table('therapy_plan_schedules', function (Blueprint $table) {
            $table->dropForeign(['therapy_plan_id']);
            $table->unique('therapy_plan_id', 'therapy_plan_id_unique');
            $table->foreign('therapy_plan_id')
                ->references('id')
                ->on('therapy_plans')
                ->cascadeOnDelete();
        });
    }
};
