<?php

namespace TornadoEngine\GamRunner;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use TornadoEngine\Access\Access;
use TornadoEngine\Nav\Nav;

class GamRunnerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gam-runner');
        $this->loadRoutesFrom(__DIR__.'/../routes/gam-runner.php');

        // The setup admin has both; with the Access module, roles can be given either.
        Gate::define('gam-runner.read', fn (User $user) => (bool) $user->is_admin);
        Gate::define('gam-runner.all', fn (User $user) => (bool) $user->is_admin);
        $this->callAfterResolving(Access::class, function (Access $access) {
            $access->permission('gam-runner.read', __('Run read-only GAM commands (info, print, show, report)'), 'GAM Runner');
            $access->permission('gam-runner.all', __('Run any GAM command'), 'GAM Runner');
        });

        $this->callAfterResolving(Nav::class, function (Nav $nav) {
            $nav->add(__('GAM Runner'), 'gam-runner.index', active: 'gam-runner.*', order: 500,
                visible: fn ($user) => $user && Gate::forUser($user)->any(['gam-runner.read', 'gam-runner.all']));
            if (method_exists($nav, 'tool')) {
                $nav->tool(__('GAM Runner'), 'gam-runner.index', __('Run GAM commands against your Google Workspace.'),
                    section: __('Google'), icon: 'terminal', color: 'green',
                    visible: fn ($user) => $user && Gate::forUser($user)->any(['gam-runner.read', 'gam-runner.all']));
            }
        });
    }
}
