<?php

require_once __DIR__ . '/../dont_touch_kinda_stuff/bootstrap.php';
internhub_start_session();
internhub_destroy_session();
header("Location: auth.php");
exit;
