<?php header('Content-Type: application/javascript'); ?>
<?php require_once __DIR__ . '/../../config.php'; $settings = get_all_settings(); ?>
var pagePostId=(typeof _PAGE!=='undefined'&&_PAGE.postId)?_PAGE.postId:0;
var pageSlug=(typeof _PAGE!=='undefined'&&_PAGE.slug)?_PAGE.slug:'';
var ajaxReqCount=0;
var pgNotifyEnabled=localStorage.getItem('comment_notify_enabled')==='1';
var pgActiveReplyId=null;
var pgActiveStickerTarget=null;

const STICKER_LIST=<?php echo json_encode(get_sticker_names(), JSON_UNESCAPED_UNICODE); ?>;
const STICKER_HEO_BASE='assets/stickers2/';
const STICKER_LIST_KAWA=<?php echo json_encode(get_sticker_names_kawa(), JSON_UNESCAPED_UNICODE); ?>;
const STICKER_KAWA_BASE='assets/stickers/';
function ajaxFetch(url,options){return fetch(url,options);}
function showToast(msg){var t=document.getElementById('layuiToast');if(t){t.textContent=msg;t.classList.add('show');setTimeout(function(){t.classList.remove('show');},2000);}}
function toggleTopMenu(ev){if(ev)ev.stopPropagation();var p=document.getElementById('topMenuPanel');var o=document.getElementById('menuOverlay');if(p.classList.contains('show')){closeTopMenu();}else{p.classList.add('show');o.classList.add('show');}}
function closeTopMenu(){document.getElementById('topMenuPanel').classList.remove('show');document.getElementById('menuOverlay').classList.remove('show');}
window.addEventListener('scroll',function(){var sy=window.scrollY;var menuBtn=document.getElementById('topMenuBtn');if(menuBtn){if(sy>120){menuBtn.classList.remove('dark');menuBtn.classList.add('light');}else{menuBtn.classList.remove('light');menuBtn.classList.add('dark');}}});

