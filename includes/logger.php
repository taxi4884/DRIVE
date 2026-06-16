<?php
function logMessage($message, $file)
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}
