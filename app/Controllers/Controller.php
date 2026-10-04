<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Lib\View;

use App\Models\Error;

use App\Extensions\ResponseTemplates;

abstract class Controller
{
    // WEB
    protected function render(string $template, array $data = []) : Response
    {
        return Response::html(View::render($template, $data));
    } 

    protected function renderNotFound(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 404, 'message' => 'Not Found', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle]);
    }

    protected function renderForbid(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 403, 'message' => 'You dont have access to this', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle]);
    }

    protected function renderUnauthorized(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 401, 'message' => 'Please authenticate first', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle]);
    }


    // JSON
    protected function jsonError(Error $error, int $statusCode) : Response
    {
        return Response::json(ResponseTemplates::error($error), $statusCode);
    }

    protected function jsonList(array $items, array $meta = [], int $statusCode = 200) : Response
    {
        return Response::json(ResponseTemplates::list($items, $meta), $statusCode);
    }

    protected function jsonObject(object $obj, int $statusCode = 200) : Response
    {
        return Response::json(ResponseTemplates::object($obj), $statusCode);
    }

    protected function jsonCreatedId(mixed $id, int $statusCode = 201) : Response
    {
        return Response::json(['createdId' => $id], $statusCode);
    }

    protected function jsonEmpty(int $statusCode = 200) : Response
    {
        return Response::json((object)[], $statusCode);
    }
} 