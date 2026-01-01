<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if (isset($user->status) && !$user->status) {
            return response()->json(['message' => 'Usuário inativo.'], 403);
        }

        $empresa = $user->empresa()->first();
        if ($empresa && isset($empresa->status) && !$empresa->status) {
            return response()->json(['message' => 'Empresa inativa.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'empresa_id' => $user->empresa_id ?? null,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Logout realizado']);
    }
}
