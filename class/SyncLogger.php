<?php

/**
 * Логер синхронізації фотографій.
 * Пише в logs/photo_sync_YYYY-MM-DD.log та виводить у консоль.
 */
class SyncLogger
{
    const LEVEL_DEBUG   = 'DEBUG';
    const LEVEL_INFO    = 'INFO';
    const LEVEL_SUCCESS = 'SUCCESS';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR   = 'ERROR';

    /** @var string Шлях до файлу логу */
    private $logFile;

    /** @var bool Виводити у консоль? */
    private $toConsole;

    /** @var bool Кольоровий вивід у консоль? */
    private $colors;

    // ANSI кольори
    private $colorMap = [
        self::LEVEL_DEBUG   => "\033[0;37m",   // сірий
        self::LEVEL_INFO    => "\033[0;36m",   // блакитний
        self::LEVEL_SUCCESS => "\033[0;32m",   // зелений
        self::LEVEL_WARNING => "\033[0;33m",   // жовтий
        self::LEVEL_ERROR   => "\033[0;31m",   // червоний
    ];
    private $colorReset = "\033[0m";

    public function __construct(?string $logDir = null, bool $toConsole = true, bool $colors = true, string $prefix = 'photo_sync_')
    {
        if ($logDir === null) {
            $logDir = __DIR__ . '/../logs';
        }

        $typeDir = trim($prefix, '_');
        $logDir = rtrim($logDir, '/\\');
        if ($typeDir !== '') {
            $logDir .= '/' . $typeDir;
        }

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logFile   = $logDir . '/' . date('Y-m-d') . '.log';
        $this->toConsole = $toConsole;
        $this->colors    = $colors && $this->isCli();
    }

    // ----------------------------------------------------------------
    // Публічні методи-скорочення
    // ----------------------------------------------------------------

    public function debug(string $message, string $context = ''): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, string $context = ''): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function success(string $message, string $context = ''): void
    {
        $this->log(self::LEVEL_SUCCESS, $message, $context);
    }

    public function warning(string $message, string $context = ''): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, string $context = ''): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /** Розділювач — зручно між блоками */
    public function separator(string $title = ''): void
    {
        $line = str_repeat('─', 60);
        $text = $title ? "── $title " . str_repeat('─', max(0, 58 - mb_strlen($title))) : $line;
        $this->writeLine('', $text);
    }

    // ----------------------------------------------------------------
    // Внутрішня логіка
    // ----------------------------------------------------------------

    private function log(string $level, string $message, string $context = ''): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $ctx       = $context ? " [$context]" : '';

        // Рядок у файл (без кольорів)
        $fileLine = "[$timestamp][$level]$ctx $message";
        file_put_contents($this->logFile, $fileLine . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Рядок у консоль
        if ($this->toConsole) {
            $consoleLine = $this->formatConsole($level, $timestamp, $message, $context);
            echo $consoleLine . PHP_EOL;
        }
    }

    private function writeLine(string $level, string $text): void
    {
        file_put_contents($this->logFile, $text . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($this->toConsole) {
            $color = $level && isset($this->colorMap[$level]) ? $this->colorMap[$level] : '';
            echo ($this->colors ? $color : '') . $text . ($this->colors ? $this->colorReset : '') . PHP_EOL;
        }
    }

    private function formatConsole(string $level, string $timestamp, string $message, string $context): string
    {
        $icon = $this->icon($level);
        $ctx  = $context ? " \033[0;90m[$context]\033[0m" : '';
        $time = "\033[0;90m$timestamp\033[0m";
        $msg  = $message;

        if ($this->colors) {
            $color = $this->colorMap[$level] ?? '';
            return "$time $icon{$color}{$msg}{$this->colorReset}$ctx";
        }

        return "[$timestamp][$level] $message" . ($context ? " [$context]" : '');
    }

    private function icon(string $level): string
    {
        $icons = [
            self::LEVEL_DEBUG   => '🔍 ',
            self::LEVEL_INFO    => 'ℹ️  ',
            self::LEVEL_SUCCESS => '✅ ',
            self::LEVEL_WARNING => '⚠️  ',
            self::LEVEL_ERROR   => '❌ ',
        ];
        return $icons[$level] ?? '';
    }

    private function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    /** Повертає шлях до поточного файлу логу */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
