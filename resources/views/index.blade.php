@php
    $csv = $result && $result['exit'] === 0 && substr_count(strtok($result['stdout'], "\n") ?: '', ',') > 0;
@endphp

<x-admin-layout :title="__('GAM Runner')">
    @unless ($gamReady)
        <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <p>{{ __('GAM is required for GAM Runner to work, and it isn\'t set up yet.') }}
                @can('administration')
                    <a href="{{ url('/administration/gam') }}" class="font-medium underline">{{ __('Set up GAM') }}</a>
                @endcan
            </p>
        </div>
    @endunless

    <div x-data="{ command: @js($command) }" @class(['grid grid-cols-1 items-start gap-6', 'lg:grid-cols-3' => $history->isNotEmpty()])>
        <div class="space-y-6 lg:col-span-2">
            <x-settings-card :title="__('Run a GAM command')">
                <form method="POST" action="{{ route('gam-runner.run') }}" class="space-y-3">
                    @csrf
                    <div>
                        <x-input-label for="command" :value="__('Command')" />
                        <div class="mt-1 flex items-stretch gap-3">
                            <div class="flex min-w-0 flex-1 items-stretch rounded-md shadow-sm">
                                <span class="inline-flex items-center rounded-s-md border border-e-0 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3 font-mono text-gray-500 dark:text-gray-400">gam</span>
                                <input id="command" name="command" type="text" x-model="command" required autocapitalize="off" autocomplete="off" spellcheck="false"
                                       placeholder="info user name@example.org"
                                       class="block w-full min-w-0 rounded-e-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 font-mono text-base focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <x-primary-button class="shrink-0">{{ __('Run') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </x-settings-card>

            @if ($error)
                <p class="rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3 text-sm text-red-800 dark:text-red-300">{{ $error }}</p>
            @endif

            @if ($result)
                <x-settings-card :title="$result['exit'] === 0 ? __('Output') : __('GAM failed (exit code :code)', ['code' => $result['exit']])">
                    <div x-data="{ download(text, name, type) { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], { type })); a.download = name; a.click(); URL.revokeObjectURL(a.href); } }" class="space-y-3">
                        @if ($result['stdout'] !== '')
                            <pre class="max-h-[60vh] overflow-auto rounded-md bg-gray-900 p-3 font-mono text-xs text-gray-100">{{ $result['stdout'] }}</pre>
                            <div class="flex justify-end">
                                <x-secondary-button type="button" x-on:click="download({{ Js::from($result['stdout']) }}, {{ Js::from($csv ? 'gam-output.csv' : 'gam-output.txt') }}, {{ Js::from($csv ? 'text/csv' : 'text/plain') }})">
                                    {{ $csv ? __('Download CSV') : __('Download') }}
                                </x-secondary-button>
                            </div>
                        @endif
                        @if (trim($result['stderr']) !== '')
                            <details @if ($result['exit'] !== 0 || $result['stdout'] === '') open @endif>
                                <summary class="flex min-h-11 cursor-pointer items-center text-gray-600 dark:text-gray-400">{{ __('Messages from GAM') }}</summary>
                                <pre class="max-h-80 overflow-auto rounded-md bg-gray-100 dark:bg-gray-900 p-3 font-mono text-xs whitespace-pre-wrap">{{ $result['stderr'] }}</pre>
                            </details>
                        @endif
                    </div>
                </x-settings-card>
            @endif
        </div>

        @if ($history->isNotEmpty())
            <x-settings-card :title="__('Your recent commands')">
                <ul class="divide-y divide-gray-200 rounded-md border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    @foreach ($history as $run)
                        <li>
                            <button type="button" x-on:click="command = @js($run->command); $nextTick(() => document.getElementById('command').focus())"
                                    class="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-start hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <code class="min-w-0 break-words font-mono">{{ $run->command }}</code>
                                <span @class(['shrink-0 text-xs', 'text-green-700 dark:text-green-400' => $run->exit === 0, 'text-red-700 dark:text-red-400' => $run->exit !== 0])>{{ $run->created_at->diffForHumans(short: true) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-settings-card>
        @endif
    </div>
</x-admin-layout>
