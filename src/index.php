<?php
date_default_timezone_set('Asia/Shanghai');

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/Env.php';

require __DIR__ . '/LogFactory.php';

require __DIR__ . '/SyncOrders.php';

sync2Dist();
