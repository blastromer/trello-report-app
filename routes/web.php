<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
Route::get('/register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);

// Protected Trello Routes
Route::prefix('trello')->middleware('auth')->group(function () {
    Route::get('/boards', [App\Http\Controllers\TrelloReportController::class, 'index'])->name('trello.boards');
    Route::get('/report/{boardId}/filter', [App\Http\Controllers\TrelloReportController::class, 'showFilterForm'])->name('trello.report.filter');
    Route::get('/report/{boardId}', function ($boardId) {
        return redirect()->route('trello.report.filter', $boardId);
    });
    Route::post('/report/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'generateReport'])->name('trello.report');
    Route::get('/export/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'export'])->name('trello.export');
    Route::get('/accountability/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'showAccountabilityForm'])->name('trello.accountability.form');
    Route::post('/accountability/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'generateAccountabilityReport'])->name('trello.accountability');
    Route::get('/accountability-report/{boardReport}/docx', [App\Http\Controllers\TrelloReportController::class, 'exportAccountabilityDocx'])->name('trello.accountability.docx');
    Route::get('/kpi/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'showIndividualKpiForm'])->name('trello.kpi.form');
    Route::post('/kpi/{boardId}', [App\Http\Controllers\TrelloReportController::class, 'generateIndividualKpiReport'])->name('trello.kpi');
    Route::get('/kpi-report/{boardReport}/docx', [App\Http\Controllers\TrelloReportController::class, 'exportIndividualKpiDocx'])->name('trello.kpi.docx');
    Route::get('/kpi-report/{boardReport}/html', [App\Http\Controllers\TrelloReportController::class, 'exportIndividualKpiHtml'])->name('trello.kpi.html');
});
