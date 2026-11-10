<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$item = filter_var($_GET['item'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$fix = filter_var($_GET['fix'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
if (!$item) {
    $item = 5;
}
if ($item > 100) {
    $item = 100;
}

$tp = new Template('_tp_table.html');

$title = 'Fill up the table';

$tp->block('data');
$tp->sub(4);
for ($no = 1; $no <= $item; $no++) {
    $name = $no;
    $tp->apply();
}

if ($fix) {
    if ($tp->getCurrentSub() != 1) {
        for ($no = $tp->getCurrentSub(); $no <= 4; $no++) {
            $name = '';
            $tp->apply();
        }
    }
}

$tp->display();
exit;
