<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\API\Agent;

use App\Actions\Tenant\Agent\CreateTrunk;
use App\Actions\Tenant\Agent\DeleteTrunk;
use App\Actions\Tenant\Agent\ListTrunks;
use App\Actions\Tenant\Agent\ShowTrunk;
use App\Actions\Tenant\Agent\UpdateTrunk;
use App\Data\Tenant\Agent\CreateTrunkData;
use App\Data\Tenant\Agent\TrunkData;
use App\Data\Tenant\Agent\UpdateTrunkData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Agent\StoreTrunkRequest;
use App\Http\Requests\Tenant\Agent\UpdateTrunkRequest;
use App\Http\Requests\Tenant\API\Agent\IndexTrunkRequest;
use App\Models\Tenant\Agent\Trunk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TrunkController extends Controller
{
    public function index(IndexTrunkRequest $request, ListTrunks $action): JsonResponse
    {
        Gate::authorize('viewAny', Trunk::class);

        $trunks = $action($request->validated());

        return response()->json([
            'data' => $trunks->map(fn (Trunk $trunk) => TrunkData::fromTrunk($trunk)->toArray())->values(),
        ]);
    }

    public function store(StoreTrunkRequest $request, CreateTrunkData $data, CreateTrunk $action): JsonResponse
    {
        Gate::authorize('create', Trunk::class);

        $trunk = $action($data);

        return response()->json([
            'data' => TrunkData::fromTrunk($trunk)->toArray(),
            'message' => 'Trunk created',
        ], 201);
    }

    public function show(Trunk $trunk, ShowTrunk $action): JsonResponse
    {
        Gate::authorize('view', $trunk);

        $trunk = $action($trunk);

        return response()->json([
            'data' => TrunkData::fromTrunk($trunk)->toArray(),
        ]);
    }

    public function update(UpdateTrunkRequest $request, Trunk $trunk, UpdateTrunkData $data, UpdateTrunk $action): JsonResponse
    {
        Gate::authorize('update', $trunk);

        $trunk = $action($trunk, $data);

        return response()->json([
            'data' => TrunkData::fromTrunk($trunk)->toArray(),
            'message' => 'Trunk updated',
        ]);
    }

    public function destroy(Trunk $trunk, DeleteTrunk $action): Response
    {
        Gate::authorize('delete', $trunk);

        $action($trunk);

        return response()->noContent();
    }
}
