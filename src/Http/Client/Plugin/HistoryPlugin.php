<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Record HTTP calls.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class HistoryPlugin implements PluginInterface
{
    /**
     * Journal use to store request / responses / exception.
     *
     * @var JournalInterface
     */
    private $journal;

    /**
     * @param JournalInterface $journal
     */
    public function __construct(JournalInterface $journal)
    {
        $this->journal = $journal;
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $journal = $this->journal;

        return $handler->handle($request)->then(function (ResponseInterface $response) use ($request, $journal) {
            $journal->addSuccess($request, $response);

            return $response;
        }, function (ClientException $exception) use ($request, $journal) {
            $journal->addFailure($request, $exception);

            throw $exception;
        });
    }
}
