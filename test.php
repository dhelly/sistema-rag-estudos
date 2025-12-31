<?php
echo "PHP Version: " . phpversion() . "\n";
echo "SQLite3: " . (extension_loaded('sqlite3') ? 'OK ✓' : 'NÃO INSTALADO ✗') . "\n";
echo "cURL: " . (extension_loaded('curl') ? 'OK ✓' : 'NÃO INSTALADO ✗') . "\n";
?>