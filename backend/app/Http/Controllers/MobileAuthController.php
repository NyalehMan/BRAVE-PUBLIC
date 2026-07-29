<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BruneiIdentity;
use App\Models\MobileAppUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'ic_no' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string'],
        ]);

        $identity = BruneiIdentity::where('ic_no', $request->ic_no)->first();

        if (!$identity || !Hash::check($request->password, $identity->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IC number or password.',
            ], 401);
        }

        

        $plainToken = Str::random(80);
        $hashedToken = hash('sha256', $plainToken);

        MobileAppUser::where('brunei_identity_id', $identity->id)->delete();

        MobileAppUser::create([
            'brunei_identity_id' => $identity->id,
            'device_id' => $request->device_id,
            'api_token' => $hashedToken,
            'last_login_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $plainToken,
            'user' => [
                'ic_no' => $identity->ic_no,
                'full_name' => $identity->full_name,
                'address' => $identity->address,
                'nationality' => $identity->nationality,
                'id_picture' => $identity->id_picture,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = MobileAppUser::with('identity')
            ->where('api_token', hash('sha256', $token))
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'ic_no' => $user->identity->ic_no,
                'full_name' => $user->identity->full_name,
                'address' => $user->identity->address,
                'nationality' => $user->identity->nationality,
                'id_picture' => $user->identity->id_picture,
            ],
        ]);
    }
}