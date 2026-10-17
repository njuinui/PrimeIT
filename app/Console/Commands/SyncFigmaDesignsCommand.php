<?php

namespace App\Console\Commands;

use App\Domain\Design\FigmaSync;
use App\Models\DesignFile;
use Illuminate\Console\Command;
use Throwable;

class SyncFigmaDesignsCommand extends Command
{
    protected $signature = 'design:sync-figma';

    protected $description = 'Import new saved versions of tracked Figma files as design versions.';

    public function handle(FigmaSync $figma): int
    {
        if (! $figma->configured()) {
            $this->warn('FIGMA_TOKEN is not set — nothing to sync.');

            return self::SUCCESS;
        }

        $total = 0;

        DesignFile::query()
            ->where('provider', 'figma')
            ->whereNotNull('external_key')
            ->whereNotIn('status', ['handed_off'])
            ->each(function (DesignFile $file) use ($figma, &$total): void {
                try {
                    $total += $figma->sync($file);
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Could not sync \"{$file->title}\".");
                }
            });

        $this->info("Imported {$total} new design version(s).");

        return self::SUCCESS;
    }
}
