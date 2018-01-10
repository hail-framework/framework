<?php
namespace Hail\Console\Exception;

use Hail\Console\Extension\AbstractExtension;

class ExtensionException extends \Exception
{
    protected $extension;

    public function __construct($message, AbstractExtension $extension = null)
    {
        parent::__construct($message);

        $this->extension = $extension;
    }

    public function getExtension()
    {
        return $this->extension;
    }
}
