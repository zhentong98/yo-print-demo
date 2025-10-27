<?php

use App\Http\Controllers\FileUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('file-uploads')->group(function () {
    Route::post('/', [FileUploadController::class, 'upload']);
    Route::get('/', [FileUploadController::class, 'index']);
    Route::get('/{id}', [FileUploadController::class, 'status']);
    Route::get('/{id}/products', [FileUploadController::class, 'products']);
    Route::delete('/{id}', [FileUploadController::class, 'destroy']);
});
