<?php
/*
  KUSINANG GAMAY — GLOBAL HALL OF FAME
  THIRDFOOD

  1) Put this file on the SAME DOMAIN as your game.
  2) Fill in the database settings below.
  3) Import kusinang_gamay_global_hof.sql once in your MySQL/MariaDB database.
  4) Point the game connector to this file, e.g. /hall-of-fame.php
*/

declare(strict_types=1);

/* ==============================
   DATABASE SETTINGS
============================== */
const DB_HOST = 'localhost';
const DB_NAME = 'kusinang_gamay';
const DB_USER = 'root';
const DB_PASS = '';

const TABLE_NAME = 'kg_global_scores';
const MAX_SCORE = 100000000;
const MAX_COMBO = 100000;

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function jsonOut(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cleanText(string $value, int $maxLength): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $maxLength, 'UTF-8');
}

function cleanKey(string $value, int $maxLength = 96): string {
    $value = preg_replace('/[^A-Za-z0-9_\-:.]/', '', $value) ?? '';
    return substr($value, 0, $maxLength);
}

function getLeaderboard(int $limit = 25): array {
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT nickname, best_score, best_combo, best_level, mode, total_runs, updated_at
            FROM ' . TABLE_NAME . '
            ORDER BY best_score DESC, best_combo DESC, updated_at ASC
            LIMIT ' . $limit;
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as $i => &$row) {
        $row['rank'] = $i + 1;
        $row['best_score'] = (int)$row['best_score'];
        $row['best_combo'] = (int)$row['best_combo'];
        $row['total_runs'] = (int)$row['total_runs'];
    }
    unset($row);
    return $rows;
}

/* ==============================
   API — SUBMIT SCORE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) jsonOut(['ok' => false, 'error' => 'Invalid JSON body.'], 400);

        $playerKey = cleanKey((string)($data['player_key'] ?? ''));
        $sessionId = cleanKey((string)($data['session_id'] ?? ''));
        $nickname = cleanText((string)($data['nickname'] ?? ''), 24);
        $level = cleanText((string)($data['level'] ?? '1'), 24);
        $mode = strtoupper(cleanText((string)($data['mode'] ?? 'STORY'), 24));
        $score = filter_var($data['score'] ?? null, FILTER_VALIDATE_INT);
        $combo = filter_var($data['combo'] ?? 0, FILTER_VALIDATE_INT);

        if ($playerKey === '' || $sessionId === '' || $nickname === '') {
            jsonOut(['ok' => false, 'error' => 'Missing player identity.'], 422);
        }
        if ($score === false || $score < 0 || $score > MAX_SCORE) {
            jsonOut(['ok' => false, 'error' => 'Invalid score.'], 422);
        }
        if ($combo === false || $combo < 0 || $combo > MAX_COMBO) {
            jsonOut(['ok' => false, 'error' => 'Invalid combo.'], 422);
        }

        $allowedModes = ['STORY', 'ENDLESS', 'STORY COMPLETE', 'FINAL'];
        if (!in_array($mode, $allowedModes, true)) $mode = 'STORY';

        $pdo = db();

        /*
          One row per local player profile. Repeated submissions from the same
          run do not increase total_runs. A new personal best replaces the
          leaderboard score; otherwise the existing best remains.
        */
        $sql = 'INSERT INTO ' . TABLE_NAME . '
                (player_key, nickname, best_score, best_combo, best_level, mode, total_runs, last_session_id)
                VALUES (:player_key, :nickname, :score, :combo, :level, :mode, 1, :session_id)
                ON DUPLICATE KEY UPDATE
                  nickname = VALUES(nickname),
                  total_runs = total_runs + IF(last_session_id <> VALUES(last_session_id), 1, 0),
                  best_combo = IF(
                    VALUES(best_score) > best_score OR
                    (VALUES(best_score) = best_score AND VALUES(best_combo) > best_combo),
                    VALUES(best_combo), best_combo
                  ),
                  best_level = IF(
                    VALUES(best_score) > best_score OR
                    (VALUES(best_score) = best_score AND VALUES(best_combo) > best_combo),
                    VALUES(best_level), best_level
                  ),
                  mode = IF(
                    VALUES(best_score) > best_score OR
                    (VALUES(best_score) = best_score AND VALUES(best_combo) > best_combo),
                    VALUES(mode), mode
                  ),
                  best_score = GREATEST(best_score, VALUES(best_score)),
                  last_session_id = VALUES(last_session_id),
                  updated_at = CURRENT_TIMESTAMP';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':player_key' => $playerKey,
            ':nickname' => $nickname,
            ':score' => $score,
            ':combo' => $combo,
            ':level' => $level,
            ':mode' => $mode,
            ':session_id' => $sessionId,
        ]);

        $rankStmt = $pdo->prepare(
            'SELECT 1 + COUNT(*) AS player_rank
             FROM ' . TABLE_NAME . ' a
             JOIN ' . TABLE_NAME . ' me ON me.player_key = :player_key
               AND (a.best_score > me.best_score OR (a.best_score = me.best_score AND a.best_combo > me.best_combo))'
        );
        $rankStmt->execute([':player_key' => $playerKey]);
        $rank = (int)($rankStmt->fetch()['player_rank'] ?? 0);

        jsonOut([
            'ok' => true,
            'message' => 'Score synced to the Global Hall of Fame.',
            'rank' => $rank,
        ]);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => 'Leaderboard service unavailable.'], 500);
    }
}

