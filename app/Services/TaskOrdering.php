<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskOrdering
{
    public function create(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $this->lockProjects([$data['project_id']]);
            $tasks = $this->tasks($data['project_id']);
            $priority = $this->priority($data['priority'] ?? $tasks->count() + 1, $tasks->count() + 1);
            $task = Task::create(['name' => $data['name'], 'project_id' => $data['project_id'], 'priority' => $priority]);
            $tasks->splice($priority - 1, 0, [$task]);
            $this->persist($tasks);

            return $task->refresh();
        }, 3);
    }

    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $sourceId = $task->project_id;
            $targetId = (int) $data['project_id'];
            $this->lockProjects([$sourceId, $targetId]);
            $fresh = Task::query()->lockForUpdate()->findOrFail($task->id);
            abort_if($fresh->project_id !== $sourceId, 409, 'This task moved to another project. Reload and try again.');
            $source = $this->tasks($sourceId)->reject(fn (Task $item) => $item->id === $task->id)->values();
            $target = $sourceId === $targetId ? $source : $this->tasks($targetId);
            $priority = $this->priority($data['priority'] ?? $target->count() + 1, $target->count() + 1);
            $fresh->update(['name' => $data['name'], 'project_id' => $targetId, 'priority' => $priority]);
            if ($sourceId !== $targetId) {
                $this->persist($source);
            }
            $target->splice($priority - 1, 0, [$fresh]);
            $this->persist($target);

            return $fresh->refresh();
        }, 3);
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $this->lockProjects([$task->project_id]);
            $fresh = Task::query()->lockForUpdate()->findOrFail($task->id);
            abort_if($fresh->project_id !== $task->project_id, 409, 'This task moved to another project. Reload and try again.');
            $fresh->delete();
            $this->persist($this->tasks($task->project_id));
        }, 3);
    }

    public function reorder(int $projectId, array $ids, string $version): Collection
    {
        return DB::transaction(function () use ($projectId, $ids, $version) {
            $this->lockProjects([$projectId]);
            $tasks = $this->tasks($projectId);
            abort_unless(hash_equals($this->version($tasks), $version), 409, 'The task list changed. Reload before reordering.');
            $ids = array_map('intval', $ids);
            $expected = $tasks->modelKeys();
            $submitted = $ids;
            sort($expected);
            sort($submitted);
            if ($expected !== $submitted) {
                throw ValidationException::withMessages(['task_ids' => 'Send every task in this project exactly once.']);
            }
            $byId = $tasks->keyBy('id');
            $ordered = collect($ids)->map(fn (int $id) => $byId->get($id));
            $this->persist($ordered);

            return $this->tasks($projectId);
        }, 3);
    }

    public function version(Collection $tasks): string
    {
        return hash('sha256', $tasks->map(fn (Task $task) => [
            $task->id, $task->name, $task->priority, $task->updated_at?->toISOString(),
        ])->values()->toJson());
    }

    private function tasks(int $projectId): Collection
    {
        return Task::where('project_id', $projectId)->orderBy('priority')->orderBy('id')->get();
    }

    private function lockProjects(array $ids): void
    {

        Project::whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
    }

    private function priority(int $priority, int $maximum): int
    {
        if ($priority < 1 || $priority > $maximum) {
            throw ValidationException::withMessages(['priority' => "Choose a priority between 1 and {$maximum}."]);
        }

        return $priority;
    }

    private function persist(Collection $tasks): void
    {
        foreach ($tasks->values() as $index => $task) {
            if ($task->priority !== $index + 1) {
                $task->update(['priority' => $index + 1]);
            }
        }
    }
}
