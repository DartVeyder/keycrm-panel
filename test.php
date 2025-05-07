<?php
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

require 'vendor/autoload.php';

$output = new ConsoleOutput();
$progressBar = new ProgressBar($output, 100);
$progressBar->start();

for ($i = 0; $i < 100; $i++) {
    usleep(50000);
    $progressBar->advance();
}

$progressBar->finish();
echo "\nГотово!\n";
