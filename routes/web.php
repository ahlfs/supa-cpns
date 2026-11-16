<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Exam\AnalysisController;
use App\Http\Controllers\Exam\AnswerController;
use App\Http\Controllers\Exam\AttemptController;
use App\Http\Controllers\Exam\PackageController;
use App\Http\Controllers\Exam\QuestionChatController;
use App\Http\Controllers\Exam\ResultController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store']);
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    // Reachable while a temporary password is still in place, or the redirect
    // from `password.changed` would loop.
    Route::get('/akun/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/akun/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // The tryout module. Packages are addressed by slug (`ExamPackage`
        // route key), sittings by id, and both live behind the same auth and
        // forced-password-change gates as the dashboard.
        Route::get('/tryout', [PackageController::class, 'index'])->name('tryout.index');
        Route::post('/tryout/{package}/mulai', [PackageController::class, 'start'])->name('tryout.start');
        Route::post('/tryout/{package}/mulai-ulang', [PackageController::class, 'restart'])->name('tryout.restart');
        Route::get('/tryout/attempt/{attempt}', [AttemptController::class, 'show'])->name('tryout.room');

        // The three writes the room makes. The two JSON ones answer with the
        // server's own count and clock, so a paused tab or a drifted system clock
        // is pulled back into line on the next tap; the finish one is a plain form
        // post, because leaving the exam has to survive the browser.
        Route::put('/tryout/attempt/{attempt}/jawaban', [AnswerController::class, 'update'])->name('tryout.answer');
        Route::put('/tryout/attempt/{attempt}/posisi', [AnswerController::class, 'position'])->name('tryout.position');
        Route::post('/tryout/attempt/{attempt}/selesai', [AttemptController::class, 'finish'])->name('tryout.finish');

        // The analysis of a finished sitting. Read-only, and gated to its owner
        // inside the controller so a guessed id opens nothing.
        Route::get('/hasil/{attempt}', [ResultController::class, 'show'])->name('hasil.show');

        // Asking for the written analysis. A POST rather than a link because each
        // generation costs money at the gateway: it has to be asked for, and the
        // page can then be reloaded for free. `ulangi` is the one deliberate
        // regeneration, so it travels in the body of the same request.
        Route::post('/hasil/{attempt}/analisa-ai', [AnalysisController::class, 'store'])->name('hasil.analisa');

        // Asking back about that analysis. It happens on the result page rather
        // than on a page of its own — unlike the item thread, the numbers the
        // question is about are already on this page — and it is a POST for the
        // same reason as above: every answer is a generation that costs money.
        Route::post('/hasil/{attempt}/analisa-ai/tanya', [AnalysisController::class, 'followUp'])->name('hasil.analisa.tanya');

        // Asking the tutor about one item of that sitting, on a page of its own.
        // Reachable only from the review list of a submitted paper — the
        // controller refuses an open sitting, because being able to ask mid-exam
        // would turn 110 questions into 110 conversations.
        Route::get('/hasil/{attempt}/soal/{question}/tanya', [QuestionChatController::class, 'show'])->name('hasil.tanya');
        Route::post('/hasil/{attempt}/soal/{question}/tanya', [QuestionChatController::class, 'store']);
    });
});
