<?php
namespace Hail\Output;

use Hail\Facades\Response;
use Hail\Exception\BadRequest;

class File
{
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

		Response::setContentType($this->contentType);
		Response::setHeader('Content-Disposition',
			($this->forceDownload ? 'attachment' : 'inline')
			. '; filename="' . $this->name . '"'
			. '; filename*=utf-8\'\'' . rawurlencode($this->name));

		$filesize = $length = filesize($file);
		$handle = fopen($file, 'r');

		if ($this->resuming) {
			Response::setHeader('Accept-Ranges', 'bytes');
			if (preg_match('#^bytes=(\d*)-(\d*)\z#', Response::getHeader('Range'), $matches)) {
				list(, $start, $end) = $matches;
				if ($start === '') {
					$start = max(0, $filesize - $end);
					$end = $filesize - 1;

				} elseif ($end === '' || $end > $filesize - 1) {
					$end = $filesize - 1;
				}
				if ($end < $start) {
					Response::setCode(416); // requested range not satisfiable
					return;
				}

				Response::setCode(206);
				Response::setHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $filesize);
				$length = $end - $start + 1;
				fseek($handle, $start);

			} else {
				Response::setHeader('Content-Range', 'bytes 0-' . ($filesize - 1) . '/' . $filesize);
			}
		}

		Response::setHeader('Content-Length', $length);
		while (!feof($handle) && $length > 0) {
			echo $s = fread($handle, min(4e6, $length));
			$length -= strlen($s);
		}
		fclose($handle);
	}
}