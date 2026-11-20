<?php
require __DIR__ . '/../app/auth.php';
iniciar_sesion_php();
$_SESSION = [];
session_destroy();
header('Location: ./');
