<?php

namespace App;

use App\User;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use App\Exceptions\Backend\BackendNotExists;
use App\Exceptions\Backend\BackendNotSelected;
use App\Exceptions\Backend\BackendNoServerProvided;

class Backend
{
    public $base;
    public $code;
    public $url;

    const TEST_METHOD = 'app/appropriateConfiguration';

    private function check()
    {
      $client = new Client;
      try {
          $r = $client->get($this->url  . self::TEST_METHOD);
      }
      catch (RequestException $e) {
          throw new BackendNotExists;
      };

      $response = $r->getBody()->getContents();
      if ($response !== 'null' && !is_array(json_decode($response, 1)))
      {
        throw new BackendNotExists;
      }
    }

    public function __construct()
    {
        $request = request();
        if ($request->path() == '/')
        {
          throw new BackendNotSelected;
        }
        $segments = explode('/', $request->path());
        $this->code = $segments[0];
        $this->base = env('APPERCODE_SERVER', false);
        if ($this->base === false)
        {
          throw new BackendNoServerProvided;
        }

        $this->url = $this->base . $this->code . '/';

        $this->check();

        if (session($this->code . '-session-token')) {
          $this->token = session($this->code . '-session-token');
        }
    }
}
