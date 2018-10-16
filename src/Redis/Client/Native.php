<?php
/**
 * @author    Colin Mollenhour <colin@mollenhour.com>
 * @copyright 2011 Colin Mollenhour <colin@mollenhour.com>
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   static
 */

namespace Hail\Redis\Client;

use Hail\Redis\Exception\RedisException;
use Hail\Redis\Helpers;

/**
 * Class Native
 *
 * @package Hail\Redis\Client
 * @author  Feng Hao <flyinghail@msn.com>
 * @inheritdoc
 */
class Native extends AbstractClient
{
    /**
     * @var string
     */
    private $cachedId;

    /**
     * @var bool
     */
    protected $usePipeline = false;

    /**
     * @var array
     */
    protected $commandNames;

    /**
     * @var string
     */
    protected $commands;

    /**
     * @var bool
     */
    protected $isWatching = false;

    /**
     * @var bool
     */
    protected $isMulti = false;

    protected $inCluster = false;

    public function __toString()
    {
        if ($this->cachedId === null) {
            $this->cachedId = $this->port === null ? 'unix://{$this->host}' : "{$this->host}:{$this->port}";
        }

        return $this->cachedId;
    }

    /**
     * @throws RedisException
     */
    public function connect()
    {
        $flags = STREAM_CLIENT_CONNECT;
        $remote_socket = $this->port === null
            ? 'unix://' . $this->host
            : 'tcp://' . $this->host . ':' . $this->port;
        // Persistent connections to UNIX sockets are not supported
        if ($this->persistent && $this->port !== null) {
            $remote_socket .= '/' . $this->persistent;
            $flags |= STREAM_CLIENT_PERSISTENT;
        }
        $result = $this->redis = @\stream_socket_client($remote_socket, $errno, $errstr, $this->timeout ?? 2.5, $flags);

        // Use recursion for connection retries
        if (!$result) {
            $this->connectFailures++;
            if ($this->connectFailures <= $this->maxConnectRetries) {
                $this->connect();

                return;
            }
            $failures = $this->connectFailures;
            $this->connectFailures = 0;
            throw new RedisException("Connection to Redis {$this->host}:{$this->port} failed after $failures failures." . (isset($errno) && isset($errstr) ? "Last Error : ({$errno}) {$errstr}" : ""));
        }

        $this->connectFailures = 0;
        $this->connected = true;

        // Set read timeout
        if ($this->readTimeout) {
            $this->setReadTimeout($this->readTimeout);
        }

        if ($password = $this->getPassword()) {
            $this->auth($password);
        }

        if ($this->database !== 0) {
            $this->select($this->database);
        }
    }

    public function inCluster()
    {
        $this->inCluster = true;
    }

    /**
     * Set the read timeout for the connection. Use 0 to disable timeouts entirely (or use a very long timeout
     * if not supported).
     *
     * @param int $timeout 0 (or -1) for no timeout, otherwise number of seconds
     *
     * @throws RedisException
     * @return static
     */
    public function setReadTimeout(int $timeout)
    {
        if ($timeout < -1) {
            throw new RedisException('Timeout values less than -1 are not accepted.');
        }
        $this->readTimeout = $timeout;
        if ($this->connected) {
            $timeout = $timeout <= 0 ? 315360000 : $timeout; // Ten-year timeout
            \stream_set_blocking($this->redis, true);
            \stream_set_timeout($this->redis, (int) \floor($timeout), ($timeout - \floor($timeout)) * 1000000);
        }

        return $this;
    }

    public function close($force = false)
    {
        $result = true;
        if ($this->redis && ($force || !$this->persistent)) {
            $result = @\fclose($this->redis);
            $this->connected = $this->usePipeline = $this->isMulti = $this->isWatching = false;
        }

        return $result;
    }

    /**
     * @param array|string $patterns
     * @param callable     $callback
     *
     * @throws RedisException
     */
    public function pSubscribe($patterns, $callback)
    {
        // Standalone mode: use infinite loop to subscribe until timeout
        $patternCount = \is_array($patterns) ? \count($patterns) : 1;
        while ($patternCount--) {
            if (isset($status)) {
                list(, , $status) = $this->readReply();
            } else {
                list(, , $status) = $this->__call('psubscribe', [$patterns]);
            }
            $this->subscribed = $status > 0;
            if (!$status) {
                throw new RedisException('Invalid pSubscribe response.');
            }
        }

        while ($this->subscribed) {
            [$type, $pattern, $channel, $message] = $this->readReply();
            if ($type !== 'pmessage') {
                throw new RedisException('Received non-pmessage reply.');
            }
            $callback($this, $pattern, $channel, $message);
        }
    }

