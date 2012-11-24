<?php

namespace Rithis\TunesIO;

use React\HttpClient\Response,
    React\HttpClient\Client,
    Evenement\EventEmitter;

use Rithis\XSPF\InvalidXSPFException,
    Rithis\XSPF\XSPFDocument;

use DateInterval,
    DateTime;

class ParseProcess extends EventEmitter
{
    /**
     * @var \React\HttpClient\Client
     */
    private $client;

    /**
     * @var \DateTime
     */
    private $firstDay;

    /**
     * @var \DateTime
     */
    private $date;

    /**
     * @var bool
     */
    private $running = false;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->firstDay = new DateTime('2012-09-08');
    }

    public function run()
    {
        if (!$this->running) {
            $this->running = true;
            $this->date = new DateTime();
            $this->request();
        }
    }

    private function request()
    {
        $url = sprintf('http://tunes.io/xspf/%s/', $this->date->format('Y-m-d'));
        $request = $this->client->request('GET', $url);

        $request->on('response', function (Response $response) {
            $content = '';

            $response->on('data', function ($data) use (&$content) {
                $content .= $data;
            });

            $response->on('end', function ($error) use (&$content) {
                if ($error === null) {
                    $this->parse($content);
                }

                $this->date->sub(new DateInterval('P1D'));

                if ($this->date >= $this->firstDay) {
                    $this->request();
                } else {
                    $this->running = false;
                }
            });
        });

        $request->end();
    }

    private function parse($content)
    {
        try {
            $playlist = new XSPFDocument();
            $playlist->loadXML($content);

            foreach ($playlist->getTracks() as $track) {
                $this->emit('track', [$track]);
            }
        } catch (InvalidXSPFException $e) {
            $this->emit('error', [$this->date, $content]);
        }
    }
}
