<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreviewBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Se non ci sono credenziali impostate nel .env, bypassa (utile per locale)
        $user = config('services.preview.user');
        $pass = config('services.preview.pass');

        if (!empty($user) && !empty($pass)) {
            if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
                $headers = ['WWW-Authenticate' => 'Basic'];
                return response('Non autorizzato', 401, $headers);
            }
        }

        return $next($request);
    }
}
