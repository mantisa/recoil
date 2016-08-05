<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\ReferenceKernel;

use Recoil\Kernel\Api;
use Recoil\Kernel\KernelState;
use Recoil\Kernel\KernelTrait;
use Recoil\Kernel\SystemKernel;
use Recoil\Strand;

final class ReferenceKernel implements SystemKernel
{
    /**
     * Create a new kernel.
     */
    public static function create() : self
    {
        $events = new EventQueue();
        $io = new IO();
        $api = new ReferenceApi($events, $io);

        return new self($events, $io, $api);
    }

    /**
     * Schedule a coroutine for execution on a new strand.
     *
     * Execution begins when the kernel is run; or, if called from within a
     * strand, when that strand cooperates.
     *
     * @param mixed $coroutine The coroutine to execute.
     */
    public function execute($coroutine) : Strand
    {
        $strand = new ReferenceStrand(
            $this,
            $this->api,
            $this->nextId++,
            $coroutine
        );

        $strand->setTerminator(
            $this->events->schedule(
                0,
                function () use ($strand) {
                    $strand->start();
                }
            )
        );

        return $strand;
    }

    private function loop()
    {
        $timeout = null;
        $hasIO = false;

        do {
            if ($timeout !== null && !$hasIO) {
                \usleep($timeout);
            }

            $timeout = $this->events->tick();
            $hasIO = $this->io->tick($timeout);
        } while (
            $this->state === KernelState::RUNNING &&
            ($timeout !== null || $hasIO)
        );
    }

    public function __construct(EventQueue $events, IO $io, Api $api)
    {
        $this->events = $events;
        $this->io = $io;
        $this->api = $api;
    }

    use KernelTrait;

    /**
     * @var EventQueue
     */
    private $events;

    /**
     * @var IO
     */
    private $io;

    /**
     * @var int The next strand ID.
     */
    private $nextId = 1;
}
