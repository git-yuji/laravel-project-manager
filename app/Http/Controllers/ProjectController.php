<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RevisionRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::where('user_id', $request->user()->id)->withCount('revisionRequests')->latest()->paginate(10);

        return view('projects.index', compact('projects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000']]);
        $project = Project::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('projects.show', $project)->with('success', '案件を登録しました。');
    }

    public function show(Request $request, Project $project)
    {
        $this->checkOwner($request, $project);
        $data = $request->validate(['status' => ['nullable', 'string', Rule::in(array_keys(RevisionRequest::STATUSES))]]);
        $status = $data['status'] ?? null;
        $revisions = $project->revisionRequests()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()->paginate(20)->withQueryString();

        if ($revisions->currentPage() > $revisions->lastPage()) {
            return redirect()->route('projects.show', [
                'project' => $project,
                'status' => $status,
                'page' => $revisions->lastPage(),
            ]);
        }

        return view('projects.show', compact('project', 'revisions', 'status'));
    }

    public function storeRevision(Request $request, Project $project)
    {
        $this->checkOwner($request, $project);
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $project->revisionRequests()->create($data);

        return redirect()->route('projects.show', $project)->with('success', '修正依頼を追加しました。');
    }

    public function updateRevision(Request $request, Project $project, RevisionRequest $revision)
    {
        $this->checkOwner($request, $project);
        abort_unless($revision->project_id === $project->id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RevisionRequest::STATUSES))],
            'filter_status' => ['nullable', 'string', Rule::in(array_keys(RevisionRequest::STATUSES))],
        ]);
        $revision->update(['status' => $data['status']]);

        return redirect()->route('projects.show', [
            'project' => $project,
            'status' => $data['filter_status'] ?? null,
        ])->with('success', '対応状況を更新しました。');
    }

    private function checkOwner(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 404);
    }
}
