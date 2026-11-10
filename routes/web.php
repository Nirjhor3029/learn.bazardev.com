<?php

use App\Http\Controllers\CourseController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CourseController::class, 'home'])->name('home');
Route::get('/{course}', [CourseController::class, 'show'])->name('course.show');
Route::get('/{course}/{chapter}', [CourseController::class, 'chapter'])->name('course.chapter');
