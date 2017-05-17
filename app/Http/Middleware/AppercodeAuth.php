<?php

namespace App\Http\Middleware;

use Closure;
use App\User;
use App\Backend;

class AppercodeAuth
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
        if (! isset(app(Backend::Class)->token)) {
            return redirect('/' . app(Backend::Class)->code . '/login');
        }

        return $next($request);
    }
}
