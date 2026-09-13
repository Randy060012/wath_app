{{-- Logo + nom de l'enseigne (partagé sidebar desktop / drawer mobile) --}}
<div class="flex items-center gap-3 px-6 py-5">
    <div class="grid h-10 w-10 place-items-center rounded-xl bg-sky-700 text-white">
        <x-icon name="droplets" class="w-5 h-5" />
    </div>
    <div>
        <p class="font-bold leading-tight text-white">{{ config('app.name', 'Pressing Pro') }}</p>
        <p class="text-xs text-sky-300">Gestion pressing & blanchisserie</p>
    </div>
</div>
