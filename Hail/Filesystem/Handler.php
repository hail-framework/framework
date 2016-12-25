<?php
namespace Hail\Filesystem;

abstract class Handler
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param string              $path
     */
    public function __construct(Filesystem $filesystem = null, $path = null)
    {
        $this->path = $path;
        $this->filesystem = $filesystem;
    }

    /**
     * Check whether the entree is a directory.
     *
     * @return bool
     */
    public function isDir()
    {
        return $this->getType() === 'dir';
    }

    /**
     * Check whether the entree is a file.
     *
     * @return bool
     */
    public function isFile()
    {
        return $this->getType() === 'file';
    }

    /**
     * Retrieve the entree type (file|dir).
     *
     * @return string file or dir
     */
    public function getType()
    {
        $metadata = $this->filesystem->getMetadata($this->path);

        return $metadata['type'];
    }

    /**
     * Set the Filesystem object.
     *
     * @param Filesystem $filesystem
     *
     * @return $this
     */
    public function setFilesystem(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        return $this;
    }
    
    /**
     * Retrieve the Filesystem object.
     *
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Set the entree path.
     *
     * @param string $path
     *
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Retrieve the entree path.
     *
     * @return string path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Plugins pass-through.
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, array $args)
    {
        array_unshift($args, $this->path);

        try {
        	switch (count($args)) {
		        case 0:
		        	return $this->filesystem->$method();
		        case 1:
			        return $this->filesystem->$method($args[0]);
		        case 2:
			        return $this->filesystem->$method($args[0], $args[1]);
		        case 3:
			        return $this->filesystem->$method($args[0], $args[1], $args[2]);
		        case 4:
			        return $this->filesystem->$method($args[0], $args[1], $args[2], $args[3]);
		        default:
			        return call_user_func_array([$this->filesystem, $method], $args);
	        }
        } catch (\BadMethodCallException $e) {
            throw new \BadMethodCallException(
                'Call to undefined method '
                . static::class
                . '::' . $method
            );
        }
    }
}
