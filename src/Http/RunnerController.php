<?php

namespace TornadoEngine\GamRunner\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;
use TornadoEngine\Gam\Gam;
use TornadoEngine\GamRunner\Command;
use TornadoEngine\GamRunner\Models\Run;

class RunnerController extends Controller
{
    public function index(Request $request): View
    {
        return $this->page($request);
    }

    public function run(Request $request, Gam $gam): View
    {
        $this->allowed();
        $text = (string) $request->validate(['command' => ['required', 'string', 'max:5000']])['command'];

        try {
            $command = Command::parse($text);
        } catch (InvalidArgumentException $e) {
            return $this->page($request, $text, error: $e->getMessage());
        }

        if (! $command->readsOnly() && Gate::denies('gam-runner.all')) {
            return $this->page($request, $text, error: __('You can only run commands that read (info, print, show, report).'));
        }

        $started = microtime(true);
        try {
            $result = $gam->run($command->args);
            $error = null;
        } catch (Throwable $e) {
            $result = null;
            $error = $e->getMessage();
        }

        Run::create([
            'user_id' => $request->user()->id,
            'command' => (string) $command,
            'exit' => $result['exit'] ?? null,
            'error' => $error ? mb_substr($error, 0, 255) : null,
            'seconds' => (int) round(microtime(true) - $started),
        ]);

        return $this->page($request, $text, $result, $error);
    }

    /**
     * @param  array{exit: int, stdout: string, stderr: string}|null  $result
     */
    private function page(Request $request, string $command = '', ?array $result = null, ?string $error = null): View
    {
        $this->allowed();

        return view('gam-runner::index', [
            'command' => $command,
            'result' => $result,
            'error' => $error,
            'readOnly' => Gate::denies('gam-runner.all'),
            'history' => Run::where('user_id', $request->user()->id)->latest('id')->take(10)->get(),
        ]);
    }

    private function allowed(): void
    {
        abort_unless(Gate::any(['gam-runner.read', 'gam-runner.all']), 403);
    }
}
