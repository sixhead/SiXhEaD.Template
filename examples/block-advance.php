<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$member = filter_var($_GET['member'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

$tp = new Template('_tp_block-advance.html');
$tp_item = new Template('_tp_item.html', 'partial');

$title = 'Block usage (advanced)';

if ($member == 1) {
    $username = 'admin';
    $raw = $tp_item->blockContent('message-member-1');
    $message = $tp_item->applyBlock('message-member-1');

    // loop style 1
    $item1_title = 'Section 1';
    $tp_item->block('item1');
    for ($no = 1; $no < 4; $no++) {
        $tp_item->apply();
    }
    $item1 = $tp_item->generate();
    $tp_item->clear('item1'); // required before this template is generated again

    // loop style 2
    $item2_title = 'Section 2';
    $item2 = '';
    $styles = ['success', 'info', 'warning', 'danger'];
    foreach ($styles as $key => $css) {
        $css_uc = ucfirst($css);
        $item2 .= $tp_item->applyBlock('item2');
    }
} else {
    $ip = '127.0.0.1';
    $raw = $tp_item->blockContent('message-member-0');
    $message = $tp_item->applyBlock('message-member-0');
}

$item3_title = 'Section 3';
$tp_item->block('item3');
$tp_item->sub(3);
for ($no = 1; $no < 9; $no++) {
    $tp_item->apply();
}
$item3 = $tp_item->generate();
$tp_item->clear('item3'); // required before this template is generated again

$item4_title = 'Section 4';
$tp->block('item4');
$tp->sub(3);
for ($no = 1; $no < 9; $no++) {
    $tp->apply();
}

$tp->display();
exit;
