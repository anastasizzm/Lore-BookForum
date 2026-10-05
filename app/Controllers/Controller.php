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
    protected function render(string $template, array $data = [], int $statusCode = 200) : Response
    {
        return Response::html(View::render($template, $data), $statusCode);
    } 

    protected function renderNotFound(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 404, 'message' => 'Not Found', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle], 404);
    }

    protected function renderForbid(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 403, 'message' => 'You dont have access to this', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle], 403);
    }

    protected function renderUnauthorized(?string $actionUrl = null, ?string $actionTitle = null)
    {
        return $this->render('message', ['statusCode' => 401, 'message' => 'Please authenticate first', 'actionUrl' => $actionUrl, 'actionTitle' => $actionTitle], 401);
    }


    // JSON
    protected function jsonError(Error $error, int $statusCode) : Response
    {
        return Response::json(ResponseTemplates::error($error), $statusCode);
    }

    protected function jsonList(array $items, array $meta = [], int $statusCode = 200) : Response
    {
        $list = array_map(
                [self::class, 'serializeItem'],
                is_array($items) ? $items : iterator_to_array($items),
            );
        return Response::json(ResponseTemplates::list($list, $meta), $statusCode);
    }

    private static function serializeItem(mixed $item): mixed
    {
        if ($item instanceof WithContext) {
            return $item->toArray();
        }

        if ($item instanceof JsonSerializable) {
            return $item->jsonSerialize();
        }

        if (method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return $item;
    }   

    protected function jsonObject(object $obj, int $statusCode = 200) : Response
    {
        $item = self::serializeItem($obj);
        return Response::json(ResponseTemplates::object($item), $statusCode);
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