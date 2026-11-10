<?php

require __DIR__ . '/../SiXhEaD.Template.php';

$tp = new Template('_tp_block.html');

$title = 'Block usage (basic)';

$tp->block('data1');
for ($no = 1; $no < 6; $no++) {
    $active = '';
    if ($no == 3) {
        $active = 'active';
    }
    $tp->apply();
}

$styles = ['success', 'info', 'warning', 'danger'];
$tp->block('data2');
foreach ($styles as $key => $css) {
    $css_uc = ucfirst($css);
    $tp->apply();
}

$user_agent = filter_var($_SERVER['HTTP_USER_AGENT'] ?? '', FILTER_UNSAFE_RAW, ['options' => ['default' => '']]);
if (preg_match('@Firefox@', $user_agent)) {
    $tp->block('firefox');
    $tp->apply();
} elseif (preg_match('@Chrome@', $user_agent)) {
    $tp->block('chrome');
    $tp->apply();
}

$tp->display();
exit;
