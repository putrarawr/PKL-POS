<?php

use App\Http\Controllers\KasirController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- Kasir Auth ---
Route::get('/kasir/login', [KasirController::class, 'showLogin'])->name('kasir.login');
Route::post('/kasir/login', [KasirController::class, 'login'])->name('kasir.login.submit');
Route::post('/kasir/logout', [KasirController::class, 'logout'])->name('kasir.logout');

// --- Kasir (harus login) ---
Route::middleware('auth:karyawan')->group(function () {
    Route::get('/kasir', [KasirController::class, 'index'])->name('kasir');
    Route::post('/kasir/simpan', [KasirController::class, 'simpan'])->name('kasir.simpan');
});

