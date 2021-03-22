<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'avatar', 'phone', 'address', 'state', 'city', 'country', 'zip'
    ];
}
