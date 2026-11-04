<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida o JWT de funcionário emitido pelo os-service (POST /api/auth/login,
 * HS256 com JWT_SECRET). Este serviço não tem tabela de usuários: confia na
 * assinatura e expõe o id do funcionário em $request->attributes('staff_id').
 */
class AuthenticateStaffJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $secret = (string) config('services.staff_jwt.secret');

        if (! $token || $secret === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $claims = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // O JWT de cliente (Lambda CPF) usa outro segredo; ainda assim, só
        // aceitamos tokens com subject de funcionário.
        if (($claims->typ ?? null) === 'customer' || ! isset($claims->sub)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('staff_id', (string) $claims->sub);

        return $next($request);
    }
}
