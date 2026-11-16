<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\PackageBuilderController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * The admin panel's own route file, so routes/web.php stays short enough to read in
 * one screen. The group's middleware, prefix and name are applied where it is
 * mounted, in bootstrap/app.php.
 */

Route::get('/', DashboardController::class)->name('dashboard');

/* The bank. The two GET forms come before the wildcard PUT/DELETE so a future
   `/soal/baru` never gets read as `/soal/{question}`. */
Route::get('/soal', [QuestionController::class, 'index'])->name('questions.index');
Route::get('/soal/baru', [QuestionController::class, 'create'])->name('questions.create');
Route::post('/soal', [QuestionController::class, 'store'])->name('questions.store');
Route::get('/soal/{question}/ubah', [QuestionController::class, 'edit'])->name('questions.edit');
Route::put('/soal/{question}', [QuestionController::class, 'update'])->name('questions.update');
Route::delete('/soal/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');

/* The importer. The commit posts to the collection route and the preview to a
   sub-path, so a hand-crafted POST to the commit URL cannot be mistaken for a
   preview — it re-checks the whole payload on its own. */
Route::get('/impor', [ImportController::class, 'index'])->name('import.index');
Route::post('/impor/pratinjau', [ImportController::class, 'preview'])->name('import.preview');
Route::post('/impor', [ImportController::class, 'commit'])->name('import.commit');

/* Packages. The route key is the slug (see ExamPackage::getRouteKeyName), so the
   parameter is named `package` and binding resolves by slug everywhere.
   The literal `/paket/baru` sits above the `/paket/{package}` GETs so it can
   never be read as a slug. */
Route::get('/paket', [PackageController::class, 'index'])->name('packages.index');
Route::get('/paket/baru', [PackageController::class, 'create'])->name('packages.create');
Route::post('/paket', [PackageController::class, 'store'])->name('packages.store');
Route::get('/paket/{package}/ubah', [PackageController::class, 'edit'])->name('packages.edit');
Route::put('/paket/{package}', [PackageController::class, 'update'])->name('packages.update');

/* The builder, one action per item: add, take out, move, and open or close the
   package. `{question}` binds by id — an item is identified by its row, not by a
   slug. */
Route::get('/paket/{package}/susun', [PackageBuilderController::class, 'show'])->name('packages.builder');
Route::post('/paket/{package}/soal', [PackageBuilderController::class, 'attach'])->name('packages.attach');
Route::delete('/paket/{package}/soal/{question}', [PackageBuilderController::class, 'detach'])->name('packages.detach');
Route::post('/paket/{package}/soal/{question}/pindah', [PackageBuilderController::class, 'move'])->name('packages.move');
Route::post('/paket/{package}/buka', [PackageBuilderController::class, 'publish'])->name('packages.publish');
Route::post('/paket/{package}/tutup', [PackageBuilderController::class, 'unpublish'])->name('packages.unpublish');

/* The accounts screen. The route key is the row id — an account is identified by
   its id, so the URL never carries an address that could itself be mistyped. The
   reset is a POST because it writes, and it is the only place a password is ever
   replaced from the panel. */
Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
Route::post('/pengguna/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
