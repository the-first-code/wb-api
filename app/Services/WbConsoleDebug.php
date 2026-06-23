<?php

namespace App\Services;

use Symfony\Component\Console\Output\OutputInterface;

class WbConsoleDebug
{
    private bool $enabled = false;

    private ?OutputInterface $output = null;

    public function enable(?OutputInterface $output = null): void
    {
        $this->enabled = true;
        $this->output = $output;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function line(string $message): void
    {
        if (! $this->enabled) {
            return;
        }

        $line = "[debug] {$message}";

        if ($this->output !== null) {
            $this->output->writeln("  <comment>{$line}</comment>");

            return;
        }

        fwrite(STDOUT, $line.PHP_EOL);
    }
}
