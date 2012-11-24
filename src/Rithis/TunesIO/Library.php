<?php

namespace Rithis\TunesIO;

use React\EventLoop\LoopInterface,
    React\Stream\Stream;

use Rithis\Player\AudioStream,
    Rithis\XSPF\Track;

use CallbackFilterIterator,
    DirectoryIterator,
    Countable;

class Library implements Countable
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var array
     */
    private $index = [];

    public function __construct($directory, LoopInterface $loop)
    {
        if (!is_readable($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->directory = realpath($directory);
        $this->loop = $loop;

        $this->fillIndex();
    }

    public function open(Track $track)
    {
        return new Stream(fopen($this->path($track), 'x+'), $this->loop);
    }

    public function add(Track $track)
    {
        $path = $this->path($track);

        if (!is_file($path)) {
            throw new \InvalidArgumentException("Trying to add track which doesn't downloaded");
        } else if (in_array($path, $this->index)) {
            throw new \InvalidArgumentException("Trying to add track which already added");
        }

        array_push($this->index, $path);
    }

    public function has(Track $track)
    {
        return in_array($this->path($track), $this->index);
    }

    public function remove(Track $track)
    {
        $path = $this->path($track);

        if (false !== ($key = array_search($path, $this->index))) {
            unset($this->index[$key]);
        }

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function next()
    {
        $file = array_shift($this->index);
        array_push($this->index, $file);

        return new AudioStream($file, $this->loop);
    }

    public function count()
    {
        return count($this->index);
    }

    private function fillIndex()
    {
        $it = new CallbackFilterIterator(new DirectoryIterator($this->directory), function (DirectoryIterator $file) {
            return $file->isFile() && $file->getExtension() == 'mp3';
        });

        /** @var $file \DirectoryIterator */
        foreach ($it as $file) {
            $this->index[] = $file->getRealPath();
        }

        shuffle($this->index);
    }

    private function path(Track $track)
    {
        return sprintf("%s/%s.mp3", $this->directory, base64_encode(sprintf('%s - %s', $track->getCreator(), $track->getTitle())));
    }
}
