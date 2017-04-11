<?php

namespace App\Http\Middleware;

use Closure;
use App\User;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $request->session()->has('session-token')){
            return redirect('/login');
        }
        else{
            $request->user = new User;
        }
        return $next($request);
    }
}
