{{-- Utilisateur connecté + rôle + AGENCE (partagé sidebar / drawer) --}}
@php
    $user = auth()->user();
@endphp
<div class="border-t border-sky-800 px-4 py-4 text-xs">
    <p class="font-semibold text-white">{{ $user?->name }}</p>
    <p class="text-sky-300">{{ $user?->getRoleNames()->implode(', ') }}</p>

    @if ($user !== null)
        {{-- MULTI-TENANT : contexte courant ($currentAgency partagé par EnsureAgencyContext) --}}
        @if ($user->isSuperAdmin())
            @php $agencyOptions = \App\Models\Agency::query()->orderBy('name')->get(['id', 'name', 'code']); @endphp
            <form method="POST" action="{{ route('admin.agency.switch') }}" class="mt-2">
                @csrf
                <label class="sr-only" for="agency-context-select">Agence de travail</label>
                <select id="agency-context-select" name="agency_id" class="input input-sm !w-full !bg-sky-950 !text-sky-100 text-xs"
                        onchange="this.form.submit()">
                    <option value="">Vue GROUPE — toutes les agences</option>
                    @foreach ($agencyOptions as $option)
                        <option value="{{ $option->id }}" @selected(($currentAgency?->id ?? null) === $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @elseif ($user->managesGroup())
            @php $ownedAgencies = $user->ownedAgencies()->orderBy('name')->get(['id', 'name', 'code']); @endphp
            @if ($ownedAgencies->count() > 1)
                {{-- PROPRIÉTAIRE multi-agences : bascule entre SES agences --}}
                <form method="POST" action="{{ route('admin.agency.switch') }}" class="mt-2">
                    @csrf
                    <label class="sr-only" for="agency-context-select">Agence de travail</label>
                    <select id="agency-context-select" name="agency_id" class="input input-sm !w-full !bg-sky-950 !text-sky-100 text-xs"
                            onchange="this.form.submit()">
                        @foreach ($ownedAgencies as $option)
                            <option value="{{ $option->id }}" @selected(($currentAgency?->id ?? null) === $option->id)>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @elseif ($currentAgency !== null)
                <p class="mt-1 flex items-center gap-1 text-sky-200">
                    <x-icon name="building-2" class="w-3.5 h-3.5" />
                    {{ $currentAgency->name }}
                </p>
            @endif
        @elseif ($user->agency !== null)
            <p class="mt-1 flex items-center gap-1 text-sky-200">
                <x-icon name="building-2" class="w-3.5 h-3.5" />
                {{ $user->agency->name }}
            </p>
        @endif
    @endif
</div>
