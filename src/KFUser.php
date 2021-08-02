<?php

namespace LaravelWrench;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class KFUser extends KFModel
{
    use HasApiTokens, Notifiable;

    protected $hidden = [
        'password'
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }
}
