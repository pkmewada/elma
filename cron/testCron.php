<?php

date_default_timezone_set('Asia/Kolkata');

file_put_contents(
    __DIR__ . '/php_test.log',
    date('Y-m-d H:i:s') . " PHP Cron Working\n",
    FILE_APPEND
);