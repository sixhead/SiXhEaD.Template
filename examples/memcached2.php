<?php

require __DIR__ . '/../SiXhEaD.Template.php';

const CACHE_TTL = 5;

$mem = new Memcached();
$mem->addServer(getenv('MEMCACHED_HOST') ?: 'memcached', 11211);

$tp = new Template('_tp_cache.html');

$title = 'Memcached example 2';
$now = date('Y-m-d H:i:s');

$cache_name = 'example_memcached2';
$data = $mem->get($cache_name);
if (!is_string($data)) {
    $data = '';
    for ($no = 1; $no < 6; $no++) {
        $data .= $tp->applyBlock('data');
    }
    $mem->set($cache_name, $data, CACHE_TTL);
}

$tp->display();
exit;
