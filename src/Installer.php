<?php

namespace Bangkah\Starter;

use Composer\Script\Event;
use Symfony\Component\Process\Process;
use function file_exists;
use function getenv;
use function getcwd;
use function sprintf;

class Installer
{
    public static function interactive(Event $event): void
    {
        $projectRoot = getcwd();

        if (!file_exists($projectRoot . '/artisan')) {
            return;
        }

        if (getenv('BANGKAH_SKIP_INTERACTIVE')) {
            return;
        }

        $io = $event->getIO();

        $io->write("\n");
        $io->write("<info>╔══════════════════════════════════════╗</info>\n");
        $io->write("<info>║   🚀 Bangkah Starter Kit Installed  ║</info>\n");
        $io->write("<info>╚══════════════════════════════════════╝</info>\n");
        $io->write("\n");

        if (!$io->isInteractive()) {
            $io->writeError("<comment>Jalankan 'php artisan starter:create' untuk mulai scaffold project.</comment>\n");
            return;
        }

        $confirm = $io->askConfirmation(
            '<question>Mulai membuat project scaffolding sekarang? (Y/n): </question>',
            true
        );

        if (!$confirm) {
            $io->writeError("<comment>Anda dapat menjalankan 'php artisan starter:create' kapan saja.</comment>\n");
            return;
        }

        $process = new Process(['php', 'artisan', 'starter:create']);
        $process->setTty(true);
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->writeError("<error>Gagal menjalankan starter:create!</error>");
        }
    }
}
