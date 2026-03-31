<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base HTTP controller for the API.
 *
 * {@see AuthorizesRequests} wires $this->authorize() to Laravel’s Gate so policies
 * (e.g. {@see \App\Policies\TaskPolicy}) receive the authenticated user. JWT middleware
 * must set {@see \Illuminate\Support\Facades\Auth::setUser()} so Gate::authorize() works.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
