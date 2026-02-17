<?php

namespace App\Http\Controllers;

// Estas son las librerías base de Laravel
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// Si este archivo tiene el namespace mal puesto, todo falla.
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}