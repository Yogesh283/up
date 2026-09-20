<?php

use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DepositController as ApiDepositController;
use App\Http\Controllers\Api\HistoryController as ApiHistoryController;
use App\Http\Controllers\Api\ReferralController as ApiReferralController;
use App\Http\Controllers\Api\ResultController as ApiResultController;
use App\Http\Controllers\Api\WithdrawalController as ApiWithdrawalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/history', [HistoryController::class, 'index'])->name('history');
    Route::get('/results', [ResultController::class, 'index'])->name('results');
    Route::get('/referral', [ReferralController::class, 'index'])->name('referral');
    Route::get('/deposit', [DepositController::class, 'index'])->name('deposit');
    Route::get('/withdrawal', [WithdrawalController::class, 'index'])->name('withdrawal');

    Route::get('/api/dashboard', [ApiDashboardController::class, 'index'])->name('api.dashboard');
    Route::post('/api/bets', [\App\Http\Controllers\Api\BetController::class, 'store'])->name('api.bets.store');
    Route::get('/api/history', [ApiHistoryController::class, 'index'])->name('api.history');
    Route::get('/api/results', [ApiResultController::class, 'index'])->name('api.results');
    Route::get('/api/referral', [ApiReferralController::class, 'index'])->name('api.referral');
    Route::get('/api/deposit', [ApiDepositController::class, 'index'])->name('api.deposit');
    Route::post('/api/deposit', [ApiDepositController::class, 'store'])->name('api.deposit.store');
    Route::get('/api/withdrawal', [ApiWithdrawalController::class, 'index'])->name('api.withdrawal');
    Route::post('/api/withdrawal', [ApiWithdrawalController::class, 'store'])->name('api.withdrawal.store');
});

require __DIR__.'/auth.php';
