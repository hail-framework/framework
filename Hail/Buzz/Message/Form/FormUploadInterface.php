<?php

namespace Hail\Buzz\Message\Form;

use Hail\Buzz\Message\MessageInterface;

interface FormUploadInterface extends MessageInterface
{
    public function setName($name);
    public function getFile();
    public function getFilename();
    public function getContentType();
}
