<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace _PhpScoper5ea00cc67502b\Symfony\Component\Cache\Traits;

use _PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\ClusterInterface;
use _PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\RedisCluster;
use _PhpScoper5ea00cc67502b\Predis\Connection\Factory;
use _PhpScoper5ea00cc67502b\Predis\Response\Status;
use _PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\CacheException;
use _PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException;
/**
 * @author Aurimas Niekis <aurimas@niekis.lt>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait RedisTrait
{
    private static $defaultConnectionOptions = ['class' => null, 'persistent' => 0, 'persistent_id' => null, 'timeout' => 30, 'read_timeout' => 0, 'retry_interval' => 0, 'lazy' => \false];
    private $redis;
    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     */
    private function init($redisClient, $namespace = '', $defaultLifetime = 0)
    {
        parent::__construct($namespace, $defaultLifetime);
        if (\preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('RedisAdapter namespace contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
        }
        if (!$redisClient instanceof \_PhpScoper5ea00cc67502b\Redis && !$redisClient instanceof \_PhpScoper5ea00cc67502b\RedisArray && !$redisClient instanceof \_PhpScoper5ea00cc67502b\RedisCluster && !$redisClient instanceof \_PhpScoper5ea00cc67502b\Predis\Client && !$redisClient instanceof \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Traits\RedisProxy) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('"%s()" expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\\Client, "%s" given.', __METHOD__, \is_object($redisClient) ? \get_class($redisClient) : \gettype($redisClient)));
        }
        $this->redis = $redisClient;
    }
    /**
     * Creates a Redis connection using a DSN configuration.
     *
     * Example DSN:
     *   - redis://localhost
     *   - redis://example.com:1234
     *   - redis://secret@example.com/13
     *   - redis:///var/run/redis.sock
     *   - redis://secret@/var/run/redis.sock/13
     *
     * @param string $dsn
     * @param array  $options See self::$defaultConnectionOptions
     *
     * @throws InvalidArgumentException when the DSN is invalid
     *
     * @return \Redis|\Predis\Client According to the "class" option
     */
    public static function createConnection($dsn, array $options = [])
    {
        if (0 !== \strpos($dsn, 'redis://')) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s" does not start with "redis://".', $dsn));
        }
        $params = \preg_replace_callback('#^redis://(?:(?:[^:@]*+:)?([^@]*+)@)?#', function ($m) use(&$auth) {
            if (isset($m[1])) {
                $auth = $m[1];
            }
            return 'file://';
        }, $dsn);
        if (\false === ($params = \parse_url($params))) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s".', $dsn));
        }
        if (!isset($params['host']) && !isset($params['path'])) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Invalid Redis DSN: "%s".', $dsn));
        }
        if (isset($params['path']) && \preg_match('#/(\\d+)$#', $params['path'], $m)) {
            $params['dbindex'] = $m[1];
            $params['path'] = \substr($params['path'], 0, -\strlen($m[0]));
        }
        if (isset($params['host'])) {
            $scheme = 'tcp';
        } else {
            $scheme = 'unix';
        }
        $params += ['host' => isset($params['host']) ? $params['host'] : $params['path'], 'port' => isset($params['host']) ? 6379 : null, 'dbindex' => 0];
        if (isset($params['query'])) {
            \parse_str($params['query'], $query);
            $params += $query;
        }
        $params += $options + self::$defaultConnectionOptions;
        if (null === $params['class'] && !\extension_loaded('redis') && !\class_exists(\_PhpScoper5ea00cc67502b\Predis\Client::class)) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\CacheException(\sprintf('Cannot find the "redis" extension, and "predis/predis" is not installed: "%s".', $dsn));
        }
        $class = null === $params['class'] ? \extension_loaded('redis') ? \_PhpScoper5ea00cc67502b\Redis::class : \_PhpScoper5ea00cc67502b\Predis\Client::class : $params['class'];
        if (\is_a($class, \_PhpScoper5ea00cc67502b\Redis::class, \true)) {
            $connect = $params['persistent'] || $params['persistent_id'] ? 'pconnect' : 'connect';
            $redis = new $class();
            $initializer = function ($redis) use($connect, $params, $dsn, $auth) {
                try {
                    @$redis->{$connect}($params['host'], $params['port'], $params['timeout'], $params['persistent_id'], $params['retry_interval']);
                } catch (\_PhpScoper5ea00cc67502b\RedisException $e) {
                    throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Redis connection failed (%s): "%s".', $e->getMessage(), $dsn));
                }
                \set_error_handler(function ($type, $msg) use(&$error) {
                    $error = $msg;
                });
                $isConnected = $redis->isConnected();
                \restore_error_handler();
                if (!$isConnected) {
                    $error = \preg_match('/^Redis::p?connect\\(\\): (.*)/', $error, $error) ? \sprintf(' (%s)', $error[1]) : '';
                    throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Redis connection failed%s: "%s".', $error, $dsn));
                }
                if (null !== $auth && !$redis->auth($auth) || $params['dbindex'] && !$redis->select($params['dbindex']) || $params['read_timeout'] && !$redis->setOption(\_PhpScoper5ea00cc67502b\Redis::OPT_READ_TIMEOUT, $params['read_timeout'])) {
                    $e = \preg_replace('/^ERR /', '', $redis->getLastError());
                    throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Redis connection failed (%s): "%s".', $e, $dsn));
                }
                return \true;
            };
            if ($params['lazy']) {
                $redis = new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Traits\RedisProxy($redis, $initializer);
            } else {
                $initializer($redis);
            }
        } elseif (\is_a($class, \_PhpScoper5ea00cc67502b\Predis\Client::class, \true)) {
            $params['scheme'] = $scheme;
            $params['database'] = $params['dbindex'] ?: null;
            $params['password'] = $auth;
            $redis = new $class((new \_PhpScoper5ea00cc67502b\Predis\Connection\Factory())->create($params));
        } elseif (\class_exists($class, \false)) {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('"%s" is not a subclass of "Redis" or "Predis\\Client".', $class));
        } else {
            throw new \_PhpScoper5ea00cc67502b\Symfony\Component\Cache\Exception\InvalidArgumentException(\sprintf('Class "%s" does not exist.', $class));
        }
        return $redis;
    }
    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        if (!$ids) {
            return [];
        }
        $result = [];
        if ($this->redis instanceof \_PhpScoper5ea00cc67502b\Predis\Client && $this->redis->getConnection() instanceof \_PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\ClusterInterface) {
            $values = $this->pipeline(function () use($ids) {
                foreach ($ids as $id) {
                    (yield 'get' => [$id]);
                }
            });
        } else {
            $values = $this->redis->mget($ids);
            if (!\is_array($values) || \count($values) !== \count($ids)) {
                return [];
            }
            $values = \array_combine($ids, $values);
        }
        foreach ($values as $id => $v) {
            if ($v) {
                $result[$id] = parent::unserialize($v);
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return (bool) $this->redis->exists($id);
    }
    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $cleared = \true;
        $hosts = [$this->redis];
        $evalArgs = [[$namespace], 0];
        if ($this->redis instanceof \_PhpScoper5ea00cc67502b\Predis\Client) {
            $evalArgs = [0, $namespace];
            $connection = $this->redis->getConnection();
            if ($connection instanceof \_PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\ClusterInterface && $connection instanceof \Traversable) {
                $hosts = [];
                foreach ($connection as $c) {
                    $hosts[] = new \_PhpScoper5ea00cc67502b\Predis\Client($c);
                }
            }
        } elseif ($this->redis instanceof \_PhpScoper5ea00cc67502b\RedisArray) {
            $hosts = [];
            foreach ($this->redis->_hosts() as $host) {
                $hosts[] = $this->redis->_instance($host);
            }
        } elseif ($this->redis instanceof \_PhpScoper5ea00cc67502b\RedisCluster) {
            $hosts = [];
            foreach ($this->redis->_masters() as $host) {
                $hosts[] = $h = new \_PhpScoper5ea00cc67502b\Redis();
                $h->connect($host[0], $host[1]);
            }
        }
        foreach ($hosts as $host) {
            if (!isset($namespace[0])) {
                $cleared = $host->flushDb() && $cleared;
                continue;
            }
            $info = $host->info('Server');
            $info = isset($info['Server']) ? $info['Server'] : $info;
            if (!\version_compare($info['redis_version'], '2.8', '>=')) {
                // As documented in Redis documentation (http://redis.io/commands/keys) using KEYS
                // can hang your server when it is executed against large databases (millions of items).
                // Whenever you hit this scale, you should really consider upgrading to Redis 2.8 or above.
                $cleared = $host->eval("local keys=redis.call('KEYS',ARGV[1]..'*') for i=1,#keys,5000 do redis.call('DEL',unpack(keys,i,math.min(i+4999,#keys))) end return 1", $evalArgs[0], $evalArgs[1]) && $cleared;
                continue;
            }
            $cursor = null;
            do {
                $keys = $host instanceof \_PhpScoper5ea00cc67502b\Predis\Client ? $host->scan($cursor, 'MATCH', $namespace . '*', 'COUNT', 1000) : $host->scan($cursor, $namespace . '*', 1000);
                if (isset($keys[1]) && \is_array($keys[1])) {
                    $cursor = $keys[0];
                    $keys = $keys[1];
                }
                if ($keys) {
                    $this->doDelete($keys);
                }
            } while ($cursor = (int) $cursor);
        }
        return $cleared;
    }
    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        if (!$ids) {
            return \true;
        }
        if ($this->redis instanceof \_PhpScoper5ea00cc67502b\Predis\Client && $this->redis->getConnection() instanceof \_PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\ClusterInterface) {
            $this->pipeline(function () use($ids) {
                foreach ($ids as $id) {
                    (yield 'del' => [$id]);
                }
            })->rewind();
        } else {
            $this->redis->del($ids);
        }
        return \true;
    }
    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $serialized = [];
        $failed = [];
        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = \serialize($value);
            } catch (\Exception $e) {
                $failed[] = $id;
            }
        }
        if (!$serialized) {
            return $failed;
        }
        $results = $this->pipeline(function () use($serialized, $lifetime) {
            foreach ($serialized as $id => $value) {
                if (0 >= $lifetime) {
                    (yield 'set' => [$id, $value]);
                } else {
                    (yield 'setEx' => [$id, $lifetime, $value]);
                }
            }
        });
        foreach ($results as $id => $result) {
            if (\true !== $result && (!$result instanceof \_PhpScoper5ea00cc67502b\Predis\Response\Status || $result !== \_PhpScoper5ea00cc67502b\Predis\Response\Status::get('OK'))) {
                $failed[] = $id;
            }
        }
        return $failed;
    }
    private function pipeline(\Closure $generator)
    {
        $ids = [];
        if ($this->redis instanceof \_PhpScoper5ea00cc67502b\RedisCluster || $this->redis instanceof \_PhpScoper5ea00cc67502b\Predis\Client && $this->redis->getConnection() instanceof \_PhpScoper5ea00cc67502b\Predis\Connection\Aggregate\RedisCluster) {
            // phpredis & predis don't support pipelining with RedisCluster
            // see https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#pipelining
            // see https://github.com/nrk/predis/issues/267#issuecomment-123781423
            $results = [];
            foreach ($generator() as $command => $args) {
                $results[] = \call_user_func_array([$this->redis, $command], $args);
                $ids[] = $args[0];
            }
        } elseif ($this->redis instanceof \_PhpScoper5ea00cc67502b\Predis\Client) {
            $results = $this->redis->pipeline(function ($redis) use($generator, &$ids) {
                foreach ($generator() as $command => $args) {
                    \call_user_func_array([$redis, $command], $args);
                    $ids[] = $args[0];
                }
            });
        } elseif ($this->redis instanceof \_PhpScoper5ea00cc67502b\RedisArray) {
            $connections = $results = $ids = [];
            foreach ($generator() as $command => $args) {
                if (!isset($connections[$h = $this->redis->_target($args[0])])) {
                    $connections[$h] = [$this->redis->_instance($h), -1];
                    $connections[$h][0]->multi(\_PhpScoper5ea00cc67502b\Redis::PIPELINE);
                }
                \call_user_func_array([$connections[$h][0], $command], $args);
                $results[] = [$h, ++$connections[$h][1]];
                $ids[] = $args[0];
            }
            foreach ($connections as $h => $c) {
                $connections[$h] = $c[0]->exec();
            }
            foreach ($results as $k => list($h, $c)) {
                $results[$k] = $connections[$h][$c];
            }
        } else {
            $this->redis->multi(\_PhpScoper5ea00cc67502b\Redis::PIPELINE);
            foreach ($generator() as $command => $args) {
                \call_user_func_array([$this->redis, $command], $args);
                $ids[] = $args[0];
            }
            $results = $this->redis->exec();
        }
        foreach ($ids as $k => $id) {
            (yield $id => $results[$k]);
        }
    }
}
