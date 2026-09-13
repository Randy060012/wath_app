{{-- ============================================================
     NAVIGATION PARTAGÉE (sidebar desktop + drawer mobile)
     ------------------------------------------------------------
     Un seul point de vérité pour les liens : la vue reçoit $nav
     (tableau construit dans layouts/app.blade.php selon le rôle).
     État actif : calculé ici par request()->routeIs().
     'separator' : intertitre de section (Administration).
     'mobile_only' : entrée absente de la sidebar desktop (drawer seul).
     ============================================================ --}}

@foreach ($nav as $item)
    @if (!empty($item['separator']))
        <p class="px-3 pt-4 pb-1 text-[11px] font-semibold uppercase tracking-wider text-sky-400">
            {{ $item['label'] }}
        </p>
        @continue
    @endif

    <a href="{{ $item['url'] }}"
       @if (!empty($item['mobile_only'])) data-mobile-only @endif
       @if (($item['active'] ?? false) === true) aria-current="page" @endif
       class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium
              {{ ($item['active'] ?? false) ? 'bg-sky-700 text-white' : 'hover:bg-sky-800' }}">
        <x-icon name="{{ $item['icon'] }}" class="w-4 h-4 shrink-0" />
        {{ $item['label'] }}
    </a>
@endforeach
