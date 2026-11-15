<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Correlaciona todas as linhas de log de uma requisição com um request id,
 * reaproveitando o header X-Request-Id se ele já vier de um serviço anterior
 * na cadeia (ex.: API Gateway) — assim os logs do Laravel podem ser cruzados
 * com os logs da Lambda/API Gateway pelo mesmo id.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
