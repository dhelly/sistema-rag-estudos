<?php
/**
 * ARQUIVO 6 de 6: logout.php (NOVO)
 * 
 * Salve este arquivo como: logout.php
 * Faz logout do usuário
 */

require_once 'auth.php';

Auth::logout();
header('Location: login.php');
exit;
?>