<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ContributionController as AdminContributionController;
use App\Http\Controllers\Admin\CoursePricingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EntitlementController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
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
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\StudyGroupGoalController;
use App\Http\Controllers\StudyGroupPostController;
use App\Http\Controllers\StudyGroupPostReactionController;
use App\Http\Controllers\StudyGroupResourceController;
use App\Http\Controllers\StudyPlanController;
use App\Http\Controllers\TerminalLaunchController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CourseController::class, 'home'])->name('home');

// Subject landing pages (DevOps, IELTS Prep, …) — the homepage only shows these as
// cards; the actual course grid for a subject lives on its own page.
Route::get('/{group}', [CourseController::class, 'group'])
    ->whereIn('group', array_keys(config('course.groups')))
    ->name('course.group');

// Server-to-server only (shared-secret, not a browser session) — the terminal
// orchestrator calls this to redeem a launch ticket. See TerminalLaunchController.
Route::post('/internal/terminal/verify-ticket', [TerminalLaunchController::class, 'verify'])
    ->middleware('terminal.secret');

// Server-to-server only (HMAC-signed, not a browser session) — pay.bazardev
// calls this when a payment's status changes. See PaymentWebhookController.
Route::post('/webhooks/pay-bazardev', [PaymentWebhookController::class, 'handle']);

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

    // Hands this logged-in reader off to terminal.bazardev.com via a one-time ticket.
    Route::get('/terminal/launch', [TerminalLaunchController::class, 'launch'])->name('terminal.launch');

    // Starts a real payment via pay.bazardev for a paid course.
    Route::post('/pay/course/{course}', [PaymentController::class, 'checkoutCourse'])
        ->middleware('throttle:10,1')->name('payments.checkout.course');
    Route::get('/pay/return', [PaymentController::class, 'returned'])->name('payments.return');

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

    // Study groups: a small, invite-code-only group of readers on one course.
    Route::prefix('study-groups')->name('study-groups.')->group(function () {
        Route::get('/', [StudyGroupController::class, 'index'])->name('index');
        Route::post('/', [StudyGroupController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::post('/join', [StudyGroupController::class, 'join'])->middleware('throttle:20,1')->name('join');

        Route::get('/{studyGroup}', [StudyGroupController::class, 'show'])->name('show');
        Route::patch('/{studyGroup}', [StudyGroupController::class, 'rename'])->name('rename');
        Route::delete('/{studyGroup}', [StudyGroupController::class, 'destroy'])->name('destroy');
        Route::post('/{studyGroup}/leave', [StudyGroupController::class, 'leave'])->name('leave');
        Route::post('/{studyGroup}/regenerate-code', [StudyGroupController::class, 'regenerateCode'])->name('regenerate-code');
        Route::delete('/{studyGroup}/members/{member}', [StudyGroupController::class, 'removeMember'])->name('members.remove');

        Route::put('/{studyGroup}/privacy', [StudyGroupController::class, 'updatePrivacy'])->name('privacy');

        Route::post('/{studyGroup}/posts', [StudyGroupPostController::class, 'store'])
            ->middleware('throttle:30,1')->name('posts.store');
        Route::delete('/{studyGroup}/posts/{post}', [StudyGroupPostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/{studyGroup}/posts/{post}/react', [StudyGroupPostReactionController::class, 'toggle'])
            ->middleware('throttle:60,1')->name('posts.react');

        Route::post('/{studyGroup}/goals', [StudyGroupGoalController::class, 'store'])->name('goals.store');
        Route::delete('/{studyGroup}/goals/{goal}', [StudyGroupGoalController::class, 'destroy'])->name('goals.destroy');

        Route::post('/{studyGroup}/resources', [StudyGroupResourceController::class, 'store'])->name('resources.store');
        Route::delete('/{studyGroup}/resources/{resource}', [StudyGroupResourceController::class, 'destroy'])->name('resources.destroy');
    });

    // Mock tests: Listening is self-reported (real test taken on British Council's site —
    // we never host IELTS audio ourselves); Reading is auto-graded; Writing/Speaking are
    // saved for a side-by-side comparison with Band 6/8 samples, never auto-scored.
    Route::prefix('mock-tests')->name('mock-tests.')->group(function () {
        Route::get('/', [MockExamController::class, 'index'])->name('index');
        Route::post('/{set}/start', [MockExamController::class, 'start'])
            ->whereIn('set', array_keys(config('mock_exam.sets')))->middleware('throttle:20,1')->name('start');

        Route::get('/{set}/{attempt}/listening', [MockExamController::class, 'listening'])->name('listening');
        Route::post('/{set}/{attempt}/listening', [MockExamController::class, 'submitListening'])->name('listening.submit');
        Route::get('/{set}/{attempt}/reading', [MockExamController::class, 'reading'])->name('reading');
        Route::post('/{set}/{attempt}/reading', [MockExamController::class, 'submitReading'])->name('reading.submit');
        Route::get('/{set}/{attempt}/writing', [MockExamController::class, 'writing'])->name('writing');
        Route::post('/{set}/{attempt}/writing', [MockExamController::class, 'submitWriting'])->name('writing.submit');
        Route::get('/{set}/{attempt}/speaking', [MockExamController::class, 'speaking'])->name('speaking');
        Route::post('/{set}/{attempt}/speaking', [MockExamController::class, 'submitSpeaking'])->name('speaking.submit');
        Route::get('/{set}/{attempt}/results', [MockExamController::class, 'results'])->name('results');
    });

    // A guided 90-day schedule across all 6 IELTS courses — see config/study_plan.php
    // and App\Services\StudyPlan. "Done" reuses the existing learning.done checkboxes.
    Route::prefix('study-plan')->name('study-plan.')->group(function () {
        Route::get('/', [StudyPlanController::class, 'show'])->name('show');
        Route::post('/start', [StudyPlanController::class, 'start'])->name('start');
        Route::get('/day/{day}', [StudyPlanController::class, 'dayDetail'])->whereNumber('day')->name('day-detail');
    });

    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:3,1')->name('verification.send');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/verify', [AdminUserController::class, 'verify'])->name('users.verify');

        Route::get('/contributions', [AdminContributionController::class, 'index'])->name('contributions.index');
        Route::post('/contributions/{contribution}/approve', [AdminContributionController::class, 'approve'])->name('contributions.approve');
        Route::post('/contributions/{contribution}/reject', [AdminContributionController::class, 'reject'])->name('contributions.reject');

        Route::get('/pricing', [CoursePricingController::class, 'index'])->name('pricing.index');
        Route::put('/pricing/{course}', [CoursePricingController::class, 'update'])->name('pricing.update');

        Route::get('/entitlements', [EntitlementController::class, 'index'])->name('entitlements.index');
        Route::post('/entitlements', [EntitlementController::class, 'store'])->name('entitlements.store');
        Route::post('/entitlements/{entitlement}/revoke', [EntitlementController::class, 'revoke'])->name('entitlements.revoke');
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
    ->middleware('course.entitled')
    ->name('course.chapter');

// Chapter quizzes: anyone can take one; only a logged-in reader's result is saved.
Route::get('/{course}/{chapter}/quiz', [QuizController::class, 'show'])
    ->whereIn('course', $courseSlugs)
    ->name('quiz.show');

Route::post('/{course}/{chapter}/quiz', [QuizController::class, 'submit'])
    ->whereIn('course', $courseSlugs)
    ->middleware('throttle:30,1')
    ->name('quiz.submit');
