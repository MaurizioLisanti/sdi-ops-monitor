<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use App\Command\SqsPollCommand;
use App\Model\Entity\Metric;
use App\Service\SqsPollerService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOutput;
use Cake\TestSuite\TestCase;

/**
 * SqsPollCommandTest — unit tests for SqsPollCommand::execute().
 *
 * All three tests use a PHPUnit mock of SqsPollerService so that no real
 * AWS connection is required. The command under test receives the mock via
 * setPollerService() before execute() is called.
 *
 * Test coverage:
 *   1. Valid message processed → poll() called, CODE_SUCCESS returned.
 *   2. Empty queue            → poll() returns [], CODE_SUCCESS returned.
 *   3. Dry-run mode           → poll() called with dryRun=true, CODE_SUCCESS.
 */
class SqsPollCommandTest extends TestCase
{
    /**
     * Build a SqsPollCommand with the given mock service pre-injected.
     *
     * @param \App\Service\SqsPollerService $mockService Mocked poller service.
     * @return \App\Command\SqsPollCommand
     */
    private function makeCommand(SqsPollerService $mockService): SqsPollCommand
    {
        $command = new SqsPollCommand();
        $command->setPollerService($mockService);

        return $command;
    }

    /**
     * Build a ConsoleArguments instance with default options and optional overrides.
     *
     * @param array<string, mixed> $options Option overrides (e.g. ['dry-run' => true]).
     * @return \Cake\Console\Arguments
     */
    private function makeArgs(array $options = []): Arguments
    {
        // CakePHP Arguments::getOption() returns string|bool|null — pass string for numeric options.
        $defaults = [
            'max-messages' => '10',
            'dry-run'      => false,
        ];

        return new Arguments([], array_merge($defaults, $options), []);
    }

    /**
     * Build a ConsoleIo backed by in-memory output buffers.
     *
     * Using real ConsoleOutput objects instead of mocks avoids strict
     * expectation overhead while still capturing all CLI output.
     *
     * @return \Cake\Console\ConsoleIo
     */
    private function makeIo(): ConsoleIo
    {
        return new ConsoleIo(new ConsoleOutput(), new ConsoleOutput());
    }

    /**
     * When the poller service processes a valid message, execute() must
     * call poll() exactly once and return CODE_SUCCESS (0).
     *
     * @return void
     */
    public function testPollProcessesValidMessage(): void
    {
        $metric = new Metric([
            'source'      => 'aws-ec2-prod-01',
            'name'        => 'cpu_usage',
            'value'       => 72.5,
            'unit'        => 'percent',
            'recorded_at' => '2026-04-08T12:00:00Z',
        ]);

        $mockService = $this->createMock(SqsPollerService::class);
        $mockService
            ->expects($this->once())
            ->method('poll')
            ->with(10, false)
            ->willReturn([$metric]);

        $result = $this->makeCommand($mockService)->execute($this->makeArgs(), $this->makeIo());

        $this->assertSame(
            Command::CODE_SUCCESS,
            $result,
            'Command must return CODE_SUCCESS when a valid message is processed.'
        );
    }

    /**
     * When the SQS queue is empty (poll() returns []), execute() must still
     * return CODE_SUCCESS — an empty queue is not an error condition.
     *
     * @return void
     */
    public function testPollHandlesEmptyQueue(): void
    {
        $mockService = $this->createMock(SqsPollerService::class);
        $mockService
            ->expects($this->once())
            ->method('poll')
            ->with(10, false)
            ->willReturn([]);

        $result = $this->makeCommand($mockService)->execute($this->makeArgs(), $this->makeIo());

        $this->assertSame(
            Command::CODE_SUCCESS,
            $result,
            'Command must return CODE_SUCCESS when the queue is empty.'
        );
    }

    /**
     * When --dry-run is passed, execute() must call poll() with dryRun=true
     * so that the service skips all DB writes and SQS deletes.
     * The command must return CODE_SUCCESS regardless of the returned array.
     *
     * @return void
     */
    public function testPollWithDryRunDoesNotPersist(): void
    {
        $mockService = $this->createMock(SqsPollerService::class);
        $mockService
            ->expects($this->once())
            ->method('poll')
            // Verify the dry-run flag is forwarded to the service — this is the
            // contract that prevents DB writes when --dry-run is active.
            ->with(10, true)
            ->willReturn([]);

        $result = $this->makeCommand($mockService)->execute(
            $this->makeArgs(['dry-run' => true]),
            $this->makeIo()
        );

        $this->assertSame(
            Command::CODE_SUCCESS,
            $result,
            'Command must return CODE_SUCCESS in dry-run mode.'
        );
    }
}
