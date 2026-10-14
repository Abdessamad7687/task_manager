<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Services\TaskOrdering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private function inbox(): Project
    {
        return Project::firstOrFail();
    }

    private function addTask(Project $project, string $name): Task
    {
        return app(TaskOrdering::class)->create(['name' => $name, 'project_id' => $project->id]);
    }

    private function order(Project $project): array
    {
        return $project->tasks()->orderBy('priority')->pluck('name')->all();
    }

    private function reorderPayload(Project $project, array $ids): array
    {
        return [
            'project_id' => $project->id,
            'task_ids' => $ids,
            'version' => app(TaskOrdering::class)->version($project->tasks()->orderBy('priority')->get()),
        ];
    }

    public function test_fresh_installation_has_an_empty_inbox(): void
    {
        $this->get('/')->assertOk()->assertSee('Inbox')->assertSee('No tasks yet.');
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_create_persists_name_priority_project_and_timestamps(): void
    {
        $project = $this->inbox();
        $this->post(route('tasks.store'), ['name' => '  Plan release  ', 'project_id' => $project->id])
            ->assertRedirect(route('tasks.index', ['project_id' => $project->id]));
        $task = Task::firstOrFail();
        $this->assertSame('Plan release', $task->name);
        $this->assertSame(1, $task->priority);
        $this->assertSame($project->id, $task->project_id);
        $this->assertNotNull($task->created_at);
        $this->assertNotNull($task->updated_at);
    }

    public function test_inserting_at_a_priority_shifts_the_remaining_tasks(): void
    {
        $project = $this->inbox();
        $this->addTask($project, 'First');
        $this->addTask($project, 'Second');
        $this->post(route('tasks.store'), ['name' => 'New first', 'project_id' => $project->id, 'priority' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['New first', 'First', 'Second'], $this->order($project));
        $this->assertSame([1, 2, 3], $project->tasks()->orderBy('priority')->pluck('priority')->all());
    }

    public function test_edit_changes_name_and_repositions_task(): void
    {
        $project = $this->inbox();
        $first = $this->addTask($project, 'First');
        $this->addTask($project, 'Second');
        $this->get(route('tasks.edit', $first))->assertOk()->assertSee('First');
        $this->put(route('tasks.update', $first), ['name' => 'Renamed', 'project_id' => $project->id, 'priority' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['Second', 'Renamed'], $this->order($project));
    }

    public function test_deleting_a_task_closes_the_priority_gap(): void
    {
        $project = $this->inbox();
        $this->addTask($project, 'First');
        $middle = $this->addTask($project, 'Second');
        $this->addTask($project, 'Third');
        $this->delete(route('tasks.destroy', $middle))->assertSessionHasNoErrors();
        $this->assertModelMissing($middle);
        $this->assertSame(['First', 'Third'], $this->order($project));
        $this->assertSame([1, 2], $project->tasks()->orderBy('priority')->pluck('priority')->all());
    }

    public function test_reordering_persists_and_leaves_other_projects_untouched(): void
    {
        $project = $this->inbox();
        $first = $this->addTask($project, 'First');
        $second = $this->addTask($project, 'Second');
        $third = $this->addTask($project, 'Third');
        $other = Project::create(['name' => 'Other']);
        $untouched = $this->addTask($other, 'Untouched');
        $this->patchJson(route('tasks.reorder'), $this->reorderPayload($project, [$third->id, $first->id, $second->id]))
            ->assertOk()->assertJsonPath('tasks.0.id', $third->id)->assertJsonPath('tasks.0.priority', 1);
        $this->assertSame(['Third', 'First', 'Second'], $this->order($project));
        $this->assertSame([1, 2, 3], $project->tasks()->orderBy('priority')->pluck('priority')->all());
        $this->assertSame(1, $untouched->refresh()->priority);
        $this->get(route('tasks.index', ['project_id' => $project->id]))->assertSeeInOrder(['Third', 'First', 'Second'])->assertDontSee('Untouched');
    }

    public function test_invalid_orders_are_rejected_without_partial_changes(): void
    {
        $project = $this->inbox();
        $first = $this->addTask($project, 'First');
        $second = $this->addTask($project, 'Second');
        $other = Project::create(['name' => 'Other']);
        $foreign = $this->addTask($other, 'Foreign');
        foreach ([[$first->id], [$first->id, $first->id], [$first->id, $foreign->id], [$first->id, 999999], []] as $ids) {
            $this->patchJson(route('tasks.reorder'), $this->reorderPayload($project, $ids))->assertUnprocessable();
            $this->assertSame(['First', 'Second'], $this->order($project));
            $this->assertSame(2, $second->refresh()->priority);
        }
    }

    public function test_stale_order_is_rejected_after_another_tab_changes_the_list(): void
    {
        $project = $this->inbox();
        $first = $this->addTask($project, 'First');
        $second = $this->addTask($project, 'Second');
        $stale = $this->reorderPayload($project, [$second->id, $first->id]);
        $this->addTask($project, 'Added in another tab');
        $this->patchJson(route('tasks.reorder'), $stale)->assertConflict();
        $this->assertSame(['First', 'Second', 'Added in another tab'], $this->order($project));
    }

    public function test_moving_a_task_to_another_project_normalizes_both_lists(): void
    {
        $source = $this->inbox();
        $moving = $this->addTask($source, 'Moving');
        $this->addTask($source, 'Staying');
        $target = Project::create(['name' => 'Work']);
        $this->addTask($target, 'Existing');
        $this->put(route('tasks.update', $moving), ['name' => 'Moved', 'project_id' => $target->id, 'priority' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Staying'], $this->order($source));
        $this->assertSame(1, $source->tasks()->first()->priority);
        $this->assertSame(['Moved', 'Existing'], $this->order($target));
        $this->assertSame([1, 2], $target->tasks()->orderBy('priority')->pluck('priority')->all());
    }

    public function test_validation_rejects_blank_names_missing_projects_and_invalid_priorities(): void
    {
        $project = $this->inbox();
        foreach ([
            ['name' => '   ', 'project_id' => $project->id],
            ['name' => str_repeat('x', 256), 'project_id' => $project->id],
            ['name' => 'Task', 'project_id' => 99999],
            ['name' => 'Task', 'project_id' => $project->id, 'priority' => 0],
            ['name' => 'Task', 'project_id' => $project->id, 'priority' => 1.5],
            ['name' => 'Task', 'project_id' => $project->id, 'priority' => 100],
        ] as $data) {
            $this->postJson(route('tasks.store'), $data)->assertUnprocessable();
            $this->assertDatabaseCount('tasks', 0);
        }
    }

    public function test_invalid_edit_rolls_back_the_entire_operation(): void
    {
        $source = $this->inbox();
        $task = $this->addTask($source, 'Original');
        $target = Project::create(['name' => 'Empty']);
        $this->putJson(route('tasks.update', $task), ['name' => 'Changed', 'project_id' => $target->id, 'priority' => 2])->assertUnprocessable();
        $this->assertSame('Original', $task->refresh()->name);
        $this->assertSame($source->id, $task->project_id);
        $this->assertSame(1, $task->priority);
    }

    public function test_projects_can_be_created_and_filtered(): void
    {
        $this->addTask($this->inbox(), 'Inbox only');
        $this->post(route('projects.store'), ['name' => '  Website  '])->assertSessionHasNoErrors();
        $project = Project::where('name', 'Website')->firstOrFail();
        $this->addTask($project, 'Website only');
        $this->get(route('tasks.index', ['project_id' => $project->id]))->assertOk()->assertSee('Website only')->assertDontSee('Inbox only');
        $this->post(route('projects.store'), ['name' => 'Website'])->assertSessionHasErrorsIn('project', 'name');
        $this->post(route('projects.store'), ['name' => '   '])->assertSessionHasErrorsIn('project', 'name');
    }

    public function test_task_names_are_escaped_and_missing_tasks_return_404(): void
    {
        $this->addTask($this->inbox(), '<script>alert(1)</script>');
        $this->get('/')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/tasks/99999/edit')->assertNotFound();
        $this->delete('/tasks/99999')->assertNotFound();
    }
}
