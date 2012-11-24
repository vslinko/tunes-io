<?php

namespace Rithis\TunesIO;

use React\EventLoop\LoopInterface,
    React\HttpClient\Response,
    React\HttpClient\Client,
    Evenement\EventEmitter;

use Rithis\XSPF\Track;

class DownloadProcess extends EventEmitter
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var \React\HttpClient\Client
     */
    private $client;

    /**
     * @var int
     */
    private $threads;

    /**
     * @var array
     */
    private $queue = [];

    /**
     * @var array
     */
    private $currentTasks = [];

    /**
     * @var bool
     */
    private $running = false;

    public function __construct(LoopInterface $loop, Client $client, $threads = 1)
    {
        $this->loop = $loop;
        $this->client = $client;
        $this->threads = $threads;
    }

    public function add(DownloadTask $track)
    {
        array_push($this->queue, $track);
    }

    public function run()
    {
        if (!$this->running) {
            $this->running = true;

            for ($i = 0; $i < $this->threads; $i++) {
                $this->check();
            }
        }
    }

    public function getCurrentTasks()
    {
        return $this->currentTasks;
    }

    private function check()
    {
        if (count($this->queue) == 0) {
            $this->loop->addTimer(1, function () {
                $this->check();
            });
        } else {
            $this->download();
        }
    }

    private function download()
    {
        /** @var $task \Rithis\TunesIO\DownloadTask */
        $task = array_shift($this->queue);

        $location = $task->getLocation();

        if ($location) {
            $this->emit('task', [$task]);

            $request = $this->client->request('GET', $location);

            $request->on('response', function (Response $response) use ($task) {
                array_push($this->currentTasks, $task);

                $destination = $task->openDestinationStream();

                $response->on('data', function ($data) use ($destination) {
                    $destination->write($data);
                });

                $response->on('end', function ($error, Response $response) use ($destination, $task) {
                    $destination->end();

                    $this->currentTasks = array_filter($this->currentTasks, function (DownloadTask $t) use ($task) {
                        return $t !== $task;
                    });

                    $this->emit($error === null && $response->getCode() == 200 ? 'track' : 'error', [$task->getTrack()]);

                    $this->check();
                });
            });

            $request->end();
        }
    }
}
