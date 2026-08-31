<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterBusinessUnit;
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
    private function resolveType(Request $request, mixed ...$args): string
    {
        $fromRoute = $request->route('type');
        if (is_string($fromRoute) && ! is_numeric($fromRoute)) {
            return $fromRoute;
        }

        foreach ($args as $arg) {
            if (is_string($arg) && ! is_numeric($arg) && in_array($arg, ['business-units', 'business_units', 'positions', 'divisions', 'departments', 'units'], true)) {
                return $arg;
            }
        }

        $path = $request->path();
        if (str_contains($path, 'business-units') || str_contains($path, 'business_units')) {
            return 'business-units';
        }
        if (str_contains($path, 'positions')) {
            return 'positions';
        }
        if (str_contains($path, 'divisions')) {
            return 'divisions';
        }
        if (str_contains($path, 'departments')) {
            return 'departments';
        }
        if (str_contains($path, 'units')) {
            return 'units';
        }

        abort(404, 'Tipe struktur tidak ditemukan.');
    }

    private function resolveId(mixed ...$args): int
    {
        foreach ($args as $arg) {
            if (is_numeric($arg)) {
                return (int) $arg;
            }
        }
        abort(400, 'ID tidak valid.');
    }

    private function getModel(string $type): string
    {
        return match ($type) {
            'business-units', 'business_units' => MasterBusinessUnit::class,
            'positions' => MasterPositionTitle::class,
            'divisions' => MasterDivision::class,
            'departments' => MasterDepartment::class,
            'units' => MasterUnit::class,
            default => abort(404, "Tipe struktur '{$type}' tidak ditemukan."),
        };
    }

    public function index(Request $request, mixed $type = null): JsonResponse
    {
        $type = $this->resolveType($request, $type);
        $model = $this->getModel($type);
        return response()->json($model::query()->latest()->get());
    }

    public function store(Request $request, mixed $type = null): JsonResponse
    {
        $type = $this->resolveType($request, $type);
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

    public function update(Request $request, mixed $param1 = null, mixed $param2 = null): JsonResponse
    {
        $type = $this->resolveType($request, $param1, $param2);
        $id = $this->resolveId($param1, $param2);
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

    public function destroy(Request $request, mixed $param1 = null, mixed $param2 = null): JsonResponse
    {
        $type = $this->resolveType($request, $param1, $param2);
        $id = $this->resolveId($param1, $param2);
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
