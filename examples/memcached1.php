<?php

require __DIR__ . '/../SiXhEaD.Template.php';

const CACHE_TTL = 5;

$mem = new Memcached();
$mem->addServer(getenv('MEMCACHED_HOST') ?: 'memcached', 11211);

$cache_name = 'example_memcached1';
$cache = $mem->get($cache_name);
if (!is_string($cache)) {
    $tp = new Template('_tp_cache.html');

    $title = 'Memcached example 1';
    $now = date('Y-m-d H:i:s');

    $tp->block('data');
    for ($no = 1; $no < 6; $no++) {
        $tp->apply();
    }

    $cache = $tp->generate();
    $mem->set($cache_name, $cache, CACHE_TTL);
}

echo $cache;
exit;
