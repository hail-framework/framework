<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Mail;


use InvalidArgumentException;
use Hail\Mail\Exception\{
	SendException,
	FallbackMailerException
};

class FallbackMailer
{
	/** @var callable[]  function (FallbackMailer $sender, SendException $e, Mailer $mailer, Message $mail) */
	public $onFailure;

	/** @var Mailer[] */
	private $mailers;

	/** @var int */
	private $retryCount;

	/** @var int in miliseconds */
	private $retryWaitTime;


	/**
	 * @param Mailer[] $mailers
	 * @param int $retryCount
	 * @param int $retryWaitTime in miliseconds
	 * @param callable $onFailure
	 */
	public function __construct(array $mailers, $retryCount = 3, $retryWaitTime = 1000, $onFailure = null)
	{
		$this->mailers = $mailers;
		$this->retryCount = $retryCount;
		$this->retryWaitTime = $retryWaitTime;
		$this->onFailure = $onFailure ?? function () {};
	}


	/**
	 * Sends email.
	 *
	 * @param Message $mail
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws FallbackMailerException
	 */
	public function send(Message $mail)
	{
		if (!$this->mailers) {
			throw new InvalidArgumentException('At least one mailer must be provided.');
		}

		for ($i = 0; $i < $this->retryCount; $i++) {
			if ($i > 0) {
				usleep($this->retryWaitTime * 1000);
			}

			foreach ($this->mailers as $mailer) {
				try {
					$mailer->send($mail);
					return;

				} catch (SendException $e) {
					$failures[] = $e;
					$this->onFailure($this, $e, $mailer, $mail);
				}
			}
		}

		$e = new FallbackMailerException('All mailers failed to send the message.');
		$e->failures = $failures;
		throw $e;
	}


	/**
	 * @param Mailer $mailer
	 *
	 * @return self
	 */
	public function addMailer(Mailer $mailer)
	{
		$this->mailers[] = $mailer;
		return $this;
	}
}
