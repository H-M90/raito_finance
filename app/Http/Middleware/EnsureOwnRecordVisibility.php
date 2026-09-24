<?php

namespace App\Http\Middleware;

use App\Support\OwnRecordVisibility;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnRecordVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && ! OwnRecordVisibility::allows($parameter, $request->user())) {
                abort(404);
            }
        }

        return $next($request);
    }
}
