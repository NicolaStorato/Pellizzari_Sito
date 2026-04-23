<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDispenserRequest;
use App\Http\Requests\UpdateDispenserRequest;
use App\Models\Dispenser;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DispenserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $dispensers = Dispenser::query()
            ->with('patient:id,name')
            ->latest()
            ->paginate(15);

        return view('dispensers.index', [
            'dispensers' => $dispensers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('dispensers.create', [
            'patients' => $this->selectablePatients(request()->user()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDispenserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->guardPatientAccess((int) $validated['patient_id'], $request->user());

        $dispenser = Dispenser::query()->create([
            ...$validated,
            'api_token' => $validated['api_token'] ?? Str::random(40),
        ]);

        return redirect()
            ->route('dispensers.show', $dispenser)
            ->with('status', 'Dispenser creato.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Dispenser $dispenser): View
    {
        $dispenser->load([
            'patient:id,name',
            'sensorLogs' => function ($query): void {
                $query->latest('recorded_at')->limit(20);
            },
            'alerts' => function ($query): void {
                $query->latest('triggered_at')->limit(10);
            },
        ]);

        return view('dispensers.show', [
            'dispenser' => $dispenser,
            'mqttCommandTemplates' => $this->mqttCommandTemplates(),
            'mqttCommandTopicBase' => $dispenser->mqtt_base_topic ?: $this->defaultMqttTopicBase($dispenser->device_uid),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dispenser $dispenser): View
    {
        return view('dispensers.edit', [
            'dispenser' => $dispenser,
            'patients' => $this->selectablePatients(request()->user()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDispenserRequest $request, Dispenser $dispenser): RedirectResponse
    {
        $validated = $request->validated();

        $this->guardPatientAccess((int) $validated['patient_id'], $request->user());

        $dispenser->update([
            ...$validated,
            'api_token' => $validated['api_token'] ?: $dispenser->api_token,
        ]);

        return redirect()
            ->route('dispensers.show', $dispenser)
            ->with('status', 'Dispenser aggiornato.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dispenser $dispenser): RedirectResponse
    {
        $dispenser->delete();

        return redirect()
            ->route('dispensers.index')
            ->with('status', 'Dispenser eliminato.');
    }

    /**
     * @return Collection<int, User>
     */
    private function selectablePatients(User $user): Collection
    {
        if ($user->hasRole(UserRole::Admin)) {
            return User::query()->patients()->orderBy('name')->get(['id', 'name']);
        }

        return $user->assignedPatients()
            ->where('users.role', UserRole::Patient->value)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);
    }

    private function guardPatientAccess(int $patientId, User $user): void
    {
        if ($user->hasRole(UserRole::Admin)) {
            return;
        }

        $allowed = $user->assignedPatients()
            ->where('users.id', $patientId)
            ->exists();

        abort_if(! $allowed, 403);
    }

    /**
     * @return array<int, array{command:string,label:string,description:string,payload:array<string, mixed>}>
     */
    private function mqttCommandTemplates(): array
    {
        $configuredTemplates = config('services.mqtt.commands', []);

        if (! is_array($configuredTemplates)) {
            return [];
        }

        return collect($configuredTemplates)
            ->filter(static fn (mixed $template, mixed $command): bool => is_array($template) && is_string($command))
            ->map(static function (array $template, string $command): array {
                $payload = $template['payload'] ?? [];

                return [
                    'command' => $command,
                    'label' => (string) ($template['label'] ?? Str::headline(str_replace('_', ' ', $command))),
                    'description' => (string) ($template['description'] ?? ''),
                    'payload' => is_array($payload) ? $payload : [],
                ];
            })
            ->values()
            ->all();
    }

    private function defaultMqttTopicBase(string $deviceUid): string
    {
        $topicRoot = trim((string) config('services.mqtt.topic_root', 'smart-dispenser'), '/');

        return $topicRoot.'/'.$deviceUid;
    }
}
