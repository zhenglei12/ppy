<?php

namespace App\Http\Middleware;

use Closure;

class CrossMiddleware
{
    public function handle($request, Closure $next)

    {
        $response = $next($request);

//        $response->header('Access-Control-Allow-Origin', '*');
//
//        $response->header('Access-Control-Allow-Headers', 'Keep-Alive,X-Requested-With,Cache-Control,Content-Type,auth,sign,Token,Pt,Xsign,toolken,code');
//
//        $response->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
//
//        $response->header('Access-Control-Allow-Credentials', 'true');
//
//        $response->header('Access-Control-Max-Age', '3600');

        return $response;

    }

}
