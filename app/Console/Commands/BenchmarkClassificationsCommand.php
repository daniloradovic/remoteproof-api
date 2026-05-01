<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClassificationService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class BenchmarkClassificationsCommand extends Command
{
    protected $signature = 'classifications:benchmark
        {--fixtures= : Optional path to a fixtures PHP file (defaults to tests/Fixtures/JobListings/fixtures.php)}
        {--threshold=9 : Minimum number of correct verdicts required for the run to pass}
        {--show-signals : Print the signals returned by the model for each fixture}';

    protected $description = 'Run the Task 7 prompt benchmark against real-world style job descriptions.';

    public function handle(ClassificationService $classifier): int
    {
        $fixturesPath = $this->option('fixtures')
            ?: base_path('tests/Fixtures/JobListings/fixtures.php');

        if (! is_string($fixturesPath) || ! is_file($fixturesPath)) {
            $this->error("Fixtures file not found: {$fixturesPath}");

            return self::FAILURE;
        }

        /** @var array<int, array{id:int, source:string, expected:string, note:string, text:string}> $fixtures */
        $fixtures = require $fixturesPath;

        if (! is_array($fixtures) || $fixtures === []) {
            $this->error('Fixtures file did not return a non-empty array.');

            return self::FAILURE;
        }

        $threshold = (int) $this->option('threshold');
        $showSignals = (bool) $this->option('show-signals');

        $rows = [];
        $passed = 0;
        $failed = [];

        foreach ($fixtures as $fixture) {
            $id = $fixture['id'] ?? '?';
            $source = $fixture['source'] ?? '?';
            $expected = strtoupper((string) ($fixture['expected'] ?? ''));
            $note = (string) ($fixture['note'] ?? '');
            $text = (string) ($fixture['text'] ?? '');

            $this->line(sprintf('Classifying #%s (%s, expects %s)...', $id, $source, $expected));

            try {
                $result = $classifier->classify($text);
            } catch (RuntimeException|Throwable $e) {
                $rows[] = [$id, $source, $expected, 'ERROR', '-', 'FAIL', $e->getMessage()];
                $failed[] = $id;

                continue;
            }

            $verdict = $result['verdict'];
            $confidence = $result['confidence'];
            $reason = $result['reason'];
            $matched = $verdict === $expected;

            if ($matched) {
                $passed++;
            } else {
                $failed[] = $id;
            }

            $rows[] = [
                $id,
                $source,
                $expected,
                $verdict,
                $confidence,
                $matched ? 'PASS' : 'FAIL',
                $note.($matched ? '' : ' — '.$reason),
            ];

            if ($showSignals) {
                $this->line('  signals: '.implode(', ', $result['signals']));
            }
        }

        $this->newLine();
        $this->table(
            ['#', 'Source', 'Expected', 'Got', 'Conf.', 'Result', 'Note / Mismatch reason'],
            $rows,
        );

        $total = count($fixtures);
        $this->info(sprintf('Score: %d / %d correct (threshold: %d).', $passed, $total, $threshold));

        if ($failed !== []) {
            $this->warn('Failed fixtures: #'.implode(', #', $failed));
        }

        return $passed >= $threshold ? self::SUCCESS : self::FAILURE;
    }
}
