<?php
require 'includes/auth.php';
header('Location: ' . appUrl(homeFor(currentRole())));
exit;
