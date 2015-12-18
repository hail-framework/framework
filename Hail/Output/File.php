<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2015/12/16 0016
 * Time: 11:18
 */

namespace Hail\Output;


use Hail\DITrait;
use Hail\Exception\BadRequest;

class File
{
	use DITrait;

	/** @var string */
	private $contentType;

	/** @var string */
	private $name = '';

	/** @var bool */
	public $resuming = true;

	/** @var bool */
	private $forceDownload = true;

	public function alias($name)
	{
		$this->name = $name;
		return $this;
	}

	public function download($contentType)
	{
		$this->forceDownload = true;
		$this->contentType = $contentType ? $contentType : 'application/octet-stream';
		return $this;
	}

	public function view($contentType)
	{
		$this->forceDownload = false;
		$this->contentType = $contentType ? $contentType : 'application/octet-stream';
		return $this;
	}

	public function send($file)
	{
		if (!is_file($file)) {
			throw new BadRequest("File '$file' doesn't exist.");
		}

		$this->response->setContentType($this->contentType);
		$this->response->setHeader('Content-Disposition',
			($this->forceDownload ? 'attachment' : 'inline')
			. '; filename="' . $this->name . '"'
			. '; filename*=utf-8\'\'' . rawurlencode($this->name));

		$filesize = $length = filesize($file);
		$handle = fopen($file, 'r');

		if ($this->resuming) {
			$this->response->setHeader('Accept-Ranges', 'bytes');
			if (preg_match('#^bytes=(\d*)-(\d*)\z#', $this->response->getHeader('Range'), $matches)) {
				list(, $start, $end) = $matches;
				if ($start === '') {
					$start = max(0, $filesize - $end);
					$end = $filesize - 1;

				} elseif ($end === '' || $end > $filesize - 1) {
					$end = $filesize - 1;
				}
				if ($end < $start) {
					$this->response->setCode(416); // requested range not satisfiable
					return;
				}

				$this->response->setCode(206);
				$this->response->setHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $filesize);
				$length = $end - $start + 1;
				fseek($handle, $start);

			} else {
				$this->response->setHeader('Content-Range', 'bytes 0-' . ($filesize - 1) . '/' . $filesize);
			}
		}

		$this->response->setHeader('Content-Length', $length);
		while (!feof($handle) && $length > 0) {
			echo $s = fread($handle, min(4e6, $length));
			$length -= strlen($s);
		}
		fclose($handle);
	}
}