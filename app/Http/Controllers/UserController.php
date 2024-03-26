<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\GlobalResource;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Create a new UserController instance.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * Login user with username and password.
     *
     * @param \Illuminate\Http\Request
     * @bodyParam  string  $username
     * @bodyParam  string  $password
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['username', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            $request->message = 'Unauthorized';
            return (new ErrorResource($request))->response()->setStatusCode(401);
        }

        $request->message = 'Success';
        $request->data = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ];
        return (new GlobalResource($request))->response()->setStatusCode(200);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $request->message = 'Success';
        $request->data = auth()->user();
        return (new GlobalResource($request))->response()->setStatusCode(200);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $request->message = 'Success';
        $request->data = [
            'access_token' => $auth()->refresh(),
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ];
        return (new GlobalResource($request))->response()->setStatusCode(200);
    }

    /**
     * Create user with username and password.
     *
     * @param \Illuminate\Http\Request
     * @bodyParam  string  $username
     * @bodyParam  string  $password
     * 
     * @return Response
     */
    public function create(Request $request)
    {
        // Only SUPER_ADMIN
        $payload = auth()->payload();
        $user = User::find($payload->get('sub'));
        if ($user != 'SUPER_ADMIN') {
            $request->message = 'Only SUPER_ADMIN users can create';
            return (new ErrorResource($request))->response()->setStatusCode(400);
        }

        // Check if username is already 
        $user = User::firstWhere('username', $request->username);
        if ($user) {
            $request->message = 'Username already exists';
            return (new ErrorResource($request))->response()->setStatusCode(400);
        }
        
        $newUser = new User();
        $newUser->username = $request->username;
        $newUser->password = Hash::make($request->password, [
            'rounds' => 15,
        ]);
        $newUser->role = "ADMIN";

        $newUser->save();

        $request->message = 'Success';
        $request->data = $newUser;
        return (new GlobalResource($request))->response()->setStatusCode(201);
    }
}
