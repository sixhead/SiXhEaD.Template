<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$tp = new Template('_tp_loop-in-loop.html', 'butterfly');

$title = 'Nested loops';

$tp->block('items1');
$styles = ['danger', 'info'];
foreach ($styles as $key => $css) {
    $item = '';
    for ($no = 1; $no < 3; $no++) {
        $item .= $tp->applyBlock('item');
    }
    $tp->apply();
}

$tp->block('items2');
$styles = ['success', 'warning'];
foreach ($styles as $key => $css) {
    $item = '';
    for ($no = 1; $no < 3; $no++) {
        $item .= $tp->applyBlock('item');
    }
    $tp->apply();
}

$tp->display();
exit;
