<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispenser;
use App\Models\TherapyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DevicePlanController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var Dispenser $dispenser */
        $dispenser = request()->attributes->get('dispenser');

        $plans = TherapyPlan::query()
            ->where('patient_id', $dispenser->patient_id)
            ->where('is_active', true)
            ->whereDate('starts_on', '<=', now())
            ->where(static function ($query): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', now());
            })
            ->with([
                'medicine:id,name,description,image_url',
                'schedules:id,therapy_plan_id,scheduled_time,timezone',
            ])
            ->get()
            ->map(static function (TherapyPlan $plan): array {
                // Prende il singolo orario del piano (max 1 schedule)
                $schedule = $plan->schedules->first();
                $timeStr = $schedule ? substr((string) $schedule->scheduled_time, 0, 5) : null;

                // Genera le occorrenze dei prossimi 7 giorni (oggi incluso)
                // filtrate in base a starts_on e ends_on
                $weekOccurrences = [];
                if ($timeStr !== null) {
                    for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                        $date = Carbon::today()->addDays($dayOffset);

                        // Rispetta il range della terapia
                        if ($plan->starts_on && $date->lt($plan->starts_on->startOfDay())) {
                            continue;
                        }
                        if ($plan->ends_on && $date->gt($plan->ends_on->endOfDay())) {
                            continue;
                        }

                        $weekOccurrences[] = [
                            'date' => $date->toDateString(),   // "YYYY-MM-DD"
                            'time' => $timeStr,                // "HH:MM"
                            'datetime' => $date->toDateString() . 'T' . $timeStr . ':00',
                        ];
                    }
                }

                return [
                    'id'           => $plan->id,
                    'medicine'     => [
                        'id'          => $plan->medicine->id,
                        'name'        => $plan->medicine->name,
                        'description' => $plan->medicine->description,
                        'image_url'   => $plan->medicine->image_url,
                    ],
                    'dose_amount'       => $plan->dose_amount,
                    'dose_unit'         => $plan->dose_unit,
                    'instructions'      => $plan->instructions,
                    'starts_on'         => $plan->starts_on?->toDateString(),
                    'ends_on'           => $plan->ends_on?->toDateString(),
                    'daily_time'        => $timeStr,
                    'week_occurrences'  => $weekOccurrences,
                ];
            })
            ->values();

        return response()->json([
            'device_uid'   => $dispenser->device_uid,
            'patient_id'   => $dispenser->patient_id,
            'plans'        => $plans,
            'week_start'   => Carbon::today()->toDateString(),
            'week_end'     => Carbon::today()->addDays(6)->toDateString(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
