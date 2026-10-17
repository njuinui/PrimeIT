<?php

namespace App\Domain\Design;

use App\Models\DesignFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports a Figma file's saved versions as design versions.
 *
 * Adobe XD (and Sketch etc.) have no comparable public version feed that
 * this app can read, so those files are tracked by logging versions by hand.
 */
final class FigmaSync
{
    public function __construct(private readonly RecordDesignVersion $record) {}

    public function configured(): bool
    {
        return filled(config('services.figma.token'));
    }

    /**
     * @return int number of new versions imported
     */
    public function sync(DesignFile $file): int
    {
        if ($file->provider !== 'figma' || $file->external_key === null) {
            return 0;
        }

        if (! $this->configured()) {
            throw new RuntimeException('Figma sync is not configured. Set FIGMA_TOKEN in the backend .env.');
        }

        $response = Http::withHeaders(['X-Figma-Token' => (string) config('services.figma.token')])
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.figma.com/v1/files/' . $file->external_key . '/versions');

        $response->throw();

        $known = $file->versions()
            ->whereNotNull('external_version_id')
            ->pluck('external_version_id')
            ->all();

        $includeAutosaves = (bool) config('services.figma.include_autosaves', false);

        $new = collect($response->json('versions', []))
            ->filter(fn ($v) => isset($v['id'], $v['created_at']))
            ->reject(fn ($v) => in_array((string) $v['id'], $known, true))
            // Autosaves have no label; importing them would drown the timeline.
            ->filter(fn ($v) => $includeAutosaves || filled($v['label'] ?? null))
            ->sortBy('created_at')
            ->values();

        foreach ($new as $version) {
            ($this->record)($file, null, [
                'label'               => filled($version['label'] ?? null) ? (string) $version['label'] : 'Autosave',
                'note'                => $version['description'] ?? null,
                'external_version_id' => (string) $version['id'],
                'external_author'     => data_get($version, 'user.handle'),
                'thumbnail_url'       => $version['thumbnail_url'] ?? null,
                'committed_at'        => Carbon::parse($version['created_at']),
            ]);
        }

        $file->forceFill(['last_synced_at' => now()])->save();

        return $new->count();
    }
}
