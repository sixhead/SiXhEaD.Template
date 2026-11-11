<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$tp = new Template('_tp_table.html');

$title = 'Fill up the table';

$tp->block('data');
$tp->sub(4);
for ($no = 1; $no <= 6; $no++) {
    $name = $no;
    $tp->apply();
}

$name = '';
while ($tp->getCurrentSub() !== 1) {
    $tp->apply();
}

$tp->display();
exit;
