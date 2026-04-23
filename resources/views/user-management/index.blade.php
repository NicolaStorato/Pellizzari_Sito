@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header">Registra Nuovo Utente</div>
        <form method="POST" action="{{ route('user-management.store') }}" class="panel-body space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700" for="role">Ruolo</label>
                    <select class="form-input" id="role" name="role" required>
                        @if ($canCreateDoctor)
                            <option value="{{ \App\UserRole::Doctor->value }}" @selected(old('role') === \App\UserRole::Doctor->value)>Dottore</option>
                        @endif
                        <option value="{{ \App\UserRole::Caregiver->value }}" @selected(old('role', \App\UserRole::Caregiver->value) === \App\UserRole::Caregiver->value)>Familiare</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="name">Nome completo</label>
                    <input class="form-input" type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Email</label>
                    <input class="form-input" type="email" id="email" name="email" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="phone">Telefono</label>
                    <input class="form-input" type="text" id="phone" name="phone" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="date_of_birth">Data di nascita</label>
                    <input class="form-input" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="address">Indirizzo</label>
                    <input class="form-input" type="text" id="address" name="address" value="{{ old('address') }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password">Password</label>
                    <input class="form-input" type="password" id="password" name="password" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password_confirmation">Conferma password</label>
                    <input class="form-input" type="password" id="password_confirmation" name="password_confirmation" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="is_active">Stato account</label>
                    <select class="form-input" id="is_active" name="is_active">
                        <option value="1" @selected(old('is_active', '1') === '1')>Attivo</option>
                        <option value="0" @selected(old('is_active') === '0')>Disattivo</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Registra Utente</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">Utenti Registrati (Dottori e Familiari)</div>
        <div class="panel-body">
            <p class="mb-4 text-sm text-slate-600">
                Per modificare un utente gia esistente o reimpostare la sua password, usa il pulsante dedicato nella colonna azioni.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="pb-2">Nome</th>
                            <th class="pb-2">Email</th>
                            <th class="pb-2">Ruolo</th>
                            <th class="pb-2">Telefono</th>
                            <th class="pb-2">Stato</th>
                            <th class="pb-2">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($managedUsers as $user)
                            <tr>
                                <td class="py-2 font-medium text-slate-900">{{ $user->name }}</td>
                                <td class="py-2">{{ $user->email }}</td>
                                <td class="py-2">{{ $user->role?->value }}</td>
                                <td class="py-2">{{ $user->phone ?: '-' }}</td>
                                <td class="py-2">
                                    @if ($user->is_active)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Attivo</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-600">Disattivo</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <a href="{{ route('user-management.edit', $user) }}#password" class="btn-secondary btn-sm">Modifica / Password</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500">Nessun utente registrato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $managedUsers->links() }}</div>
        </div>
    </section>
@endsection
