<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/src/Storage.php';
require_once __DIR__ . '/src/Domain.php';
require_once __DIR__ . '/src/View.php';

$storage = new Storage(__DIR__ . '/data/data.json');
$data = $storage->load();
$page = (string) ($_GET['page'] ?? 'dashboard');
$isLoggedIn = !empty($_SESSION['loggedIn']);

/** Redirects after a successful mutation. @return never */
function redirectSaved(string $page, ?string $id = null): never
{
    $location = '?page=' . rawurlencode($page) . '&saved=1';
    if ($id !== null) $location .= '&id=' . rawurlencode($id);
    header('Location: ' . $location);
    exit;
}

/** Redirects with a user-facing error. @return never */
function redirectError(string $page, string $message, ?string $id = null): never
{
    $location = '?page=' . rawurlencode($page) . '&error=' . rawurlencode($message);
    if ($id !== null) $location .= '&id=' . rawurlencode($id);
    header('Location: ' . $location);
    exit;
}

/** Validates a matchday date and time. */
function validDateTime(string $date, string $time): bool
{
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);
    $timeObject = DateTime::createFromFormat('H:i', $time);
    return $dateObject !== false && $dateObject->format('Y-m-d') === $date
        && $timeObject !== false && $timeObject->format('H:i') === $time;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!$isLoggedIn && !in_array($action, ['login', 'logout'], true)) redirectError('dashboard', 'Bitte zuerst einloggen.');
    try {
        if ($action === 'login') {
            $password = (string) ($_POST['password'] ?? '');
            if (!hash_equals($data['auth']['password'], $password)) redirectError($page, 'Falsches Passwort.');
            session_regenerate_id(true);
            $_SESSION['loggedIn'] = true;
            header('Location: ?page=' . rawurlencode($page));
            exit;
        }
        if ($action === 'logout') {
            unset($_SESSION['loggedIn']);
            header('Location: ?page=' . rawurlencode($page));
            exit;
        }
        if ($action === 'save_team') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') redirectError('team', 'Bitte einen Mannschaftsnamen eingeben.');
            $data['team']['name'] = $name;
            $storage->save($data);
            redirectSaved('team');
        }
        if ($action === 'add_player') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') redirectError('team', 'Bitte einen Spielernamen eingeben.');
            $data['team']['players'][] = ['id' => newId(), 'name' => $name];
            $storage->save($data);
            redirectSaved('team');
        }
        if ($action === 'delete_player') {
            $id = (string) ($_POST['playerId'] ?? '');
            $data['team']['players'] = array_values(array_filter($data['team']['players'], static fn (array $player): bool => $player['id'] !== $id));
            $storage->save($data);
            redirectSaved('team');
        }
        if ($action === 'add_matchday' || $action === 'update_matchday') {
            $date = (string) ($_POST['date'] ?? '');
            $time = (string) ($_POST['time'] ?? '');
            $location = trim((string) ($_POST['location'] ?? ''));
            $opponents = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $_POST['opponents'] ?? [])));
            if (!validDateTime($date, $time) || $location === '') redirectError('matchdays', 'Bitte Datum, Uhrzeit und Ort vollständig ausfüllen.');
            if (count($opponents) < 1 || count($opponents) > 2) redirectError('matchdays', 'Bitte mindestens einen und maximal zwei Gegner eintragen.');
            if ($action === 'add_matchday') {
                $games = array_map(static fn (string $opponent): array => ['id' => newId(), 'opponent' => $opponent, 'sets' => []], $opponents);
                $data['matchdays'][] = ['id' => newId(), 'date' => $date, 'time' => $time, 'location' => $location, 'opponents' => $opponents, 'games' => $games];
            } else {
                $matchdayId = (string) ($_POST['id'] ?? '');
                foreach ($data['matchdays'] as &$matchday) {
                    if ($matchday['id'] !== $matchdayId) continue;
                    $matchday['date'] = $date;
                    $matchday['time'] = $time;
                    $matchday['location'] = $location;
                    $matchday['opponents'] = $opponents;
                    foreach ($opponents as $index => $opponent) {
                        $matchday['games'][$index] ??= ['id' => newId(), 'opponent' => $opponent, 'sets' => []];
                        $matchday['games'][$index]['opponent'] = $opponent;
                    }
                    $matchday['games'] = array_slice($matchday['games'], 0, count($opponents));
                }
                unset($matchday);
            }
            $storage->save($data);
            redirectSaved('matchdays');
        }
        if ($action === 'delete_matchday') {
            $id = (string) ($_POST['id'] ?? '');
            $data['matchdays'] = array_values(array_filter($data['matchdays'], static fn (array $matchday): bool => $matchday['id'] !== $id));
            $storage->save($data);
            redirectSaved('matchdays');
        }
        if (in_array($action, ['add_set', 'delete_set', 'save_set'], true)) {
            $matchdayId = (string) ($_POST['matchdayId'] ?? '');
            $gameId = (string) ($_POST['gameId'] ?? '');
            $setId = (string) ($_POST['setId'] ?? '');
            foreach ($data['matchdays'] as &$matchday) {
                if ($matchday['id'] !== $matchdayId) continue;
                foreach ($matchday['games'] as &$game) {
                    if ($game['id'] !== $gameId) continue;
                    if ($action === 'add_set' && count($game['sets']) < 3) $game['sets'][] = ['id' => newId(), 'assignments' => []];
                    if ($action === 'delete_set') $game['sets'] = array_values(array_filter($game['sets'], static fn (array $set): bool => $set['id'] !== $setId));
                    if ($action === 'save_set') {
                        $assignments = [];
                        foreach (is_array($_POST['assignments'] ?? null) ? $_POST['assignments'] : [] as $playerId => $assignment) {
                            $name = playerName($data, (string) $playerId);
                            if (!is_array($assignment) || !isset($assignment['selected']) || $name === '') continue;
                            $assignments[] = ['playerId' => (string) $playerId, 'playerName' => $name, 'points' => max(0, min(999, (int) ($assignment['points'] ?? 0)))];
                        }
                        foreach ($game['sets'] as &$set) if ($set['id'] === $setId) $set['assignments'] = $assignments;
                        unset($set);
                    }
                }
                unset($game);
            }
            unset($matchday);
            $storage->save($data);
            redirectSaved('matchday', $matchdayId);
        }
    } catch (Throwable $exception) {
        redirectError('dashboard', 'Speichern fehlgeschlagen: ' . $exception->getMessage());
    }
}

