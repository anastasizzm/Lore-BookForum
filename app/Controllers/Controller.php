<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Lib\View;

use App\Models\Error;

use App\Extensions\ResponseTemplates;

abstract class Controller
{
    protected function render(string $template, array $data = []) : Response
    {
        return Response::html(View::render($template, $data));
    } 

    protected function jsonError(Error $error, int $statusCode, string $message) : Response
    {
        return $this->jsonErrors([$error], $statusCode, $message);
    }

    protected function jsonErrors(array $errors, int $statusCode, string $message) : Response
    {
        return Response::json(ResponseTemplates::errors($errors, $statusCode, $message), $statusCode);
    }

    protected function jsonValidationErrors(array $errors) : Response
    {
        return $this->jsonErrors($errors, 422, "Can't process the input data");
    }    

    protected function jsonList(array $items, array $meta = [], int $statusCode = 200) : Response
    {
        return Response::json(ResponseTemplates::list($items, $meta), $statusCode);
    }

    protected function jsonObject(object $obj, array $meta = [], int $statusCode = 200) : Response
    {
        return Response::json(ResponseTemplates::object($obj, $meta), $statusCode);
    }

    protected function jsonEmpty(int $statusCode = 200) : Response
    {
        return Response::json([], $statusCode);
    }
} 