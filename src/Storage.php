<?php

declare(strict_types=1);

/**
 * Provides safe JSON persistence for the application data.
 */
final class Storage
{
    private string $filePath;

    /**
     * Creates a storage service for the given JSON file.
     *
     * @param string $filePath Absolute path to the JSON data file.
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Loads normalized application data, creating an empty document when needed.
     *
     * @return array<string, mixed> Normalized application data.
     */
    public function load(): array
    {
        if (!is_file($this->filePath)) {
            $data = self::emptyData();
            $this->save($data);
            return $data;
        }

        $contents = file_get_contents($this->filePath);
        $data = is_string($contents) ? json_decode($contents, true) : null;
        if (!is_array($data)) {
            throw new RuntimeException('Die Datendatei ist beschädigt oder ungültig.');
        }

        return self::normalize($data);
    }

    /**
     * Saves application data with an exclusive lock and an atomic replacement.
     *
     * @param array<string, mixed> $data Application data to persist.
     * @return void
     */
    public function save(array $data): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Das Datenverzeichnis konnte nicht erstellt werden.');
        }

        $temporaryPath = $this->filePath . '.tmp';
        $handle = fopen($temporaryPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Die Datendatei konnte nicht geschrieben werden.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Die Datendatei konnte nicht gesperrt werden.');
            }
            $json = json_encode(self::normalize($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            fwrite($handle, $json . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (!rename($temporaryPath, $this->filePath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Die Datendatei konnte nicht aktualisiert werden.');
        }
    }

    /**
     * Returns the initial empty document shape.
     *
     * @return array<string, mixed> Empty application data.
     */
    public static function emptyData(): array
    {
        return [
            'team' => ['name' => 'Meine Mannschaft', 'players' => []],
            'matchdays' => [],
        ];
    }

    /**
     * Normalizes optional or legacy values to the current document shape.
     *
     * @param array<string, mixed> $data Raw application data.
     * @return array<string, mixed> Normalized application data.
     */
    private static function normalize(array $data): array
    {
        $team = is_array($data['team'] ?? null) ? $data['team'] : [];
        $players = [];
        foreach (($team['players'] ?? []) as $player) {
            if (is_array($player) && isset($player['id'], $player['name'])) {
                $players[] = [
                    'id' => (string) $player['id'],
                    'name' => trim((string) $player['name']),
                ];
            }
        }

        $matchdays = [];
        foreach (($data['matchdays'] ?? []) as $matchday) {
            if (!is_array($matchday) || !isset($matchday['id'])) {
                continue;
            }
            $games = [];
            foreach (($matchday['games'] ?? []) as $game) {
                if (!is_array($game) || !isset($game['id'])) {
                    continue;
                }
                $sets = [];
                foreach (($game['sets'] ?? []) as $set) {
                    if (!is_array($set) || !isset($set['id'])) {
                        continue;
                    }
                    $assignments = [];
                    foreach (($set['assignments'] ?? []) as $assignment) {
                        if (is_array($assignment) && isset($assignment['playerId'], $assignment['playerName'])) {
                            $assignments[] = [
                                'playerId' => (string) $assignment['playerId'],
                                'playerName' => trim((string) $assignment['playerName']),
                                'points' => max(0, (int) ($assignment['points'] ?? 0)),
                            ];
                        }
                    }
                    $sets[] = ['id' => (string) $set['id'], 'assignments' => $assignments];
                }
                $games[] = [
                    'id' => (string) $game['id'],
                    'opponent' => trim((string) ($game['opponent'] ?? '')),
                    'sets' => $sets,
                ];
            }
            $matchdays[] = [
                'id' => (string) $matchday['id'],
                'date' => (string) ($matchday['date'] ?? ''),
                'time' => (string) ($matchday['time'] ?? ''),
                'location' => trim((string) ($matchday['location'] ?? '')),
                'opponents' => array_values(array_filter(array_map('strval', $matchday['opponents'] ?? []))),
                'games' => $games,
            ];
        }

        return [
            'team' => ['name' => trim((string) ($team['name'] ?? 'Meine Mannschaft')), 'players' => $players],
            'matchdays' => $matchdays,
        ];
    }
}