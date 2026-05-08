<?php
session_start();
session_destroy();
header('Location: /emi/auth/login.php');
exit;
