<?php

namespace App\Http\Middleware;

use Closure;

class SentryContext
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
        if (app()->bound('sentry')) {
            /** @var \Raven_Client $sentry */
            $sentry = app('sentry');

            // Add user context
            if (auth()->check()) {
                $user = auth()->user();
                $sentry->user_context([ 'id' => $user->id, 'username' => $user->name ]);
            } else {
                $sentry->user_context([ 'id' => null ]);
            }

            // Add tags context
            // $sentry->tags_context([...]);
        }

        return $next($request);
    }
}
