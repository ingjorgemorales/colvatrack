<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 10), 100);
        $query = Project::withCount('vehicles')->latest();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('Projects/Index', [
            'projects' => $query->paginate($perPage)->withQueryString(),
            'filters' => $request->only('search', 'status', 'per_page'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Projects/Form', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $vehicleIds = $data['vehicle_ids'] ?? [];
        unset($data['vehicle_ids']);

        DB::transaction(function () use ($data, $vehicleIds) {
            $project = Project::create($data);
            $this->syncVehicles($project, $vehicleIds);
        });

        return redirect()->route('proyectos.index')->with('success', 'Proyecto creado.');
    }

    public function edit(Project $project)
    {
        return Inertia::render('Projects/Form', $this->formData($project));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project);
        $vehicleIds = $data['vehicle_ids'] ?? [];
        unset($data['vehicle_ids']);

        DB::transaction(function () use ($project, $data, $vehicleIds) {
            $project->update($data);
            $this->syncVehicles($project, $vehicleIds);
        });

        return redirect()->route('proyectos.index')->with('success', 'Proyecto actualizado.');
    }

    public function destroy(Project $project)
    {
        $project->update(['status' => 'inactive']);

        return back()->with('success', 'Proyecto inactivado.');
    }

    public function toggleStatus(Project $project)
    {
        $project->update(['status' => $project->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', $project->status === 'active' ? 'Proyecto activado.' : 'Proyecto inactivado.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('projects', 'name')->ignore($project?->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'vehicle_ids' => ['array'],
            'vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
        ]);
    }

    private function formData(?Project $project = null): array
    {
        return [
            'project' => $project?->load('vehicles:id,plate,project_id'),
            'vehicles' => Vehicle::query()
                ->where('status', 'active')
                ->where(fn ($query) => $query
                    ->whereNull('project_id')
                    ->when($project, fn ($q) => $q->orWhere('project_id', $project->id)))
                ->orderBy('plate')
                ->get(['id', 'plate', 'project_id']),
        ];
    }

    private function syncVehicles(Project $project, array $vehicleIds): void
    {
        Vehicle::where('project_id', $project->id)
            ->whereNotIn('id', $vehicleIds)
            ->update(['project_id' => null]);

        if ($vehicleIds) {
            Vehicle::whereIn('id', $vehicleIds)
                ->where(fn ($query) => $query->whereNull('project_id')->orWhere('project_id', $project->id))
                ->update(['project_id' => $project->id]);
        }
    }
}
