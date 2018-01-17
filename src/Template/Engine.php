<?php

namespace Hail\Template;

use Hail\Template\Extension\ExtensionInterface;
use Hail\Template\Processor\Helpers as Processor;
use Hail\Template\Processor\ProcessorInterface;
use Hail\Template\Processor\{
    Tal, Vue
};

class Engine
{
    /**
     * @var ProcessorInterface[]
     */
    protected $processors = [
        Tal\TalDefine::class,
        Tal\TalCondition::class,
        Tal\TalRepeat::class,
        Tal\TalContent::class,
        Tal\TalReplace::class,
        Tal\TalAttributes::class,

        Vue\VuePhp::class,
    ];

    /**
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $fallback;

    /**
     * @var string
     */
    protected $cacheDirectory;

    /**
     * Variables shared by all templates.
     *
     * @var array
     */
    protected $sharedVariables = [];

    /**
     * Specific template variables.
     *
     * @var array
     */
    protected $templateVariables = [];

    /**
     * Collection of template functions.
     *
     * @var callable[]
     */
    protected $functions;

    public function __construct(array $config = [])
    {
        if (!isset($config['directory']) && !isset($config['fallback'])) {
            throw new \LogicException('Path to template directory is not set.');
        }

        if (!isset($config['cache'])) {
            throw new \LogicException('Path to temporary directory is not set.');
        }

        $this->setDirectory($config['directory'] ?? null);
        $this->setFallback($config['fallback'] ?? null);
        $this->setCacheDirectory($config['cache']);

        if (isset($config['processors']) && \is_array($config['processors'])) {
            $this->processors = \array_merge($this->processors, $config['processors']);
        }
    }

    /**
     * Add preassigned template data.
     *
     * @param  array             $data      ;
     * @param  null|string|array $templates ;
     *
     * @return self
     * @throws \InvalidArgumentException
     */
    public function addData(array $data, $templates = null): self
    {
        if (null === $templates) {
            $this->sharedVariables = \array_merge($this->sharedVariables, $data);

            return $this;
        }

        if (\is_string($templates)) {
            $templates = [$templates];
        }

        if (\is_array($templates)) {
            foreach ($templates as $template) {
                if (isset($this->templateVariables[$template])) {
                    $this->templateVariables[$template] = \array_merge($this->templateVariables[$template], $data);
                } else {
                    $this->templateVariables[$template] = $data;
                }
            }

            return $this;
        }

        throw new \InvalidArgumentException(
            'The templates variable must be null, an array or a string, ' . \gettype($templates) . ' given.'
        );
    }

    /**
     * Get all preassigned template data.
     *
     * @param  null|string $template ;
     *
     * @return array
     */
    public function getData($template = null): array
    {
        if (isset($template, $this->templateVariables[$template])) {
            return \array_merge($this->sharedVariables, $this->templateVariables[$template]);
        }

        return $this->sharedVariables;
    }

    /**
     * Set path to templates directory.
     *
     * @param  string $directory
     *
     * @return self
     */
    public function setDirectory($directory): self
    {
        $this->directory = $this->normalizeDirectory($directory);

        return $this;
    }

    /**
     * Set path to fallback directory.
     *
     * @param  string $fallback
     *
     * @return self
     */
    public function setFallback($fallback): self
    {
        $this->fallback = $this->normalizeDirectory($fallback);

        return $this;
    }

    /**
     * Set path to templates cache directory.
     *
     * @param  string $directory
     *
     * @return self
     */
    public function setCacheDirectory($directory): self
    {
        $this->cacheDirectory = $this->normalizeDirectory($directory);

        return $this;
    }

    /**
     * Register a new template function.
     *
     * @param string   $name
     * @param callback $callback
     *
     * @return self
     */
    public function registerFunction(string $name, callable $callback): self
    {
        if (\preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $name) !== 1) {
            throw new \LogicException('Not a valid function name.');
        }

        $this->functions[$name] = $callback;

