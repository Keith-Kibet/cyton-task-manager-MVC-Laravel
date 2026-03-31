<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        title: 'Task Management API',
        version: '1.0.0',
        description: 'Cytonn Software Engineering internship challenge: tasks, daily report, JWT (Bearer) auth, and simple RBAC (user / admin). Swagger UI: GET /api/documentation.',
    ),
    servers: [
        new OA\Server(url: 'http://127.0.0.1:8000', description: 'Local (routes are under /api/...)'),
    ],
)]
#[OA\SecurityScheme(
    securityScheme: 'jwt',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Paste the JWT from POST /api/login or /api/register (Authorize: Bearer <token>)',
)]
final class ApiSpec
{
}
