<?php

use Illuminate\Foundation\Application;

return Application::configure()
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->create();