        return $this;
    }

    /**
     * Remove a template function.
     *
     * @param  string $name
     *
     * @return self
     */
    public function dropFunction($name): self
    {
        unset($this->functions[$name]);

        return $this;
    }

    /**
     * Get a template function.
     *
     * @param  string $name
     *
     * @return callable
     */
    public function getFunction($name)
    {
        if (!isset($this->functions[$name])) {
            throw new \LogicException('The template function "' . $name . '" was not found.');
        }

        return $this->functions[$name];
    }

    /**
     * Call the function.
     *
     * @param string   $name
     * @param Template $template
     * @param array    $arguments
     *
     * @return mixed
     */
    public function callFunction($name, Template $template = null, $arguments = [])
    {
        $callable = $this->getFunction($name);

        if (\is_array($callable) &&
            isset($callable[0]) &&
            $callable[0] instanceof ExtensionInterface
        ) {
            $callable[0]->template = $template;
        }

        return $callable(...$arguments);
    }

    /**
     * Check if a template function exists.
     *
     * @param  string $name
     *
     * @return bool
     */
    public function doesFunctionExist($name)
    {
        return isset($this->functions[$name]);
    }

    /**
     * Load an extension.
     *
     * @param  ExtensionInterface $extension
     *
     * @return Engine
     */
    public function loadExtension(ExtensionInterface $extension)
    {
        $extension->register($this);

        return $this;
    }

    /**
     * Load multiple extensions.
     *
     * @param  array $extensions
     *
     * @return Engine
     */
    public function loadExtensions(array $extensions = [])
    {
        foreach ($extensions as $extension) {
            $this->loadExtension($extension);
        }

        return $this;
    }

    /**
     * add processor class.
     *
     * @param string      $processor
     * @param string|null $ref
     *
     * @return self
     */
    public function addProcessorAfter(string $processor, string $ref = null): self
    {
        if ($ref === null) {
            $index = false;
        } else {
            $index = \array_search($ref, $this->processors, true);
        }

        if ($index === false) {
            $this->processors[] = $processor;
        } else {
            \array_splice($this->processors, $index + 1, 0, $processor);
        }

        return $this;
    }

    /**
     * add processor class before $ref.
     *
     * @param string      $processor
     * @param string|null $ref
     *
     * @return self
     */
    public function addProcessorBefore(string $processor, string $ref = null): self
    {
        if ($ref === null) {
            $index = false;
        } else {
            $index = \array_search($ref, $this->processors, true);
        }

        if ($index === false) {
            \array_unshift($this->processors, $processor);
        } else {
            \array_splice($this->processors, $index, 0, $processor);
        }

        return $this;
    }

    /**
     * Compile the html, and return the compiled file.
     *
     * @return string the compiled file.
     */
    public function compile($name): string
    {
        [
            'template' => $template,
            'cache' => $cache,
        ] = $this->getTemplateFile($name);

        if (\filemtime($template) < @\filemtime($cache)) {
            return $cache;
        }

        $root = Tokenizer\Tokenizer::parseFile($template);

        Processor::parseElement($root, $this->processors);

        \file_put_contents($cache, (string) $root);

        return $cache;
    }

    protected function getTemplateFile(string $name)
    {
        foreach ([$this->directory, $this->fallback] as $dir) {
            if (empty($dir)) {
                continue;
            }

            $file = $this->getFile($dir . $name);
            if ($file !== null) {
                return [
                    'template' => $file,
                    'cache' => $this->convertToCache($file, $dir),
                ];
            }
        }

        throw new \LogicException('Template file not found:' . $name);
    }

    protected function convertToCache(string $template, string $dir)
    {
        $file = \str_replace($dir, $this->cacheDirectory, $template);

        if (\is_file($file)) {
            return $file;
        }

        $dir = \dirname($file);
        if (!\is_dir($dir)) {
            if (!@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
                throw new \RuntimeException('Cache directory not exists: ' .
                    \str_replace($this->cacheDirectory, '', $dir)
                );
            }
        }

        return $file;
    }

    protected function getFile(string $file)
    {
        if (\is_dir($file)) {
            $file .= '/index.php';
        } elseif (\strrchr($file, '.') !== '.php') {
            $file .= '.php';
        }

        if (\is_file($file)) {
            return $file;
        }

        return null;
    }

    protected function normalizeDirectory(?string $dir): ?string
    {
        if ($dir !== null) {
            if (\strpos($dir, '\\') !== false) {
                $dir = \str_replace('\\', '/', $dir);
            }

            $dir = \rtrim($dir, '/') . '/';

            if (DIRECTORY_SEPARATOR !== '/') {
                $dir = \str_replace('/', DIRECTORY_SEPARATOR, $dir);
            }
        }

        return $dir;
    }

    /**
     * Render the compiled php code by data.
     *
     * @param string $name
     * @param array  $params
     */
    public function render(string $name, array $params = []): void
    {
        echo $this->capture($name, $params);
    }


    /**
     * @param string $name
     * @param array  $params
     *
     * @return string
     * @throws
     */
    public function capture(string $name, array $params): string
    {
        return $this->make($name)->render($params);
    }

    /**
     * @param string $name
     *
     * @return Template
     */
    public function make(string $name): Template
    {
        $name = \trim($name, '/\\');
        $file = $this->compile($name);

        return new Template($this, $name, $file);
    }
}