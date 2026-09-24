<?php

declare(strict_types=1);

/**
 * Generates a unique identifier for a domain entity.
 *
 * @return string Unique identifier.
 */
function newId(): string
{
    return bin2hex(random_bytes(8));
}

/**
 * Returns player statistics derived from all saved assignments.
 *
 * @param array<string, mixed> $data Application data.
 * @return array<string, array{days:int, sets:int, points:int}> Statistics by player ID.
 */
function calculatePlayerStats(array $data): array
{
    $stats = [];
    foreach ($data['team']['players'] as $player) {
        $stats[$player['id']] = ['name' => $player['name'], 'days' => 0, 'sets' => 0, 'points' => 0];
    }

    foreach ($data['matchdays'] as $matchday) {
        $dayPlayers = [];
        foreach ($matchday['games'] as $game) {
            foreach ($game['sets'] as $set) {
                foreach ($set['assignments'] as $assignment) {
                    $id = $assignment['playerId'];
                    if (!isset($stats[$id])) {
                        $stats[$id] = ['name' => $assignment['playerName'], 'days' => 0, 'sets' => 0, 'points' => 0];
                    }
                    $dayPlayers[$id] = true;
                    $stats[$id]['sets']++;
                    $stats[$id]['points'] += $assignment['points'];
                }
            }
        }
        foreach (array_keys($dayPlayers) as $id) {
            $stats[$id]['days']++;
        }
    }

    uasort($stats, static function (array $left, array $right): int {
        return [$left['days'], $left['sets'], $left['points'], strtolower($left['name'])]
            <=> [$right['days'], $right['sets'], $right['points'], strtolower($right['name'])];
    });
    return $stats;
}

/**
 * Finds a matchday by its identifier.
 *
 * @param array<string, mixed> $data Application data.
 * @param string $id Matchday identifier.
 * @return array<string, mixed>|null Matchday or null when absent.
 */
function findMatchday(array $data, string $id): ?array
{
    foreach ($data['matchdays'] as $matchday) {
        if ($matchday['id'] === $id) {
            return $matchday;
        }
    }
    return null;
}

/**
 * Returns a player's current name by identifier.
 *
 * @param array<string, mixed> $data Application data.
 * @param string $id Player identifier.
 * @return string Player name.
 */
function playerName(array $data, string $id): string
{
    foreach ($data['team']['players'] as $player) {
        if ($player['id'] === $id) {
            return $player['name'];
        }
    }
    return '';
}

/**
 * Sorts matchdays chronologically.
 *
 * @param array<int, array<string, mixed>> $matchdays Matchdays to sort.
 * @return array<int, array<string, mixed>> Sorted matchdays.
 */
function sortMatchdays(array $matchdays): array
{
    usort($matchdays, static fn (array $left, array $right): int => strcmp($left['date'] . $left['time'], $right['date'] . $right['time']));
    return $matchdays;
}