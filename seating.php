<?php
require_once 'config.php';
if (!hasRole('player')) { showError(t('games_error_access')); }
includeHeader();
?>

<h2>🪑 <?php echo t('seating_title'); ?></h2>
<p style="color:#666;margin-bottom:24px;"><?php echo t('seating_desc'); ?></p>

<!-- SETUP -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="form-group" style="margin:0;">
        <?php echo t('seating_tables'); ?>
        <input type="number" id="numTables" min="1" max="30" value="5"
               style="width:100%;font-size:1.4rem;font-weight:bold;padding:10px;"
               oninput="document.getElementById('numPlayers').value=(parseInt(this.value)||0)*4">
        <?php echo t('seating_tables_hint'); ?>
    </div>
    <div class="form-group" style="margin:0;">
        <?php echo t('seating_rounds'); ?>
        <input type="number" id="numRounds" min="1" max="12" value="4"
               style="width:100%;font-size:1.4rem;font-weight:bold;padding:10px;">
        <?php echo t('seating_rounds_hint'); ?>
    </div>
    <div class="form-group" style="margin:0;">
        <?php echo t('seating_players_total'); ?>
        <input type="number" id="numPlayers" value="20" readonly
               style="width:100%;font-size:1.4rem;font-weight:bold;padding:10px;background:#f0f0f0;cursor:default;">
        <?php echo t('seating_players_hint'); ?>
    </div>
</div>

<!-- SPELARE/LAG -->
<div style="background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h3 style="color:#005B99;margin:0;"><?php echo t('seating_teams_title'); ?></h3>
            <small style="color:#666;"><?php echo t('seating_teams_desc'); ?></small>
        </div>
        <span id="tcBadge" style="background:#005B99;color:white;padding:4px 12px;border-radius:20px;font-size:0.85em;">0 lag</span>
    </div>

    <div style="display:grid;grid-template-columns:160px 1fr 1fr 1fr 1fr 36px;gap:8px;margin-bottom:8px;">
        <span style="font-size:0.75em;color:#999;text-transform:uppercase;padding:0 4px;"><?php echo t('seating_team_name'); ?></span>
        <span style="font-size:0.75em;color:#999;text-transform:uppercase;padding:0 4px;"><?php echo t('seating_player'); ?> 1</span>
        <span style="font-size:0.75em;color:#999;text-transform:uppercase;padding:0 4px;"><?php echo t('seating_player'); ?> 2</span>
        <span style="font-size:0.75em;color:#999;text-transform:uppercase;padding:0 4px;"><?php echo t('seating_player'); ?> 3</span>
        <span style="font-size:0.75em;color:#999;text-transform:uppercase;padding:0 4px;"><?php echo t('seating_player'); ?> 4</span>
        <span></span>
    </div>

    <div id="teamList"></div>

    <button class="btn btn-secondary" onclick="addTeam()" style="margin-top:12px;width:100%;border-style:dashed;">
        ＋ <?php echo t('seating_add_team'); ?>
    </button>
</div>

<button class="btn" onclick="generate()" style="font-size:1.1em;padding:14px 40px;margin-bottom:32px;">
    🀇 <?php echo t('seating_generate'); ?>
</button>

<!-- RESULTAT -->
<div id="results-section" style="display:none;">

    <div style="background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;gap:28px;flex-wrap:wrap;">
        <div id="statsBar"></div>
    </div>

    <div id="qualityReport" style="background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:16px 20px;margin-bottom:20px;"></div>

    <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <button class="btn btn-secondary" onclick="printView('rounds')">🖨️ <?php echo t('seating_print_rounds'); ?></button>
        <button class="btn btn-secondary" onclick="printView('players')">🖨️ <?php echo t('seating_print_players'); ?></button>
    </div>

    <div style="display:flex;gap:4px;background:#eee;padding:4px;border-radius:8px;margin-bottom:20px;width:fit-content;">
        <button id="tabRounds" onclick="switchView('rounds',this)"
                style="padding:8px 20px;border:none;border-radius:6px;background:#005B99;color:white;cursor:pointer;font-weight:bold;">
            <?php echo t('seating_view_rounds'); ?>
        </button>
        <button id="tabPlayers" onclick="switchView('players',this)"
                style="padding:8px 20px;border:none;border-radius:6px;background:transparent;color:#333;cursor:pointer;">
            <?php echo t('seating_view_players'); ?>
        </button>
    </div>

    <div id="vRounds"></div>
    <div id="vPlayers" style="display:none;"></div>
</div>

