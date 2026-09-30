<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Laravel's default behaviour, even for an API-only app like this
        // one, is to redirect a logged-out visitor to a route named
        // "login" - which we never defined, since there's no login page,
        // only a login endpoint. Without this line, that default tries to
        // build a URL for a route that doesn't exist and crashes with a
        // 500 instead of a clean 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This app is a pure JSON API with no login page, so every error
        // (including "you're not logged in" and validation failures)
        // should come back as JSON. Without this, a plain request that
        // doesn't explicitly ask for JSON (a normal browser visit, or a
        // request missing an Accept header) gets treated as if it wants
        // an HTML page and tries to redirect somewhere - which crashes,
        // since this app has no such pages.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
