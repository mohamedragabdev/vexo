<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(["id",'name', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    public $incrementing = false;

protected $keyType = 'string';
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    //==============
    // Relations
    //==============

    public function sender(){
        return $this->hasMany(Conversation::class,'sender_id');
    }

    public function receiver(){
        return $this->hasMany(Conversation::class,'receiver_id');
    }
    
    public function senderMessages(){
        return $this->hasMany(Message::class,'sender_id');
    }
    public function receiverMessages(){
        return $this->hasMany(Message::class,'receiver_id');
    }
}
