<?php

return [

    'secret' => env('JWT_SECRET'),

    'ttl' => (int) env('JWT_TTL', 86_400),

    'algo' => env('JWT_ALGO', 'HS256'),

];
