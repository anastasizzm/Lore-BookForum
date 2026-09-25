<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Lib\View;

abstract class Controller
{
    protected function render(string $template, array $data = []) : Response
    {
        return Response::html(View::render($template, $data));
    } 
} 