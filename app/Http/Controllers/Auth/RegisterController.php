<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class RegisterController extends Controller
{
     public function sendOTP(){
        $otp = random_int(111111,999999);
        return $otp;
    }

    public function register(RegisterRequest $request)
    {
        $cer = User::where('phone', $request->phone)->first();

        if ($cer) {
            return response()->json([
                'message' => 'You are registered already, please login'
            ], 409);
        }
               $otp = $this->sendOTP();
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'phone' => $request->phone
        ]);
 
        return response()->json([
            'message' => 'successful registered',
            'user' => $user,
            'otp'=>$otp
        ],201);
    }


   
}