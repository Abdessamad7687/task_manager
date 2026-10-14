@extends('layouts.app')
@section('title', 'Edit task')

@section('content')
    <a href="{{ route('tasks.index', ['project_id' => $task->project_id]) }}" class="d-inline-block mb-3">← Back to tasks</a>
    <h1 class="h2 mb-4">Edit task</h1>
    <div class="row">
        <section class="col-lg-8">
            <div class="card card-body">
                <form method="post" action="{{ route('tasks.update', $task) }}" id="edit-task-form" data-original-project="{{ $task->project_id }}" data-original-priority="{{ $task->priority }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label for="task-name" class="form-label">Task name</label>
                        <input class="form-control @error('name') is-invalid @enderror" name="name" id="task-name" value="{{ old('name', $task->name) }}" required maxlength="255" autofocus @error('name') aria-invalid="true" aria-describedby="task-name-error" @enderror>
                        @error('name')<div id="task-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-8">
                            <label for="edit-project" class="form-label">Project</label>
                            <select class="form-select @error('project_id') is-invalid @enderror" name="project_id" id="edit-project" @error('project_id') aria-invalid="true" aria-describedby="project-error" @enderror>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" data-max="{{ $project->tasks_count + ($project->id === $task->project_id ? 0 : 1) }}" @selected((int) old('project_id', $task->project_id) === $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')<div class="invalid-feedback" id="project-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-4">
                            <label for="edit-priority" class="form-label">Priority</label>
                            <input class="form-control @error('priority') is-invalid @enderror" type="number" id="edit-priority" name="priority" min="1" value="{{ old('priority', $task->priority) }}" required aria-describedby="priority-help @error('priority') priority-error @enderror" @error('priority') aria-invalid="true" @enderror>
                            @error('priority')<div class="invalid-feedback" id="priority-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <p class="form-text mt-3" id="priority-help">#1 is the top of the list. Other tasks shift automatically to make room.</p>
                    <dl class="row small text-body-secondary border-top pt-3 mt-4">
                        <dt class="col-sm-4">Created</dt><dd class="col-sm-8">{{ $task->created_at->format('M j, Y · H:i') }} UTC</dd>
                        <dt class="col-sm-4">Last updated</dt><dd class="col-sm-8">{{ $task->updated_at->format('M j, Y · H:i') }} UTC</dd>
                    </dl>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">
                            <svg class="align-text-bottom me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 12 4 4L19 6"/></svg>Save changes
                        </button>
                        <a class="btn btn-outline-secondary" href="{{ route('tasks.index', ['project_id' => $task->project_id]) }}">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