    /**
     * @param string|array $channels
     * @param callable     $callback
     *
     * @throws RedisException
     */
    public function subscribe($channels, $callback)
    {
        // Standalone mode: use infinite loop to subscribe until timeout
        $channelCount = \is_array($channels) ? \count($channels) : 1;
        while ($channelCount--) {
            if (isset($status)) {
                list(, , $status) = $this->readReply();
            } else {
                list(, , $status) = $this->__call('subscribe', [$channels]);
            }
            $this->subscribed = $status > 0;
            if (!$status) {
                throw new RedisException('Invalid subscribe response.');
            }
        }

        while ($this->subscribed) {
            [$type, $channel, $message] = $this->readReply();
            if ($type !== 'message') {
                throw new RedisException('Received non-message reply.');
            }
            $callback($this, $channel, $message);
        }
    }

    public static function normalize($name, $args)
    {
        $name = \strtolower($name);

        $trackedArgs = [];

        // Send request via native PHP
        switch ($name) {
            case 'eval':
            case 'evalsha':
                $script = $args[0];
                $keys = (array) ($args[1] ?? []);
                $eArgs = (array) ($args[2] ?? []);
                $args = [$script, \count($keys), $keys, $eArgs];
                break;
            case 'zinterstore':
            case 'zunionstore':
                $dest = $args[0];
                $keys = (array) ($args[1] ?? []);
                $weights = $args[2]['weights'] ?? null;
                $aggregate = $args[2]['aggregate'] ?? null;
                $args = [$dest, \count($keys), $keys];
                if ($weights) {
                    $args[] = 'WEIGHTS';
                    $args[] = (array) $weights;
                }
                if ($aggregate) {
                    $args[] = 'AGGREGATE';
                    $args[] = $aggregate;
                }
                break;
            case 'set':
                // The php redis module has different behaviour with ttl
                // https://github.com/phpredis/phpredis#set
                if (\count($args) === 3) {
                    if (\is_int($args[2])) {
                        $args = [$args[0], $args[1], ['EX', $args[2]]];
                    } elseif (\is_array($args[2])) {
                        $tmp_args = $args;
                        $args = [$tmp_args[0], $tmp_args[1]];
                        foreach ($tmp_args[2] as $k => $v) {
                            if (\is_string($k)) {
                                $args[] = [$k, $v];
                            } elseif (\is_int($k)) {
                                $args[] = $v;
                            }
                        }
                        unset($tmp_args);
                    }
                }
                break;
            case 'scan':
                $trackedArgs = [&$args[0]];
                if (empty($trackedArgs[0])) {
                    $trackedArgs[0] = 0;
                }
                $eArgs = [$trackedArgs[0]];
                if (!empty($args[1])) {
                    $eArgs[] = 'MATCH';
                    $eArgs[] = $args[1];
                }
                if (!empty($args[2])) {
                    $eArgs[] = 'COUNT';
                    $eArgs[] = $args[2];
                }
                $args = $eArgs;
                break;
            case 'sscan':
            case 'zscan':
            case 'hscan':
                $trackedArgs = [&$args[1]];
                if (empty($trackedArgs[0])) {
                    $trackedArgs[0] = 0;
                }
                $eArgs = [$args[0], $trackedArgs[0]];
                if (!empty($args[2])) {
                    $eArgs[] = 'MATCH';
                    $eArgs[] = $args[2];
                }
                if (!empty($args[3])) {
                    $eArgs[] = 'COUNT';
                    $eArgs[] = $args[3];
                }
                $args = $eArgs;
                break;
            case 'zrangebyscore':
            case 'zrevrangebyscore':
            case 'zrange':
            case 'zrevrange':
                if (isset($args[3]) && \is_array($args[3])) {
                    // map options
                    $cArgs = [];
                    if (!empty($args[3]['withscores'])) {
                        $cArgs[] = 'withscores';
                    }
                    if (($name === 'zrangebyscore' || $name === 'zrevrangebyscore') && \array_key_exists('limit',
                            $args[3])
                    ) {
                        $cArgs[] = ['limit' => $args[3]['limit']];
                    }
                    $args[3] = $cArgs;
                    $trackedArgs = $cArgs;
                }
                break;
            case 'mget':
                if (isset($args[0]) && \is_array($args[0])) {
                    $args = \array_values($args[0]);
                }
                break;
            case 'hmset':
                if (isset($args[1]) && \is_array($args[1])) {
                    $cArgs = [];
                    foreach ($args[1] as $id => $value) {
                        $cArgs[] = $id;
                        $cArgs[] = $value;
                    }
                    $args[1] = $cArgs;
                }
                break;
            case 'zsize':
                $name = 'zcard';
                break;
            case 'zdelete':
                $name = 'zrem';
                break;
            case 'hmget':
                // hmget needs to track the keys for rehydrating the results
                if (isset($args[1])) {
                    $trackedArgs = $args[1];
                }
        }
        // Flatten arguments
        $args = Helpers::flattenArguments($args);

        return [$name, $args, $trackedArgs];
    }

