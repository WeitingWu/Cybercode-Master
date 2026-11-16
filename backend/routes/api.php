<?php

use App\Http\Controllers\CodeMasterController as C;
use Illuminate\Support\Facades\Route;

Route::get('status', [C::class, 'status']);
Route::post('analyze', [C::class, 'analyze']);
Route::get('history', [C::class, 'history']);
Route::get('history/{analysis}', [C::class, 'show']);
Route::delete('history/{analysis}', [C::class, 'destroy']);
