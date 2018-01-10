<?php

namespace Hail\Util;

final class StreamFilter
{
    /**
     * @var string
     */
    static private $registered;

    /**
     * append a callback filter to the given stream
     *
     * @param resource $stream
     * @param callable $callback
     * @param int      $read_write
     *
     * @return resource filter resource which can be used for `remove()`
     * @throws \RuntimeException on error
     * @uses stream_filter_append()
     */
    public static function append($stream, $callback, $read_write = STREAM_FILTER_ALL)
    {
        $ret = @\stream_filter_append($stream, self::register(), $read_write, $callback);
        if ($ret === false) {
            $error = \error_get_last() + ['message' => ''];
            throw new \RuntimeException('Unable to append filter: ' . $error['message']);
        }

        return $ret;
    }

    /**
     * prepend a callback filter to the given stream
     *
     * @param resource $stream
     * @param callable $callback
     * @param int      $read_write
     *
     * @return resource filter resource which can be used for `remove()`
     * @throws \RuntimeException on error
     * @uses stream_filter_prepend()
     */
    public static function prepend($stream, $callback, $read_write = STREAM_FILTER_ALL)
    {
        $ret = @\stream_filter_prepend($stream, self::register(), $read_write, $callback);
        if ($ret === false) {
            $error = \error_get_last() + ['message' => ''];
            throw new \RuntimeException('Unable to prepend filter: ' . $error['message']);
        }

        return $ret;
    }

    /**
     * Creates filter fun (function) which uses the given built-in $filter
     *
     * Some filters may accept or require additional filter parameters – most
     * filters do not require filter parameters.
     * If given, the optional `$parameters` argument will be passed to the
     * underlying filter handler as-is.
     * In particular, note how *not passing* this parameter at all differs from
     * explicitly passing a `null` value (which many filters do not accept).
     * Please refer to the individual filter definition for more details.
     *
     * @param string $filter     built-in filter name. See stream_get_filters() or http://php.net/manual/en/filters.php
     * @param mixed  $parameters (optional) parameters to pass to the built-in filter as-is
     *
     * @return callable a filter callback which can be append()'ed or prepend()'ed
     * @throws \RuntimeException on error
     * @link http://php.net/manual/en/filters.php
     * @see  stream_get_filters()
     * @see  append()
     */
    public static function create($filter, $parameters = null): callable
    {
        $fp = \fopen('php://memory', 'w');
        if ($parameters === null) {
            $resource = @\stream_filter_append($fp, $filter, STREAM_FILTER_WRITE);
        } else {
            $resource = @\stream_filter_append($fp, $filter, STREAM_FILTER_WRITE, $parameters);
        }
        if ($resource === false) {
            \fclose($fp);
            $error = \error_get_last() + ['message' => ''];
            throw new \RuntimeException('Unable to access built-in filter: ' . $error['message']);
        }

        // append filter function which buffers internally
        $buffer = '';
        self::append($fp, function ($chunk) use (&$buffer) {
            $buffer .= $chunk;

            // always return empty string in order to skip actually writing to stream resource
            return '';
        }, STREAM_FILTER_WRITE);

        $closed = false;

        return function ($chunk = null) use ($fp, &$buffer, &$closed) {
            if ($closed) {
                throw new \RuntimeException('Unable to perform operation on closed stream');
            }
            if ($chunk === null) {
                $closed = true;
                $buffer = '';
                \fclose($fp);

                return $buffer;
            }
            // initialize buffer and invoke filters by attempting to write to stream
            $buffer = '';
            \fwrite($fp, $chunk);

            // buffer now contains everything the filter function returned
            return $buffer;
        };
    }

    /**
     * remove a callback filter from the given stream
     *
     * @param resource $filter
     *
     * @return boolean true on success or false on error
     * @throws \RuntimeException on error
     * @uses stream_filter_remove()
     */
    public static function remove($filter)
    {
        if (@stream_filter_remove($filter) === false) {
            throw new \RuntimeException('Unable to remove given filter');
        }

        return true;
    }

    /**
     * registers the callback filter and returns the resulting filter name
     *
     * There should be little reason to call this function manually.
     *
     * @return string filter name
     * @uses CallbackFilter
     */
    public static function register()
    {
        if (self::$registered === null) {
            self::$registered = 'stream-callback';
            \stream_filter_register(self::$registered, CallbackFilter::class);
        }

        return self::$registered;
    }
}

/**
 * @internal
 * @see StreamFilter::append
 * @see StreamFilter::prepare
 */
final class CallbackFilter extends \php_user_filter
{
    private $callback;
    private $closed = true;
    private $supportsClose = false;

    public function onCreate()
    {
        $this->closed = false;

        if (!\is_callable($this->params)) {
            throw new \InvalidArgumentException('No valid callback parameter given to stream_filter_(append|prepend)');
        }
        $this->callback = $this->params;

        // callback supports end event if it accepts invocation without arguments
        $ref = new \ReflectionFunction($this->callback);
        $this->supportsClose = ($ref->getNumberOfRequiredParameters() === 0);

        return true;
    }

    public function onClose()
    {
        $this->closed = true;

        // callback supports closing and is not already closed
        if ($this->supportsClose) {
            $this->supportsClose = false;
            // invoke without argument to signal end and discard resulting buffer
            try {
                ($this->callback)();
            } catch (\Throwable $ignored) {
                // this might be called during engine shutdown, so it's not safe
                // to raise any errors or exceptions here
                // trigger_error('Error closing filter: ' . $ignored->getMessage(), E_USER_WARNING);
            }
        }

        $this->callback = null;
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        // concatenate whole buffer from input brigade
        $data = '';
        while ($bucket = \stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
            $data .= $bucket->data;
        }

        // skip processing callback that already ended
        if ($this->closed) {
            return PSFS_FEED_ME;
        }

        // only invoke filter function if buffer is not empty
        // this may skip flushing a closing filter
        if ($data !== '') {
            try {
                $data = ($this->callback)($data);
            } catch (\Throwable $e) {
                // exception should mark filter as closed
                $this->onClose();
                \trigger_error('Error invoking filter: ' . $e->getMessage(), E_USER_WARNING);

                return PSFS_ERR_FATAL;
            }
        }

        // mark filter as closed after processing closing chunk
        if ($closing) {
            $this->closed = true;

            // callback supports closing and is not already closed
            if ($this->supportsClose) {
                $this->supportsClose = false;

                // invoke without argument to signal end and append resulting buffer
                try {
                    $data .= ($this->callback)();
                } catch (\Throwable $e) {
                    \trigger_error('Error ending filter: ' . $e->getMessage(), E_USER_WARNING);

                    return PSFS_ERR_FATAL;
                }
            }
        }

        if ($data !== '') {
            // create a new bucket for writing the resulting buffer to the output brigade
            // reusing an existing bucket turned out to be bugged in some environments (ancient PHP versions and HHVM)
            $bucket = @\stream_bucket_new($this->stream, $data);

            // legacy PHP versions (PHP < 5.4) do not support passing data from the event signal handler
            // because closing the stream invalidates the stream and its stream bucket brigade before
            // invoking the filter close handler.
            if ($bucket !== false) {
                \stream_bucket_append($out, $bucket);
            }
        }

        return PSFS_PASS_ON;
    }
}
