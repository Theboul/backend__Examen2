<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */


    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Especifica los orígenes exactos
    'allowed_origins' => [
        'http://localhost:5173',              // Desarrollo local
        'http://localhost:3000',              // Por si usas otro puerto
        'https://gestion-academicas.vercel.app/', // Producción Vercel
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.vercel\.app$/',      // Permite todos los subdominios de Vercel
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    //Debe ser true para Sanctum
    'supports_credentials' => true,

];
