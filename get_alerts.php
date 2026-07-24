<?php

$logFile = '/var/log/syslog';
// OPTION :
// $logFile = "/var/log/fail2ban.log";
// $logFile = "/var/log/auth.log";

if (file_exists($logFile)) {
  $lines = shell_exec("tail -n 50 $logFile");

  $lines = explode("\n", $lines);

  foreach ($lines as $line) {
    if (strpos($line, 'Ban') !== false) {
      echo "<span style='color:red'>$line</span><br>";
    } elseif (strpos($line, 'Failed') !== false) {
      echo "<span style='color:orange'>$line</span><br>";
    } else {
      echo htmlspecialchars($line) . '<br>';
    }
  }
} else {
  echo 'Log introuvable';
}

?>
