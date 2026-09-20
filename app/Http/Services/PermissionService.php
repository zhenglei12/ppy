<?php


namespace App\Http\Services;


use Illuminate\Http\Resources\Json\ResourceCollection;

class PermissionService extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection
        ];
    }
}
