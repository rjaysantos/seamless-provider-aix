<?php

namespace Providers\Aix;

use Illuminate\Support\Facades\Route;

Route::prefix('aix')->group(function () {
    Route::prefix('in')->group(function () {
        Route::post('play', [AixController::class, 'play']);
        Route::post('visual', [AixController::class, 'visual']);
    });
});