$teamName = $data['team']['name'];
if (!$isLoggedIn && $page !== 'dashboard') {
    header('Location: ?page=dashboard');
    exit;
}
if ($page === 'team') {
    renderHeader('Mannschaft', $teamName, 'team');
    renderPageHeading('Kader', 'Mannschaft pflegen', 'Spieler hinzufügen und die aktuelle Mannschaft übersichtlich halten.');
    ?><section class="grid two-col">
        <article class="panel"><h2>Mannschaftsname</h2><form method="post" class="stack-form"><input type="hidden" name="action" value="save_team"><label for="team-name">Name</label><input id="team-name" name="name" value="<?= e($teamName) ?>" required><button class="button primary" type="submit">Speichern</button></form></article>
        <article class="panel"><div class="panel-heading"><div><p class="eyebrow">Aktueller Kader</p><h2><?= count($data['team']['players']) ?> Spieler</h2></div></div>
        <?php if (!$data['team']['players']): renderEmptyState('Noch keine Spieler', 'Füge den ersten Namen hinzu.'); else: ?>
            <ul class="player-list"><?php foreach ($data['team']['players'] as $player): ?><li><span><?= e($player['name']) ?></span><form method="post" data-confirm="Spieler aus der aktuellen Mannschaft entfernen?"><input type="hidden" name="action" value="delete_player"><input type="hidden" name="playerId" value="<?= e($player['id']) ?>"><button class="icon-button danger" title="Spieler entfernen" aria-label="Spieler entfernen">×</button></form></li><?php endforeach; ?></ul>
        <?php endif; ?></article>
    </section><section class="panel narrow-panel"><h2>Spieler hinzufügen</h2><form method="post" class="inline-form"><input type="hidden" name="action" value="add_player"><label class="sr-only" for="player-name">Spielername</label><input id="player-name" name="name" placeholder="Name der Person" required><button class="button primary" type="submit">Spieler hinzufügen</button></form></section><?php
    renderFooter(); exit;
}
if ($page === 'matchdays') {
    renderHeader('Spieltage', $teamName, 'matchdays');
    renderPageHeading('Kalender', 'Spieltage', 'Termine, Gegner und Spielorte zentral verwalten.', '<a class="button primary" href="#new-matchday">+ Spieltag anlegen</a>');
    $matchdays = sortMatchdays($data['matchdays']);
    if (!$matchdays) renderEmptyState('Noch keine Spieltage', 'Lege den ersten Termin für deine Mannschaft an.');
    foreach ($matchdays as $matchday): ?><article class="matchday-row"><div class="date-tile"><strong><?= e(date('d', strtotime($matchday['date']))) ?></strong><span><?= e(date('M', strtotime($matchday['date']))) ?></span></div><div class="row-main"><h2><a href="?page=matchday&amp;id=<?= e($matchday['id']) ?>"><?= e(implode(' · ', $matchday['opponents'])) ?></a></h2><p><?= e($matchday['time']) ?> Uhr · <?= e($matchday['location']) ?></p></div><span class="row-meta"><?= count($matchday['games']) ?> Spiele</span><form method="post" data-confirm="Diesen Spieltag wirklich löschen?"><input type="hidden" name="action" value="delete_matchday"><input type="hidden" name="id" value="<?= e($matchday['id']) ?>"><button class="icon-button danger" title="Spieltag löschen" aria-label="Spieltag löschen">×</button></form></article><?php endforeach; ?>
    <section class="panel" id="new-matchday"><h2>Spieltag anlegen</h2><form method="post" class="form-grid"><input type="hidden" name="action" value="add_matchday"><label>Datum<input type="date" name="date" required></label><label>Uhrzeit<input type="time" name="time" required></label><label class="span-2">Ort<input name="location" placeholder="Sporthalle, Straße" required></label><label>Gegner 1<input name="opponents[]" required></label><label>Gegner 2 <span class="muted">(optional)</span><input name="opponents[]" placeholder="Optional"></label><div class="form-actions span-2"><button class="button primary" type="submit">Spieltag speichern</button></div></form></section><?php
    renderFooter(); exit;
}
if ($page === 'matchday') {
    $matchday = findMatchday($data, (string) ($_GET['id'] ?? ''));
    if ($matchday === null) redirectError('matchdays', 'Der Spieltag wurde nicht gefunden.');
    renderHeader('Spieltagsdetails', $teamName, 'matchdays');
    renderPageHeading('Spieltagsdetails', implode(' · ', $matchday['opponents']), date('d.m.Y', strtotime($matchday['date'])) . ' · ' . $matchday['time'] . ' Uhr · ' . $matchday['location'], '<a class="button secondary" href="?page=matchdays">← Zurück</a>');
    ?><section class="panel"><details><summary>Spieltagsdaten bearbeiten</summary><form method="post" class="form-grid edit-form"><input type="hidden" name="action" value="update_matchday"><input type="hidden" name="id" value="<?= e($matchday['id']) ?>"><label>Datum<input type="date" name="date" value="<?= e($matchday['date']) ?>" required></label><label>Uhrzeit<input type="time" name="time" value="<?= e($matchday['time']) ?>" required></label><label class="span-2">Ort<input name="location" value="<?= e($matchday['location']) ?>" required></label><?php foreach ($matchday['opponents'] as $opponent): ?><label>Gegner<input name="opponents[]" value="<?= e($opponent) ?>" required></label><?php endforeach; ?><div class="form-actions span-2"><button class="button primary" type="submit">Spieltagsdaten speichern</button></div></form></details></section><?php
    ?><div class="game-list"><?php foreach ($matchday['games'] as $gameIndex => $game): ?><article class="game-card"><div class="game-heading"><div><p class="eyebrow">Spiel <?= $gameIndex + 1 ?></p><h2><?= e($teamName) ?> <span>gegen</span> <?= e($game['opponent']) ?></h2></div><form method="post"><input type="hidden" name="action" value="add_set"><input type="hidden" name="matchdayId" value="<?= e($matchday['id']) ?>"><input type="hidden" name="gameId" value="<?= e($game['id']) ?>"><button class="button secondary" type="submit" <?= count($game['sets']) >= 3 ? 'disabled' : '' ?>>+ Satz</button></form></div><?php if (!$game['sets']) renderEmptyState('Noch keine Sätze', 'Lege den ersten Satz an, um Einsätze zu erfassen.'); ?><div class="set-list"><?php foreach ($game['sets'] as $setIndex => $set): ?><section class="set-card"><div class="set-heading"><h3>Satz <?= $setIndex + 1 ?></h3><form method="post" data-confirm="Diesen Satz wirklich löschen?"><input type="hidden" name="action" value="delete_set"><input type="hidden" name="matchdayId" value="<?= e($matchday['id']) ?>"><input type="hidden" name="gameId" value="<?= e($game['id']) ?>"><input type="hidden" name="setId" value="<?= e($set['id']) ?>"><button class="text-button danger" type="submit">Satz löschen</button></form></div><form method="post"><input type="hidden" name="action" value="save_set"><input type="hidden" name="matchdayId" value="<?= e($matchday['id']) ?>"><input type="hidden" name="gameId" value="<?= e($game['id']) ?>"><input type="hidden" name="setId" value="<?= e($set['id']) ?>"><div class="assignment-list"><?php foreach ($data['team']['players'] as $player): $assignment = null; foreach ($set['assignments'] as $saved) if ($saved['playerId'] === $player['id']) $assignment = $saved; ?><label class="assignment"><input type="checkbox" name="assignments[<?= e($player['id']) ?>][selected]" value="1" <?= $assignment !== null ? 'checked' : '' ?>><span><?= e($player['name']) ?></span><span class="point-control"><button type="button" class="step-button" data-step="-1" aria-label="Punkt abziehen">−</button><input type="number" name="assignments[<?= e($player['id']) ?>][points]" value="<?= e((string) ($assignment['points'] ?? 0)) ?>" min="0" max="999" aria-label="Punkte für <?= e($player['name']) ?>"><button type="button" class="step-button" data-step="1" aria-label="Punkt hinzufügen">+</button></span></label><?php endforeach; ?></div><button class="button primary" type="submit">Satz speichern</button></form></section><?php endforeach; ?></div></article><?php endforeach; ?></div><?php
    renderFooter(); exit;
}
if ($page === 'stats') {
    renderHeader('Statistik', $teamName, 'stats');
    renderPageHeading('Auswertung', 'Spielerstatistik', 'Einsätze über alle gespeicherten Spieltage hinweg.');
    $stats = calculatePlayerStats($data);
    ?><section class="panel"><div class="table-wrap"><table><thead><tr><th>Spieler</th><th>Spieltage</th><th>Sätze</th><th>Punkte</th></tr></thead><tbody><?php foreach ($stats as $stat): ?><tr><th><?= e($stat['name']) ?></th><td><?= $stat['days'] ?></td><td><?= $stat['sets'] ?></td><td><?= $stat['points'] ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php
    renderFooter(); exit;
}
renderHeader('Übersicht', $teamName, 'dashboard');
$upcoming = sortMatchdays($data['matchdays']);
$dashboardStats = calculatePlayerStats($data);
renderPageHeading('Saisonübersicht', 'Mannschaftsüberblick', 'Spieltage planen, Einsätze dokumentieren und Statistiken.');
?><section class="dashboard-grid"><a class="metric-card" href="?page=team"><span class="metric-label">Spieler</span><strong><?= count($data['team']['players']) ?></strong><span class="metric-link">Mannschaft pflegen →</span></a><a class="metric-card" href="?page=matchdays"><span class="metric-label">Spieltage</span><strong><?= count($data['matchdays']) ?></strong><span class="metric-link">Kalender öffnen →</span></a><a class="metric-card" href="?page=stats"><span class="metric-label">Sätze erfasst</span><strong><?= array_sum(array_map(static fn (array $matchday): int => array_sum(array_map(static fn (array $game): int => count($game['sets']), $matchday['games'])), $data['matchdays'])) ?></strong><span class="metric-link">Statistik ansehen →</span></a></section><section class="panel"><div class="panel-heading"><div><p class="eyebrow">Nächste Termine</p><h2>Spieltage</h2></div><a class="text-button" href="?page=matchdays">Alle anzeigen →</a></div><?php if (!$upcoming): ?><?php renderEmptyState('Dein Kalender ist leer', 'Lege einen Spieltag an, um Einsätze und Sätze zu dokumentieren.'); ?><?php else: ?><div class="compact-list"><?php foreach (array_slice($upcoming, 0, 4) as $matchday): ?><a class="compact-row" href="?page=matchday&amp;id=<?= e($matchday['id']) ?>"><span class="compact-date"><?= e(date('d.m.', strtotime($matchday['date']))) ?></span><span><strong><?= e(implode(' · ', $matchday['opponents'])) ?></strong><small><?= e($matchday['location']) ?> · <?= e($matchday['time']) ?> Uhr</small></span><span>→</span></a><?php endforeach; ?></div><?php endif; ?></section><section class="panel compact-stats"><div class="panel-heading"><div><p class="eyebrow">Kompakte Auswertung</p><h2>Spielerstatistik</h2></div><a class="text-button" href="?page=stats">Details →</a></div><?php if (!$dashboardStats): ?><?php renderEmptyState('Noch keine Statistik', 'Sobald Einsätze erfasst sind, erscheinen sie hier.'); ?><?php else: ?><div class="compact-stats-list"><?php foreach ($dashboardStats as $stat): ?><div class="compact-stat-row"><strong><?= e($stat['name']) ?></strong><span><?= $stat['days'] ?> Spieltage</span><span><?= $stat['sets'] ?> Sätze</span><span><?= $stat['points'] ?> Punkte</span></div><?php endforeach; ?></div><?php endif; ?></section><?php
if (isValidHttpUrl($data['links']['ssvbTableUrl'])): ?><p class="external-link"><a href="<?= e($data['links']['ssvbTableUrl']) ?>" target="_blank" rel="noopener noreferrer">SSVB-Tabelle Mixed-Liga →</a></p><?php endif;
renderFooter();
