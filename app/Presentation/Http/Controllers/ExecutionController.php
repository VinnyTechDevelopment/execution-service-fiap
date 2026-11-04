<?php

namespace App\Presentation\Http\Controllers;

use App\Application\Execution\UseCases\AdvanceExecutionUseCase;
use App\Application\Execution\UseCases\QueryExecutionUseCase;
use App\Application\Execution\UseCases\TrackServiceExecutionUseCase;
use App\Domain\Execution\Entities\ExecutionJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExecutionController
{
    public function queue(Request $request, QueryExecutionUseCase $useCase): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([...ExecutionJob::ACTIVE_STATUSES, ExecutionJob::STATUS_FINISHED, ExecutionJob::STATUS_CANCELLED])],
        ]);

        $jobs = $useCase->queue($validated['status'] ?? null);

        return response()->json([
            'data' => array_map(static fn (ExecutionJob $job) => $job->toArray(), $jobs),
            'total' => count($jobs),
        ]);
    }

    public function show(string $serviceOrderId, QueryExecutionUseCase $useCase): JsonResponse
    {
        return response()->json(['execution' => $useCase->show($serviceOrderId)->toArray()]);
    }

    public function averageExecutionTime(QueryExecutionUseCase $useCase): JsonResponse
    {
        return response()->json(['data' => $useCase->averageExecutionTime()]);
    }

    public function diagnosis(string $serviceOrderId, Request $request, AdvanceExecutionUseCase $useCase): JsonResponse
    {
        return $this->advance($serviceOrderId, AdvanceExecutionUseCase::ACTION_DIAGNOSIS, $request, $useCase, 'Diagnostico iniciado.');
    }

    public function repair(string $serviceOrderId, Request $request, AdvanceExecutionUseCase $useCase): JsonResponse
    {
        return $this->advance($serviceOrderId, AdvanceExecutionUseCase::ACTION_REPAIR, $request, $useCase, 'Reparo iniciado.');
    }

    public function finish(string $serviceOrderId, Request $request, AdvanceExecutionUseCase $useCase): JsonResponse
    {
        return $this->advance($serviceOrderId, AdvanceExecutionUseCase::ACTION_FINISH, $request, $useCase, 'Execucao finalizada. OS Service notificado.');
    }

    public function startService(string $serviceOrderId, string $serviceId, TrackServiceExecutionUseCase $useCase): JsonResponse
    {
        return response()->json(['execution' => $useCase->start($serviceOrderId, $serviceId)->toArray()]);
    }

    public function finishService(string $serviceOrderId, string $serviceId, TrackServiceExecutionUseCase $useCase): JsonResponse
    {
        return response()->json(['execution' => $useCase->finish($serviceOrderId, $serviceId)->toArray()]);
    }

    private function advance(string $serviceOrderId, string $action, Request $request, AdvanceExecutionUseCase $useCase, string $message): JsonResponse
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);
        $job = $useCase->execute($serviceOrderId, $action, $validated['notes'] ?? null);

        return response()->json(['execution' => $job->toArray(), 'message' => $message]);
    }
}
