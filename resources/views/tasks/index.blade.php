@extends('layouts.app')

@section('content')
    <h1 class="h2">Tasks</h1>
    <p class="text-body-secondary mb-4">Choose a project and put your tasks in order. Priority #1 comes first.</p>

    <div class="row g-4">
        <aside class="col-lg-3">
            <div class="card">
                <div class="card-header">Projects</div>
                <div class="card-body">
                    <form method="get" action="{{ route('tasks.index') }}">
                        <label for="project-filter" class="form-label">Current project</label>
                        <select class="form-select mb-2" name="project_id" id="project-filter" data-project-filter>
                            @foreach ($projects as $item)
                                <option value="{{ $item->id }}" @selected($project?->id === $item->id)>{{ $item->name }} ({{ $item->tasks_count }})</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" type="submit">View tasks</button>
                    </form>
                    <hr>
                    <details @if ($errors->project->any()) open @endif>
                        <summary class="text-primary">New project</summary>
                        <form method="post" action="{{ route('projects.store') }}" class="mt-3">
                            @csrf
                            <label for="project-name" class="form-label">Project name</label>
                            <input class="form-control @error('name', 'project') is-invalid @enderror" id="project-name" name="name" maxlength="100" required value="{{ $errors->project->any() ? old('name') : '' }}" @error('name', 'project') aria-invalid="true" aria-describedby="project-name-error" @enderror>
                            @error('name', 'project')<div class="invalid-feedback" id="project-name-error">{{ $message }}</div>@enderror
                            <button class="btn btn-primary btn-sm mt-2" type="submit">Create project</button>
                        </form>
                    </details>
                </div>
            </div>
        </aside>

        <section class="col-lg-9" aria-labelledby="project-heading">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between gap-3">
                    <h2 id="project-heading" class="h5 mb-0 text-break">{{ $project?->name ?? 'Your tasks' }}</h2>
                    <span class="badge text-bg-secondary">{{ $tasks->count() }} {{ Str::plural('task', $tasks->count()) }}</span>
                </div>
                @if ($project)
                    <div class="card-body border-bottom">
                        <form method="post" action="{{ route('tasks.store') }}" class="row g-3 align-items-start">
                            @csrf
                            <input type="hidden" name="project_id" value="{{ $project->id }}">
                            <div class="col-sm-8">
                                <label for="task-name" class="form-label">Task name</label>
                                <input class="form-control @error('name') is-invalid @enderror" id="task-name" name="name" value="{{ $errors->project->any() ? '' : old('name') }}" placeholder="What needs to get done?" required maxlength="255" @error('name') aria-invalid="true" aria-describedby="task-name-error" @enderror>
                                @error('name')<div class="invalid-feedback" id="task-name-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="task-priority" class="form-label">Priority</label>
                                <input class="form-control @error('priority') is-invalid @enderror" type="number" name="priority" id="task-priority" min="1" max="{{ $tasks->count() + 1 }}" value="{{ old('priority', $tasks->count() + 1) }}" required @error('priority') aria-invalid="true" aria-describedby="task-priority-error" @enderror>
                                @error('priority')<div class="invalid-feedback" id="task-priority-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12"><button type="submit" class="btn btn-primary">Add task</button></div>
                        </form>
                    </div>
                    <div class="card-body py-2 d-flex justify-content-between gap-3 text-body-secondary small">
                        <span>Drag a task up or down to set its priority</span>
                        <span id="save-status" role="status" aria-live="polite"></span>
                    </div>
                    <div id="reorder-error" class="alert alert-danger mx-3" role="alert" hidden><span></span> <a class="alert-link" href="{{ route('tasks.index', ['project_id' => $project->id]) }}">Reload tasks</a></div>
                    <ol class="list-group list-group-flush list-unstyled mb-0" id="task-list" data-project-id="{{ $project->id }}" data-version="{{ $version }}" data-reorder-url="{{ route('tasks.reorder') }}">
                        @foreach ($tasks as $task)
                            <li class="list-group-item task-row" data-id="{{ $task->id }}">
                                <div class="row g-2 align-items-center py-1">
                                    <div class="col-auto"><span class="drag-handle text-body-secondary fs-4 user-select-none" title="Drag to reorder" aria-hidden="true">⠿</span></div>
                                    <div class="col-auto"><span class="priority-badge badge text-bg-primary" aria-label="Priority {{ $task->priority }}">#{{ $task->priority }}</span></div>
                                    <div class="col">
                                        <div class="fw-medium text-break">{{ $task->name }}</div>
                                        <small class="text-body-secondary">Added <time datetime="{{ $task->created_at->toISOString() }}">{{ $task->created_at->format('M j, Y') }}</time></small>
                                    </div>
                                    <div class="col-12 col-md-auto d-flex align-items-center justify-content-end gap-2">
                                        <div class="btn-group btn-group-sm" hidden data-move-actions>
                                            <button type="button" data-move="up" class="btn btn-outline-secondary" aria-label="Move {{ $task->name }} up" title="Move up">↑</button>
                                            <button type="button" data-move="down" class="btn btn-outline-secondary" aria-label="Move {{ $task->name }} down" title="Move down">↓</button>
                                        </div>
                                        <a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-primary btn-sm" aria-label="Edit {{ $task->name }}">Edit</a>
                                        <form method="post" action="{{ route('tasks.destroy', $task) }}" data-delete-form>
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Delete {{ $task->name }}">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @if ($tasks->isEmpty())
                        <div class="card-body text-center py-5"><h3 class="h5">No tasks yet.</h3><p class="text-body-secondary mb-0">Add your first task using the form above.</p></div>
                    @else
                        <div class="card-footer small text-body-secondary">Reordering saves automatically. You can also use the arrow buttons or edit a task’s priority.</div>
                    @endif
                    <noscript><p class="small text-body-secondary m-3">To reorder tasks without JavaScript, choose Edit and change the priority.</p></noscript>
                @else
                    <div class="card-body"><p class="mb-0">Create a project to add your first task.</p></div>
                @endif
            </div>
        </section>
    </div>
@endsection
