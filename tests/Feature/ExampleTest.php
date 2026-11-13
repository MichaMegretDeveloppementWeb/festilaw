<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

test('the application root serves the home page', function () {
    $this->get('/')->assertOk();
});
