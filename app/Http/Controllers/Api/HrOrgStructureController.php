<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterPositionTitle;
use App\Models\MasterDivision;
use App\Models\MasterDepartment;
use App\Models\MasterUnit;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrOrgStructureController extends Controller
{
    private function getModel(string $type)
    {
        return match ($type) {
            'positions' => MasterPositionTitle::class,
            'divisions' => MasterDivision::class,
            'departments' => MasterDepartment::class,
            'units' => MasterUnit::class,
            default => abort(404, 'Tipe struktur tidak ditemukan.'),
        };
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $model = $this->getModel($type);
        return response()->json($model::query()->latest()->get());
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->getModel($type);
        $tableName = (new $model)->getTable();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($tableName, 'name')],
            'is_active' => ['required', 'boolean'],
        ]);

        $record = $model::query()->create($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'MasterOrgStructure',
            'created',
            "Master {$type} #{$record->id}: {$record->name}",
            null,
            $record,
            $model,
            $record->id
        );

        return response()->json(['message' => 'Data berhasil dibuat.', 'data' => $record], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->getModel($type);
        $record = $model::query()->findOrFail($id);
        $tableName = $record->getTable();

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique($tableName, 'name')->ignore($id)],
            'is_active' => ['required', 'boolean'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($record);
        $record->update($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'MasterOrgStructure',
            'updated',
            "Master {$type} #{$record->id}: {$record->name}",
            $beforeAudit,
            $record->fresh(),
            $model,
            $record->id
        );

        return response()->json(['message' => 'Data berhasil diperbarui.', 'data' => $record]);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->getModel($type);
        $record = $model::query()->findOrFail($id);
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($record);
        $subjectId = $record->id;
        $subjectLabel = "Master {$type} #{$record->id}: {$record->name}";

        $record->delete();

        app(HrdAuditLogService::class)->record(
            $request,
            'MasterOrgStructure',
            'deleted',
            $subjectLabel,
            $beforeAudit,
            null,
            $model,
            $subjectId
        );

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
