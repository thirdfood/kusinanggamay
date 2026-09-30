/* =========================================================
   KUSINANG GAMAY — GLOBAL HALL OF FAME CONNECTOR
   Paste this inside the SAME script scope where kgRecordRun,
   kgCurrentProfile, kgSessionRunId, score, highestCombo,
   level, finaleActive and endlessMode already exist.
========================================================= */

const KG_GLOBAL_HOF_URL = '/hall-of-fame.php';

async function kgSubmitGlobalScore(modeLabel){
  const p = kgCurrentProfile();
  if(!p || !p.id || !p.name) return;

  const payload = {
    player_key: p.id,
    session_id: kgSessionRunId || ('run_' + Date.now()),
    nickname: p.name,
    score: Math.max(0, Number(score) || 0),
    combo: Math.max(0, Number(highestCombo) || 0),
    level: finaleActive ? 'FINAL' : (endlessMode ? ('∞/' + level) : String(level)),
    mode: modeLabel || (endlessMode ? 'ENDLESS' : 'STORY')
  };

  try{
    const response = await fetch(KG_GLOBAL_HOF_URL, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      keepalive: true,
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    if(data?.ok){
      console.log('Global Hall of Fame synced. Rank:', data.rank);
    }
  }catch(err){
    /* Offline / server unavailable: local Hall of Fame still works. */
    console.warn('Global Hall of Fame sync skipped:', err);
  }
}

/* =========================================================
   REPLACE YOUR CURRENT kgRecordRun() WITH THIS VERSION.
   It keeps the existing local Hall of Fame AND sends the
   same completed run to the global website leaderboard.
========================================================= */
function kgRecordRun(modeLabel){
  const p = kgCurrentProfile();
  if(!p) return;

  let runs = kgJsonGet(KG_KEYS.hall, []);
  const rec = {
    session: kgSessionRunId,
    name: p.name,
    score: +score || 0,
    combo: +highestCombo || 0,
    level: finaleActive ? 'FINAL' : (endlessMode ? '∞/' + level : String(level)),
    mode: modeLabel || (endlessMode ? 'ENDLESS' : 'STORY'),
    date: Date.now()
  };

  const idx = runs.findIndex(r => r.session === rec.session);
  if(idx >= 0) runs[idx] = rec;
  else runs.push(rec);

  runs.sort((a,b) => b.score - a.score || b.combo - a.combo);
  kgJsonSet(KG_KEYS.hall, runs.slice(0,50));

  /* NEW: sync this finished run to the global website board. */
  kgSubmitGlobalScore(rec.mode);
}

/* =========================================================
   OPTIONAL: USE THE EXISTING IN-GAME HALL OF FAME MODAL
   AS A GLOBAL TOP 10 BOARD INSTEAD OF LOCAL-ONLY SCORES.
   Replace your current kgRenderHall() with this function.
========================================================= */
async function kgRenderHall(){
  const box = document.getElementById('kgScoreList');
  box.innerHTML = '<div class="kg-empty-board">CONNECTING TO GLOBAL HALL OF FAME...</div>';

  try{
    const response = await fetch(KG_GLOBAL_HOF_URL + '?api=leaderboard&limit=10', {
      cache: 'no-store',
      credentials: 'same-origin'
    });
    const data = await response.json();

    if(!data?.ok || !Array.isArray(data.players)) throw new Error('Bad leaderboard response');

    box.innerHTML = '';
    if(!data.players.length){
      box.innerHTML = '<div class="kg-empty-board">NO GLOBAL SCORES YET.<br>BE THE FIRST.</div>';
      return;
    }

    data.players.forEach((r,i)=>{
      const row = document.createElement('div');
      row.className = 'kg-score-row';
      row.innerHTML = `<div class="kg-score-rank">${i+1}</div><div><strong>${kgEscape(r.nickname)}</strong><br><span style="font-size:7px">${kgEscape(r.mode || 'STORY')}</span></div><div class="kg-score-meta">⭐ ${Number(r.best_score).toLocaleString()}<br>COMBO ×${Number(r.best_combo).toLocaleString()}<br>LV ${kgEscape(r.best_level)}</div>`;
      box.appendChild(row);
    });
  }catch(err){
    /* Fallback to this phone's local scores if the internet/server is unavailable. */
    const runs = kgJsonGet(KG_KEYS.hall,[]).slice().sort((a,b)=>b.score-a.score||b.combo-a.combo).slice(0,10);
    box.innerHTML = '';
    if(!runs.length){
      box.innerHTML = '<div class="kg-empty-board">GLOBAL BOARD OFFLINE.<br>NO LOCAL SCORES YET.</div>';
      return;
    }
    runs.forEach((r,i)=>{
      const row=document.createElement('div');row.className='kg-score-row';
      row.innerHTML=`<div class="kg-score-rank">${i+1}</div><div><strong>${kgEscape(r.name)}</strong><br><span style="font-size:7px">LOCAL • ${kgEscape(r.mode||'STORY')}</span></div><div class="kg-score-meta">⭐ ${r.score}<br>COMBO ×${r.combo}<br>LV ${kgEscape(r.level)}</div>`;
      box.appendChild(row);
    });
  }
}