    /**
     * @param       $name
     * @param array $args
     * @param array $trackedArgs
     *
     * @return $this|array|bool|mixed|null|string
     * @throws RedisException
     */
    public function execute($name, $args = [], $trackedArgs = [])
    {
        // In pipeline mode
        if ($this->usePipeline) {
            if ($name === 'pipeline') {
                throw new RedisException('A pipeline is already in use and only one pipeline is supported.');
            }

            if ($name === 'exec') {
                if ($this->isMulti) {
                    $this->commandNames[] = [$name, $trackedArgs];
                    $this->commands .= Helpers::prepareCommand([$name]);
                }

                // Write request
                if ($this->commands) {
                    $this->writeCommand($this->commands);
                }
                $this->commands = null;

                // Read response
                $queuedResponses = $response = [];
                foreach ($this->commandNames as $command) {
                    [$n, $arguments] = $command;
                    $result = $this->readReply($n, true);
                    if ($result !== null) {
                        $result = $this->decodeReply($n, $result, $arguments);
                    } else {
                        $queuedResponses[] = $command;
                    }
                    $response[] = $result;
                }

                if ($this->isMulti) {
                    $response = \array_pop($response);

                    foreach ($queuedResponses as $key => [$n, $arguments]) {
                        $response[$key] = $this->decodeReply($n, $response[$key], $arguments);
                    }
                }
                $this->commandNames = null;
                $this->usePipeline = $this->isMulti = false;

                return $response;
            }

            if ($name === 'discard') {
                $this->commands = null;
                $this->commandNames = null;
                $this->usePipeline = $this->isMulti = false;
            } else {
                if ($name === 'multi') {
                    $this->isMulti = true;
                }
                \array_unshift($args, $name);
                $this->commandNames[] = [$name, $trackedArgs];
                $this->commands .= Helpers::prepareCommand($args);

                return $this;
            }
        }

        // Start pipeline mode
        if ($name === 'pipeline') {
            $this->usePipeline = true;
            $this->commandNames = [];
            $this->commands = '';

            return $this;
        }

        // If unwatching, allow reconnect with no error thrown
        if ($name === 'unwatch') {
            $this->isWatching = false;
        }

        // Non-pipeline mode
        \array_unshift($args, $name);
        $command = Helpers::prepareCommand($args);
        $this->writeCommand($command);
        $response = $this->readReply($name);
        $response = $this->decodeReply($name, $response, $trackedArgs);

        // Watch mode disables reconnect so error is thrown
        if ($name === 'watch') {
            $this->isWatching = true;
        } // Transaction mode
        else {
            if ($this->isMulti && ($name === 'exec' || $name === 'discard')) {
                $this->isMulti = false;
            } // Started transaction
            else {
                if ($this->isMulti || $name === 'multi') {
                    $this->isMulti = true;
                    $response = $this;
                }
            }
        }

        return $response;
    }

    /**
     * @param $name
     * @param $args
     *
     * @return array|bool|Native|mixed|null|string
     * @throws RedisException
     */
    public function __call($name, $args)
    {
        [$name, $args, $trackedArgs] = self::normalize($name, $args);

        return $this->execute($name, $args, $trackedArgs);
    }

    /**
     * @param $command
     *
     * @throws RedisException
     */
    protected function writeCommand($command)
    {
        // Reconnect on lost connection (Redis server "timeout" exceeded since last command)
        if (\feof($this->redis)) {
            // If a watch or transaction was in progress and connection was lost, throw error rather than reconnect
            // since transaction/watch state will be lost.
            if (($this->isMulti && !$this->usePipeline) || $this->isWatching) {
                $this->close(true);
                throw new RedisException('Lost connection to Redis server during watch or transaction.');
            }

            $this->close(true);
            $this->connect();
        }

        $commandLen = \strlen($command);
        $lastFailed = false;
        for ($written = 0; $written < $commandLen; $written += $fwrite) {
            $fwrite = \fwrite($this->redis, \substr($command, $written));
            if ($fwrite === false || ($fwrite === 0 && $lastFailed)) {
                $this->close(true);
                throw new RedisException('Failed to write entire command to stream');
            }
            $lastFailed = $fwrite === 0;
        }
    }

