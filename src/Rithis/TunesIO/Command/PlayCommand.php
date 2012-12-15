<?php

namespace Rithis\TunesIO\Command;

use Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Command\Command;

use React\Dns\Resolver\Factory as DnsResolverFactory,
    React\HttpClient\Factory as HttpClientFactory,
    React\EventLoop\Factory as EventLoopFactory,
    React\Stream\Stream;

use Rithis\TunesIO\DownloadProcess,
    Rithis\Player\SoXPlayerStream,
    Rithis\TunesIO\ControlStream,
    Rithis\TunesIO\ParseProcess,
    Rithis\TunesIO\DownloadTask,
    Rithis\Player\AudioStream,
    Rithis\Player\SoXPlayer,
    Rithis\TunesIO\Library,
    Rithis\XSPF\Track;

use DateTime;
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\LibEvLoop;

class PlayCommand extends Command
{
    protected function configure()
    {
        $this->setName('play');
        $this->setDescription("Run tunes.io player");
        $this->addOption('library', 'l', InputOption::VALUE_REQUIRED, 'Path to library', 'library');
        $this->addOption('download-threads', 't', InputOption::VALUE_REQUIRED, 'How many tracks download parallel', 3);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // processors
        $loop = new LibEvLoop();
        $httpClient = (new HttpClientFactory())->create($loop, (new DnsResolverFactory())->createCached('8.8.8.8', $loop));

        $playerStream = new SoXPlayerStream($loop);
        $player = new SoXPlayer($playerStream);
        $library = new Library($input->getOption('library'), $loop);
        $controlStream = new ControlStream();
        $parse = new ParseProcess($httpClient);
        $download = new DownloadProcess($loop, $httpClient, $input->getOption('download-threads'));

        // actions
        $play = function (AudioStream $track) use ($output, $player) {
            $output->writeln("Playing <info>$track</info>");
            $player->play($track);
        };

        $playNext = function () use ($library, $play) {
            $play($library->nextTrack());
        };

        $quit = function () use ($playerStream, $download, $library, $loop) {
            $playerStream->close();

            /** @var $task \Rithis\TunesIO\DownloadTask */
            foreach ($download->getCurrentTasks() as $task) {
                $library->removeTrack($task->getTrack());
            }

            $loop->stop();
        };

        // processes
        $player->on('end', $playNext);

        $controlStream->any(['n', 'next'], $playNext);
        $controlStream->any(['q', 'quit', 'exit'], $quit);

        $parse->on('track', function (Track $track) use ($library, $download) {
            if (!$library->hasTrack($track)) {
                $download->add(new DownloadTask($track, $library));
            }
        });
        $parse->on('error', function (DateTime $date) use ($output) {
            $formattedDate = $date->format('Y-m-d');
            $output->writeln("<error>Can't parse playlist for $formattedDate</error>");
        });

        $download->on('task', function (DownloadTask $task) use ($library, $output) {
            $track = $task->getTrack();
            $creator = $track->getCreator();
            $title = $track->getTitle();
            $output->writeln("Downloading <info>$creator - $title</info>");
        });
        $download->on('track', [$library, 'addTrack']);
        $download->on('error', [$library, 'removeTrack']);

        // initialization
        //declare(ticks = 1);
//        pcntl_signal(SIGINT, function () use ($output, $quit) {
//            $output->write("\n");
//            $quit();
//        });

        if (count($library) > 0) {
            $playNext();
        } else {
            $download->once('track', $playNext);
        }

        //(new Stream(fopen('php://stdin', 'r'), $loop))->pipe($controlStream);

        //$parse->run();
        //$download->run();
        $loop->run();
    }
}
