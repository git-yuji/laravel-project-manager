<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_only_owned_project_names(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '会社サイトの改修']);
        $project->revisionRequests()->create(['content' => '修正']);
        Project::create(['user_id' => $owner->id, 'name' => '採用ページ', 'description' => '会社サイト']);
        Project::create(['user_id' => User::factory()->create()->id, 'name' => '会社サイトの非公開案件']);

        $this->actingAs($owner)->get(route('projects.index', ['search' => '会社サイト']))
            ->assertOk()->assertSee('会社サイトの改修')->assertSee('修正依頼 1件')
            ->assertDontSee('採用ページ')->assertDontSee('会社サイトの非公開案件')
            ->assertSee('value="会社サイト"', false)->assertSee('検索をクリア');
        $this->get(route('projects.index'))->assertOk()->assertSee('採用ページ')->assertSee('会社サイトの改修');
    }

    public function test_search_preserves_query_when_changing_pages_and_corrects_out_of_range_page(): void
    {
        $owner = User::factory()->create();
        for ($index = 0; $index < 11; $index++) {
            Project::create(['user_id' => $owner->id, 'name' => '検索案件'.$index]);
        }
        Project::create(['user_id' => $owner->id, 'name' => '対象外']);
        $this->actingAs($owner);
        $url = route('projects.index', ['search' => '検索案件']);

        $this->get($url)->assertOk()->assertSee('search='.urlencode('検索案件').'&amp;page=2', false)->assertDontSee('対象外');
        $this->get($url.'&page=2')->assertOk()->assertSee('value="検索案件"', false)
            ->assertViewHas('projects', fn ($projects) => $projects->count() === 1 && $projects->total() === 11);
        $this->get($url.'&page=99')->assertRedirect(route('projects.index', ['search' => '検索案件', 'page' => 2]));
    }

    public function test_no_matches_and_empty_search_have_different_messages(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('projects.index'))->assertOk()
            ->assertSee('まだ案件がありません。最初の案件を登録しましょう。');
        $this->get(route('projects.index', ['search' => '対象なし']))->assertOk()
            ->assertSee('検索条件に一致する案件がありません。')->assertSee('検索をクリア');
        Project::create(['user_id' => $owner->id, 'name' => '案件']);
        $this->get(route('projects.index', ['search' => '   ']))->assertOk()->assertSee('案件')
            ->assertDontSee('検索をクリア')->assertViewHas('projects', fn ($projects) => $projects->total() === 1);
    }

    #[DataProvider('literalSearchTerms')]
    public function test_search_treats_special_characters_as_literal_text(string $search): void
    {
        $owner = User::factory()->create();
        Project::create(['user_id' => $owner->id, 'name' => '対象'.$search.'案件']);
        Project::create(['user_id' => $owner->id, 'name' => '対象外案件']);

        $this->actingAs($owner)->get(route('projects.index', ['search' => $search]))->assertOk()
            ->assertViewHas('projects', fn ($projects) => $projects->total() === 1)
            ->assertSee('対象'.$search.'案件')->assertDontSee('対象外案件')->assertSee($search);
    }

    public static function literalSearchTerms(): array
    {
        return ['percent' => ['%'], 'underscore' => ['_'], 'escape' => ['!'], 'SQL-like text' => ["' OR 1=1 --"],
            'HTML' => ['"><script>alert(1)</script>'], 'zero' => ['0'], 'maximum length' => [str_repeat('案', 100)]];
    }

    public function test_search_rejects_embedded_nul_instead_of_matching_truncated_pattern(): void
    {
        $owner = User::factory()->create();
        Project::create(['user_id' => $owner->id, 'name' => 'foo']);

        $this->actingAs($owner)->getJson(route('projects.index', ['search' => "foo\0bar"]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_project_creation_and_update_reject_embedded_nul_names(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['user_id' => $owner->id, 'name' => '元の案件']);
        $this->actingAs($owner);

        $this->post(route('projects.store'), ['name' => "foo\0bar"])->assertSessionHasErrors('name');
        $this->patch(route('projects.update', $project), ['name' => "foo\0bar"])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => '元の案件']);
    }

    public function test_invalid_search_is_rejected_and_guests_cannot_search(): void
    {
        $url = route('projects.index');
        $this->get($url.'?search=案件')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->getJson($url.'?'.http_build_query(['search' => ['案件']]))->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson($url.'?'.http_build_query(['search' => str_repeat('案', 101)]))->assertUnprocessable()->assertJsonValidationErrors('search');
    }
}