    /**
     * @param string $name
     * @param bool   $returnQueued
     *
     * @return array|bool|null|string
     * @throws RedisException
     */
    protected function readReply($name = '', $returnQueued = false)
    {
        $reply = \fgets($this->redis);
        if ($reply === false) {
            $info = \stream_get_meta_data($this->redis);
            $this->close(true);
            if ($info['timed_out']) {
                throw new RedisException('Read operation timed out.', RedisException::CODE_TIMED_OUT);
            }

            throw new RedisException('Lost connection to Redis server.', RedisException::CODE_DISCONNECTED);
        }
        $reply = \rtrim($reply, "\r\n");
        #echo "> $name: $reply\n";
        $replyType = $reply[0];
        switch ($replyType) {
            /* Error reply */
            case '-':
                if ($this->isMulti || $this->usePipeline) {
                    $response = false;
                } elseif ($name === 'evalsha' && \strpos($reply, '-NOSCRIPT') === 0) {
                    $response = null;
                } elseif ($this->inCluster && (\strpos($reply, '-MOVED') === 0 || \strpos($reply, '-ASK') === 0)) {
                    return new RedisException(substr($reply, 1));
                } else {
                    throw new RedisException(substr($reply, 1));
                }
                break;
            /* Inline reply */
            case '+':
                $response = \substr($reply, 1);
                if ($response === 'OK') {
                    return true;
                }

                if ($response === 'QUEUED') {
                    return $returnQueued ? null : true;
                }
                break;
            /* Bulk reply */
            case '$':
                if ($reply === '$-1') {
                    return false;
                }
                $size = (int) \substr($reply, 1);
                $response = \stream_get_contents($this->redis, $size + 2);
                if (!$response) {
                    $this->close(true);
                    throw new RedisException('Error reading reply.');
                }
                $response = \substr($response, 0, $size);
                break;
            /* Multi-bulk reply */
            case '*':
                $count = \substr($reply, 1);
                if ($count === '-1') {
                    return false;
                }

                $response = [];
                for ($i = 0; $i < $count; $i++) {
                    $response[] = $this->readReply();
                }
                break;
            /* Integer reply */
            case ':':
                $response = (int) \substr($reply, 1);
                break;
            default:
                throw new RedisException('Invalid response: ' . \print_r($reply, true));
                break;
        }

        return $response;
    }

    /**
     * @param       $name
     * @param       $response
     * @param array $arguments
     *
     * @return array|bool
     * @throws RedisException
     */
    protected function decodeReply($name, $response, array &$arguments = [])
    {
        // Smooth over differences between phpredis and standalone response
        switch ($name) {
            case '': // Minor optimization for multi-bulk replies
                break;
            case 'config':
            case 'hgetall':
                $keys = $values = [];
                $i = 0;
                foreach ($response as $v) {
                    if (++$i % 2 === 1) {
                        $keys[] = $v;
                    } else {
                        $values[] = $v;
                    }
                }

                $response = \count($keys) ? \array_combine($keys, $values) : [];
                break;
            case 'info':
                $lines = \explode("\r\n", \trim($response, "\r\n"));
                $response = [];
                foreach ($lines as $line) {
                    if (!$line || $line[0] === '#') {
                        continue;
                    }
                    [$key, $value] = \explode(':', $line, 2);
                    $response[$key] = $value;
                }
                break;
            case 'ttl':
                if ($response === -1) {
                    $response = false;
                }
                break;
            case 'hmget':
                if (\count($arguments) !== \count($response)) {
                    throw new RedisException('hmget arguments and response do not match: ' . \print_r($arguments,
                            true) . ' ' . \print_r($response, true));
                }
                // rehydrate results into key => value form
                $response = \array_combine($arguments, $response);
                break;
            case 'scan':
            case 'sscan':
                $arguments[0] = (int) $response[0];
                unset($response[0]);
                $response = empty($response[1]) ? [] : $response[1];
                break;
            case 'hscan':
            case 'zscan':
                $arguments[0] = (int) $response[0];
                unset($response[0]);
                $response = empty($response[1]) ? [] : $response[1];
                if (!empty($response) && \is_array($response)) {
                    $count = \count($response);
                    $out = [];
                    for ($i = 0; $i < $count; $i += 2) {
                        $out[$response[$i]] = $response[$i + 1];
                    }
                    $response = $out;
                }
                break;
            case 'zrangebyscore':
            case 'zrevrangebyscore':
            case 'zrange':
            case 'zrevrange':
                if (\in_array('withscores', $arguments, true)) {
                    // Map array of values into key=>score list like phpRedis does
                    $item = null;
                    $out = [];
                    foreach ($response as $value) {
                        if ($item === null) {
                            $item = $value;
                        } else {
                            // 2nd value is the score
                            $out[$item] = (float) $value;
                            $item = null;
                        }
                    }
                    $response = $out;
                }
                break;
        }

        return $response;
    }
}