/* ==============================
   API — READ LEADERBOARD
============================== */
if (isset($_GET['api']) && $_GET['api'] === 'leaderboard') {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
        jsonOut(['ok' => true, 'players' => getLeaderboard($limit)]);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => 'Leaderboard service unavailable.'], 500);
    }
}

/* ==============================
   PAGE DATA
============================== */
$players = [];
$pageError = '';
try {
    $players = getLeaderboard(25);
} catch (Throwable $e) {
    $pageError = 'The Global Hall of Fame is temporarily unavailable.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Kusinang Gamay — Global Hall of Fame</title>
<style>
:root{--ink:#2b2025;--cream:#fff0c9;--cream2:#efca7e;--red:#9e3e42;--red2:#652a31;--gold:#e7ad43;--wood:#714434;--wood2:#422b2a;--green:#527a4e}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:#171116;color:var(--cream);font-family:"Courier New",monospace}body{min-height:100vh;background:radial-gradient(circle at 50% 0,#49303a 0,transparent 42%),linear-gradient(#21161f,#100c10);padding:24px}.wrap{width:min(900px,100%);margin:auto}.eyebrow{text-align:center;font-size:11px;font-weight:900;letter-spacing:4px;color:#e8c77c}.title{text-align:center;margin:10px 0 6px;font-size:clamp(30px,7vw,64px);line-height:.95;color:#fff1b0;text-shadow:4px 4px 0 #8f373d,7px 7px 0 #44272d}.sub{text-align:center;color:#d8bc89;font-size:11px;font-weight:900;line-height:1.5;margin-bottom:22px}.board{background:#eac979;border:7px solid var(--ink);box-shadow:0 0 0 6px var(--wood),12px 12px 0 rgba(0,0,0,.35);padding:14px}.board-head{display:grid;grid-template-columns:58px 1fr 118px 90px;gap:7px;padding:8px 10px;background:var(--wood2);color:#ffe7a9;font-size:9px;font-weight:900;letter-spacing:1px}.row{display:grid;grid-template-columns:58px 1fr 118px 90px;gap:7px;align-items:center;margin-top:7px;padding:10px;background:#fff0bd;border:4px solid #5a3934;color:#3d2a2a;font-size:10px;font-weight:900}.rank{font-size:18px;text-align:center}.name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.meta{font-size:8px;color:#765243;margin-top:3px}.score{text-align:right;font-size:14px;color:#8a373b}.combo{text-align:right;font-size:9px;color:#65453d}.row.top1{background:#ffe18b}.row.top2{background:#f2e8c9}.row.top3{background:#e7b989}.empty,.error{text-align:center;padding:35px 15px;background:#fff0bd;border:4px solid #5a3934;color:#563a34;font-weight:900}.footer{text-align:center;margin-top:18px;color:#8f7c68;font-size:9px;line-height:1.5}.live{display:inline-flex;align-items:center;gap:7px;margin-bottom:10px;padding:5px 8px;border:3px solid #563830;background:#fff0bd;color:#5a3934;font-size:8px;font-weight:900}.dot{width:8px;height:8px;background:#5fa965;border:2px solid #315c35;animation:pulse 1.1s steps(2,end) infinite}@keyframes pulse{50%{opacity:.35}}@media(max-width:620px){body{padding:12px}.board{padding:8px;border-width:5px}.board-head{grid-template-columns:42px 1fr 88px;font-size:7px}.board-head span:last-child{display:none}.row{grid-template-columns:42px 1fr 88px;font-size:8px;padding:8px}.combo{display:none}.rank{font-size:14px}.score{font-size:11px}}
</style>
</head>
<body>
<main class="wrap">
  <div class="eyebrow">THIRDFOOD PRESENTS</div>
  <h1 class="title">GLOBAL HALL OF FAME</h1>
  <p class="sub">KUSINANG GAMAY • BEST SCORES FROM PLAYERS EVERYWHERE</p>
  <div class="live"><span class="dot"></span> GLOBAL BOARD • AUTO REFRESH</div>
  <section class="board" id="board">
    <div class="board-head"><span>RANK</span><span>PLAYER</span><span style="text-align:right">SCORE</span><span style="text-align:right">COMBO</span></div>
    <div id="rows">
    <?php if ($pageError): ?>
      <div class="error"><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$players): ?>
      <div class="empty">NO GLOBAL SCORES YET.<br>BE THE FIRST TO ENTER THE BOARD.</div>
    <?php else: foreach ($players as $p): ?>
      <div class="row <?= $p['rank']===1?'top1':($p['rank']===2?'top2':($p['rank']===3?'top3':'')) ?>">
        <div class="rank"><?= $p['rank']===1?'🥇':($p['rank']===2?'🥈':($p['rank']===3?'🥉':'#'.$p['rank'])) ?></div>
        <div class="name"><?= htmlspecialchars($p['nickname'], ENT_QUOTES, 'UTF-8') ?><div class="meta"><?= htmlspecialchars($p['mode'].' • LV '.$p['best_level'].' • '.$p['total_runs'].' RUN'.($p['total_runs']==1?'':'S'), ENT_QUOTES, 'UTF-8') ?></div></div>
        <div class="score"><?= number_format((int)$p['best_score']) ?></div>
        <div class="combo">×<?= number_format((int)$p['best_combo']) ?></div>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </section>
  <div class="footer">Only each player's best score is ranked. The page refreshes automatically every 20 seconds.</div>
</main>
<script>
const rows = document.getElementById('rows');
const esc = s => String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
async function refreshBoard(){
  try{
    const r = await fetch('?api=leaderboard&limit=25',{cache:'no-store'});
    const data = await r.json();
    if(!data.ok) return;
    if(!data.players.length){rows.innerHTML='<div class="empty">NO GLOBAL SCORES YET.<br>BE THE FIRST TO ENTER THE BOARD.</div>';return;}
    rows.innerHTML = data.players.map(p=>{
      const cls=p.rank===1?'top1':p.rank===2?'top2':p.rank===3?'top3':'';
      const medal=p.rank===1?'🥇':p.rank===2?'🥈':p.rank===3?'🥉':'#'+p.rank;
      return `<div class="row ${cls}"><div class="rank">${medal}</div><div class="name">${esc(p.nickname)}<div class="meta">${esc(p.mode)} • LV ${esc(p.best_level)} • ${p.total_runs} RUN${p.total_runs===1?'':'S'}</div></div><div class="score">${Number(p.best_score).toLocaleString()}</div><div class="combo">×${Number(p.best_combo).toLocaleString()}</div></div>`;
    }).join('');
  }catch(e){}
}
setInterval(refreshBoard,20000);
</script>
</body>
</html>
