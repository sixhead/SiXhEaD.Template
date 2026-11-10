<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$tp = new Template('_tp_other.html');
$tp->setGlobal(false);

$title = 'Usage from a class or function';
$tp->assign('title', $title);
_setData();

$tp->display();
exit;

function _setData(): void
{
    global $tp;

    $tp->block('data');
    for ($no = 1; $no < 6; $no++) {
        $tp->assign('no', $no);
        $tp->apply();
    }
}
