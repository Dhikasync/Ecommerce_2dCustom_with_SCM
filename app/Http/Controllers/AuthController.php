<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request) 
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'no_hp' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'no_hp' => $data['no_hp'] ?? null,
            'password' => $data['password'],
            'role' => 'user',
            'is_aktif' => true,
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'Registered',
            'user' => $user,
        ], 201);
    }

    /**
     * FR-AUTH-06 #2-#4: verifikasi email lewat tautan bertanda tangan.
     *
     * Urutan pemeriksaan: signature (middleware), user, hash email,
     * status aktif, lalu token. Signature sudah diperiksa sebelum method ini.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email sudah diverifikasi, silakan login',
                'redirect' => url('/login'),
            ]);
        }

        $token = (string) $request->query('token', '');

        if (! $user->email_verification_token || ! hash_equals($user->email_verification_token, $token)) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid'], 403);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        return response()->json([
            'message' => 'Email berhasil diverifikasi, silakan login',
            'redirect' => url('/login'),
        ]);
    }
}
