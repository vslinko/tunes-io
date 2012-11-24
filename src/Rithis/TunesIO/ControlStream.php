<?php

namespace Rithis\TunesIO;

use React\Stream\WritableStream;

class ControlStream extends WritableStream
{
    /**
     * @var string
     */
    private $buffer = '';

    public function write($data)
    {
        $chunk = $this->buffer . $data;

        if (false !== strpos($chunk, "\n")) {
            $frames = explode("\n", $chunk);

            $chunk = array_pop($frames);

            foreach ($frames as $frame) {
                $this->parseFrame($frame);
            }
        }

        $this->buffer = $chunk;
    }

    private function parseFrame($frame)
    {
        if (false === strpos($frame, ' ')) {
            $command = $frame;
            $argument = null;
        } else {
            list($command, $argument) = explode(' ', $frame, 2);
        }

        $this->emit(strtolower($command), [$argument]);
    }

    public function any(array $events, $listener)
    {
        foreach ($events as $event) {
            $this->on($event, $listener);
        }
    }
}
