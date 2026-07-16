<?php

declare(strict_types=1);

/*
 | Messages de validation par defaut (repli). La plupart des messages affiches sont
 | personnalises dans les composants Livewire ; ce fichier couvre les regles restantes
 | (max, string, file, mimes...) pour qu'aucun message anglais ne fuite en francais.
 */

return [
    'accepted' => 'Le champ :attribute doit etre accepte.',
    'active_url' => "Le champ :attribute n'est pas une URL valide.",
    'after' => 'Le champ :attribute doit etre une date posterieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit etre une date posterieure ou egale au :date.',
    'alpha' => 'Le champ :attribute ne peut contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, des chiffres et des tirets.',
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'array' => 'Le champ :attribute doit etre un tableau.',
    'before' => 'Le champ :attribute doit etre une date anterieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit etre une date anterieure ou egale au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max elements.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caracteres.',
    ],
    'boolean' => 'Le champ :attribute doit etre vrai ou faux.',
    'confirmed' => 'Le champ de confirmation :attribute ne correspond pas.',
    'date' => "Le champ :attribute n'est pas une date valide.",
    'date_equals' => 'Le champ :attribute doit etre une date egale au :date.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'different' => 'Les champs :attribute et :other doivent etre differents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'email' => 'Le champ :attribute doit etre une adresse e-mail valide.',
    'ends_with' => 'Le champ :attribute doit se terminer par l\'une des valeurs suivantes : :values.',
    'exists' => 'Le champ :attribute selectionne est invalide.',
    'file' => 'Le champ :attribute doit etre un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value elements.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre superieur a :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caracteres.',
    ],
    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value elements.',
        'file' => 'Le fichier :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre superieur ou egal a :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caracteres.',
    ],
    'image' => 'Le champ :attribute doit etre une image.',
    'in' => 'Le champ :attribute selectionne est invalide.',
    'integer' => 'Le champ :attribute doit etre un entier.',
    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value elements.',
        'file' => 'Le fichier :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre inferieur a :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caracteres.',
    ],
    'lte' => [
        'array' => 'Le champ :attribute doit contenir au plus :value elements.',
        'file' => 'Le fichier :attribute doit peser au plus :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre inferieur ou egal a :value.',
        'string' => 'Le champ :attribute doit contenir au plus :value caracteres.',
    ],
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max elements.',
        'file' => 'Le fichier :attribute ne peut pas peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut pas etre superieur a :max.',
        'string' => 'Le champ :attribute ne peut pas contenir plus de :max caracteres.',
    ],
    'mimes' => 'Le champ :attribute doit etre un fichier de type : :values.',
    'mimetypes' => 'Le champ :attribute doit etre un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min elements.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caracteres.',
    ],
    'not_in' => 'Le champ :attribute selectionne est invalide.',
    'not_regex' => "Le format du champ :attribute n'est pas valide.",
    'numeric' => 'Le champ :attribute doit etre un nombre.',
    'present' => 'Le champ :attribute doit etre present.',
    'regex' => "Le format du champ :attribute n'est pas valide.",
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_unless' => 'Le champ :attribute est obligatoire sauf si :other fait partie de :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est present.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont presents.',
    'required_without' => "Le champ :attribute est obligatoire quand :values n'est pas present.",
    'required_without_all' => "Le champ :attribute est obligatoire quand aucun de :values n'est present.",
    'same' => 'Les champs :attribute et :other doivent etre identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size elements.',
        'file' => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit etre :size.',
        'string' => 'Le champ :attribute doit contenir :size caracteres.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par l\'une des valeurs suivantes : :values.',
    'string' => 'Le champ :attribute doit etre une chaine de caracteres.',
    'unique' => 'La valeur du champ :attribute est deja utilisee.',
    'uploaded' => "Le fichier :attribute n'a pas pu etre televerse.",
    'url' => "Le champ :attribute n'est pas une URL valide.",

    /*
     | Noms lisibles des champs, injectes dans les messages ci-dessus a la place du nom technique.
     */
    'attributes' => [
        'company_name' => "nom de l'entreprise",
        'company_registration_number' => "numero d'immatriculation",
        'first_name' => 'prenom',
        'last_name' => 'nom',
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'website_url' => 'site web',
        'product_types' => 'types de produits',
        'message' => 'message',
        'document' => 'document',
    ],

    'custom' => [],
];
