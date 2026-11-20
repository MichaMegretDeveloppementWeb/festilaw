{{-- Le gabarit des ecrans de Falcon Analytics (Audience et Marketing), nomme dans config/analytics.php
     (`layouts.admin`). Le package demande un composant Blade qui recoit `title` et rend `$slot` ; notre mise
     en page est une vue Livewire, que ce composant reprend telle quelle. Surface pleine : aucune colonne ni
     marge autour du contenu, car chaque ecran du package porte sa propre boite (docs/installation.md § 5). --}}
@props(['title' => null])

@include('layouts.admin', ['title' => $title, 'surface' => 'full', 'slot' => $slot])
