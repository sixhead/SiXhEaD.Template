<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$tp = new Template('_tp_other.html');

$title = 'Usage from a class or function';
_setData();

$tp->display();
exit;

function _setData(): void
{
    global $tp;

    $tp->setGlobal(false);
    $tp->block('data');
    for ($no = 1; $no < 6; $no++) {
        $tp->assign('no', $no);
        $tp->apply();
    }
    $tp->setGlobal(true);
}
