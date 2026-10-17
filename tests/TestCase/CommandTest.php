<?php
declare(strict_types=1);

namespace PieceOfCake2\SecurityCheck\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\SecurityCheck\Command;

class CommandTest extends TestCase
{
    /**
     * Runs the command with checks that record their calls and return the given exit codes
     *
     * @param array<string> $argv Arguments
     * @param array<string, int> $codes Exit codes by check
     * @param array<string> $calls Receives the calls
     * @param string $output Receives the output
     * @param array<string, mixed> $env Environment variables
     * @return int
     */
    private static function runChecks(array $argv, array $codes, array &$calls = [], string &$output = '', array $env = []): int
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $checks = [];
        foreach (Command::CHECKS as $name) {
            $checks[$name] = static function (array $arguments, array $environment) use ($name, $codes, &$calls): int {
                $calls[] = $name . ' ' . implode(' ', $arguments);

                return $codes[$name] ?? Command::SUCCESS;
            };
        }
        $code = (new Command($stdout, $stderr, $checks))->run($argv, $env);
        rewind($stdout);
        rewind($stderr);
        $output = stream_get_contents($stdout) . stream_get_contents($stderr);

        return $code;
    }

    public function testRunsTheChecksInOrder(): void
    {
        $calls = [];
        $output = '';

        $code = self::runChecks(['--project=app', '--settings=config/security.php'], [], $calls, $output);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame([
            'sca --project=app --settings=config/security.php',
            'sast --project=app --settings=config/security.php',
            'dast --project=app --settings=config/security.php',
        ], $calls);
        $this->assertStringContainsString("security-sca\n", $output);
        $this->assertStringContainsString('Security checks: sca passed, sast passed, dast passed.', $output);
    }

    public function testExitCodeIsTheHighest(): void
    {
        $output = '';
        $calls = [];
        $this->assertSame(Command::FAILURE, self::runChecks([], ['sast' => Command::FAILURE], $calls, $output));
        $this->assertStringContainsString('sca passed, sast failed, dast passed', $output);

        $this->assertSame(Command::ERROR, self::runChecks([], ['sca' => Command::FAILURE, 'dast' => Command::ERROR], $calls, $output));
        $this->assertStringContainsString('sca failed, sast passed, dast error', $output);
    }

    public function testOnly(): void
    {
        $calls = [];
        self::runChecks(['--only=dast, SCA'], [], $calls);
        // In the order of the checks
        $this->assertSame(['sca --project=.', 'dast --project=.'], $calls);

        $calls = [];
        $output = '';
        self::runChecks([], [], $calls, $output, ['SECURITY_CHECK_ONLY' => 'sast']);
        $this->assertSame(['sast --project=.'], $calls);
    }

    public function testOptions(): void
    {
        $this->assertNull(Command::options(['--help']));
        $this->assertSame(['project' => '.', 'settings' => null, 'only' => ['sca', 'sast', 'dast']], Command::options([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--only must be a list of sca, sast, dast: iast');
        Command::options(['--only=sca,iast']);
    }

    public function testUnknownOption(): void
    {
        $output = '';
        $calls = [];

        $this->assertSame(Command::ERROR, self::runChecks(['--pdf=x.pdf'], [], $calls, $output));
        $this->assertStringContainsString('Unknown option: --pdf=x.pdf', $output);
        $this->assertSame([], $calls);
    }

    public function testMissingCheck(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        $code = (new Command($stdout, $stderr, []))->run(['--only=sca']);

        rewind($stderr);
        $this->assertSame(Command::ERROR, $code);
        $this->assertStringContainsString('the check sca is not available', (string)stream_get_contents($stderr));
    }

    public function testUsage(): void
    {
        $output = '';
        $calls = [];

        $this->assertSame(Command::SUCCESS, self::runChecks(['--help'], [], $calls, $output));
        $this->assertStringContainsString('Usage: vendor/bin/security-check', $output);
    }

    public function testTheChecksOfThePackages(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');

        // The commands of the packages, with an option that each refuses: they run, and fail
        $code = (new Command($stdout, $stderr))->run(['--project=' . TMP . 'none', '--only=sca']);

        rewind($stderr);
        $this->assertSame(Command::ERROR, $code);
        $this->assertStringContainsString('composer.lock is not found', (string)stream_get_contents($stderr));
    }
}
