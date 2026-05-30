<?php
require_once __DIR__ . '/../layouts/header.php';
$isSuperadmin = support_chat_is_superadmin($user ?? []);
$initialState = [
    'threads' => $threads ?? [],
    'activeThread' => $activeThread ?? null,
    'messages' => $messages ?? [],
    'stats' => $threadStats ?? ['all' => 0, 'open' => 0, 'closed' => 0, 'unread' => 0, 'urgent' => 0, 'assigned' => 0],
    'filters' => $initialFilters ?? ['search' => '', 'status' => 'all', 'priority' => 'all', 'assigned' => 'all', 'unread_only' => false],
    'user' => [
        'id' => (int) (($user['id'] ?? 0)),
        'name' => (string) ($user['name'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'store_id' => (int) ($user['store_id'] ?? 0),
    ],
];
?>
<style>
.sc-flash{display:none;border:1px solid var(--kp-border);border-radius:14px;padding:10px 14px;margin-bottom:14px;font-weight:600}.sc-flash.show{display:block}.sc-flash.ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}.sc-flash.err{background:#fef2f2;border-color:#fecaca;color:#991b1b}.sc-flash.info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.sc-settings,.sc-side,.sc-main,.sc-item,.sc-compose,.sc-empty{border:1px solid var(--kp-border);background:#fff;border-radius:18px}.sc-settings{padding:16px 18px;margin-bottom:16px}.sc-settings summary{cursor:pointer;font-weight:700;list-style:none;display:flex;align-items:center;gap:8px}.sc-settings summary::-webkit-details-marker{display:none}
.sc-shell{display:grid;grid-template-columns:320px minmax(0,1fr);gap:16px;min-height:calc(100vh - 220px)}.sc-side{display:flex;flex-direction:column;overflow:hidden;min-height:0}.sc-side-head,.sc-head,.sc-compose{padding:16px}.sc-side-head,.sc-head{border-bottom:1px solid var(--kp-border)}.sc-list{padding:10px;display:flex;flex-direction:column;gap:8px;overflow:auto;min-height:0;flex:1}
.sc-item{padding:12px 14px;text-align:left;display:block;width:100%}.sc-item.active{border-color:rgba(0,191,99,.35);background:#f8fffb}.sc-item-name{font-weight:700;display:flex;align-items:center;gap:8px}.sc-item-sub,.sc-sub,.sc-meta{font-size:12px;color:var(--kp-muted)}.sc-item-last{margin-top:6px;font-size:13px;color:#334155;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.sc-item-top{display:flex;justify-content:space-between;gap:10px;align-items:center}.sc-item-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.sc-unread{min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:var(--kp-primary);color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center}
.sc-main{display:flex;flex-direction:column;overflow:hidden}.sc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}.sc-title{font-size:1.05rem;font-weight:700}.sc-title-row{display:flex;align-items:center;gap:10px}.sc-title-icon{width:38px;height:38px;border-radius:12px;background:rgba(0,191,99,.1);color:var(--kp-primary-dark);display:inline-flex;align-items:center;justify-content:center}.sc-head-right,.sc-switch{display:flex;gap:8px;flex-wrap:wrap}.sc-switch .btn,.sc-head-right .btn{min-height:40px;border-radius:999px}
.sc-chip{display:inline-flex;align-items:center;gap:6px;min-height:28px;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent}.sc-chip .material-icons-outlined{font-size:14px}.sc-chip.bot{background:rgba(14,165,233,.1);color:#0369a1;border-color:rgba(14,165,233,.16)}.sc-chip.live{background:rgba(0,191,99,.12);color:#0f7a48;border-color:rgba(0,191,99,.16)}.sc-chip.closed{background:rgba(239,68,68,.1);color:#b91c1c;border-color:rgba(239,68,68,.16)}.sc-chip.open{background:rgba(15,23,42,.06);color:#334155;border-color:rgba(15,23,42,.08)}.sc-chip.priority-high{background:rgba(245,158,11,.1);color:#b45309;border-color:rgba(245,158,11,.16)}.sc-chip.priority-urgent{background:rgba(239,68,68,.12);color:#b91c1c;border-color:rgba(239,68,68,.16)}.sc-chip.assigned{background:rgba(99,102,241,.1);color:#4338ca;border-color:rgba(99,102,241,.16)}
.sc-side-head{display:grid;gap:12px}.sc-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.sc-stat{padding:10px 12px;border-radius:14px;border:1px solid var(--kp-border);background:#f8fafc}.sc-stat-value{font-size:18px;font-weight:700;line-height:1;color:#0f172a}.sc-stat-label{margin-top:6px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--kp-muted)}.sc-filters{display:flex;flex-wrap:wrap;gap:8px}.sc-filter{min-height:34px;padding:6px 12px;border-radius:999px;border:1px solid var(--kp-border);background:#fff;color:#475569;font-size:12px;font-weight:600}.sc-filter.active{border-color:rgba(0,191,99,.22);background:rgba(0,191,99,.08);color:#0f7a48}
.sc-search{position:relative}.sc-search .material-icons-outlined{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--kp-muted)}.sc-search .form-control{padding-left:40px}
.sc-panel{display:none;padding:14px 16px;border-bottom:1px solid var(--kp-border);background:#f8fafc}.sc-panel.show{display:grid;gap:12px}.sc-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.sc-panel-actions{display:flex;gap:8px;flex-wrap:wrap}.sc-panel-box{padding:12px 14px;border:1px solid var(--kp-border);border-radius:14px;background:#fff}.sc-panel-box-title{font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--kp-muted);margin-bottom:6px}
.sc-body{flex:1;overflow:auto;padding:16px;background:#f8fafc}.sc-msgs{display:grid;gap:12px}.sc-msg{display:flex}.sc-msg.mine{justify-content:flex-end}.sc-bubble{max-width:min(700px,88%);padding:12px 14px;border:1px solid var(--kp-border);border-radius:16px;background:#fff}.sc-msg.mine .sc-bubble{background:#0f7a48;border-color:#0f7a48;color:#fff}.sc-msg.bot .sc-bubble{background:#f0f9ff;border-color:#bae6fd}.sc-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.sc-compose{border-top:1px solid var(--kp-border)}.sc-compose textarea{min-height:82px;resize:vertical}.sc-compose-foot{margin-top:12px;display:flex;justify-content:flex-end}
.sc-empty{min-height:220px;display:grid;place-items:center;text-align:center;color:var(--kp-muted);padding:24px}
@media (max-width:991px){.sc-shell{grid-template-columns:1fr}.sc-side{max-height:420px}}@media (max-width:575px){.sc-side-head,.sc-head,.sc-body,.sc-compose,.sc-panel{padding:14px}.sc-bubble{max-width:100%}.sc-switch{width:100%}.sc-switch .btn{flex:1 1 100%}.sc-stats{grid-template-columns:1fr}}
</style>

<div class="kp-page-header align-items-center">
    <div class="text-center text-md-start w-100">
        <h2 class="kp-page-title d-inline-flex align-items-center gap-2">
            <span class="material-icons-outlined">support_agent</span>
            Live Support
        </h2>
    </div>
</div>

<div id="flash" class="sc-flash" role="status" aria-live="polite"></div>

<?php if ($isSuperadmin): ?>
    <div class="sc-shell">
        <aside class="sc-side">
            <div class="sc-side-head">
                <div class="fw-semibold d-flex align-items-center gap-2">
                    <span class="material-icons-outlined">forum</span>
                    Room
                </div>
                <div class="mt-3 sc-search">
                    <span class="material-icons-outlined">search</span>
                    <input id="search" class="form-control" type="search" placeholder="Cari toko">
                </div>
                <div id="stats" class="sc-stats"></div>
                <div id="filters" class="sc-filters"></div>
            </div>
            <div id="list" class="sc-list"></div>
        </aside>

        <section class="sc-main">
            <div id="head" class="sc-head"></div>
            <div id="insight" class="sc-panel"></div>
            <div id="body" class="sc-body"></div>
            <div class="sc-compose">
                <label class="form-label" for="composer">Balas</label>
                <textarea id="composer" class="form-control" placeholder="Tulis balasan..."></textarea>
                <div class="sc-compose-foot">
                    <button id="bSend" type="button" class="btn kp-btn-primary">
                        <span class="material-icons-outlined">send</span>
                        Kirim
                    </button>
                </div>
            </div>
        </section>
    </div>
<?php else: ?>
    <section class="sc-main">
        <div id="head" class="sc-head"></div>
        <div id="insight" class="sc-panel"></div>
        <div id="body" class="sc-body"></div>
        <div class="sc-compose">
            <label class="form-label" for="composer">Tulis pesan</label>
            <textarea id="composer" class="form-control" placeholder="Jelaskan kendala Anda..."></textarea>
            <div class="sc-compose-foot">
                <button id="bSend" type="button" class="btn kp-btn-primary">
                    <span class="material-icons-outlined">send</span>
                    Kirim
                </button>
            </div>
        </div>
    </section>
<?php endif; ?>

<script>
(() => {
const api=<?php echo json_encode(base_url('api/support_chat.php'), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,csrf=<?php echo json_encode(csrf_token(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>,isSuper=<?php echo $isSuperadmin ? 'true' : 'false'; ?>;
const S=<?php echo json_encode($initialState, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
const baseFilters=()=>({search:'',status:'all',priority:'all',assigned:'all',unread_only:false});
const normalizeFilters=v=>({search:String(v?.search||''),status:String(v?.status||'all'),priority:String(v?.priority||'all'),assigned:String(v?.assigned||'all'),unread_only:Boolean(v?.unread_only)});
S.filters=normalizeFilters(S.filters||baseFilters());
S.stats=typeof S.stats==='object'&&S.stats?S.stats:{all:0,open:0,closed:0,unread:0,urgent:0,assigned:0};
let cur=S.activeThread?Number(S.activeThread.id):(S.threads[0]?Number(S.threads[0].id):0),busy=false,flashTimer=0,searchTimer=0;
const el={flash:document.getElementById('flash'),list:document.getElementById('list'),head:document.getElementById('head'),body:document.getElementById('body'),composer:document.getElementById('composer'),send:document.getElementById('bSend'),search:document.getElementById('search'),stats:document.getElementById('stats'),filters:document.getElementById('filters'),insight:document.getElementById('insight')};
const A=()=>((S.threads||[]).find(t=>Number(t.id)===Number(cur))||S.activeThread||null);
const E=v=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
const N=v=>E(v).replace(/\n/g,'<br>');
const F=v=>{if(!v)return'-';const d=new Date(String(v).replace(' ','T'));return Number.isNaN(d.getTime())?v:new Intl.DateTimeFormat('id-ID',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}).format(d);};
const modeText=t=>String(t?.chat_mode||'bot')==='live'?'Live Chat ON':'Bot ON';
const modeClass=t=>String(t?.chat_mode||'bot')==='live'?'live':'bot';
const modeIcon=t=>String(t?.chat_mode||'bot')==='live'?'support_agent':'smart_toy';
const partnerText=t=>String(t?.chat_mode||'bot')==='live'?'Superadmin':'Bot KASPINDO';
const statusText=t=>String(t?.status||'open')==='closed'?'Closed':'Open';
const statusClass=t=>String(t?.status||'open')==='closed'?'closed':'open';
const statusIcon=t=>String(t?.status||'open')==='closed'?'lock':'chat';
const priorityText=t=>String(t?.priority_label||'Normal');
const priorityClass=t=>{const p=String(t?.priority||'normal');if(p==='urgent')return'priority-urgent';if(p==='high')return'priority-high';return'';};
const assignedText=t=>String(t?.assigned_to_name||'').trim()!==''?`Diambil ${String(t.assigned_to_name)}`:'Belum diambil';
const flash=(m='',c='info')=>{clearTimeout(flashTimer);el.flash.className='sc-flash';el.flash.textContent='';if(!m)return;el.flash.className=`sc-flash show ${c}`;el.flash.textContent=m;flashTimer=window.setTimeout(()=>{el.flash.className='sc-flash';el.flash.textContent='';},3200);};
const sync=()=>{if(el.search)el.search.value=String(S.filters?.search||'');};
const q=(extra={})=>{const f=normalizeFilters(S.filters||baseFilters());const p=new URLSearchParams({action:String(extra.action||'bootstrap'),search:String(f.search||''),status:String(f.status||'all'),priority:String(f.priority||'all'),assigned:String(f.assigned||'all')});if(f.unread_only)p.set('unread_only','1');if(extra.thread_id)p.set('thread_id',String(extra.thread_id));if(extra.after_id)p.set('after_id',String(extra.after_id));return p.toString();};
const j=async(u,o={})=>{const r=await fetch(u,o),p=await r.json();if(!r.ok||!p.ok)throw new Error(p.message||'Terjadi kesalahan pada support chat.');return p;};
const apply=(p,opt={})=>{const o={append:false,keepMessages:false,...opt};if(Array.isArray(p.threads))S.threads=p.threads;if(p.filters&&typeof p.filters==='object')S.filters=normalizeFilters(p.filters);if(p.stats&&typeof p.stats==='object')S.stats={...S.stats,...p.stats};if(Object.prototype.hasOwnProperty.call(p,'summary'))S.summary=String(p.summary||'');if(Object.prototype.hasOwnProperty.call(p,'draft'))S.draft=String(p.draft||'');if(p.active_thread){S.activeThread=p.active_thread;cur=Number(p.active_thread.id||0);}else if(!(S.threads||[]).some(t=>Number(t.id)===Number(cur))){S.activeThread=null;cur=0;}if(Array.isArray(p.messages)){if(o.append)S.messages=[...(S.messages||[]),...p.messages];else if(!o.keepMessages)S.messages=p.messages;}sync();};
const renderStats=()=>{if(!el.stats)return;const s=S.stats||{};el.stats.innerHTML=`<div class="sc-stat"><div class="sc-stat-value">${Number(s.open||0)}</div><div class="sc-stat-label">Open</div></div><div class="sc-stat"><div class="sc-stat-value">${Number(s.unread||0)}</div><div class="sc-stat-label">Unread</div></div><div class="sc-stat"><div class="sc-stat-value">${Number(s.urgent||0)}</div><div class="sc-stat-label">Urgent</div></div><div class="sc-stat"><div class="sc-stat-value">${Number(s.assigned||0)}</div><div class="sc-stat-label">Assigned</div></div>`;};
const filterDefs=()=>[{key:'all',label:'Semua',active:f=>f.status==='all'&&f.priority==='all'&&f.assigned==='all'&&!f.unread_only,apply:f=>normalizeFilters({...baseFilters(),search:f.search})},{key:'unread',label:'Unread',active:f=>Boolean(f.unread_only),apply:f=>normalizeFilters({...baseFilters(),search:f.search,unread_only:true})},{key:'open',label:'Open',active:f=>f.status==='open'&&!f.unread_only&&f.priority==='all'&&f.assigned==='all',apply:f=>normalizeFilters({...baseFilters(),search:f.search,status:'open'})},{key:'urgent',label:'Urgent',active:f=>f.priority==='urgent'&&!f.unread_only&&f.assigned==='all',apply:f=>normalizeFilters({...baseFilters(),search:f.search,priority:'urgent'})},{key:'mine',label:'Milik Saya',active:f=>f.assigned==='mine'&&!f.unread_only&&f.status==='all'&&f.priority==='all',apply:f=>normalizeFilters({...baseFilters(),search:f.search,assigned:'mine'})}];
const renderFilters=()=>{if(!el.filters)return;const f=normalizeFilters(S.filters);const defs=filterDefs();el.filters.innerHTML=defs.map(def=>`<button type="button" class="sc-filter ${def.active(f)?'active':''}" data-filter="${E(def.key)}">${E(def.label)}</button>`).join('');el.filters.querySelectorAll('[data-filter]').forEach(btn=>btn.addEventListener('click',async()=>{const key=String(btn.getAttribute('data-filter')||'all');const def=defs.find(item=>item.key===key);if(!def||busy)return;S.filters=def.apply(normalizeFilters(S.filters));S.summary='';S.draft='';await boot(cur,false);}));};
const renderList=()=>{if(!el.list)return;const ts=Array.isArray(S.threads)?S.threads:[];if(!ts.length){el.list.innerHTML=`<div class="sc-empty"><div>Belum ada room.</div></div>`;return;}el.list.innerHTML=ts.map(t=>{const active=Number(t.id)===Number(cur),unread=Number(t.unread_count||0),priorityChip=String(t.priority||'normal')!=='normal'?`<span class="sc-chip ${priorityClass(t)}"><span class="material-icons-outlined">priority_high</span>${E(priorityText(t))}</span>`:'',assignedChip=String(t.assigned_to_name||'').trim()!==''?`<span class="sc-chip assigned"><span class="material-icons-outlined">assignment_ind</span>${E(String(t.assigned_to_name||''))}</span>`:'';return `<button type="button" class="sc-item ${active?'active':''}" data-id="${Number(t.id)}"><div class="sc-item-top"><div class="sc-item-name"><span class="material-icons-outlined">storefront</span>${E(t.store_name||'Toko')}</div>${unread>0?`<span class="sc-unread">${unread}</span>`:''}</div><div class="sc-item-sub mt-1">${E(t.store_code||'-')} - ${E(F(t.last_message_at))}</div><div class="sc-item-tags"><span class="sc-chip ${modeClass(t)}"><span class="material-icons-outlined">${modeIcon(t)}</span>${E(modeText(t))}</span><span class="sc-chip ${statusClass(t)}"><span class="material-icons-outlined">${statusIcon(t)}</span>${E(statusText(t))}</span>${priorityChip}${assignedChip}</div><div class="sc-item-last">${E(t.last_message||'Belum ada pesan')}</div></button>`;}).join('');el.list.querySelectorAll('[data-id]').forEach(b=>b.addEventListener('click',async()=>{const nextId=Number(b.getAttribute('data-id')||'0');if(!nextId||nextId===cur||busy)return;S.summary='';S.draft='';await boot(nextId,false);}));};
const renderInsight=()=>{if(!el.insight)return;el.insight.className='sc-panel';el.insight.innerHTML='';};
const renderHead=()=>{if(!el.head)return;const t=A();if(!t){el.head.innerHTML=`<div class="sc-title-row"><span class="sc-title-icon"><span class="material-icons-outlined">forum</span></span><div class="sc-title">Pilih room</div></div>`;return;}const mode=String(t.chat_mode||'bot'),status=String(t.status||'open'),priority=String(t.priority||'normal'),assignedToMe=Number(t.assigned_to_user_id||0)===Number(S.user?.id||0);if(!isSuper){el.head.innerHTML=`<div><div class="sc-title-row"><span class="sc-title-icon"><span class="material-icons-outlined">${modeIcon(t)}</span></span><div class="sc-title">Live Support</div></div><div class="sc-sub mt-2">Sedang chat dengan ${E(partnerText(t))}</div><div class="sc-item-tags mt-2"><span class="sc-chip ${modeClass(t)}"><span class="material-icons-outlined">${modeIcon(t)}</span>${E(modeText(t))}</span><span class="sc-chip ${statusClass(t)}"><span class="material-icons-outlined">${statusIcon(t)}</span>${E(statusText(t))}</span></div></div>`;return;}const priorityChip=`<span class="sc-chip ${priorityClass(t)}"><span class="material-icons-outlined">priority_high</span>${E(priorityText(t))}</span>`;const assignedChip=`<span class="sc-chip assigned"><span class="material-icons-outlined">assignment_ind</span>${E(assignedText(t))}</span>`;el.head.innerHTML=`<div><div class="sc-title-row"><span class="sc-title-icon"><span class="material-icons-outlined">storefront</span></span><div class="sc-title">${E(t.store_name||'Toko')}</div></div><div class="sc-sub mt-2">${E(t.store_code||'-')} - ${E(F(t.last_message_at))}</div><div class="sc-item-tags mt-2"><span class="sc-chip ${modeClass(t)}"><span class="material-icons-outlined">${modeIcon(t)}</span>${E(modeText(t))}</span><span class="sc-chip ${statusClass(t)}"><span class="material-icons-outlined">${statusIcon(t)}</span>${E(statusText(t))}</span>${priorityChip}${assignedChip}</div></div><div class="sc-head-right"><div class="sc-switch"><button id="modeBot" type="button" class="btn ${mode==='bot'?'kp-btn-primary':'kp-btn-ghost'}"><span class="material-icons-outlined">smart_toy</span>Bot ON</button><button id="modeLive" type="button" class="btn ${mode==='live'?'kp-btn-primary':'kp-btn-ghost'}"><span class="material-icons-outlined">support_agent</span>Live Chat ON</button></div><div class="sc-switch"><button id="assignSelf" type="button" class="btn ${assignedToMe?'kp-btn-primary':'kp-btn-ghost'}"><span class="material-icons-outlined">${assignedToMe?'person_remove':'assignment_ind'}</span>${assignedToMe?'Lepas':'Ambil'}</button><button data-priority="normal" type="button" class="btn ${priority==='normal'?'kp-btn-primary':'kp-btn-ghost'}">Normal</button><button data-priority="high" type="button" class="btn ${priority==='high'?'kp-btn-primary':'kp-btn-ghost'}">High</button><button data-priority="urgent" type="button" class="btn ${priority==='urgent'?'kp-btn-primary':'kp-btn-ghost'}">Urgent</button><button id="toggleStatus" type="button" class="btn kp-btn-ghost"><span class="material-icons-outlined">${status==='closed'?'lock_open':'close'}</span>${status==='closed'?'Buka Chat':'Close Chat'}</button></div></div>`;const modeBot=document.getElementById('modeBot'),modeLive=document.getElementById('modeLive'),assignSelf=document.getElementById('assignSelf'),toggleStatus=document.getElementById('toggleStatus');if(modeBot)modeBot.addEventListener('click',async()=>{if(mode==='bot'||busy)return;await act('set_mode',{mode:'bot'},'Mode bot aktif.');});if(modeLive)modeLive.addEventListener('click',async()=>{if(mode==='live'||busy)return;await act('set_mode',{mode:'live'},'Mode live chat aktif.');});if(assignSelf)assignSelf.addEventListener('click',async()=>{if(busy)return;await act(assignedToMe?'unassign':'assign_to_me',{},assignedToMe?'Room dilepas.':'Room diambil.');});if(toggleStatus)toggleStatus.addEventListener('click',async()=>{await act('set_status',{status:status==='closed'?'open':'closed'},status==='closed'?'Chat dibuka lagi.':'Chat ditutup.');});el.head.querySelectorAll('[data-priority]').forEach(btn=>btn.addEventListener('click',async()=>{const nextPriority=String(btn.getAttribute('data-priority')||'normal');if(nextPriority===priority||busy)return;await act('set_priority',{priority:nextPriority},`Prioritas diubah ke ${nextPriority}.`);}));};
const renderBody=(scroll=false)=>{if(!el.body)return;const t=A(),messages=Array.isArray(S.messages)?S.messages:[];if(!t){el.body.innerHTML=`<div class="sc-empty"><div>Belum ada room dipilih.</div></div>`;return;}if(!messages.length){el.body.innerHTML=`<div class="sc-empty"><div>Belum ada pesan.</div></div>`;return;}el.body.innerHTML=`<div class="sc-msgs">${messages.map(m=>{const role=String(m.sender_role||''),mine=isSuper?role==='superadmin':role==='admin',bot=role==='bot',metaIcon=bot?'smart_toy':(role==='superadmin'?'support_agent':'person');return `<div class="sc-msg ${mine?'mine':''} ${bot?'bot':''}"><div class="sc-bubble"><div class="sc-meta"><span class="material-icons-outlined" style="font-size:14px">${metaIcon}</span>${E(m.sender_name||'User')} - ${E(F(m.created_at))}</div><div>${N(m.message_text||'')}</div></div></div>`;}).join('')}</div>`;if(scroll)el.body.scrollTop=el.body.scrollHeight;};
const renderComposer=()=>{const t=A(),mode=String(t?.chat_mode||'bot');if(el.composer){if(!t)el.composer.placeholder='Pilih room dulu...';else if(isSuper)el.composer.placeholder=mode==='live'?'Tulis balasan langsung ke toko...':'Balas sekarang, sistem akan ubah room ke live chat...';else el.composer.placeholder=mode==='live'?'Tulis pesan, superadmin akan balas...':'Tulis pesan, bot akan balas...';}if(el.send)el.send.disabled=busy||!t;};
const render=scroll=>{renderStats();renderFilters();renderList();renderHead();renderInsight();renderBody(Boolean(scroll));renderComposer();};
const boot=async(threadId=cur,silent=true)=>{const p=await j(`${api}?${q({action:'bootstrap',thread_id:Number(threadId||0)})}`);if(!silent&&el.composer)el.composer.value='';apply(p);render(!silent);};
const act=async(action,extra={},msg='')=>{if(!A()||!cur){flash('Pilih room dulu.','err');return;}if(busy)return;busy=true;render();try{const p=await j(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:csrf,action,thread_id:Number(cur),filters:S.filters,...extra})});apply(p);if(action==='send_message'&&el.composer)el.composer.value='';render(action==='send_message');if(msg)flash(msg,'ok');}catch(err){flash(err.message||'Gagal memproses support chat.','err');}finally{busy=false;render();}};
const poll=async()=>{if(busy)return;if(!cur){try{await boot(0,true);}catch(err){}return;}const last=Array.isArray(S.messages)&&S.messages.length?Number(S.messages[S.messages.length-1].id||0):0;try{const p=await j(`${api}?${q({action:'messages',thread_id:cur,after_id:last})}`);const hasNew=Array.isArray(p.messages)&&p.messages.length>0;apply(p,{append:hasNew,keepMessages:!hasNew});render(hasNew);}catch(err){}};
if(el.send)el.send.addEventListener('click',async()=>{const message=(el.composer?.value||'').trim();if(!message){flash('Tulis pesan dulu.','err');return;}await act('send_message',{message},'Pesan terkirim.');});
if(el.composer&&el.send)el.composer.addEventListener('keydown',async ev=>{if((ev.ctrlKey||ev.metaKey)&&ev.key==='Enter'){ev.preventDefault();el.send.click();}});
if(el.search)el.search.addEventListener('input',()=>{clearTimeout(searchTimer);S.filters.search=String(el.search.value||'').trim();searchTimer=window.setTimeout(async()=>{await boot(cur,false);},250);});
sync();render(true);window.setInterval(async()=>{await poll();},4000);
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
