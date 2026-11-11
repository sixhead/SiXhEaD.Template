<?php

require __DIR__ . '/../SiXhEaD.Template.php';

header('Content-Type: application/xml; charset=UTF-8');

$tp = new Template(__DIR__ . '/_tp_simple.xml');

$title = htmlspecialchars('How to use SiXhEaD.Template with XML & <text>', ENT_XML1 | ENT_QUOTES, 'UTF-8');

$tp->block('data');
for ($no = 1; $no < 6; $no++) {
    $tp->apply();
}

echo $tp->generate();
exit;
