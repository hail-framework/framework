<?php

namespace Hail\Buzz\Listener;

use Hail\Buzz\Listener\History\Journal;
use Hail\Buzz\Message\MessageInterface;
use Hail\Buzz\Message\RequestInterface;

class HistoryListener implements ListenerInterface
{
    private $journal;
    private $startTime;

    public function __construct(Journal $journal)
    {
        $this->journal = $journal;
    }

    public function getJournal()
    {
        return $this->journal;
    }

    public function preSend(RequestInterface $request)
    {
        $this->startTime = microtime(true);
    }

    public function postSend(RequestInterface $request, MessageInterface $response)
    {
        $this->journal->record($request, $response, microtime(true) - $this->startTime);
    }
}
