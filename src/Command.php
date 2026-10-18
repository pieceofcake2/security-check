<?php
declare(strict_types=1);

namespace PieceOfCake2\SecurityCheck;

use Closure;
use InvalidArgumentException;
use PieceOfCake2\SecurityDast\Command as DastCommand;
use PieceOfCake2\SecuritySast\Command as SastCommand;
use PieceOfCake2\SecuritySca\Command as ScaCommand;

/**
 * The command `security-check`: runs the security checks of pieceofcake2 one after another, each with its own PDF
 * report: `security-sca` (the Composer packages), `security-sast` (the code) and `security-dast` (the running
 * application).
 *
 * Each check reads its settings from its section of the settings file (`config/security.php`) and from its
 * environment variables, as when it runs alone. The exit code is the highest of the checks: 2 when one failed to
 * run, 1 when one found what reaches its `--fail-on`, 0 otherwise.
 */
final class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const ERROR = 2;

    /**
     * The checks, in the order they run
     */
    public const CHECKS = ['sca', 'sast', 'dast'];

    /**
     * The checks by name: each runs its command with the arguments and the environment variables
     *
     * @var array<string, \Closure(array<string>, array<string, mixed>): int>
     */
    private array $checks;

    /**
     * Constructor
     *
     * @param resource $stdout Output
     * @param resource $stderr Error output
     * @param array<string, \Closure(array<string>, array<string, mixed>): int>|null $checks The checks by name, default:
     *  the commands of the packages
     */
    public function __construct(
        private $stdout = STDOUT,
        private $stderr = STDERR,
        ?array $checks = null,
    ) {
        $this->checks = $checks ?? [
            'sca' => fn(array $argv, array $env): int => (new ScaCommand($this->stdout, $this->stderr))->run($argv, $env),
            'sast' => fn(array $argv, array $env): int => (new SastCommand($this->stdout, $this->stderr))->run($argv, $env),
            'dast' => fn(array $argv, array $env): int => (new DastCommand($this->stdout, $this->stderr))->run($argv, $env),
        ];
    }

    /**
     * Runs the checks
     *
     * @param array<string> $argv Arguments, without the name of the command
     * @param array<string, mixed> $env Environment variables, e.g. `$_SERVER`
     * @return int Exit code
     */
    public function run(array $argv, array $env = []): int
    {
        try {
            $options = self::options($argv, $env);
        } catch (InvalidArgumentException $e) {
            fwrite($this->stderr, $e->getMessage() . "\n\n" . self::usage());

            return self::ERROR;
        }
        if ($options === null) {
            fwrite($this->stdout, self::usage());

            return self::SUCCESS;
        }

        $arguments = ['--project=' . $options['project']];
        if ($options['settings'] !== null) {
            $arguments[] = '--settings=' . $options['settings'];
        }
        $codes = [];
        foreach ($options['only'] as $name) {
            fwrite($this->stdout, sprintf("security-%s\n", $name));
            $codes[$name] = ($this->checks[$name] ?? self::missing($name))($arguments, $env);
        }

        $results = [];
        foreach ($codes as $name => $code) {
            $results[] = sprintf('%s %s', $name, match ($code) {
                self::SUCCESS => 'passed',
                self::FAILURE => 'failed',
                default => 'error',
            });
        }
        fwrite($this->stdout, sprintf("Security checks: %s.\n", implode(', ', $results)));

        return $codes === [] ? self::SUCCESS : max($codes);
    }

    /**
     * The options of the arguments and the environment variables, or null for `--help`
     *
     * @param array<string> $argv Arguments
     * @param array<string, mixed> $env Environment variables
     * @return array{project: string, settings: string|null, only: array<string>}|null
     * @throws \InvalidArgumentException When an argument is invalid
     */
    public static function options(array $argv, array $env = []): ?array
    {
        $only = is_scalar($env['SECURITY_CHECK_ONLY'] ?? null) && trim((string)$env['SECURITY_CHECK_ONLY']) !== '' ? (string)$env['SECURITY_CHECK_ONLY'] : null;
        $options = ['project' => '.', 'settings' => null, 'only' => $only];
        foreach ($argv as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                return null;
            }
            if (!preg_match('/\A--(project|settings|only)=(.*)\z/s', $arg, $matches)) {
                throw new InvalidArgumentException(sprintf('Unknown option: %s', $arg));
            }
            $options[$matches[1]] = $matches[2];
        }

        $names = $options['only'] === null ? self::CHECKS : array_values(array_filter(array_map(static fn(string $name): string => strtolower(trim($name)), explode(',', $options['only'])), static fn(string $name): bool => $name !== ''));
        $unknown = array_diff($names, self::CHECKS);
        if ($names === []) {
            throw new InvalidArgumentException(sprintf('--only must name at least one of %s.', implode(', ', self::CHECKS)));
        }
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('--only must be a list of %s: %s', implode(', ', self::CHECKS), implode(', ', $unknown)));
        }

        return [
            'project' => (string)$options['project'],
            'settings' => $options['settings'],
            // In the order of CHECKS, once each
            'only' => array_values(array_intersect(self::CHECKS, $names)),
        ];
    }

    /**
     * A check that is not there
     *
     * @param string $name Name
     * @return \Closure(array<string>, array<string, mixed>): int
     */
    private function missing(string $name): Closure
    {
        return function (array $argv, array $env) use ($name): int {
            fwrite($this->stderr, sprintf("security-check: the check %s is not available.\n", $name));

            return self::ERROR;
        };
    }

    /**
     * The help of the command
     *
     * @return string
     */
    public static function usage(): string
    {
        return <<<'TEXT'
Usage: vendor/bin/security-check [options]

Runs the security checks of the project one after another, each with its PDF report: security-sca (the
Composer packages), security-sast (the code) and security-dast (the running application). Each check reads its
section of the settings file and its environment variables, as when it runs alone.

Options:
  --project=DIR     Directory of the project (default: the current directory)
  --settings=FILE   Settings file (SECURITY_SETTINGS, default: config/security.php or app/Config/security.php)
  --only=LIST       The checks to run, comma separated: sca, sast, dast (SECURITY_CHECK_ONLY, default: all)
  --help            Show this help

Exit code: the highest of the checks: 0 passed, 1 a check found what reaches its --fail-on, 2 error.

TEXT;
    }
}
