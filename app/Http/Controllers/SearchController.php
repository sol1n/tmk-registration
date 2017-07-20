<?php

namespace App\Http\Controllers;

use App\Helpers\AjaxResponse;
use App\Object;
use App\Schema;
use App\Backend;
use App\Services\SchemaManager;
use App\Services\UserManager;
use Illuminate\Http\Request;
use App\Services\ObjectManager;
use Illuminate\Support\Collection;


class SearchController extends Controller
{
    private function searchUsers($query) {
        /**
         * @var Collection $users
         */
        $users = new Collection();
        $users = app(UserManager::class)->search(['where' => json_encode(['username' => ['$regex' => $query]])]);
        return $users;
    }

    private function searchObject (Schema $schema, $query) {
        $fields = Object::getShortViewFields($schema);
        $queryArray = [];
        foreach ($fields as $field) {
            $queryArray[] =  [$field => ['$regex' => $query]];
        };
        $queryArray = ['where' => json_encode(['$or' => $queryArray])];
        $objects = app(ObjectManager::class)->search($schema, $queryArray);
        return $objects;
    }

    public function SearchRef(Request $request)
    {
        $response = new AjaxResponse();
        $ref = $request->get('ref');
        $query = $request->get('q');
        if (!$ref) {
            $response->setResponseError(`Parameter ref/'s not been found`);
        }
        if (!$query) {
            $response->setResponseError(`Parameter q/'s not been found`);
        }
        if ($response->type != AjaxResponse::ERROR) {
            if ($ref == 'ref Users') {
                $users = $this->searchUsers($query);
                $response->data = $users->map(function($item, $index) {
                    return ['id' => $item->id, 'text' => $item->username];
                });
            } else {
                $schemaId = $code = str_replace('ref ', '', $ref);;

                $schema = app(SchemaManager::class)->find($schemaId);
                if ($schema) {
                 $objects = $this->searchObject($schema, $query);
                 $response->data = $objects->map(function($item, $index) {
                        return  ['id' => $item->id, 'text' => $item->shortView()];
                 });
                }
                else{
                    $response->setResponseError(`Schema hasn't been found`);
                }
            }
        }
        return response()->json($response);
    }
}
