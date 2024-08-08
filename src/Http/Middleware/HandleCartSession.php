<?php
 
namespace SunErgoS\LaravelCart\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
 
class HandleCartSession
{
    public function handle(Request $request, Closure $next)
    {
         
        return $next($request);

    }
}