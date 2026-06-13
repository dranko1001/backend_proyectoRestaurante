<?php

return [

    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',

    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],

    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'array' => 'El campo :attribute no puede tener más de :max elementos.',
    ],

    'unique' => 'El valor de :attribute ya está registrado.',

    'in' => 'El valor de :attribute no es válido.',

    'attributes' => [
        'nombre' => 'nombre',
        'apellido' => 'apellido',
        'cedula' => 'cédula',
        'telefono' => 'teléfono',
        'correo' => 'correo electrónico',
        'password' => 'contraseña',
        'activo' => 'estado activo',
        'rol' => 'rol',
    ],

];
