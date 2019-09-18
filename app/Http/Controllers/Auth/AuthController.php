<?php

namespace App\Http\Controllers\Auth;

use Validator;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use Laravel\Lumen\Routing\Controller;
use App\Models\User;

class AuthController extends Controller
{
    private $request;

    protected function jwt(User $user)
    {
        $payload = [
            'iss' => "lumen-jwt", // Issuer of the token
            'sub' => $user->id, // Subject of the token
            'iat' => time(), // Time when JWT was issued. 
            'exp' => time() + 60 * 60 * 24 * 30 * 12 // Expiration time
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.

        return JWT::encode($payload, env('JWT_SECRET'));
    }

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function userAuthenticate(User $user)
    {
        $this->validate($this->request, [
            'email' => 'required',
            'password' => 'required'
        ]);
        $user = User::where('email', $this->request->email)->first();
        if (!$user) {

            return response()->json([
                'error' => 'User does not exist.'
            ], 401);
        }

        // Verify the password and generate the token
        if (Hash::check($this->request->password, $user->password)) {

            return response()->json([
                'status' => 200,
                'message' => 'Login Successful',
                'data' => [
                    'token' => $this->jwt($user),
                    'email' => $user->email,
                    'user_type' => $user->user_type,
                ]
            ], 200);
        }
        return response()->json([
            'status' => 403,
            'error' => 'Login details provided does not exit.'
        ], 403);
    }
}
