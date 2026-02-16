<?php

return [

    'secret' => env('JWT_SECRET'),

    'algorithm' => env('JWT_ALGORITHM', 'HS256'),

    // Minutos
    'access_expiration' => env('JWT_ACCESS_EXP', 15),
    'refresh_expiration' => env('JWT_REFRESH_EXP', 10080), // 7 días
];
