<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskOrdering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request, TaskOrdering $ordering): View
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer', 'exists:projects,id']]);
        $projects = Project::withCount('tasks')->orderBy('id')->get();
        $project = isset($data['project_id']) ? $projects->firstWhere('id', (int) $data['project_id']) : $projects->first();
        $tasks = $project ? $project->tasks()->orderBy('priority')->orderBy('id')->get() : collect();

        return view('tasks.index', ['projects' => $projects, 'project' => $project, 'tasks' => $tasks, 'version' => $ordering->version($tasks)]);
    }

    public function store(SaveTaskRequest $request, TaskOrdering $ordering): RedirectResponse
    {
        $task = $ordering->create($request->validated());

        return to_route('tasks.index', ['project_id' => $task->project_id])->with('status', 'Task created.');
    }

    public function edit(Task $task): View
    {
        return view('tasks.edit', ['task' => $task, 'projects' => Project::withCount('tasks')->orderBy('id')->get()]);
    }

    public function update(SaveTaskRequest $request, Task $task, TaskOrdering $ordering): RedirectResponse
    {
        $task = $ordering->update($task, $request->validated());

        return to_route('tasks.index', ['project_id' => $task->project_id])->with('status', 'Task updated.');
    }

    public function destroy(Task $task, TaskOrdering $ordering): RedirectResponse
    {
        $ordering->delete($task);

        return to_route('tasks.index', ['project_id' => $task->project_id])->with('status', 'Task deleted.');
    }

    public function reorder(Request $request, TaskOrdering $ordering): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_ids' => ['present', 'array'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
            'version' => ['required', 'string', 'size:64'],
        ]);
        $tasks = $ordering->reorder((int) $data['project_id'], $data['task_ids'], $data['version']);

        return response()->json(['tasks' => $tasks->map->only(['id', 'priority']), 'version' => $ordering->version($tasks)]);
    }
}
