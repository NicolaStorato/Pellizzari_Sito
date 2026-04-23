@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header">Modifica Utente</div>

        <form method="POST" action="{{ route('user-management.update', $managedUser) }}" class="panel-body space-y-4">
            @csrf
            @method('PATCH')

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @if ($canCreateDoctor)
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="role">Ruolo</label>
                        <select class="form-input" id="role" name="role">
                            <option value="{{ \App\UserRole::Doctor->value }}" @selected(old('role', $managedUser->role?->value) === \App\UserRole::Doctor->value)>Dottore</option>
                            <option value="{{ \App\UserRole::Caregiver->value }}" @selected(old('role', $managedUser->role?->value) === \App\UserRole::Caregiver->value)>Familiare</option>
                        </select>
                    </div>
                @else
                    <input type="hidden" name="role" value="{{ $managedUser->role?->value }}">
                @endif

                <div>
                    <label class="text-sm font-medium text-slate-700" for="name">Nome completo</label>
                    <input class="form-input" type="text" id="name" name="name" value="{{ old('name', $managedUser->name) }}" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="email">Email</label>
                    <input class="form-input" type="email" id="email" name="email" value="{{ old('email', $managedUser->email) }}" required>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="phone">Telefono</label>
                    <input class="form-input" type="text" id="phone" name="phone" value="{{ old('phone', $managedUser->phone) }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="date_of_birth">Data di nascita</label>
                    <input class="form-input" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', optional($managedUser->date_of_birth)->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="address">Indirizzo</label>
                    <input class="form-input" type="text" id="address" name="address" value="{{ old('address', $managedUser->address) }}">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password">Nuova password (lascia vuoto per mantenere quella attuale)</label>
                    <input class="form-input" type="password" id="password" name="password">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="password_confirmation">Conferma password</label>
                    <input class="form-input" type="password" id="password_confirmation" name="password_confirmation">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700" for="is_active">Stato account</label>
                    <select class="form-input" id="is_active" name="is_active">
                        <option value="1" @selected(old('is_active', $managedUser->is_active ? '1' : '0') === '1')>Attivo</option>
                        <option value="0" @selected(old('is_active', $managedUser->is_active ? '1' : '0') === '0')>Disattivo</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('user-management.index') }}" class="btn-secondary">Annulla</a>
                <button type="submit" class="btn-primary">Salva modifiche</button>
            </div>
        </form>
    </section>
@endsection
