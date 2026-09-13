{{-- Utilisateur connecté + rôle (partagé sidebar / drawer) --}}
<div class="border-t border-sky-800 px-4 py-4 text-xs">
    <p class="font-semibold text-white">{{ auth()->user()?->name }}</p>
    <p class="text-sky-300">{{ auth()->user()?->getRoleNames()->implode(', ') }}</p>
</div>
