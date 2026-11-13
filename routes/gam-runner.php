<?php

use Illuminate\Support\Facades\Route;
use TornadoEngine\GamRunner\Http\RunnerController;

Route::middleware(['web', 'auth'])
    ->prefix('gam-runner')
    ->name('gam-runner.')
    ->group(function () {
        Route::get('/', [RunnerController::class, 'index'])->name('index');
        Route::post('/', [RunnerController::class, 'run'])->name('run');
    });