<style>
.table-card {
    background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden;
    transition:border-color 0.2s;
}
.table-card:hover { border-color:#005B99; }
.table-head {
    background:#005B99;color:white;padding:8px 14px;
    display:flex;justify-content:space-between;align-items:center;
}
.table-num { font-size:0.85em;font-weight:bold;letter-spacing:0.05em; }
.table-tbadge { font-size:0.72em;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:20px; }
.seats { padding:8px; }
.seat { display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:5px;margin-bottom:3px; }
.seat.east { background:#fff8e1;border:1px solid #FECC02; }
.wbadge {
    width:24px;height:24px;border-radius:50%;display:flex;align-items:center;
    justify-content:center;font-size:0.68em;font-weight:bold;flex-shrink:0;
}
.wE { background:#FECC02;color:#333; }
.wS,.wW,.wN { background:#eee;color:#666; }
.seat-name { font-size:0.88em;font-weight:500;flex:1; }
.seat-team { font-size:0.68em;color:#999; }
.round-badge {
    display:inline-block;background:#FECC02;color:#003D6B;
    font-size:0.78em;font-weight:bold;padding:3px 12px;border-radius:20px;
    letter-spacing:0.05em;margin-right:10px;
}
.team-row { display:grid;grid-template-columns:160px 1fr 1fr 1fr 1fr 36px;gap:8px;align-items:center;margin-bottom:8px; }
.team-row input {
    background:#fff;border:1px solid #ccc;border-radius:5px;padding:6px 9px;
    font-size:0.88em;width:100%;outline:none;
}
.team-row input:focus { border-color:#005B99; }
.team-row input.tname { font-weight:bold;color:#005B99; }
.btn-rm {
    width:32px;height:32px;background:#fff0f0;border:1px solid #ffcccc;
    color:#c00;border-radius:5px;cursor:pointer;font-size:0.9em;
}
.btn-rm:hover { background:#ffe0e0; }
.player-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px; }
.player-card-sched { background:#fff;border:1px solid #ddd;border-radius:8px;overflow:hidden; }
.player-head-sched { padding:10px 14px;background:#005B99;color:white;display:flex;align-items:center;gap:10px; }
.player-num-badge {
    width:30px;height:30px;background:rgba(254,204,2,0.9);color:#003D6B;
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-weight:bold;font-size:0.78em;flex-shrink:0;
}
.player-sched-rows { padding:8px 10px; }
.pr { display:flex;align-items:center;gap:8px;padding:4px 6px;border-radius:4px;font-size:0.82em; }
.pr:nth-child(odd) { background:#f9f9f9; }
.prr { color:#999;width:22px;font-size:0.78em;flex-shrink:0; }
.prt { font-weight:500;width:60px;flex-shrink:0; }
.prw { font-size:0.7em;padding:1px 7px;border-radius:10px;font-weight:bold;flex-shrink:0; }
.prwE { background:#FECC02;color:#333; }
.prwS,.prwW,.prwN { background:#eee;color:#666; }
.stat-item { display:flex;flex-direction:column;gap:2px; }
.stat-val { font-size:1.3em;font-weight:bold;color:#005B99; }
.stat-lbl { font-size:0.72em;text-transform:uppercase;color:#999;letter-spacing:0.08em; }
.stat-div { width:1px;background:#ddd;align-self:stretch; }

@media (max-width:700px) {
    .team-row, .col-hdr { grid-template-columns:130px 1fr 1fr 36px; }
    .team-row input:nth-child(4), .team-row input:nth-child(5) { display:none; }
}
@media print {
    nav, .filter-box, .btn, h2, p, #results-section > div:first-child,
    #qualityReport, .print-btns, .view-tabs, #statsBar { display:none !important; }
    #results-section { display:block !important; }
    .table-card, .player-card-sched { break-inside:avoid;border:1px solid #ccc !important; }
    .table-head { background:#005B99 !important;-webkit-print-color-adjust:exact; }
    .seat.east { background:#fff8e1 !important;-webkit-print-color-adjust:exact; }
    .wE { background:#FECC02 !important;-webkit-print-color-adjust:exact; }
}
</style>

<script>
const LANG = {
    wind_e: '<?php echo t("seating_wind_e"); ?>',
    wind_s: '<?php echo t("seating_wind_s"); ?>',
    wind_w: '<?php echo t("seating_wind_w"); ?>',
    wind_n: '<?php echo t("seating_wind_n"); ?>',
    stat_players: '<?php echo t("seating_stat_players"); ?>',
    stat_tables: '<?php echo t("seating_stat_tables"); ?>',
    stat_rounds: '<?php echo t("seating_stat_rounds"); ?>',
    stat_total: '<?php echo t("seating_stat_total"); ?>',
    stat_pergame: '<?php echo t("seating_stat_pergame"); ?>',
    quality_title: '<?php echo t("seating_quality_title"); ?>',
    quality_repeat: '<?php echo t("seating_quality_repeat"); ?>',
    quality_max: '<?php echo t("seating_quality_max"); ?>',
    quality_east: '<?php echo t("seating_quality_east"); ?>',
    quality_perfect: '<?php echo t("seating_quality_perfect"); ?>',
    quality_coll: '<?php echo t("seating_quality_coll"); ?>',
    quality_max_lbl: '<?php echo t("seating_quality_max_lbl"); ?>',
    quality_times: '<?php echo t("seating_quality_times"); ?>',
    round_lbl: '<?php echo t("seating_round"); ?>',
    tables_lbl: '<?php echo t("seating_tables_lbl"); ?>',
    table_lbl: '<?php echo t("seating_table"); ?>',
    seated_lbl: '<?php echo t("seating_games_seated"); ?>',
    add_team: '<?php echo t("seating_add_team"); ?>',
    team_name: '<?php echo t("seating_team_name"); ?>',
    player_lbl: '<?php echo t("seating_player"); ?>',
    lag_lbl: '<?php echo t("seating_teams_title"); ?>',
};
const MJNAMES = [
  "Röd Drake","Grön Drake","Vit Drake","Gyllene Vind","Östervind",
  "Västervind","Nordanvind","Sunnanvind","Bambuskogen","Körsbärsblom",
  "Jadepalaset","Drakporten","Järnväggen","Bergstoppen","Flödande Å",
  "Silvermånen","Åskdunder","Soluppgång","Lotusdammen","Pärlfloden",
  "Vilda Vindar","Riichi-mästarna","Tenpai-tigrarna","Kan-kungarna","Dora-jägarna",
  "Pinfu-spelarna","Tsumo-krigarna","Ron-räidarna","Kokushi-klanen","Chanta-hallen"
];

let schedule = null, players = [];
const WINDS = [LANG.wind_e, LANG.wind_s, LANG.wind_w, LANG.wind_n];
const WIND_KEYS = ['E','S','W','N'];

function addTeam(name='', p1='', p2='', p3='', p4='') {
    const used = Array.from(document.querySelectorAll('.tname')).map(i=>i.value);
    const avail = MJNAMES.filter(n=>!used.includes(n));
    const dName = name || avail[0] || 'Lag ' + (document.querySelectorAll('.team-row').length+1);
    const row = document.createElement('div');
    row.className = 'team-row';
    row.innerHTML = `
        <input class="tname" type="text" placeholder="${LANG.team_name}" value="${dName}">
        <input class="pin" type="text" placeholder="${LANG.player_lbl} 1" value="${p1}">
        <input class="pin" type="text" placeholder="${LANG.player_lbl} 2" value="${p2}">
        <input class="pin" type="text" placeholder="${LANG.player_lbl} 3" value="${p3}">
        <input class="pin" type="text" placeholder="${LANG.player_lbl} 4" value="${p4}">
        <button class="btn-rm" onclick="this.parentElement.remove();updateTC()">✕</button>`;
    document.getElementById('teamList').appendChild(row);
    updateTC();
}

function updateTC() {
    const n = document.querySelectorAll('.team-row').length;
    document.getElementById('tcBadge').textContent = n + ' ' + LANG.lag_lbl.split('/')[0].trim().toLowerCase();
}

function buildPlayers(numP) {
    const ps = [];
    const teamRows = document.querySelectorAll('.team-row');
    const usedTeams = new Set();
    teamRows.forEach(row => {
        const tname = row.querySelector('.tname').value.trim() || 'Lag';
        usedTeams.add(tname);
        row.querySelectorAll('.pin').forEach((inp, i) => {
            if (ps.length < numP)
                ps.push({ id: ps.length+1, name: inp.value.trim() || tname+' '+LANG.player_lbl.charAt(0).toUpperCase()+(i+1), team: tname });
        });
    });
    const avail = MJNAMES.filter(n=>!usedTeams.has(n));
    let gi=0, gpos=0;
    while (ps.length < numP) {
        if (gpos===0 && gi>=avail.length) avail.push(LANG.lag_lbl.split('/')[0].trim()+' '+(gi+1));
        const gname = avail[gi] || LANG.lag_lbl.split('/')[0].trim()+' '+(gi+1);
        ps.push({ id: ps.length+1, name: 'LANG.player_lbl+' '+(ps.length+1), team: gname });
        gpos++;
        if (gpos>=4) { gpos=0; gi++; }
    }
    return ps;
}

function shuffle(a) {
    for (let i=a.length-1;i>0;i--) {
        const j=Math.floor(Math.random()*(i+1));
        [a[i],a[j]]=[a[j],a[i]];
    }
    return a;
}
function mk(a,b) { return a<b?`${a},${b}`:`${b},${a}`; }

function generateSchedule(pList, nTables, nRounds) {
    const met={}, eastCnt=new Array(pList.length+1).fill(0), rounds=[];
    for (let r=0;r<nRounds;r++) {
        let ids=pList.map(p=>p.id), bestTables=null, bestScore=Infinity;
        for (let att=0;att<120;att++) {
            shuffle(ids);
            const tables=greedyAssign(ids,nTables,met);
            const score=scoreAssign(tables,met);
            if (score<bestScore){bestScore=score;bestTables=tables.map(t=>[...t]);}
        }
        const roundTables=bestTables.map((seats,ti)=>{
            const eastSeat=seats.reduce((b,id)=>eastCnt[id]<eastCnt[b]?id:b,seats[0]);
            const idx=seats.indexOf(eastSeat);
            const rotated=[...seats.slice(idx),...seats.slice(0,idx)];
            for (let i=0;i<rotated.length;i++)
                for (let j=i+1;j<rotated.length;j++){const k=mk(rotated[i],rotated[j]);met[k]=(met[k]||0)+1;}
            eastCnt[eastSeat]++;
            return {tableNum:ti+1,seats:rotated,east:eastSeat};
        });
        rounds.push({round:r+1,tables:roundTables});
    }
    return {rounds,eastCnt,met};
}

function greedyAssign(ids,nTables,met) {
    const assigned=new Set(), tables=[];
    for (let t=0;t<nTables;t++) {
        const table=[];
        for (const id of ids){if(!assigned.has(id)){table.push(id);assigned.add(id);break;}}
        while (table.length<4) {
            let best=null,bestS=Infinity;
            for (const id of ids){
                if(assigned.has(id))continue;
                let s=0;for(const t2 of table)s+=(met[mk(id,t2)]||0)**2*10;
                if(s<bestS){bestS=s;best=id;}
            }
            if(best===null){for(const id of ids){if(!assigned.has(id)){best=id;break;}}}
            if(best===null)break;
            table.push(best);assigned.add(best);
        }
        tables.push(table);
    }
    return tables;
}

function scoreAssign(tables,met) {
    let s=0;
    for (const t of tables)
        for (let i=0;i<t.length;i++)
            for (let j=i+1;j<t.length;j++)
                s+=(met[mk(t[i],t[j])]||0)**2*100;
    return s;
}

function generate() {
    const nT=Math.min(30,Math.max(1,parseInt(document.getElementById('numTables').value)||5));
    const nR=Math.min(12,Math.max(1,parseInt(document.getElementById('numRounds').value)||4));
    const nP=nT*4;
    document.getElementById('numPlayers').value=nP;
    players=buildPlayers(nP);
    schedule=generateSchedule(players,nT,nR);
    renderStats(nP,nT,nR);
    renderQuality(schedule,nR);
    renderRounds();
    renderPlayers();
    const rs=document.getElementById('results-section');
    rs.style.display='block';
    rs.scrollIntoView({behavior:'smooth',block:'start'});
}

function renderStats(nP,nT,nR) {
    document.getElementById('statsBar').innerHTML=`
        <div style="display:flex;gap:28px;flex-wrap:wrap;">
            <div class="stat-item"><div class="stat-val">${nP}</div><div class="stat-lbl">${LANG.stat_players}</div></div>
            <div class="stat-div"></div>
            <div class="stat-item"><div class="stat-val">${nT}</div><div class="stat-lbl">${LANG.stat_tables}</div></div>
            <div class="stat-div"></div>
            <div class="stat-item"><div class="stat-val">${nR}</div><div class="stat-lbl">${LANG.stat_rounds}</div></div>
            <div class="stat-div"></div>
            <div class="stat-item"><div class="stat-val">${nT*nR}</div><div class="stat-lbl">${LANG.stat_total}</div></div>
            <div class="stat-div"></div>
            <div class="stat-item"><div class="stat-val">${nR}</div><div class="stat-lbl">${LANG.stat_pergame}</div></div>
        </div>`;
}

function renderQuality(sched,nR) {
    const vals=Object.values(sched.met);
    const repeated=vals.filter(v=>v>1).length;
    const maxMet=vals.length?Math.max(...vals):0;
    const ec=sched.eastCnt.filter(v=>v>0);
    const eMin=ec.length?Math.min(...ec):0;
    const eMax=ec.length?Math.max(...ec):0;
    const spread=eMax-eMin;
    const rCol=repeated===0?'#2e7d32':repeated<5?'#e65100':'#c62828';
    const eCol=spread<=1?'#2e7d32':spread<=2?'#e65100':'#c62828';
    document.getElementById('qualityReport').innerHTML=`
        <strong style="color:#005B99;">⚡ ${LANG.quality_title}</strong>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:12px;">
            <div><div style="font-size:1.1em;font-weight:bold;color:${rCol};">${repeated===0?'✓ '+LANG.quality_perfect:repeated+' '+LANG.quality_coll}</div><div style="font-size:0.78em;color:#999;">${LANG.quality_repeat}</div></div>
            <div><div style="font-size:1.1em;font-weight:bold;color:${maxMet<=1?'#2e7d32':'#e65100'};">${maxMet}${LANG.quality_max_lbl}</div><div style="font-size:0.78em;color:#999;">${LANG.quality_max}</div></div>
            <div><div style="font-size:1.1em;font-weight:bold;color:${eCol};">${eMin}–${eMax} ${LANG.quality_times}</div><div style="font-size:0.78em;color:#999;">${LANG.quality_east}</div></div>
        </div>`;
}

function renderRounds() {
    document.getElementById('vRounds').innerHTML=schedule.rounds.map(round=>`
        <div style="margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                <span class="round-badge">${LANG.round_lbl} ${round.round}</span>
                <strong>${round.tables.length} ${LANG.tables_lbl} · ${round.tables.length*4} ${LANG.seated_lbl}</strong>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;">
                ${round.tables.map(table=>{
                    const firstTeam=players.find(p=>p.id===table.seats[0])?.team||'';
                    const sameTeam=table.seats.every(id=>players.find(p=>p.id===id)?.team===firstTeam);
                    return `<div class="table-card">
                        <div class="table-head">
                            <span class="table-num">${LANG.table_lbl} ${table.tableNum}</span>
                            ${sameTeam?`<span class="table-tbadge">${firstTeam}</span>`:''}
                        </div>
                        <div class="seats">
                            ${table.seats.map((id,i)=>{
                                const p=players.find(p=>p.id===id);
                                const w=WINDS[i];const wk=WIND_KEYS[i];const isE=i===0;
                                return `<div class="seat${isE?' east':''}">
                                    <div class="wbadge w${wk}">${w}</div>
                                    <div class="seat-name">${p?p.name:'#'+id}</div>
                                    <div class="seat-team">${p?p.team:''}</div>
                                </div>`;
                            }).join('')}
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>`).join('');
}

function renderPlayers() {
    document.getElementById('vPlayers').innerHTML=`<div class="player-grid">${
        players.map(player=>{
            const rows=schedule.rounds.map(round=>{
                let tNum=null,wind=null;
                round.tables.forEach(t=>{
                    const i=t.seats.indexOf(player.id);
                    if(i!==-1){tNum=t.tableNum;wind=WINDS[i];wk=WIND_KEYS[i];}
                });
                return `<div class="pr">
                    <span class="prr">R${round.round}</span>
                    <span class="prt">Bord ${tNum}</span>
                    <span class="prw prw${wind==='Ö'?'E':wind==='S'?'S':wind==='V'?'W':'N'}">${wind}</span>
                </div>`;
            }).join('');
            return `<div class="player-card-sched">
                <div class="player-head-sched">
                    <div class="player-num-badge">${player.id}</div>
                    <div>
                        <div style="font-weight:bold;">${player.name}</div>
                        <div style="font-size:0.75em;opacity:0.8;">${player.team}</div>
                    </div>
                </div>
                <div class="player-sched-rows">${rows}</div>
            </div>`;
        }).join('')
    }</div>`;
}

function switchView(view,btn) {
    document.getElementById('tabRounds').style.background=view==='rounds'?'#005B99':'transparent';
    document.getElementById('tabRounds').style.color=view==='rounds'?'white':'#333';
    document.getElementById('tabPlayers').style.background=view==='players'?'#005B99':'transparent';
    document.getElementById('tabPlayers').style.color=view==='players'?'white':'#333';
    document.getElementById('vRounds').style.display=view==='rounds'?'block':'none';
    document.getElementById('vPlayers').style.display=view==='players'?'block':'none';
}

function printView(view) {
    document.getElementById('vRounds').style.display=view==='rounds'?'block':'none';
    document.getElementById('vPlayers').style.display=view==='players'?'block':'none';
    window.print();
    switchView('rounds',document.getElementById('tabRounds'));
}
</script>

<?php includeFooter(); ?>
