<?php

namespace TornadoEngine\GamRunner;

use InvalidArgumentException;

/**
 * A GAM command typed on the page: split into arguments like a shell would (quotes and
 * backslashes), but never run through a shell.
 */
final class Command
{
    /** Commands that only read. */
    private const READING = ['info', 'print', 'show', 'report'];

    /** What can come before the action: gam user ann@example.org show filters. */
    private const SELECTORS = ['user', 'users', 'group', 'group_ns', 'group_susp', 'group_users', 'ou', 'ou_ns', 'ou_susp', 'ou_and_children', 'ou_and_children_ns', 'ou_and_children_susp', 'ous', 'ous_and_children', 'query', 'queries', 'cros', 'crosquery', 'courses', 'license', 'licenses', 'domains', 'select'];

    /**
     * @param  list<string>  $args
     */
    private function __construct(public readonly array $args) {}

    public static function parse(string $text): self
    {
        $text = preg_replace('/^\s*gam\s+/i', '', trim($text));
        $args = [];
        $current = null;
        $quote = null;

        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];

            if ($quote === null && ctype_space($char)) {
                if ($current !== null) {
                    $args[] = $current;
                    $current = null;
                }

                continue;
            }
            if ($char === '\\' && $quote !== "'" && $i + 1 < $length) {
                $current .= $text[++$i];

                continue;
            }
            if ($char === '"' || $char === "'") {
                if ($quote === null) {
                    $quote = $char;
                    $current ??= '';

                    continue;
                }
                if ($quote === $char) {
                    $quote = null;

                    continue;
                }
            }
            $current .= $char;
        }

        if ($quote !== null) {
            throw new InvalidArgumentException(__('A quote isn\'t closed.'));
        }
        if ($current !== null) {
            $args[] = $current;
        }
        if (! $args) {
            throw new InvalidArgumentException(__('Type a GAM command.'));
        }

        return new self($args);
    }

    /**
     * Whether the command only reads: it starts with info, print, show or report, or with a
     * selector (user ann@…, ou /Staff, all users) followed by one of those.
     */
    public function readsOnly(): bool
    {
        $words = array_map('strtolower', $this->args);

        $action = match (true) {
            in_array($words[0], self::READING, true) => $words[0],
            $words[0] === 'all' && isset($words[2]) => $words[2],
            in_array($words[0], self::SELECTORS, true) && isset($words[2]) => $words[2],
            default => null,
        };

        return in_array($action, self::READING, true);
    }

    public function __toString(): string
    {
        return implode(' ', array_map(fn ($arg) => preg_match('/^[\w@.\/:=,+-]+$/', $arg) ? $arg : '"'.addcslashes($arg, '"\\').'"', $this->args));
    }
}
