<?php

namespace Kodfabriken\LaravelWrench;

use Illuminate\Foundation\Auth\User;
use Laravel\Passport\HasApiTokens;

class KFUser extends KFModel implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use HasApiTokens, Notifiable, Authenticatable, Authorizable, CanResetPassword;

    protected $hidden = [
        'password'
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }
}
