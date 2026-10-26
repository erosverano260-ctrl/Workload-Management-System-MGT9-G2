<?php
require 'includes/auth.php';
logoutUser();
header('Location: login.php');
exit;
