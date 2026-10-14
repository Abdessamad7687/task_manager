<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->name)) {
            $request->merge(['name' => trim($request->name)]);
        }
        $data = $request->validateWithBag('project', ['name' => ['required', 'string', 'max:100', 'unique:projects,name']]);
        $project = Project::create($data);

        return to_route('tasks.index', ['project_id' => $project->id])->with('status', 'Project created. Add your first task below.');
    }
}
