<?php

namespace Hail\Image;

abstract class AbstractDriver
{
    /**
     * Decoder instance to init images from
     *
     * @var \Hail\Image\AbstractDecoder
     */
    public $decoder;

    /**
     * Image encoder instance
     *
     * @var \Hail\Image\AbstractEncoder
     */
    public $encoder;

    /**
     * Creates new image instance
     *
     * @param  int $width
     * @param  int $height
     * @param  string  $background
     * @return \Hail\Image\Image
     */
    abstract public function newImage($width, $height, $background);

    /**
     * Reads given string into color object
     *
     * @param  string $value
     * @return AbstractColor
     */
    abstract public function parseColor($value);

    /**
     * Returns clone of given core
     *
     * @return mixed
     */
    public function cloneCore($core)
    {
        return clone $core;
    }

    /**
     * Initiates new image from given input
     *
     * @param  mixed $data
     * @return \Hail\Image\Image
     */
    public function init($data)
    {
        return $this->decoder->init($data);
    }

    /**
     * Encodes given image
     *
     * @param  Image   $image
     * @param  string  $format
     * @param  int $quality
     * @return \Hail\Image\Image
     */
    public function encode($image, $format, $quality)
    {
        return $this->encoder->process($image, $format, $quality);
    }

    /**
     * Executes named command on given image
     *
     * @param  Image  $image
     * @param  string $name
     * @param  array $arguments
     * @return \Hail\Image\Commands\AbstractCommand
     */
    public function executeCommand($image, $name, $arguments)
    {
        $name = \mb_convert_case($name[0], MB_CASE_UPPER) . \mb_substr($name, 1, \mb_strlen($name));

        $commandName = $this->getCommandClassName($name);
        $command = new $commandName($arguments);
        $command->execute($image);

        return $command;
    }

    /**
     * Returns classname of given command name
     *
     * @param  string $name
     * @return string
     */
    private function getCommandClassName($name)
    {
        $name = \mb_convert_case($name[0], MB_CASE_UPPER) . \mb_substr($name, 1, \mb_strlen($name));

        $namespace = $this->getNamespace();
        $commandName = ucfirst($name);

        $classnameLocal = "\{$namespace}\Commands\{$commandName}Command";
        if (class_exists($classnameLocal)) {
            return $classnameLocal;
        }

        $classnameGlobal = "\Hail\Image\Commands\{$commandName}Command";
        if (class_exists($classnameGlobal)) {
            return $classnameGlobal;
        }

        throw new \Hail\Image\Exception\NotSupportedException(
            "Command ({$name}) is not available for driver ({$namespace})."
        );
    }

    /**
     * Returns name of current driver instance
     *
     * @return string
     */
    public function getNamespace()
    {
        $reflect = new \ReflectionClass($this);

        return $reflect->getNamespaceName();
    }
}
