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
}
