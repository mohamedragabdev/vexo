<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request  ;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perMinute(3)->by('otp:' . $request->input('phone'))->response(function(Request $request,array $header){
                    return response()->json([
                        'message'=>'لقد طلبت رقم التحقق لهذا الرقم مرات كثيرة ,اعد المحاولة في وقت لاحق '
                    ],429,$header);
                }),
                Limit::perMinute(5)->by($request->ip())->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'تم تجاوز عدد المحاولات المسموح بها. حاول مرة أخرى بعد قليل.',
                ], 429, $headers);})
            ];
        });
    }
}
