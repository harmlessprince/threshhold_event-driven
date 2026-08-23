<?php

use App\Http\Controllers\AchievementsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('users/{user}/achievements', [AchievementsController::class, 'show'])
    ->name('users.achievements');
