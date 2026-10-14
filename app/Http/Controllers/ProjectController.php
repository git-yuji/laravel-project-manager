<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\RevisionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $searchInput = $request->input('search');
        $data = $request->validate(['search' => [
            Rule::requiredIf(is_string($searchInput) && str_contains($searchInput, "\0")),
            'nullable', 'string', 'max:100', 'not_regex:/\x00/',
        ]]);
        $search = $data['search'] ?? '';
        $searchPattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $projects = Project::where('user_id', $request->user()->id)
            ->when($search !== '', fn ($query) => $query->whereRaw("name LIKE ? ESCAPE '!'", [$searchPattern]))
            ->withCount('revisionRequests')->latest()->paginate(10)->appends(['search' => $search]);

        if ($projects->currentPage() > $projects->lastPage()) {
            return redirect()->route('projects.index', ['search' => $search, 'page' => $projects->lastPage()]);
        }

        return view('projects.index', compact('projects', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'not_regex:/\x00/'], 'description' => ['nullable', 'string', 'max:2000']]);
        $project = Project::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('projects.show', $project)->with('success', '案件を登録しました。');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->checkOwner($request, $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/\x00/'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $project->update($data);

        return redirect()->route('projects.show', $project)->with('success', '案件情報を更新しました。');
    }

    public function show(Request $request, Project $project)
    {
        $this->checkOwner($request, $project);
        $data = $request->validate(['status' => ['nullable', 'string', Rule::in(array_keys(RevisionRequest::STATUSES))]]);
        $status = $data['status'] ?? null;
        $revisions = $project->revisionRequests()
            ->with('statusHistories.user')
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
        DB::transaction(function () use ($revision, $data, $request): void {
            if (DB::connection()->getDriverName() === 'sqlite') {
                // SQLite needs a write before reading to serialize competing updates, including on PHP 8.3.
                RevisionRequest::whereKey($revision->id)->toBase()->update(['status' => DB::raw('status')]);
            }

            $revision = RevisionRequest::whereKey($revision->id)->lockForUpdate()->firstOrFail();

            if ($revision->status === $data['status']) {
                return;
            }

            $revision->statusHistories()->create([
                'user_id' => $request->user()->id,
                'from_status' => $revision->status,
                'to_status' => $data['status'],
            ]);
            $revision->update(['status' => $data['status']]);
        });

        return redirect()->route('projects.show', [
            'project' => $project,
            'status' => $data['filter_status'] ?? null,
        ])->with('success', '対応状況を更新しました。');
    }

    public function updateRevisionContent(Request $request, Project $project, RevisionRequest $revision): RedirectResponse
    {
        $this->checkOwner($request, $project);
        abort_unless($revision->project_id === $project->id, 404);
        $contentKey = 'edit_content.'.$revision->id;
        $data = $request->validate([
            $contentKey => ['required', 'string', 'max:2000'],
            'filter_status' => ['nullable', 'string', Rule::in(array_keys(RevisionRequest::STATUSES))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $revision->update(['content' => data_get($data, $contentKey)]);

        return redirect()->route('projects.show', [
            'project' => $project,
            'status' => $data['filter_status'] ?? null,
            'page' => $data['page'] ?? null,
        ])->with('success', '修正依頼の内容を更新しました。');
    }

    private function checkOwner(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 404);
    }
}
