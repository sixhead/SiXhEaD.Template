<?php

require __DIR__ . '/../SiXhEaD.Template.php';

const CACHE_TTL = 5;

$redis = new Redis([
    'host' => getenv('REDIS_HOST') ?: 'redis',
    'port' => 6379,
    'connectTimeout' => 1.0,
]);

$tp = new Template('_tp_cache.html');

$title = 'Redis example 2';
$now = date('Y-m-d H:i:s');

$cache_name = 'example_redis2';
$data = $redis->get($cache_name);
if (!is_string($data)) {
    $data = '';
    for ($no = 1; $no < 6; $no++) {
        $data .= $tp->applyBlock('data');
    }
    $redis->set($cache_name, $data, ['ex' => CACHE_TTL]);
}

$tp->display();
exit;
