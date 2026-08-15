<?php
require __DIR__ . '/../src/bootstrap.php';

do_logout();
redirect('login.php');