function pgReloadComments(){fetch('./'+pageSlug+'.html?cp=1').then(function(r){return r.text();}).then(function(html){var parser=new DOMParser();var doc=parser.parseFromString(html,'text/html');var newList=doc.querySelector('.pg-comment-list');var curList=document.getElementById('pgList');if(newList&&curList){curList.innerHTML=newList.innerHTML;pgReinitComments();var newHint=doc.querySelector('.pg-page-end-hint');var curHint=document.querySelector('.pg-page-end-hint');if(newHint&&!curHint){var commentsSection=document.querySelector('.pg-comments-section');if(commentsSection)commentsSection.appendChild(newHint.cloneNode(true));}}else{window.location.reload();}}).catch(function(){window.location.reload();});}
function pgReinitComments(){pgSubmitting=false;pgActiveReplyId=null;pgActiveStickerTarget=null;pgCloseAllStickerPanels();try{var e=localStorage.getItem('comment_email');if(e){var emEl=document.getElementById('pgEmail');if(emEl){emEl.value=e;pgFetchAvatar();}}}catch(ex){}try{var savedAvatar=localStorage.getItem('comment_avatar');if(savedAvatar){var preview=document.getElementById('pgAvatarPreview');if(preview)preview.innerHTML='<img src="'+savedAvatar+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';}}catch(ex){}try{var n=localStorage.getItem('comment_nick');if(n){var nmEl=document.getElementById('pgName');if(nmEl)nmEl.value=n;}}catch(ex){}try{var s=localStorage.getItem('comment_site');if(s){var urEl=document.getElementById('pgUrl');if(urEl)urEl.value=s;}}catch(ex){}pgInitNotify();}
document.addEventListener('click',function(e){if(e.target.closest('.top-menu-panel')||e.target.closest('.top-menu-btn'))return;closeTopMenu();});

(function pgInit(){
    try{var e=localStorage.getItem('comment_email');if(e){document.getElementById('pgEmail').value=e;pgFetchAvatar();}}catch(ex){}
    try{var savedAvatar=localStorage.getItem('comment_avatar');if(savedAvatar){var preview=document.getElementById('pgAvatarPreview');if(preview)preview.innerHTML='<img src="'+savedAvatar+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';}}catch(ex){}
    try{var n=localStorage.getItem('comment_nick');if(n)document.getElementById('pgName').value=n;}catch(ex){}
    try{var s=localStorage.getItem('comment_site');if(s)document.getElementById('pgUrl').value=s;}catch(ex){}
    pgInitNotify();
})();
document.addEventListener('click',function(e){
    var target=e.target;
    if(!target.closest('.pg-sticker-panel')&&!target.closest('.pg-sticker-btn')){
        pgCloseAllStickerPanels();
    }
    if(!pgActiveReplyId)return;
    var box=document.getElementById('replyBox-'+pgActiveReplyId);
    if(!box||box.style.display!=='block')return;
    if(box.contains(target))return;
    if(target.closest('.pg-reply-btn')||target.closest('.pg-reply-item'))return;
    if(target.closest('.pg-sticker-btn')||target.closest('.pg-notify-btn')||target.closest('.pg-btn-send'))return;
    pgCloseReply(pgActiveReplyId);
});

function pgToggleStickerPanel(cid){var pid=cid?'pgStickerPanel-'+cid:'pgStickerPanel';var p=document.getElementById(pid);if(!p)return;if(p.classList.contains('show')){p.classList.remove('show');pgActiveStickerTarget=null;}else{pgCloseAllStickerPanels();pgActiveStickerTarget=cid?('pgReContent-'+cid):'pgContent';var gid=cid?'pgStickerGrid-'+cid:'pgStickerGrid';var g=document.getElementById(gid);if(g&&!g.dataset.loaded){pgSwitchStickerTab('kawa',cid);g.dataset.loaded='1';}p.classList.add('show');}}
function pgCloseAllStickerPanels(){document.querySelectorAll('.pg-sticker-panel').forEach(function(p){p.classList.remove('show');});pgActiveStickerTarget=null;}
function pgSwitchStickerTab(tab,cid){var gid=cid?'pgStickerGrid-'+cid:'pgStickerGrid';var g=document.getElementById(gid);if(!g)return;var h='';if(tab==='heo'){STICKER_LIST.forEach(function(s){h+='<div class="pg-sticker-grid-item" onclick="pgInsertSticker(\''+s+'\',\'heo\')" title="'+s+'"><img src="'+STICKER_HEO_BASE+pgStickerNameToFilename(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}else{STICKER_LIST_KAWA.forEach(function(s){var name=s.replace('kawa-','');h+='<div class="pg-sticker-grid-item" onclick="pgInsertSticker(\''+name+'\',\'kawa\')" title="'+s+'"><img src="'+STICKER_KAWA_BASE+encodeURIComponent(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}g.innerHTML=h;var prefix=cid?'#pgStickerPanel-'+cid+' ':'#pgStickerPanel ';var tabs=document.querySelectorAll((cid?'#pgStickerPanel-'+cid:'#pgStickerPanel')+' .pg-sticker-tab');tabs.forEach(function(t){t.classList.toggle('active',t.dataset.tab===tab);});}
function pgInsertSticker(name,type){var taId=pgActiveStickerTarget||'pgContent';var ta=document.getElementById(taId);if(!ta)return;ta.focus();var code=type==='kawa'?':kawa-'+name+':':':heo-'+name+':';var start=ta.selectionStart,end=ta.selectionEnd,taVal=ta.value;ta.value=taVal.substring(0,start)+code+taVal.substring(end);ta.selectionStart=ta.selectionEnd=start+code.length;pgCloseAllStickerPanels();}
function pgStickerNameToFilename(name){return name.replace(/./g,function(ch){var code=ch.charCodeAt(0);return code>127?'#U'+code.toString(16):ch;}).replace(/#/g,'%23');}

function pgInitNotify(){var off=document.getElementById('pgNotifyOff');var on=document.getElementById('pgNotifyOn');var btn=document.getElementById('pgNotifyBtn');if(!off||!on||!btn)return;if(pgNotifyEnabled){btn.classList.add('on');off.style.display='none';on.style.display='block';}else{btn.classList.remove('on');off.style.display='block';on.style.display='none';}}
function pgToggleNotify(){pgNotifyEnabled=!pgNotifyEnabled;localStorage.setItem('comment_notify_enabled',pgNotifyEnabled?'1':'0');pgInitNotify();}
function pgFetchAvatar(){var email=document.getElementById('pgEmail').value.trim();var hint=document.getElementById('pgHint');if(!email||!email.includes('@')){if(hint){hint.textContent='填写邮箱自动获取头像';hint.style.color='';}return;}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';var preview=document.getElementById('pgAvatarPreview');if(preview)preview.innerHTML='<img src="'+avatarUrl+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';if(hint){hint.textContent='填写邮箱自动获取头像';hint.style.color='';}}
var pgSubmitting=false;
function pgSubmit(){if(pgSubmitting){showToast('发送中，请稍候...');return;}var email=document.getElementById('pgEmail').value.trim();var name=document.getElementById('pgName').value.trim();var url=document.getElementById('pgUrl').value.trim();var content=document.getElementById('pgContent').value.trim();if(!email||!name||!content){showToast('请填写邮箱、昵称和评论内容');if(!email)pgShake('pgEmail');if(!name)pgShake('pgName');if(!content)pgShake('pgContent');return;}pgSubmitting=true;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送中...';btn.style.opacity='0.6';btn.style.pointerEvents='none';}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';var body='post_id='+pagePostId+'&nickname='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)+'&website='+encodeURIComponent(url)+'&content='+encodeURIComponent(content)+'&avatar='+encodeURIComponent(avatarUrl)+'&parent_id=0';if(pgNotifyEnabled)body+='&notify=1';ajaxFetch('api/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(r){return r.json();}).then(function(data){pgSubmitting=false;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}if(data.code===1){localStorage.setItem('comment_email',email);localStorage.setItem('comment_nick',name);if(url)localStorage.setItem('comment_site',url);var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';localStorage.setItem('comment_avatar',avatarUrl);document.getElementById('pgContent').value='';if(data.msg&&data.msg.indexOf('审核')!==-1){showToast('评论已提交，等待审核');}else{showToast('评论成功');pgReloadComments();}}else{showToast(data.msg||'评论失败');}}).catch(function(){pgSubmitting=false;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}showToast('评论失败');});}
function pgShowReply(commentId,replyTo){
var box=document.getElementById('replyBox-'+commentId);
var mainForm=document.getElementById('pgForm');
if(!box)return;
if(pgActiveReplyId&&pgActiveReplyId!==commentId){
var old=document.getElementById('replyBox-'+pgActiveReplyId);
if(old){old.style.display='none';old.innerHTML='';}
}
if(box.style.display==='block'){
box.style.display='none';box.innerHTML='';pgActiveReplyId=null;
if(mainForm)mainForm.style.display='';
return;
}
if(mainForm)mainForm.style.display='none';
pgActiveReplyId=commentId;
var email='',name='',url='';
try{email=localStorage.getItem('comment_email')||'';}catch(e){}
try{name=localStorage.getItem('comment_nick')||'';}catch(e){}
try{url=localStorage.getItem('comment_site')||'';}catch(e){}
var notifyOn=pgNotifyEnabled?'block':'none';
var notifyOff=pgNotifyEnabled?'none':'block';
var notifyCls=pgNotifyEnabled?' pg-notify-btn on':' pg-notify-btn';
var h="<div class='pg-comment-form' style='margin-bottom:0;'>";
h+="<div class='pg-form-row-email'>";
h+="<input type='email' class='pg-form-input pg-re-email-"+commentId+"' placeholder='输入邮箱' oninput='pgFetchReplyAvatar("+commentId+")' style='padding-right:46px;'>";
h+="<div class='pg-form-avatar-preview' id='pgReplyAvatarPreview-"+commentId+"'>";
h+="<img src='https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp' alt='' style='width:100%;height:100%;object-fit:cover;'>";
h+="</div></div>";
h+="<div class='pg-form-row'>";
h+="<input type='text' class='pg-form-input pg-re-name-"+commentId+"' placeholder='昵称' value='"+pgEsc(name)+"'>";
h+="<input type='url' class='pg-form-input pg-re-url-"+commentId+"' placeholder='网址（选填）' value='"+pgEsc(url)+"'>";
h+="</div>";
h+="<div class='pg-form-row'>";
h+="<textarea class='pg-form-textarea pg-re-content-"+commentId+"' id='pgReContent-"+commentId+"' placeholder='回复 "+pgEsc(replyTo)+"...' style='min-height:56px;'></textarea>";
h+="</div>";
h+="<div class='pg-sticker-panel' id='pgStickerPanel-"+commentId+"'>";
h+="<div class='pg-sticker-tabs'>";
h+="<span class='pg-sticker-tab active' data-tab='kawa' onclick='pgSwitchStickerTab(\"kawa\","+commentId+")'>三小只</span>";
h+="<span class='pg-sticker-tab' data-tab='heo' onclick='pgSwitchStickerTab(\"heo\","+commentId+")'>Heo</span>";
h+="</div>";
h+="<div class='pg-sticker-grid' id='pgStickerGrid-"+commentId+"'></div>";
h+="</div>";
h+="<div class='pg-form-footer'>";
h+="<span class='pg-form-hint'>填写邮箱自动获取头像</span>";
h+="<div style='display:flex;gap:6px;align-items:center;'>";
h+="<button class='"+notifyCls+"' id='pgReNotifyBtn-"+commentId+"' onclick='pgToggleReplyNotify("+commentId+")' title='邮件提醒'>";
h+="<svg id='pgReNotifyOff-"+commentId+"' viewBox='0 0 24 24' style='display:"+notifyOff+"'><path d='M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z'/><line x1='3' y1='3' x2='21' y2='21' stroke='currentColor' stroke-width='1.5' fill='none'/></svg>";
h+="<svg id='pgReNotifyOn-"+commentId+"' viewBox='0 0 24 24' style='display:"+notifyOn+"'><path d='M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z'/></svg>";
h+="</button>";
h+="<button class='pg-sticker-btn' onclick='pgToggleStickerPanel("+commentId+")' title='表情'>";
h+="<svg viewBox='0 0 24 24' style='width:16px;height:16px;fill:var(--primary);'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3.5-9c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm7 0c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z'/></svg>";
h+="</button>";
h+="<button class='pg-btn-send' onclick='pgSendReply("+commentId+")'>发送</button>";
h+="</div></div></div>";
box.innerHTML=h;box.style.display='block';
setTimeout(function(){var t=document.querySelector('.pg-re-content-'+commentId);if(t)t.focus();},50);
}
function pgCloseReply(commentId){var box=document.getElementById('replyBox-'+commentId);var mainForm=document.getElementById('pgForm');if(box){box.style.display='none';box.innerHTML='';}if(pgActiveReplyId===commentId)pgActiveReplyId=null;if(mainForm)mainForm.style.display='';}
function pgToggleReplyNotify(cid){pgNotifyEnabled=!pgNotifyEnabled;localStorage.setItem('comment_notify_enabled',pgNotifyEnabled?'1':'0');var btn=document.getElementById('pgReNotifyBtn-'+cid);var off=document.getElementById('pgReNotifyOff-'+cid);var on=document.getElementById('pgReNotifyOn-'+cid);if(btn){if(pgNotifyEnabled)btn.classList.add('on');else btn.classList.remove('on');}if(off)off.style.display=pgNotifyEnabled?'none':'block';if(on)on.style.display=pgNotifyEnabled?'block':'none';}
function pgSendReply(parentId){if(pgSubmitting){showToast('发送中，请稍候...');return;}var cEl=document.querySelector('.pg-re-content-'+parentId);var content=cEl?cEl.value.trim():'';if(!content){showToast('请输入回复内容');return;}var mEmail=document.getElementById('pgEmail').value.trim();var mName=document.getElementById('pgName').value.trim();var mUrl=document.getElementById('pgUrl').value.trim();var rEmail=document.querySelector('.pg-re-email-'+parentId);var rName=document.querySelector('.pg-re-name-'+parentId);var rUrl=document.querySelector('.pg-re-url-'+parentId);var email=(rEmail&&rEmail.value.trim())?rEmail.value.trim():mEmail;var name=(rName&&rName.value.trim())?rName.value.trim():mName;var url=(rUrl&&rUrl.value.trim())?rUrl.value.trim():mUrl;if(!email||!name){showToast('请填写邮箱和昵称');if(!email)pgShake('pgEmail');if(!name)pgShake('pgName');return;}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';pgSubmitting=true;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送中...';btn.style.opacity='0.6';btn.style.pointerEvents='none';}var body='post_id='+pagePostId+'&nickname='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)+'&website='+encodeURIComponent(url)+'&content='+encodeURIComponent(content)+'&avatar='+encodeURIComponent(avatarUrl)+'&parent_id='+parentId;if(pgNotifyEnabled)body+='&notify=1';ajaxFetch('api/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(r){return r.json();}).then(function(data){pgSubmitting=false;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}if(data.code===1){localStorage.setItem('comment_email',email);localStorage.setItem('comment_nick',name);if(url)localStorage.setItem('comment_site',url);localStorage.setItem('comment_avatar',avatarUrl);pgCloseReply(parentId);if(data.msg&&data.msg.indexOf('审核')!==-1){showToast('评论已提交，等待审核');}else{showToast('回复成功');pgReloadComments();}}else{showToast(data.msg||'回复失败');}}).catch(function(){pgSubmitting=false;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}showToast('回复失败');});}
function pgToggleReplies(commentId){var list=document.getElementById('replies-'+commentId);var btn=document.getElementById('toggleBtn-'+commentId);if(!list||!btn)return;if(list.classList.contains('hidden')){list.classList.remove('hidden');btn.classList.remove('collapsed');}else{list.classList.add('hidden');btn.classList.add('collapsed');}}
function pgShake(id){var el=document.getElementById(id);if(!el)return;el.style.borderColor='#ec4899';el.style.animation='pgShake 0.4s ease';setTimeout(function(){el.style.borderColor='';el.style.animation='';},400);}
function pgEsc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function pgReplyKey(e,cid){if(e.key==='Enter'&&(e.metaKey||e.ctrlKey)){e.preventDefault();pgSendReply(cid);}}
function pgFetchReplyAvatar(cid){var el=document.querySelector('.pg-re-email-'+cid);if(!el)return;var email=el.value.trim();if(!email||!email.includes('@'))return;var preview=document.getElementById('pgReplyAvatarPreview-'+cid);if(!preview)return;var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';preview.innerHTML='<img src="'+avatarUrl+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';}

function initTabs(){
    document.querySelectorAll('.sc-tabs').forEach(function(tabsContainer){
        var triggers = tabsContainer.querySelectorAll('.sc-tab-trigger');
        var panes = tabsContainer.querySelectorAll('.sc-tab-pane');
        panes.forEach(function(p){
            p.style.display = p.classList.contains('active') ? 'block' : 'none';
        });
        triggers.forEach(function(trigger){
            trigger.addEventListener('click', function(){
                var tabsId = this.dataset.tabs;
                var idx = this.dataset.index;
                triggers.forEach(function(t){
                    if(t.dataset.tabs === tabsId){ t.classList.remove('active'); }
                });
                this.classList.add('active');
                panes.forEach(function(p){
                    if(p.dataset.tabs === tabsId){
                        if(p.dataset.index === idx){
                            p.style.display = 'block';
                            p.classList.add('active');
                        } else {
                            p.style.display = 'none';
                            p.classList.remove('active');
                        }
                    }
                });
            });
        });
    });
}
if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initTabs);
} else {
    initTabs();
}

function pgMd5(str){function rl(l,s){return(l<<s)|(l>>>(32-s));}function au(lX,lY){var lX8=(lX&0x80000000),lY8=(lY&0x80000000),lX4=(lX&0x40000000),lY4=(lY&0x40000000),lR=(lX&0x3FFFFFFF)+(lY&0x3FFFFFFF);if(lX4&lY4)return lR^0x80000000^lX8^lY8;if(lX4|lY4)return(lR&0x40000000)?lR^0xC0000000^lX8^lY8:lR^0x40000000^lX8^lY8;return lR^lX8^lY8;}function f(x,y,z){return(x&y)|((~x)&z);}function g(x,y,z){return(x&z)|(y&(~z));}function h(x,y,z){return x^y^z;}function i(x,y,z){return y^(x|(~z));}function ff(a,b,c,d,x,s,ac){return au(rl(au(au(au(f(b,c,d),a),ac),x),s),b);}function gg(a,b,c,d,x,s,ac){return au(rl(au(au(au(g(b,c,d),a),ac),x),s),b);}function hh(a,b,c,d,x,s,ac){return au(rl(au(au(au(h(b,c,d),a),ac),x),s),b);}function ii(a,b,c,d,x,s,ac){return au(rl(au(au(au(i(b,c,d),a),ac),x),s),b);}str=unescape(encodeURIComponent(str));var x=[],k,AA,BB,CC,DD,a,b,c,d;var S11=7,S12=12,S13=17,S14=22,S21=5,S22=9,S23=14,S24=20,S31=4,S32=11,S33=16,S34=23,S41=6,S42=10,S43=15,S44=21;var n=str.length;var lNumOfWords=((n+8-((n+8)%64))/64+1)*16;var lBP=0,lBC=0;while(lBC<n){x[(lBC-(lBC%4))/4]=x[(lBC-(lBC%4))/4]|(str.charCodeAt(lBC)<<((lBC%4)*8));lBC++;}x[(lBC-(lBC%4))/4]=x[(lBC-(lBC%4))/4]|(0x80<<((lBC%4)*8));x[lNumOfWords-2]=n<<3;x[lNumOfWords-1]=n>>>29;for(k=0;k<x.length;k+=16){a=0x67452301;b=0xEFCDAB89;c=0x98BADCFE;d=0x10325476;AA=a;BB=b;CC=c;DD=d;a=ff(a,b,c,d,x[k+0],S11,0xD76AA478);d=ff(d,a,b,c,x[k+1],S12,0xE8C7B756);c=ff(c,d,a,b,x[k+2],S13,0x242070DB);b=ff(b,c,d,a,x[k+3],S14,0xC1BDCEEE);a=ff(a,b,c,d,x[k+4],S11,0xF57C0FAF);d=ff(d,a,b,c,x[k+5],S12,0x4787C62A);c=ff(c,d,a,b,x[k+6],S13,0xA8304613);b=ff(b,c,d,a,x[k+7],S14,0xFD469501);a=ff(a,b,c,d,x[k+8],S11,0x698098D8);d=ff(d,a,b,c,x[k+9],S12,0x8B44F7AF);c=ff(c,d,a,b,x[k+10],S13,0xFFFF5BB1);b=ff(b,c,d,a,x[k+11],S14,0x895CD7BE);a=ff(a,b,c,d,x[k+12],S11,0x6B901122);d=ff(d,a,b,c,x[k+13],S12,0xFD987193);c=ff(c,d,a,b,x[k+14],S13,0xA679438E);b=ff(b,c,d,a,x[k+15],S14,0x49B40821);a=gg(a,b,c,d,x[k+1],S21,0xF61E2562);d=gg(d,a,b,c,x[k+6],S22,0xC040B340);c=gg(c,d,a,b,x[k+11],S23,0x265E5A51);b=gg(b,c,d,a,x[k+0],S24,0xE9B6C7AA);a=gg(a,b,c,d,x[k+5],S21,0xD62F105D);d=gg(d,a,b,c,x[k+10],S22,0x2441453);c=gg(c,d,a,b,x[k+15],S23,0xD8A1E681);b=gg(b,c,d,a,x[k+4],S24,0xE7D3FBC8);a=gg(a,b,c,d,x[k+9],S21,0x21E1CDE6);d=gg(d,a,b,c,x[k+14],S22,0xC33707D6);c=gg(c,d,a,b,x[k+3],S23,0xF4D50D87);b=gg(b,c,d,a,x[k+8],S24,0x455A14ED);a=gg(a,b,c,d,x[k+13],S21,0xA9E3E905);d=gg(d,a,b,c,x[k+2],S22,0xFCEFA3F8);c=gg(c,d,a,b,x[k+7],S23,0x676F02D9);b=gg(b,c,d,a,x[k+12],S24,0x8D2A4C8A);a=hh(a,b,c,d,x[k+5],S31,0xFFFA3942);d=hh(d,a,b,c,x[k+8],S32,0x8771F681);c=hh(c,d,a,b,x[k+11],S33,0x6D9D6122);b=hh(b,c,d,a,x[k+14],S34,0xFDE5380C);a=hh(a,b,c,d,x[k+1],S31,0xA4BEEA44);d=hh(d,a,b,c,x[k+4],S32,0x4BDECFA9);c=hh(c,d,a,b,x[k+7],S33,0xF6BB4B60);b=hh(b,c,d,a,x[k+10],S34,0xBEBFBC70);a=hh(a,b,c,d,x[k+13],S31,0x289B7EC6);d=hh(d,a,b,c,x[k+0],S32,0xEAA127FA);c=hh(c,d,a,b,x[k+3],S33,0xD4EF3085);b=hh(b,c,d,a,x[k+6],S34,0x4881D05);a=hh(a,b,c,d,x[k+9],S31,0xD9D4D039);d=hh(d,a,b,c,x[k+12],S32,0xE6DB99E5);c=hh(c,d,a,b,x[k+15],S33,0x1FA27CF8);b=hh(b,c,d,a,x[k+2],S34,0xC4AC5665);a=ii(a,b,c,d,x[k+0],S41,0xF4292244);d=ii(d,a,b,c,x[k+7],S42,0x432AFF97);c=ii(c,d,a,b,x[k+14],S43,0xAB9423A7);b=ii(b,c,d,a,x[k+5],S44,0xFC93A039);a=ii(a,b,c,d,x[k+12],S41,0x655B59C3);d=ii(d,a,b,c,x[k+3],S42,0x8F0CCC92);c=ii(c,d,a,b,x[k+10],S43,0xFFEFF47D);b=ii(b,c,d,a,x[k+1],S44,0x85845DD1);a=ii(a,b,c,d,x[k+8],S41,0x6FA87E4F);d=ii(d,a,b,c,x[k+15],S42,0xFE2CE6E0);c=ii(c,d,a,b,x[k+6],S43,0xA3014314);b=ii(b,c,d,a,x[k+13],S44,0x4E0811A1);a=ii(a,b,c,d,x[k+4],S41,0xF7537E82);d=ii(d,a,b,c,x[k+11],S42,0xBD3AF235);c=ii(c,d,a,b,x[k+2],S43,0x2AD7D2BB);b=ii(b,c,d,a,x[k+9],S44,0xEB86D391);a=au(a,AA);b=au(b,BB);c=au(c,CC);d=au(d,DD);}var th=function(v){var h='',t,l;for(var c=0;c<=3;c++){l=(v>>>(c*8))&255;t='0'+l.toString(16);h+=t.substr(t.length-2,2);}return h;};return(th(a)+th(b)+th(c)+th(d)).toLowerCase();}
