{{-- Tete de robot, pour designer un modele d'IA. La planche d'icones du kit (Heroicons) n'en a pas : celle-ci
     est dessinee dans le meme trait. La taille vient de la classe passee (h-3.5 w-3.5 par defaut). --}}
<svg {{ $attributes->merge(['class' => 'h-3.5 w-3.5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M12 3.25v2.5" />
    <circle cx="12" cy="2.5" r="0.75" />
    <rect x="4.75" y="5.75" width="14.5" height="12.5" rx="3" />
    <path d="M2.5 10.5v3M21.5 10.5v3" />
    <circle cx="9" cy="11.25" r="1.1" />
    <circle cx="15" cy="11.25" r="1.1" />
    <path d="M9.25 14.75h5.5" />
    <path d="M9 18.25v2.5M15 18.25v2.5" />
</svg>
