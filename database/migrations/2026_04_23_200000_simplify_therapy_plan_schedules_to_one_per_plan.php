<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Porta therapy_plan_schedules a 1 sola riga per piano terapeutico.
     *
     * - Rimuove eventuali righe duplicate lasciando solo la prima per ogni piano
     * - Rimuove la FK su therapy_plan_id (per poter droppare l'indice unique)
     * - Rimuove il vecchio unique (therapy_plan_id, scheduled_time)
     * - Aggiunge un unique su therapy_plan_id (1 orario per piano)
     * - Ricrea la FK
     */
    public function up(): void
    {
        // 1. Elimina le righe duplicate tenendo solo quella con id minore per ogni piano
        DB::statement('
            DELETE tps1 FROM therapy_plan_schedules tps1
            INNER JOIN therapy_plan_schedules tps2
                ON tps1.therapy_plan_id = tps2.therapy_plan_id
                AND tps1.id > tps2.id
        ');

        Schema::table('therapy_plan_schedules', function (Blueprint $table) {
            // 2. Rimuovi prima la foreign key (che si appoggia sull'indice unique)
            $table->dropForeign(['therapy_plan_id']);

            // 3. Ora puoi droppare il vecchio unique composito
            $table->dropUnique('therapy_plan_time_unique');

            // 4. Aggiungi unique su therapy_plan_id → 1 sola schedule per piano
            $table->unique('therapy_plan_id', 'therapy_plan_id_unique');

            // 5. Ricrea la foreign key
            $table->foreign('therapy_plan_id')
                ->references('id')
                ->on('therapy_plans')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('therapy_plan_schedules', function (Blueprint $table) {
            $table->dropForeign(['therapy_plan_id']);
            $table->dropUnique('therapy_plan_id_unique');
            $table->unique(['therapy_plan_id', 'scheduled_time'], 'therapy_plan_time_unique');
            $table->foreign('therapy_plan_id')
                ->references('id')
                ->on('therapy_plans')
                ->cascadeOnDelete();
        });
    }
};
