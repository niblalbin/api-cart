<?php
echo '<p>Versione PHP: ' . phpversion() . '</p>';
echo '<h2>Dettagli del server:</h2>';
echo '<pre>';
print_r($_SERVER);
echo '</pre>';
?>