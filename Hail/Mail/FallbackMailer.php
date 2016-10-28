<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Mail;


use Hail\Exception\InvalidArgument;

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
	 * @param Mailer[]
	 * @param int
	 * @param int in miliseconds
	 */
	public function __construct(array $mailers, $retryCount = 3, $retryWaitTime = 1000)
	{
		$this->mailers = $mailers;
		$this->retryCount = $retryCount;
		$this->retryWaitTime = $retryWaitTime;
	}


	/**
	 * Sends email.
	 * @return void
	 * @throws InvalidArgument，Exception\FallbackMailer
	 */
	public function send(Message $mail)
	{
		if (!$this->mailers) {
			throw new InvalidArgument('At least one mailer must be provided.');
		}

		for ($i = 0; $i < $this->retryCount; $i++) {
			if ($i > 0) {
				usleep($this->retryWaitTime * 1000);
			}

			foreach ($this->mailers as $mailer) {
				try {
					$mailer->send($mail);
					return;

				} catch (Exception\Send $e) {
					$failures[] = $e;
					$this->onFailure($this, $e, $mailer, $mail);
				}
			}
		}

		$e = new Exception\FallbackMailer('All mailers failed to send the message.');
		$e->failures = $failures;
		throw $e;
	}


	/**
	 * @return self
	 */
	public function addMailer(Mailer $mailer)
	{
		$this->mailers[] = $mailer;
		return $this;
	}

}
