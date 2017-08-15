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
use App\Exceptions\Backend\LogoutException;

class Backend
{
    public $base;
    public $code;
    public $url;

    const TEST_METHOD = 'app/appropriateConfiguration';
    const LOGOUT_METHOD = 'logout';

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
        $defaultBackend = env('APPERCODE_DEFAULT_BACKEND', false);
        if ($defaultBackend)
        {
          $this->code = $defaultBackend;
        }
        else
        {
          $request = request();
          if ($request->path() == '/')
          {
            throw new BackendNotSelected;
          }
          else
          {
            $segments = explode('/', $request->path());
            $this->code = $segments[0];
          }
        }
      
        
        $this->base = env('APPERCODE_SERVER', false);
        if (!$this->base)
        {
          throw new BackendNoServerProvided;
        }

        $this->url = $this->base . $this->code . '/';

        $this->check();

        if (session($this->code . '-session-token')) {
          $this->token = session($this->code . '-session-token');
        }
    }

    public function logout()
    {
      if (isset($this->token))
      {
        $client = new Client;
        try {
            $r = $client->get(
              $this->url  . self::LOGOUT_METHOD, 
              ['headers' => ['X-Appercode-Session-Token' => $this->token]]
            );
        }
        catch (RequestException $e) {
            throw new LogoutException;
        };

        session()->flush();
        $this->token = null;
      }
    
      return $this;
    }
}
