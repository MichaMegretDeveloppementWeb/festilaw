<?php

declare(strict_types=1);

/*
 | Mensajes de validacion por defecto (respaldo). La mayoria de los mensajes mostrados
 | estan personalizados en los componentes Livewire; este archivo cubre las reglas
 | restantes (max, string, file, mimes...) para que ningun mensaje en ingles se filtre.
 */

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'active_url' => 'El campo :attribute no es una URL valida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, numeros y guiones.',
    'alpha_num' => 'El campo :attribute solo puede contener letras y numeros.',
    'array' => 'El campo :attribute debe ser una lista.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe contener entre :min y :max elementos.',
        'file' => 'El archivo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'date' => 'El campo :attribute no es una fecha valida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute no coincide con el formato :format.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits digitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max digitos.',
    'email' => 'El campo :attribute debe ser una direccion de correo valida.',
    'ends_with' => 'El campo :attribute debe terminar con uno de los siguientes valores: :values.',
    'exists' => 'El campo :attribute seleccionado no es valido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute debe tener un valor.',
    'gt' => [
        'array' => 'El campo :attribute debe contener mas de :value elementos.',
        'file' => 'El archivo :attribute debe pesar mas de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener mas de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe contener al menos :value elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener al menos :value caracteres.',
    ],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El campo :attribute seleccionado no es valido.',
    'integer' => 'El campo :attribute debe ser un numero entero.',
    'lt' => [
        'array' => 'El campo :attribute debe contener menos de :value elementos.',
        'file' => 'El archivo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute debe contener como maximo :value elementos.',
        'file' => 'El archivo :attribute debe pesar como maximo :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener como maximo :value caracteres.',
    ],
    'max' => [
        'array' => 'El campo :attribute no puede contener mas de :max elementos.',
        'file' => 'El archivo :attribute no puede pesar mas de :max kilobytes.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'string' => 'El campo :attribute no puede tener mas de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe contener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => 'El campo :attribute seleccionado no es valido.',
    'not_regex' => 'El formato del campo :attribute no es valido.',
    'numeric' => 'El campo :attribute debe ser un numero.',
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato del campo :attribute no es valido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_unless' => 'El campo :attribute es obligatorio a menos que :other este en :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values esta presente.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando :values estan presentes.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no esta presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de :values esta presente.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El archivo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe comenzar con uno de los siguientes valores: :values.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'unique' => 'El valor del campo :attribute ya esta en uso.',
    'uploaded' => 'El archivo :attribute no se pudo subir.',
    'url' => 'El campo :attribute no es una URL valida.',

    /*
     | Nombres legibles de los campos, inyectados en los mensajes anteriores.
     */
    'attributes' => [
        'company_name' => 'nombre de la empresa',
        'company_registration_number' => 'numero de registro',
        'first_name' => 'nombre',
        'last_name' => 'apellidos',
        'name' => 'nombre',
        'email' => 'correo electronico',
        'website_url' => 'sitio web',
        'product_types' => 'tipos de productos',
        'message' => 'mensaje',
        'document' => 'documento',
    ],

    'custom' => [],
];
