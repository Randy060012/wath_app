{{--
    ÉTAPE 1 — COMPOSANT ICÔNE (Lucide, vendorisé sans Node.js)
    ------------------------------------------------------------------
    Usage :  <x-icon name="printer" class="w-4 h-4" />
    Le <i data-lucide> est remplacé par un <svg> au chargement par
    lucide.createIcons() (voir public/assets/js/app.js).
    Les icônes héritent de currentColor → elles suivent le texte.
--}}
@props(['name', 'class' => 'w-4 h-4'])

<i data-lucide="{{ $name }}" {{ $attributes->merge(['class' => $class]) }}></i>
