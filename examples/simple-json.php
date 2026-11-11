<?php

require __DIR__ . '/../SiXhEaD.Template.php';

header('Content-Type: application/json');

$tp = new Template(__DIR__ . '/_tp_simple.json');

$title = json_encode('How to use "SiXhEaD.Template"', JSON_THROW_ON_ERROR);

$tp->block('data');
$separator = '';
for ($no = 1; $no < 6; $no++) {
    $tp->apply();
    $separator = ', ';
}

echo $tp->generate();
exit;
