<?php

require __DIR__ . '/../SiXhEaD.Template.php';

const CACHE_TTL = 5;

$redis = new Redis([
    'host' => getenv('REDIS_HOST') ?: 'redis',
    'port' => 6379,
    'connectTimeout' => 1.0,
]);

$cache_name = 'example_redis1';
$cache = $redis->get($cache_name);
if (!is_string($cache)) {
    $tp = new Template('_tp_cache.html');

    $title = 'Redis example 1';
    $now = date('Y-m-d H:i:s');

    $tp->block('data');
    for ($no = 1; $no < 6; $no++) {
        $tp->apply();
    }

    $cache = $tp->generate();
    $redis->set($cache_name, $cache, ['ex' => CACHE_TTL]);
}

echo $cache;
exit;
