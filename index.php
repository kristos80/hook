<?php
declare(strict_types=1);

use Kristos80\Hooks\Hook;

require_once __DIR__ . "/vendor/autoload.php";

$hook = new Hook();

$hook->addAction("end_init", function() {
	echo "Finished";
});

$hook->addAction([
	"init",
	"d_init",
], function() use ($hook) {
	echo "Started";
});

$hook->addAction("init", function() {
	echo PHP_EOL;
}, 9);

$hook->doAction("init");
$hook->doAction("d_init");
$hook->doAction("end_init");



