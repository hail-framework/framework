<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Interop\Http\Factory\UploadedFileFactoryInterface;
use Hail\Http\Factory;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class UploadedFileFactory implements UploadedFileFactoryInterface
{
	public function createUploadedFile(
		$file,
		$size = null,
		$error = \UPLOAD_ERR_OK,
		$clientFilename = null,
		$clientMediaType = null
	) {
        return Factory::uploadedFile($file, $size, $error, $clientFilename, $clientMediaType);
    }
}
