<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ContributionController as AdminContributionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\QuizController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CourseController::class, 'home'])->name('home');

/*
|--------------------------------------------------------------------------
| Accounts — reading stays public; an account only adds personal features.
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // Personal learning: progress, bookmarks, private notes.
    Route::get('/my-learning', [LearningController::class, 'dashboard'])->name('learning.dashboard');
    Route::middleware('throttle:120,1')->prefix('learn/{course}/{chapter}')->name('learning.')->group(function () {
        Route::post('/done', [LearningController::class, 'done'])->name('done');
        Route::post('/bookmark', [LearningController::class, 'bookmark'])->name('bookmark');
        Route::put('/note', [LearningController::class, 'note'])->name('note');
    });

    // Contributions: a resource, a tip or a mistake report for a chapter. Public only after admin approval.
    Route::post('/learn/{course}/{chapter}/contributions', [ContributionController::class, 'store'])
        ->middleware('throttle:20,1')->name('contributions.store');
    Route::delete('/contributions/{contribution}', [ContributionController::class, 'destroy'])->name('contributions.destroy');

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:3,1')->name('verification.send');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');

        Route::get('/contributions', [AdminContributionController::class, 'index'])->name('contributions.index');
        Route::post('/contributions/{contribution}/approve', [AdminContributionController::class, 'approve'])->name('contributions.approve');
        Route::post('/contributions/{contribution}/reject', [AdminContributionController::class, 'reject'])->name('contributions.reject');
    });
});

/*
|--------------------------------------------------------------------------
| Course pages — catch-all style URLs (/{course}/{chapter}), so they are
| limited to real course slugs and registered LAST. That keeps /login,
| /account, /admin/... free for the routes above.
|--------------------------------------------------------------------------
*/
$courseSlugs = array_keys(config('course.courses'));

Route::get('/{course}', [CourseController::class, 'show'])
    ->whereIn('course', $courseSlugs)
    ->name('course.show');

// Final exam: logged-in readers only, registered before the chapter route so "exam" is never read as a chapter.
Route::middleware('auth')->group(function () use ($courseSlugs) {
    Route::get('/{course}/exam', [ExamController::class, 'show'])
        ->whereIn('course', $courseSlugs)->name('exam.show');
    Route::post('/{course}/exam', [ExamController::class, 'submit'])
        ->whereIn('course', $courseSlugs)->middleware('throttle:10,1')->name('exam.submit');
});

// Certificates: the page is public (the link is the proof); only the owner can refresh the printed name.
Route::get('/certificate/{code}', [CertificateController::class, 'show'])
    ->where('code', '[A-Za-z0-9-]{14}')->name('certificate.show');
Route::post('/certificate/{code}/name', [CertificateController::class, 'refreshName'])
    ->where('code', '[A-Za-z0-9-]{14}')->middleware(['auth', 'throttle:20,1'])->name('certificate.name');

Route::get('/{course}/{chapter}', [CourseController::class, 'chapter'])
    ->whereIn('course', $courseSlugs)
    ->name('course.chapter');

// Chapter quizzes: anyone can take one; only a logged-in reader's result is saved.
Route::get('/{course}/{chapter}/quiz', [QuizController::class, 'show'])
    ->whereIn('course', $courseSlugs)
    ->name('quiz.show');

Route::post('/{course}/{chapter}/quiz', [QuizController::class, 'submit'])
    ->whereIn('course', $courseSlugs)
    ->middleware('throttle:30,1')
    ->name('quiz.submit');
