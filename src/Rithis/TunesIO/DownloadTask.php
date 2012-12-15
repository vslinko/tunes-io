<?php

namespace Rithis\TunesIO;

use Rithis\XSPF\Track;

class DownloadTask
{
    /**
     * @var \Rithis\XSPF\Track
     */
    private $track;

    /**
     * @var \Rithis\TunesIO\Library
     */
    private $library;

    public function __construct(Track $track, Library $library)
    {
        $this->track = $track;
        $this->library = $library;
    }

    public function getTrack()
    {
        return $this->track;
    }

    public function getLocation()
    {
        $locations = $this->track->getLocations();

        return count($locations) > 0 ? $locations[0] : null;
    }

    public function openDestinationStream()
    {
        return $this->library->openTrack($this->track);
    }
}
