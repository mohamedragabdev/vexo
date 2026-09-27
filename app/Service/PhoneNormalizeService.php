<?php

namespace App\Service;

class PhoneNormalizeService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }
    public function normalize(string $phone){
        $phone = preg_replace('/\D/',"",$phone);
        if(str_starts_with($phone,"0")){
            $phone ="+2".$phone;
        }elseif(str_starts_with($phone,"20")){
            $phone ="+".$phone;
        }
        return $phone;
    }
}
