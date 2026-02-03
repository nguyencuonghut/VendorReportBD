<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\VendorReportController;
use App\Http\Controllers\DepartmentController;

/*** Login Routes ***/
Route::group(['middleware' => 'guest'], function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    // Password Reset Routes
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});

/*** Authenticated Routes ***/
Route::group(['middleware' => 'auth'], function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});


Route::group(['middleware' => 'auth'], function () {
    Route::get('/', function () {
        return Inertia::render('Home');
    });

    // User Management Routes - Authorization handled by UserPolicy
    // Bulk delete route must be defined before resource routes
    Route::delete('users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');
    Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{user}/force', [UserController::class, 'forceDelete'])->name('users.force-delete');
    Route::resource('users', UserController::class);

    // Role Management Routes - Authorization handled by RolePolicy
    Route::delete('roles/bulk-delete', [RoleController::class, 'bulkDelete'])->name('roles.bulk-delete');
    Route::resource('roles', RoleController::class);

    // Activity Log Routes - Authorization handled by ActivityPolicy
    Route::delete('activity-logs/clear', [ActivityLogController::class, 'clear'])->name('activity-logs.clear');
    Route::resource('activity-logs', ActivityLogController::class)->only(['index', 'show', 'destroy']);

    // Department Routes - Authorization handled by DepartmentPolicy
    Route::resource('departments', DepartmentController::class);

    // Vendor Report Routes - Authorization handled by VendorReportPolicy
    // Custom action routes must be defined before resource routes
    Route::post('vendor-reports/{vendorReport}/submit', [VendorReportController::class, 'submit'])->name('vendor-reports.submit');
    Route::post('vendor-reports/{vendorReport}/approve', [VendorReportController::class, 'approve'])->name('vendor-reports.approve');
    Route::post('vendor-reports/{vendorReport}/reject', [VendorReportController::class, 'reject'])->name('vendor-reports.reject');
    Route::post('vendor-reports/{vendorReport}/cancel', [VendorReportController::class, 'cancel'])->name('vendor-reports.cancel');
    Route::post('vendor-reports/{vendorReport}/clone', [VendorReportController::class, 'clone'])->name('vendor-reports.clone');
    Route::get('vendor-reports/files/{file}/view', [VendorReportController::class, 'viewFile'])->name('vendor-reports.files.view');
    Route::get('vendor-reports/files/{file}/download', [VendorReportController::class, 'downloadFile'])->name('vendor-reports.files.download');
    Route::resource('vendor-reports', VendorReportController::class);

    // Backup Routes - Authorization handled by BackupPolicy
    Route::get('backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::get('backup/download', [\App\Http\Controllers\BackupController::class, 'backup'])->name('backup.download');
    Route::get('backup/configurations', [\App\Http\Controllers\BackupController::class, 'configurations'])->name('backup.configurations');
    Route::post('backup/configurations', [\App\Http\Controllers\BackupController::class, 'storeConfiguration'])->name('backup.configurations.store');
    Route::put('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'updateConfiguration'])->name('backup.configurations.update');
    Route::delete('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'deleteConfiguration'])->name('backup.configurations.delete');
    Route::patch('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'toggleConfiguration'])->name('backup.configurations.toggle');
    Route::post('backup/configurations/test-google-drive', [\App\Http\Controllers\BackupController::class, 'testGoogleDrive'])->name('backup.configurations.test-google-drive');
    Route::post('backup/configurations/{configuration}/run', [\App\Http\Controllers\BackupController::class, 'runConfiguration'])->name('backup.configurations.run');

    // Google Drive OAuth routes - Keep role middleware for security
    Route::group(['middleware' => 'role:Super Admin'], function () {
        Route::post('/auth/google-drive/connect', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'redirectToGoogle'])->name('google-drive.connect');
        Route::get('/auth/google-drive/callback', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'handleCallback'])->name('google-drive.callback');
        Route::post('/auth/google-drive/exchange-token', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'exchangeToken'])->name('google-drive.exchange-token');
        Route::get('/api/google-drive/status', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'getConnectionStatus'])->name('google-drive.status');
        Route::get('/api/google-drive/folders', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'getFolders'])->name('google-drive.folders');
        Route::post('/api/google-drive/create-folder', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'createBackupFolder'])->name('google-drive.create-folder');
        Route::post('/api/google-drive/select-folder', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'saveFolderSelection'])->name('google-drive.select-folder');
        Route::post('/api/google-drive/disconnect', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'disconnect'])->name('google-drive.disconnect');
    });
});
