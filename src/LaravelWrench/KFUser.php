<?php

namespace Kodfabriken\LaravelWrench;

use Illuminate\Foundation\Auth\User;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class KFUser extends KFModel implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
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
