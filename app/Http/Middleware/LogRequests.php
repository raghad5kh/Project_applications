<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LogRequests
{
    /**
     * Handle an incoming request.
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        // Log request information to a custom log file
        Log::channel('custom')->info('Request URL: ' . $request->fullUrl());
        Log::channel('custom')->info('Request Method: ' . $request->method());
        Log::channel('custom')->info('Request IP: ' . $request->ip());
        Log::channel('custom')->info('User ID: ' . optional($user)->id);
        Log::channel('custom')->info('User Name: ' . optional($user)->name);
        // Continue processing the request
       $response= $next($request);
        // Log success or failure based on the response status code
        if ($response->isSuccessful()) {
            Log::channel('custom')->info('Request succeeded. Status Code: ' . $response->getStatusCode());
        } else {
            Log::channel('custom')->error('Request failed. Status Code: ' . $response->getStatusCode());
        }
        return $response;
    }
}
