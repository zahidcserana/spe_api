<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $guarded = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public function create($data)
    {
        $lastInsertId = $this::orderBy('id', 'desc')->first();
        $userData = array(
            'name' => $data['name'],
            // 'email' => $data['email'],
            'user_mobile' => $data['user_mobile'],
            'password' => Hash::make('dgda@' . $data['user_mobile']),
            'userid' => $lastInsertId->id + 1 . Carbon::now()->timestamp,
            'verification_pin' => rand(1000, 4000),
        );

        $userId = $this::insertGetId($userData);

        $user = $this::find($userId);
        return $user;
    }
}
