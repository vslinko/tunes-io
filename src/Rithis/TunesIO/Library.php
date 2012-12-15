<?php

namespace Rithis\TunesIO;

use React\EventLoop\LoopInterface,
    React\Stream\Stream;

use Rithis\Player\AudioStream,
    Rithis\XSPF\XSPFDocument,
    Rithis\XSPF\Track;

class Library extends XSPFDocument
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $playlistFile;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    public function __construct($directory, LoopInterface $loop)
    {
        parent::__construct();

        $this->preserveWhiteSpace = false;
        $this->formatOutput = true;

        if (!is_readable($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->directory = realpath($directory);
        $this->playlistFile = sprintf("%s/library.xspf", $this->directory);

        if (is_file($this->playlistFile)) {
            $this->load($this->playlistFile);
            shuffle($this->index);
        } else {
            $this->dump();
        }

        $this->loop = $loop;
    }

    public function openTrack(Track $track)
    {
        return new Stream(fopen($this->path($track), 'x+'), $this->loop);
    }

    public function addTrack(Track $track)
    {
        $localTrack = new Track();
        $localTrack->addLocation(sprintf("file://%s", $this->path($track)));
        $localTrack->setCreator($track->getCreator());
        $localTrack->setTitle($track->getTitle());

        parent::addTrack($localTrack);

        $this->dump();
    }

    public function removeTrack(Track $track)
    {
        parent::removeTrack($track);

        $path = $this->path($track);

        if (is_file($path)) {
            unlink($path);
        }
    }

    public function nextTrack()
    {
        /** @var $track \Rithis\XSPF\Track */
        list($trackElement, $track) = array_shift($this->index);
        array_push($this->index, [$trackElement, $track]);

        list(, $location) = explode('://', $track->getLocations()[0], 2);

        return new AudioStream($location, $this->loop);
    }

    public function dump()
    {
        $this->save($this->playlistFile);
    }

    private function path(Track $track)
    {
        return sprintf("%s/%s.mp3", $this->directory, base64_encode(sprintf('%s - %s', $track->getCreator(), $track->getTitle())));
    }
}
