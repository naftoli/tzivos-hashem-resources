<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\FileDashboardController;
use App\Http\Controllers\Admin\FileVisibilityController;
use App\Models\PageBlock;
use App\Models\User;
use App\Support\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The Parsha list ("8 features" under each week) counts what the person really sees on that week's page now:
 * Full, the week, each feature's switch, Choose weeks, Remove and who can see. "0 available" when nothing is left.
 */
class ParshaFeatureCountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentSite::forget();
        config(['admin.api_token' => 'site-token', 'admin.site_api' => '/resources/siteApi.php']);
        $date = base_path('../src/data/pages/date.json');
        if (! is_file($date)) {
            $this->markTestSkipped('Needs the site\'s src/data/pages/date.json next to the admin.');
        }
        $dir = sys_get_temp_dir().'/pfc-pages-'.getmypid();
        File::ensureDirectoryExists($dir);
        File::copy($date, $dir.'/date.json');
        $this->artisan('resources:import-pages', ['dir' => $dir])->assertSuccessful();
        File::deleteDirectory($dir);
        $this->admin = User::factory()->create(['email' => 'hq-pfc@example.org', 'role' => 'hq_admin']);
    }

    protected function tearDown(): void
    {
        CurrentSite::forget();
        parent::tearDown();
    }

    public function test_with_everything_on_the_counts_are_the_cards_on_each_week(): void
    {
        $c = $this->counts();
        $this->assertSame('6 features', $c['dpBereishis']);
        $this->assertSame('8 features', $c['dpVayishlach']);   // 7 features + Tanya Baal Peh
        $this->assertSame('8 features', $c['dpHaazinu']);
        $this->assertSame('', $c['dpMishpatim'], 'a week with no Hachayol yet stays blank');
    }

    public function test_full_off_shows_0_available_on_every_week(): void
    {
        $this->save(['full' => false]);
        $c = $this->counts();
        foreach (['dpBereishis', 'dpVayishlach', 'dpYisro', 'dpNitzavimVayeilech', 'dpHaazinu'] as $week) {
            $this->assertSame('0 available', $c[$week], $week);
        }
        $this->assertStringNotContainsString(' features<', $this->page('dParsha'));
        $this->assertSame('', $c['dpMishpatim']);

        $this->save(['full' => true]);
        $this->assertSame('8 features', $this->counts()['dpVayishlach']);
    }

    public function test_a_week_off_shows_0_available_for_that_week_only(): void
    {
        $this->save(['weeks_off' => ['672']]);
        $c = $this->counts();
        $this->assertSame('0 available', $c['dpVayishlach']);
        $this->assertSame('8 features', $c['dpVayechi']);
    }

    public function test_feature_switches_and_choose_weeks_lower_the_count(): void
    {
        $this->save(['features' => ['tanyabaalpeh' => ['on' => false]]]);
        $this->assertSame('7 features', $this->counts()['dpVayishlach'], 'the picture card is counted');

        $this->save(['features' => ['tanyabaalpeh' => ['on' => true], 'orders' => ['on' => false]]]);
        $c = $this->counts();
        $this->assertSame('7 features', $c['dpVayishlach']);
        $this->assertSame('5 features', $c['dpBereishis']);

        $this->save(['features' => ['orders' => ['on' => true, 'weeks' => ['672']]]]);
        $c = $this->counts();
        $this->assertSame('8 features', $c['dpVayishlach']);
        $this->assertSame('5 features', $c['dpBereishis']);
    }

    public function test_one_feature_left_says_1_feature(): void
    {
        $features = array_map(fn ($f) => ['on' => false, 'weeks' => $f['weeks']], $this->switches()['state']['features']);
        $features['orders']['on'] = true;
        $this->save(['features' => $features]);
        $this->assertSame('1 feature', $this->counts()['dpVayishlach']);
    }

    public function test_remove_lowers_the_count_and_restore_brings_it_back(): void
    {
        foreach ([['part', 'i672tanyabaalpeh', '7 features'], ['feature', 'rebbe', '7 features'], ['issue', '672', '0 available']] as [$scope, $key, $after]) {
            $this->actingAs($this->admin)->post(route('admin.files.remove'), ['scope' => $scope, 'key' => $key])->assertRedirect();
            $this->assertSame($after, $this->counts()['dpVayishlach'], "removed $scope $key");
            $this->actingAs($this->admin)->post(route('admin.files.restore'), ['scope' => $scope, 'key' => $key])->assertRedirect();
            $this->assertSame('8 features', $this->counts()['dpVayishlach'], "restored $scope $key");
        }
    }

    public function test_the_count_is_what_this_person_may_see(): void
    {
        User::factory()->create(['email' => 'staff-pfc@example.org', 'role' => 'hq_staff']);
        $this->actingAs($this->admin)->from(route('admin.files'))
            ->put(route('admin.files.update'), ['scope' => 'feature', 'key' => 'orders', 'roles' => ['hq_staff']])->assertRedirect();

        $this->assertSame('7 features', $this->counts()['dpVayishlach'], 'a teacher does not see Orders');
        $this->assertSame('8 features', $this->counts('staff-pfc@example.org')['dpVayishlach'], 'HQ staff does');
    }

    public function test_a_week_added_later_gets_its_count(): void
    {
        $dp = PageBlock::where('block_key', 'dpMishpatim')->first();
        $card = '<button class="card" data-go="i682orders" style="--cc:var(--purple)"><div class="in">Orders</div></button>';
        $dp->update(['html' => str_replace('<div class="note"><b>Awaiting issue</b>', $card.'<div class="note"><b>Awaiting issue</b>', $dp->html)]);
        $this->assertSame('1 feature', $this->counts()['dpMishpatim']);
    }

    // ---- helpers ----

    /** @return array<string, string> week page => what its card on the Parsha list says */
    private function counts(string $email = 'ada@example.org'): array
    {
        preg_match_all('/data-go="(dp[A-Za-z0-9]+)"><b>.*?<\/b><span>(.*?)<\/span>/s', $this->page('dParsha', $email), $m);

        return array_combine($m[1], $m[2]);
    }

    private function page(string $key, string $email = 'ada@example.org'): string
    {
        return (string) $this->withToken('site-token')->withHeaders(['X-Resources-Email' => $email, 'X-Resources-Role' => 'Teacher'])
            ->getJson('/api/content/pages/'.$key)->assertOk()->json('html');
    }

    private function switches(): array
    {
        $c = new class extends FileDashboardController
        {
            public function get(int $id): array
            {
                return $this->structure($id);
            }
        };

        return FileVisibilityController::switchesFor(CurrentSite::id(), ...$c->get(CurrentSite::id()));
    }

    private function save(array $change): array
    {
        $now = $this->switches()['state'];
        $body = array_replace($now, $change);
        if (isset($change['features'])) {
            $body['features'] = array_replace($now['features'], array_map(fn ($f) => $f + ['weeks' => null], $change['features']));
        }

        return $this->actingAs($this->admin)->putJson(route('admin.files.visibility.update'), $body)->assertOk()->json('state');
    }
}
