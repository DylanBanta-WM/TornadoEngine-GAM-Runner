<?php

namespace TornadoEngine\GamRunner\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use TornadoEngine\Access\Models\Role;
use TornadoEngine\Gam\Gam;
use TornadoEngine\GamRunner\Command;
use TornadoEngine\GamRunner\Models\Run;

class GamRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(Gam::class, new Gam('http://gam:8000', 'test-token'));
        Http::fake(['gam:8000/run' => Http::response(['exit' => 0, 'stdout' => "primaryEmail,name.fullName\nann@school.test,Ann Teacher\n", 'stderr' => 'Getting all Users'])]);
    }

    private function withRole(string ...$permissions): User
    {
        $role = Role::create(['name' => implode(' ', $permissions)]);
        $role->setPermissions($permissions);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_commands_are_split_like_a_shell_without_one(): void
    {
        $this->assertSame(['update', 'user', 'ann@school.test', 'firstname', 'Mary Ann', 'notes', "it's"],
            Command::parse('gam update user ann@school.test firstname "Mary Ann" notes "it\'s"')->args);
        $this->assertSame(['print', 'users', 'query', 'orgUnitPath=/Staff'], Command::parse("print users query 'orgUnitPath=/Staff'")->args);
        $this->assertSame(['info', 'user', 'a;rm -rf /'], Command::parse('info user "a;rm -rf /"')->args);
        $this->expectExceptionMessage('A quote isn\'t closed.');
        Command::parse('info user "ann');
    }

    public function test_reading_commands_are_recognised(): void
    {
        foreach (['info domain', 'print users fields primaryemail', 'user ann@school.test show filters', 'ou /Staff print users', 'all users print', 'report users'] as $text) {
            $this->assertTrue(Command::parse($text)->readsOnly(), $text);
        }
        foreach (['delete user ann@school.test', 'update user ann@school.test suspended on', 'user ann@school.test delete filters', 'csv users.csv gam info user ~email', 'batch cmds.txt', 'info'] as $text) {
            $this->assertSame($text === 'info', Command::parse($text)->readsOnly(), $text);
        }
    }

    public function test_a_command_runs_and_shows_its_output(): void
    {
        $user = $this->withRole('gam-runner.read');

        $this->actingAs($user)->post('/gam-runner', ['command' => 'gam print users fields primaryemail,fullname'])
            ->assertOk()
            ->assertSee('ann@school.test,Ann Teacher')
            ->assertSee('Download CSV')
            ->assertSee('Getting all Users');

        Http::assertSent(fn ($request) => ($request->data()['args'] ?? null) === ['print', 'users', 'fields', 'primaryemail,fullname']);
        $run = Run::sole();
        $this->assertSame(['print users fields primaryemail,fullname', 0, $user->id], [$run->command, $run->exit, $run->user_id]);
        $this->actingAs($user)->get('/gam-runner')->assertSee('Your recent commands');
    }

    public function test_read_only_people_cant_change_google(): void
    {
        $this->actingAs($this->withRole('gam-runner.read'))->post('/gam-runner', ['command' => 'delete user ann@school.test'])
            ->assertOk()
            ->assertSee('You can only run commands that read');
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/run'));

        $this->actingAs($this->withRole('gam-runner.all'))->post('/gam-runner', ['command' => 'update user ann@school.test suspended on'])->assertOk();
        Http::assertSent(fn ($request) => ($request->data()['args'] ?? null) === ['update', 'user', 'ann@school.test', 'suspended', 'on']);
    }

    public function test_gam_errors_are_shown(): void
    {
        Http::swap(new Factory);
        Http::fake(['gam:8000/run' => Http::response(['error' => 'GAM is busy'], 429)]);

        $this->actingAs($this->withRole('gam-runner.read'))->post('/gam-runner', ['command' => 'info domain'])->assertSee('GAM is busy');
        $this->assertSame('GAM is busy with other commands. Try again in a moment.', Run::sole()->error);
    }

    public function test_only_people_with_a_gam_runner_permission_see_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/gam-runner')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/gam-runner', ['command' => 'info domain'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('GAM Runner');
        $this->actingAs($this->withRole('gam-runner.read'))->get('/dashboard')->assertSee('GAM Runner');
    }

    public function test_it_says_when_gam_isnt_set_up(): void
    {
        $user = $this->withRole('gam-runner.read');
        $this->actingAs($user)->get('/gam-runner')->assertSee('GAM is required for GAM Runner to work, and it isn', false);

        Http::fake(['gam:8000/status' => Http::response(['configured' => true])]);
        $this->actingAs($user)->get('/gam-runner')->assertDontSee('GAM is required for GAM Runner');
    }
}
