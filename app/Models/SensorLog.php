<?php

namespace App\Models;

use Database\Factories\SensorLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorLog extends Model
{
    /** @use HasFactory<SensorLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dispenser_id',
        'patient_id',
        'temperature',
        'humidity',
        'threshold_exceeded',
        'threshold_violations',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'threshold_exceeded' => 'boolean',
            'threshold_violations' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(Dispenser::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
