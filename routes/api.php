<?php

use App\Http\Controllers\Auth\AuthenticatedController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::prefix('/v1')->group(function () {
    //=================================
    // * Auth Routes
    //=================================
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [AuthenticatedController::class, 'login'])->middleware('throttle:5,1');

    Route::post('/send-otp', [RegisterController::class, 'sendOTP'])->middleware('throttle:otp');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthenticatedController::class, 'logout']);

        Route::apiResource('/conversation',ConversationController::class);

        Route::post('/send-message', [MessageController::class,'store'])->middleware('throttle:100,1');
        Route::put('/update-message/{id}', [MessageController::class,'update'])->middleware('throttle:100,1');
        Route::delete('/delete-message/{id}', [MessageController::class,'destroy'])->middleware('throttle:100,1');
        Route::get('/chat', function () {
            return view('Chat.ChatMessage');
        });
    });
});
