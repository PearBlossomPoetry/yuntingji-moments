<?php header('Content-Type: application/javascript'); ?>
<?php require_once __DIR__ . '/../../config.php'; $settings = get_all_settings(); $nickname_color = $settings['nickname_color'] ?? '#5d6d9e'; ?>
var articlePostId=(typeof _ARTICLE!=='undefined'&&_ARTICLE.postId)?_ARTICLE.postId:0;
var articleSlug=(typeof _ARTICLE!=='undefined'&&_ARTICLE.slug)?_ARTICLE.slug:'';
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

function pgReloadComments(){fetch('./'+articleSlug+'.html?cp=1').then(function(r){return r.text();}).then(function(html){var parser=new DOMParser();var doc=parser.parseFromString(html,'text/html');var newList=doc.querySelector('.pg-comment-list');var curList=document.getElementById('pgList');if(newList&&curList){curList.innerHTML=newList.innerHTML;pgReinitComments();var newHint=doc.querySelector('.pg-page-end-hint');var curHint=document.querySelector('.pg-page-end-hint');if(newHint&&!curHint){var commentsSection=document.querySelector('.pg-comments-section');if(commentsSection)commentsSection.appendChild(newHint.cloneNode(true));}}else{window.location.reload();}}).catch(function(){window.location.reload();});}
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
function pgSwitchStickerTab(tab,cid){var gid=cid?'pgStickerGrid-'+cid:'pgStickerGrid';var g=document.getElementById(gid);if(!g)return;var h='';if(tab==='heo'){STICKER_LIST.forEach(function(s){h+='<div class="pg-sticker-grid-item" onclick="pgInsertSticker(\''+s+'\',\'heo\')" title="'+s+'"><img src="'+STICKER_HEO_BASE+pgStickerNameToFilename(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}else{STICKER_LIST_KAWA.forEach(function(s){var name=s.replace('kawa-','');h+='<div class="pg-sticker-grid-item" onclick="pgInsertSticker(\''+name+'\',\'kawa\')" title="'+s+'"><img src="'+STICKER_KAWA_BASE+encodeURIComponent(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}g.innerHTML=h;var tabs=document.querySelectorAll((cid?'#pgStickerPanel-'+cid:'#pgStickerPanel')+' .pg-sticker-tab');tabs.forEach(function(t){t.classList.toggle('active',t.dataset.tab===tab);});}
function pgInsertSticker(name,type){var taId=pgActiveStickerTarget||'pgContent';var ta=document.getElementById(taId);if(!ta)return;ta.focus();var code=type==='kawa'?':kawa-'+name+':':':heo-'+name+':';var start=ta.selectionStart,end=ta.selectionEnd,taVal=ta.value;ta.value=taVal.substring(0,start)+code+taVal.substring(end);ta.selectionStart=ta.selectionEnd=start+code.length;pgCloseAllStickerPanels();}
function pgStickerNameToFilename(name){return name.replace(/./g,function(ch){var code=ch.charCodeAt(0);return code>127?'#U'+code.toString(16):ch;}).replace(/#/g,'%23');}

function pgInitNotify(){var off=document.getElementById('pgNotifyOff');var on=document.getElementById('pgNotifyOn');var btn=document.getElementById('pgNotifyBtn');if(!off||!on||!btn)return;if(pgNotifyEnabled){btn.classList.add('on');off.style.display='none';on.style.display='block';}else{btn.classList.remove('on');off.style.display='block';on.style.display='none';}}
function pgToggleNotify(){pgNotifyEnabled=!pgNotifyEnabled;localStorage.setItem('comment_notify_enabled',pgNotifyEnabled?'1':'0');pgInitNotify();}
function pgFetchAvatar(){var email=document.getElementById('pgEmail').value.trim();var hint=document.getElementById('pgHint');if(!email||!email.includes('@')){if(hint){hint.textContent='填写邮箱自动获取头像';hint.style.color='';}return;}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';var preview=document.getElementById('pgAvatarPreview');if(preview)preview.innerHTML='<img src="'+avatarUrl+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';if(hint){hint.textContent='填写邮箱自动获取头像';hint.style.color='';}}
var pgSubmitting=false;
function pgSubmit(){if(pgSubmitting){showToast('发送中，请稍候...');return;}var email=document.getElementById('pgEmail').value.trim();var name=document.getElementById('pgName').value.trim();var url=document.getElementById('pgUrl').value.trim();var content=document.getElementById('pgContent').value.trim();if(!email||!name||!content){showToast('请填写邮箱、昵称和评论内容');if(!email)pgShake('pgEmail');if(!name)pgShake('pgName');if(!content)pgShake('pgContent');return;}pgSubmitting=true;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送中...';btn.style.opacity='0.6';btn.style.pointerEvents='none';}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';var body='post_id='+articlePostId+'&nickname='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)+'&website='+encodeURIComponent(url)+'&content='+encodeURIComponent(content)+'&avatar='+encodeURIComponent(avatarUrl)+'&parent_id=0';if(pgNotifyEnabled)body+='&notify=1';ajaxFetch('api/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(r){return r.json();}).then(function(data){pgSubmitting=false;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}if(data.code===1){localStorage.setItem('comment_email',email);localStorage.setItem('comment_nick',name);if(url)localStorage.setItem('comment_site',url);var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';localStorage.setItem('comment_avatar',avatarUrl);document.getElementById('pgContent').value='';if(data.msg&&data.msg.indexOf('审核')!==-1){showToast('评论已提交，等待审核');}else{showToast('评论成功');pgReloadComments();}}else{showToast(data.msg||'评论失败');}}).catch(function(err){pgSubmitting=false;var btn=document.querySelector('#pgForm .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}showToast('网络错误');});}

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
h+='<span class="pg-sticker-tab active" data-tab="kawa" onclick="pgSwitchStickerTab(\'kawa\','+commentId+')">三小只</span>';
h+='<span class="pg-sticker-tab" data-tab="heo" onclick="pgSwitchStickerTab(\'heo\','+commentId+')">Heo</span>';
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

function pgSendReply(parentId){if(pgSubmitting){showToast('发送中，请稍候...');return;}var cEl=document.querySelector('.pg-re-content-'+parentId);var content=cEl?cEl.value.trim():'';if(!content){showToast('请输入回复内容');return;}var mEmail=document.getElementById('pgEmail').value.trim();var mName=document.getElementById('pgName').value.trim();var mUrl=document.getElementById('pgUrl').value.trim();var rEmail=document.querySelector('.pg-re-email-'+parentId);var rName=document.querySelector('.pg-re-name-'+parentId);var rUrl=document.querySelector('.pg-re-url-'+parentId);var email=(rEmail&&rEmail.value.trim())?rEmail.value.trim():mEmail;var name=(rName&&rName.value.trim())?rName.value.trim():mName;var url=(rUrl&&rUrl.value.trim())?rUrl.value.trim():mUrl;if(!email||!name){showToast('请填写邮箱和昵称');if(!email)pgShake('pgEmail');if(!name)pgShake('pgName');return;}var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';pgSubmitting=true;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送中...';btn.style.opacity='0.6';btn.style.pointerEvents='none';}var body='post_id='+articlePostId+'&nickname='+encodeURIComponent(name)+'&email='+encodeURIComponent(email)+'&website='+encodeURIComponent(url)+'&content='+encodeURIComponent(content)+'&avatar='+encodeURIComponent(avatarUrl)+'&parent_id='+parentId;if(pgNotifyEnabled)body+='&notify=1';ajaxFetch('api/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(r){return r.json();}).then(function(data){pgSubmitting=false;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}if(data.code===1){localStorage.setItem('comment_email',email);localStorage.setItem('comment_nick',name);if(url)localStorage.setItem('comment_site',url);var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';localStorage.setItem('comment_avatar',avatarUrl);pgCloseReply(parentId);if(data.msg&&data.msg.indexOf('审核')!==-1){showToast('评论已提交，等待审核');}else{showToast('回复成功');pgReloadComments();}}else{showToast(data.msg||'回复失败');}}).catch(function(err){pgSubmitting=false;var btn=document.querySelector('#replyBox-'+parentId+' .pg-btn-send');if(btn){btn.textContent='发送';btn.style.opacity='';btn.style.pointerEvents='';}showToast('网络错误');});}

function pgToggleReplies(commentId){var list=document.getElementById('replies-'+commentId);var btn=document.getElementById('toggleBtn-'+commentId);if(!list||!btn)return;if(list.classList.contains('hidden')){list.classList.remove('hidden');btn.classList.remove('collapsed');}else{list.classList.add('hidden');btn.classList.add('collapsed');}}
function pgShake(id){var el=document.getElementById(id);if(!el)return;el.style.borderColor='#ec4899';el.style.animation='pgShake 0.4s ease';setTimeout(function(){el.style.borderColor='';el.style.animation='';},400);}
function pgEsc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function pgReplyKey(e,cid){if(e.key==='Enter'&&(e.metaKey||e.ctrlKey)){e.preventDefault();pgSendReply(cid);}}
function pgFetchReplyAvatar(cid){var el=document.querySelector('.pg-re-email-'+cid);if(!el)return;var email=el.value.trim();if(!email||!email.includes('@'))return;var preview=document.getElementById('pgReplyAvatarPreview-'+cid);if(!preview)return;var avatarUrl='';if(email.endsWith('@qq.com')){var qq=email.replace('@qq.com','');if(/^\d+$/.test(qq))avatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';}if(!avatarUrl)avatarUrl='https://cravatar.cn/avatar/'+pgMd5(email.toLowerCase())+'?s=100&d=mp';preview.innerHTML='<img src="'+avatarUrl+'" alt="avatar" style="width:100%;height:100%;object-fit:cover;">';}

function pgMd5(str){function rl(l,s){return(l<<s)|(l>>>(32-s));}function au(lX,lY){var lX8=(lX&0x80000000),lY8=(lY&0x80000000),lX4=(lX&0x40000000),lY4=(lY&0x40000000),lR=(lX&0x3FFFFFFF)+(lY&0x3FFFFFFF);if(lX4&lY4)return lR^0x80000000^lX8^lY8;if(lX4|lY4)return(lR&0x40000000)?lR^0xC0000000^lX8^lY8:lR^0x40000000^lX8^lY8;return lR^lX8^lY8;}function f(x,y,z){return(x&y)|((~x)&z);}function g(x,y,z){return(x&z)|(y&(~z));}function h(x,y,z){return x^y^z;}function i(x,y,z){return y^(x|(~z));}function ff(a,b,c,d,x,s,ac){return au(rl(au(au(au(f(b,c,d),a),ac),x),s),b);}function gg(a,b,c,d,x,s,ac){return au(rl(au(au(au(g(b,c,d),a),ac),x),s),b);}function hh(a,b,c,d,x,s,ac){return au(rl(au(au(au(h(b,c,d),a),ac),x),s),b);}function ii(a,b,c,d,x,s,ac){return au(rl(au(au(au(i(b,c,d),a),ac),x),s),b);}str=unescape(encodeURIComponent(str));var x=[],k,AA,BB,CC,DD,a,b,c,d;var S11=7,S12=12,S13=17,S14=22,S21=5,S22=9,S23=14,S24=20,S31=4,S32=11,S33=16,S34=23,S41=6,S42=10,S43=15,S44=21;var n=str.length;var lNumOfWords=((n+8-((n+8)%64))/64+1)*16;var lBP=0,lBC=0;while(lBC<n){x[(lBC-(lBC%4))/4]=x[(lBC-(lBC%4))/4]|(str.charCodeAt(lBC)<<((lBC%4)*8));lBC++;}x[(lBC-(lBC%4))/4]=x[(lBC-(lBC%4))/4]|(0x80<<((lBC%4)*8));x[lNumOfWords-2]=n<<3;x[lNumOfWords-1]=n>>>29;for(k=0;k<x.length;k+=16){a=0x67452301;b=0xEFCDAB89;c=0x98BADCFE;d=0x10325476;AA=a;BB=b;CC=c;DD=d;a=ff(a,b,c,d,x[k+0],S11,0xD76AA478);d=ff(d,a,b,c,x[k+1],S12,0xE8C7B756);c=ff(c,d,a,b,x[k+2],S13,0x242070DB);b=ff(b,c,d,a,x[k+3],S14,0xC1BDCEEE);a=ff(a,b,c,d,x[k+4],S11,0xF57C0FAF);d=ff(d,a,b,c,x[k+5],S12,0x4787C62A);c=ff(c,d,a,b,x[k+6],S13,0xA8304613);b=ff(b,c,d,a,x[k+7],S14,0xFD469501);a=ff(a,b,c,d,x[k+8],S11,0x698098D8);d=ff(d,a,b,c,x[k+9],S12,0x8B44F7AF);c=ff(c,d,a,b,x[k+10],S13,0xFFFF5BB1);b=ff(b,c,d,a,x[k+11],S14,0x895CD7BE);a=ff(a,b,c,d,x[k+12],S11,0x6B901122);d=ff(d,a,b,c,x[k+13],S12,0xFD987193);c=ff(c,d,a,b,x[k+14],S13,0xA679438E);b=ff(b,c,d,a,x[k+15],S14,0x49B40821);a=gg(a,b,c,d,x[k+1],S21,0xF61E2562);d=gg(d,a,b,c,x[k+6],S22,0xC040B340);c=gg(c,d,a,b,x[k+11],S23,0x265E5A51);b=gg(b,c,d,a,x[k+0],S24,0xE9B6C7AA);a=gg(a,b,c,d,x[k+5],S21,0xD62F105D);d=gg(d,a,b,c,x[k+10],S22,0x02441453);c=gg(c,d,a,b,x[k+15],S23,0xD8A1E681);b=gg(b,c,d,a,x[k+4],S24,0xE7D3FBC8);a=gg(a,b,c,d,x[k+9],S21,0x21E1CDE6);d=gg(d,a,b,c,x[k+14],S22,0xC33707D6);c=gg(c,d,a,b,x[k+3],S23,0xF4D50D87);b=gg(b,c,d,a,x[k+8],S24,0x455A14ED);a=gg(a,b,c,d,x[k+13],S21,0xA9E3E905);d=gg(d,a,b,c,x[k+2],S22,0xFCEFA3F8);c=gg(c,d,a,b,x[k+7],S23,0x676F02D9);b=gg(b,c,d,a,x[k+12],S24,0x8D2A4C8A);a=hh(a,b,c,d,x[k+5],S31,0xFFFA3942);d=hh(d,a,b,c,x[k+8],S32,0x8771F681);c=hh(c,d,a,b,x[k+11],S33,0x6D9D6122);b=hh(b,c,d,a,x[k+14],S34,0xFDE5380C);a=hh(a,b,c,d,x[k+1],S31,0xA4BEEA44);d=hh(d,a,b,c,x[k+4],S32,0x4BDECFA9);c=hh(c,d,a,b,x[k+7],S33,0xF6BB4B60);b=hh(b,c,d,a,x[k+10],S34,0xBEBFBC70);a=hh(a,b,c,d,x[k+13],S31,0x289B7EC6);d=hh(d,a,b,c,x[k+0],S32,0xEAA127FA);c=hh(c,d,a,b,x[k+3],S33,0xD4EF3085);b=hh(b,c,d,a,x[k+6],S34,0x04881D05);a=hh(a,b,c,d,x[k+9],S31,0xD9D4D039);d=hh(d,a,b,c,x[k+12],S32,0xE6DB99E5);c=hh(c,d,a,b,x[k+15],S33,0x1FA27CF8);b=hh(b,c,d,a,x[k+2],S34,0xC4AC5665);a=ii(a,b,c,d,x[k+0],S41,0xF4292244);d=ii(d,a,b,c,x[k+7],S42,0x432AFF97);c=ii(c,d,a,b,x[k+14],S43,0xAB9423A7);b=ii(b,c,d,a,x[k+5],S44,0xFC93A039);a=ii(a,b,c,d,x[k+12],S41,0x655B59C3);d=ii(d,a,b,c,x[k+3],S42,0x8F0CCC92);c=ii(c,d,a,b,x[k+10],S43,0xFFEFF47D);b=ii(b,c,d,a,x[k+1],S44,0x85845DD1);a=ii(a,b,c,d,x[k+8],S41,0x6FA87E4F);d=ii(d,a,b,c,x[k+15],S42,0xFE2CE6E0);c=ii(c,d,a,b,x[k+6],S43,0xA3014314);b=ii(b,c,d,a,x[k+13],S44,0x4E0811A1);a=ii(a,b,c,d,x[k+4],S41,0xF7537E82);d=ii(d,a,b,c,x[k+11],S42,0xBD3AF235);c=ii(c,d,a,b,x[k+2],S43,0x2AD7D2BB);b=ii(b,c,d,a,x[k+9],S44,0xEB86D391);a=au(a,AA);b=au(b,BB);c=au(c,CC);d=au(d,DD);}return pgWordToHex(a)+pgWordToHex(b)+pgWordToHex(c)+pgWordToHex(d);}
function pgWordToHex(lValue){var WordToHexValue='',WordToHexValue_temp='',lByte,lCount;for(lCount=0;lCount<=3;lCount++){lByte=(lValue>>>(lCount*8))&255;WordToHexValue_temp='0'+lByte.toString(16);WordToHexValue=WordToHexValue+WordToHexValue_temp.substr(WordToHexValue_temp.length-2,2);}return WordToHexValue;}

(function() {
    var host = document.getElementById('articleShadowHost');
    var tmpl = document.getElementById('articleContent');
    if (!host || !tmpl) return;
    var shadow = host.attachShadow({mode: 'open'});
    var style = document.createElement('style');
    style.textContent = ':host{display:block;font-size:<?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>px;color:#333;line-height:1.8;font-family:inherit;padding:0 8px !important;}' +
        'p{margin-bottom:12px;}' +
        'img{max-width:100%;border-radius:12px;margin:8px 0;height:auto;}' +
        'a{color:<?php echo htmlspecialchars($nickname_color); ?>;text-decoration:none !important;}' +
        'a:hover{text-decoration:none !important;}' +
        '@import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap");' +
        ':host{--text-primary:#1c1917;--text-secondary:#57534e;--text-muted:#a8a29e;--accent:#c2410c;--border:#e7e5e4;}' +
        ':host h1,:host h2,:host h3,:host h4,:host h5,:host h6{letter-spacing:0;}' +
        ':host h1{font-size:clamp(1.5rem,2.6vw,2rem);font-weight:800;line-height:1.2;letter-spacing:-0.02em;color:var(--text-primary);position:relative;display:block;margin:1em 0 0.4em;}' +
        ':host h1 .eyebrow{display:block;font-size:0.5rem;font-weight:600;letter-spacing:0.25em;text-transform:uppercase;color:var(--accent);margin-bottom:10px;}' +
        ':host h1::after{content:"";display:block;width:32px;height:2px;background:var(--text-primary);margin-top:10px;transition:width 0.5s cubic-bezier(0.16,1,0.3,1);}' +
        ':host h1:hover::after{width:64px;}' +
        ':host h2{font-size:clamp(1.4rem,2.5vw,1.9rem);font-weight:700;line-height:1.2;letter-spacing:-0.01em;color:var(--text-primary);position:relative;display:inline-block;isolation:isolate;margin:1em 0 0.5em;}' +
        ':host h2::after{content:"";position:absolute;bottom:2px;left:0;width:100%;height:8px;background:#fde68a;z-index:-1;opacity:0.5;transition:all 0.3s ease;}' +
        ':host h2:hover::after{height:12px;opacity:0.7;background:#fcd34d;}' +
        ':host h3{font-size:clamp(1.1rem,1.8vw,1.35rem);font-weight:700;line-height:1.3;color:var(--text-primary);padding-left:22px;position:relative;transition:padding-left 0.4s cubic-bezier(0.16,1,0.3,1);display:block;margin:1em 0 0.5em;}' +
        ':host h3::before,:host h3::after{content:"";position:absolute;left:0;top:0.12em;bottom:0.12em;width:3px;border-radius:2px;background:var(--border);transition:all 0.4s cubic-bezier(0.16,1,0.3,1);}' +
        ':host h3::after{left:7px;opacity:0.5;}' +
        ':host h3:hover{padding-left:26px;}' +
        ':host h3:hover::before,:host h3:hover::after{background:var(--accent);}' +
        ':host h3:hover::after{opacity:0.2;}' +
        ':host h4{font-size:0.92rem;font-weight:600;letter-spacing:0.05em;color:var(--text-primary);display:inline-block;position:relative;padding-top:12px;margin:1.1em 0 0.4em;}' +
        ':host h4::before{content:"";position:absolute;top:-1px;left:0;width:5px;height:5px;border-radius:50%;background:var(--border);transition:all 0.4s cubic-bezier(0.16,1,0.3,1);z-index:1;}' +
        ':host h4::after{content:"";position:absolute;top:1px;left:0;width:24px;height:1px;background:var(--border);transition:all 0.5s cubic-bezier(0.16,1,0.3,1);}' +
        ':host h4:hover::before{background:var(--accent);transform:scale(1.6);}' +
        ':host h4:hover::after{width:100%;background:var(--accent);}' +
        ':host h5{font-size:0.78rem;font-weight:600;letter-spacing:0.15em;text-transform:uppercase;color:var(--text-primary);display:inline-flex;align-items:center;gap:12px;position:relative;margin:0.8em 0 0.4em;}' +
        ':host h5::before,:host h5::after{content:"";width:4px;height:4px;border-radius:50%;background:var(--border);transition:all 0.45s cubic-bezier(0.16,1,0.3,1);}' +
        ':host h5:hover::before,:host h5:hover::after{background:var(--accent);width:20px;border-radius:2px;height:2px;}' +
        ':host h6{font-family:Inter,sans-serif;font-size:0.75rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-primary);display:inline-flex;align-items:center;gap:10px;margin:0.8em 0 0.4em;transition:color 0.3s;}' +
        ':host h6::before,:host h6::after{content:"";display:block;width:16px;height:2px;background:var(--border);transition:background 0.3s;}' +
        ':host h6:hover{color:var(--accent);}' +
        ':host h6:hover::before,:host h6:hover::after{background:var(--accent);}' +
        'ul.list3{list-style:none;padding-left:60px;margin:2px 0;}' +
        'ul.list3 li{margin:2px 0;position:relative;}' +
        'ul.list3 li::before{content:"";width:10px;height:2px;background:#888;position:absolute;left:-20px;top:50%;transform:translateY(-50%);}' +
        'ol.ol1{list-style:none;padding-left:60px;margin:2px 0;counter-reset:num;}' +
        'ol.ol1 li{margin:2px 0;position:relative;counter-increment:num;}' +
        'ol.ol1 li::before{content:counter(num);width:18px;height:18px;background:#888;color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;line-height:1;position:absolute;left:-24px;top:50%;transform:translateY(-50%);}' +
        'ul:not(.list3),ol:not(.ol1){margin:8px 0 8px 20px;}' +
        'li{margin:4px 0;}' +
        'blockquote{border-left:3px solid #5d6d9e;background:#f0f3fa;border-radius:0 10px 10px 0;padding:8px 16px 8px 24px;margin:10px 0;color:#4a5568;font-size:14px;line-height:1.7;position:relative;}' +
                'blockquote::before{content:\'\\0022\';position:absolute;left:8px;top:4px;font-size:22px;color:#5d6d9e;opacity:0.25;line-height:1;font-family:Georgia,serif;}' +
                'blockquote p{margin:0;}' +
        'code{background:#fff0f0;color:#e11d48;padding:2px 5px;border-radius:3px;font-family:inherit;font-size:0.85em;line-height:1.4;border:1px solid #fecdd3;vertical-align:baseline;position:relative;top:-1px;}' +
        'pre{background:#f8f8f8;padding:12px;border-radius:6px;overflow-x:auto;margin:10px 0;}' +
        'pre code{padding:0;background:none;color:inherit;display:inline;font-size:inherit;line-height:inherit;}' +
        'table{width:100%;border-collapse:collapse;margin:10px 0;font-size:13px;}' +
        'th,td{border:1px solid #e0e0e0;padding:8px;text-align:center;}' +
        'th{font-weight:550;background:#f5f5f5;}' +
        'iframe{max-width:100%;}' +
        'video{max-width:100%;height:auto;}' +
        'kbd{display:inline-block;padding:3px 8px;font-family:inherit;font-size:0.85em;line-height:1.4;color:#333;background:linear-gradient(to bottom,#f7f7f7,#eaeaea);border:1px solid #bbb;border-radius:5px;box-shadow:0 1px 2px rgba(0,0,0,0.15),inset 0 1px 0 rgba(255,255,255,0.8);white-space:nowrap;margin:0 1px;vertical-align:middle;}' +
        'details{border:1px solid #eee;border-radius:10px;margin:12px 0;overflow:hidden;background:#fff;}' +
        'details summary{padding:12px 16px;background:#f9fafb;cursor:pointer;font-weight:500;font-size:14px;color:#222;display:flex;justify-content:space-between;align-items:center;transition:background .2s;list-style:none;}' +
        'details summary:hover{background:#f3f4f6;}' +
        'details summary::-webkit-details-marker{display:none;}' +
        'details summary::after{content:"\\25BC";font-size:10px;color:#999;transition:transform .3s;}' +
        'details[open] summary::after{transform:rotate(180deg);}' +
        'details>*:not(summary){padding:10px 14px;margin:0;color:#555;line-height:1.7;font-size:13px;}';
    var gFontSize = '<?php echo htmlspecialchars($settings['global_font_size'] ?? '13'); ?>';
    var gFontWeight = '<?php echo htmlspecialchars($settings['global_font_weight'] ?? '500'); ?>';
    var codeFontSize = Math.max(11, parseInt(gFontSize) - 1) + 'px';
    var hlStyle = document.createElement('style');
    hlStyle.textContent = '.mac-code-block{margin:8px 0;border-radius:6px;overflow:hidden;background:#fafafa;border:0.5px solid #e0e0e0;box-shadow:0 1px 4px rgba(0,0,0,.04);font-size:'+codeFontSize+';line-height:1.6;}' +
        '.mac-code-header{display:flex;align-items:center;justify-content:space-between;padding:3px 10px;background:linear-gradient(to bottom,#f5f5f5,#eee);border-bottom:0.5px solid #ddd;}' +
        '.mac-code-header-left{display:flex;align-items:center;gap:5px;}' +
        '.mac-dot{width:8px;height:8px;border-radius:50%;box-shadow:inset 0 0 0 0.5px rgba(0,0,0,.1);}' +
        '.mac-dot-red{background:#ff5f57;}.mac-dot-yellow{background:#febc2e;}.mac-dot-green{background:#28c840;}' +
        '.mac-code-title{font-size:'+codeFontSize+';color:#aaa;margin-left:4px;font-family:inherit;}' +
        '.mac-code-copy{padding:2px 8px;font-size:'+codeFontSize+';color:#999;background:rgba(255,255,255,0.6);border:0.5px solid #ccc;border-radius:3px;cursor:pointer;transition:all .15s;font-family:inherit;}' +
        '.mac-code-copy:hover{background:#fff;border-color:#bbb;color:#666;}' +
        '.mac-code-copy.copied{color:#28c840;border-color:#28c840;background:rgba(40,200,64,0.08);}' +
        '.mac-code-body{display:flex;overflow-x:auto;background:#fafafa;}' +
        '.mac-line-numbers{flex-shrink:0;padding:6px 2px;background:#f5f5f5;border-right:0.5px solid #e8e8e8;text-align:center;min-width:24px;}' +
        '.mac-line-num{display:block;padding:0 2px;font-size:'+codeFontSize+';line-height:1.6;color:#ccc;font-family:inherit;text-align:center;}' +
        '.mac-code-content{flex:1;padding:6px 10px;overflow-x:auto;white-space:pre;word-wrap:normal;tab-size:4;}' +
        '.mac-code-content code{font-family:inherit !important;font-size:inherit !important;background:none !important;padding:0 !important;border-radius:0 !important;border:none !important;border-width:0 !important;border-style:none !important;border-color:transparent !important;outline:none !important;box-shadow:none !important;color:inherit !important;line-height:inherit !important;vertical-align:baseline !important;top:auto !important;position:static !important;}' +
        '.mac-inline-code{font-family:inherit;font-size:0.92em;background:#eff1f5;color:#d6336c;padding:0.18em 0.55em;border-radius:6px;border:none;vertical-align:baseline;}' +
        '.gc-s{color:#008000;}.gc-k{color:#006699;font-weight:'+gFontWeight+';}.gc-c{color:#999988;font-style:italic;}' +
        '.gc-t{color:#445588;font-weight:'+gFontWeight+';}.gc-l{color:#009999;}.gc-p{color:#666;}' +
        '.gc-f{color:#990000;font-weight:'+gFontWeight+';}.gc-v{color:#336699;}.gc-g{color:#000080;}' +
        '.gc-a{color:#008080;}.gc-r{color:#d14;}.gc-d{color:#999988;}.gc-n{color:#333;}' +
        'code:not(.mac-code-content code):not(.mac-inline-code){background:#f4f4f4;padding:2px 6px;border-radius:3px;font-size:'+codeFontSize+';}' +
        'pre:not(.mac-code-block pre){background:#f8f8f8;padding:12px;border-radius:6px;overflow-x:auto;margin:10px 0;}pre code{padding:0;background:none;}' +
        '.sc-btn{display:inline-block;padding:6px 20px;border-radius:20px;border:1px solid #ddd;background:#f9f9f9;color:#555;font-size:13px;text-decoration:none !important;cursor:pointer;transition:all 0.2s ease;line-height:1.5;margin:2px;user-select:none;box-shadow:0 1px 2px rgba(0,0,0,0.04);}' +
        '.sc-btn:hover{background:#f0f0f0;border-color:#ccc;color:#333 !important;transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,0.08);text-decoration:none !important;}' +
        '.sc-btn-primary{background:#5d6d9e;border-color:#5d6d9e;color:#fff;}' +
        '.sc-btn-primary:hover{background:#4a5a8a;border-color:#4a5a8a;color:#fff !important;}' +
        '.sc-btn-success{background:#67c23a;border-color:#67c23a;color:#fff;}' +
        '.sc-btn-success:hover{background:#5daf34;border-color:#5daf34;color:#fff;}' +
        '.sc-btn-warning{background:#e6a23c;border-color:#e6a23c;color:#fff;}' +
        '.sc-btn-warning:hover{background:#d4922b;border-color:#d4922b;color:#fff;}' +
        '.sc-btn-error{background:#f56c6c;border-color:#f56c6c;color:#fff;}' +
        '.sc-btn-error:hover{background:#e05050;border-color:#e05050;color:#fff;}' +
        '.sc-hint{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:8px;margin:16px 0;font-size:14px;line-height:1.6;box-shadow:0 1px 3px rgba(0,0,0,0.04);}' +
        '.sc-hint-icon{flex-shrink:0;width:20px;height:20px;}' +
        '.sc-hint-icon svg{width:100%;height:100%;}' +
        '.sc-hint-body{flex:1;min-width:0;}' +
        '.sc-hint-title{font-weight:600;font-size:14px;line-height:20px;}' +
        '.sc-hint-content{font-size:14px;line-height:1.6;}' +
        '.sc-hint-info{background:#f0f5ff;border-left:4px solid #2684ff;color:#2c5aa0;}' +
        '.sc-hint-info .sc-hint-icon{color:#2684ff;}.sc-hint-info .sc-hint-title{color:#2c5aa0;}' +
        '.sc-hint-warning{background:#fffbe6;border-left:4px solid #faad14;color:#946c20;}' +
        '.sc-hint-warning .sc-hint-icon{color:#faad14;}.sc-hint-warning .sc-hint-title{color:#946c20;}' +
        '.sc-hint-error{background:#fff1f0;border-left:4px solid #f5222d;color:#b23a3a;}' +
        '.sc-hint-error .sc-hint-icon{color:#f5222d;}.sc-hint-error .sc-hint-title{color:#b23a3a;}' +
        '.sc-hint-success{background:#f0f9eb;border-left:4px solid #52c41a;color:#4a8a2a;}' +
        '.sc-hint-success .sc-hint-icon{color:#52c41a;}.sc-hint-success .sc-hint-title{color:#4a8a2a;}' +
        '.sc-tabs{margin:12px 0;border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.04);}' +
        '.sc-tabs-nav{display:flex;background:#fafafa;overflow-x:auto;-webkit-overflow-scrolling:touch;}' +
        '.sc-tab-trigger{padding:10px 16px;border:none;background:none;color:#666;font-size:14px;cursor:pointer;white-space:nowrap;border-bottom:2px solid transparent;transition:color 0.2s ease,border-color 0.2s ease,background 0.2s ease;font-family:inherit;position:relative;}' +
        '.sc-tab-trigger:hover{color:#333;background:#f0f0f0;}' +
        '.sc-tab-trigger.active{color:#5d6d9e;border-bottom-color:#5d6d9e;font-weight:500;background:#fff;}' +
        '.sc-tabs-content{padding:0;background:#fff;}' +
        '.sc-tab-pane{animation:scTabFadeIn 0.25s ease;display:none;font-size:14px;line-height:1.7;padding:14px 16px;}' +
        '.sc-tab-pane.active{display:block;}' +
        '.sc-tab-pane>:first-child{margin-top:0;}' +
        '.sc-tab-pane>:last-child{margin-bottom:0;}' +
        '.sc-collapse{margin:12px 0;border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;}' +
        '.sc-collapse-header{display:flex;align-items:center;justify-content:space-between;width:100%;padding:12px 16px;border:none;background:#fafafa;color:#555;font-size:14px;cursor:pointer;font-family:inherit;transition:background 0.2s ease;-webkit-tap-highlight-color:transparent;outline:none;user-select:none;-webkit-touch-callout:none;}' +
        '.sc-collapse-header:hover{background:#f0f0f0;}' +
        '.sc-collapse-title{font-weight:500;}' +
        '.sc-collapse-arrow{width:16px;height:16px;transition:transform 0.3s ease;flex-shrink:0;}' +
        '.sc-collapse.open .sc-collapse-arrow{transform:rotate(180deg);}' +
        '.sc-collapse-body{display:none;padding:14px 16px;background:#fff;font-size:14px;line-height:1.7;border-top:1px solid #e8e8e8;}' +
        '.sc-collapse.open .sc-collapse-body{display:block;}' +
        '.sc-collapse-body>*{margin-top:0!important;margin-bottom:0!important;}' +
        '.sc-file{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid #ddd;border-radius:8px;background:#fafafa;color:#333;text-decoration:none;font-size:14px;margin:6px 0;transition:all 0.2s ease;max-width:100%;}' +
        '.sc-file:hover{background:#f0f0f0;border-color:#ccc;}' +
        '.sc-file-icon{width:20px;height:20px;flex-shrink:0;}' +
        '.sc-file-name{word-break:break-all;}' +
        '.sc-github{margin:8px 0;border:1px solid #e8e8e8;border-radius:8px;overflow:hidden;background:#fafafa;font-size:12px;}' +
        '.sc-github-header{display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid #f0f0f0;}' +
        '.sc-github-logo{width:18px;height:18px;flex-shrink:0;fill:#888;}' +
        '.sc-github-name{font-size:14px;font-weight:600;color:#5d6d9e;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}' +
        '.sc-github-stats{display:flex;align-items:center;gap:10px;flex-shrink:0;font-size:11px;color:#666;}' +
        '.sc-github-stars,.sc-github-forks{display:inline-flex;align-items:center;gap:3px;}' +
        '.sc-github-stars svg{width:12px;height:12px;fill:#e3b341;}' +
        '.sc-github-forks svg{width:12px;height:12px;fill:#888;}' +
        '.sc-github-body{padding:8px 12px;}' +
        '.sc-github-desc{font-size:12px;color:#555;line-height:1.5;margin:0;}' +
        '.sc-github-link{font-size:12px;color:#5d6d9e;text-decoration:none;}' +
        '.sc-github-footer{display:flex;justify-content:space-between;align-items:center;padding:6px 12px;background:#f5f5f5;border-top:1px solid #f0f0f0;font-size:11px;color:#888;}' +
        '.sc-github-download{display:inline-flex;align-items:center;gap:3px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#555;padding:2px 8px;font-size:11px;text-decoration:none;cursor:pointer;white-space:nowrap;flex-shrink:0;}' +
        '.sc-bilibili{position:relative;width:100%;max-width:100%;padding-top:56.25%;margin:18px 0;border-radius:14px;overflow:hidden;background:#000;box-shadow:0 2px 8px rgba(0,0,0,0.1);}' +
        '.sc-bilibili iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;}' +
        '.sc-linkcard-wrap{margin:10px 0;}' +
        '.sc-linkcard-wrap .sc-linkcard,.sc-linkcard{display:flex;align-items:center;gap:14px;padding:16px 20px;background:#f5f5f5;border-radius:10px;text-decoration:none !important;color:inherit;transition:all 0.25s ease;border:1px solid transparent;width:100%;box-sizing:border-box;}' +
        '.sc-linkcard-wrap .sc-linkcard{margin:0;}' +
        '.sc-linkcard{margin:10px 0;}' +
        '.sc-linkcard-wrap .sc-linkcard:hover,.sc-linkcard:hover{background:#fff;border-color:#e8e8e8;box-shadow:0 2px 8px rgba(0,0,0,0.04);}' +
        '.sc-linkcard-icon{width:40px;height:40px;border-radius:10px;background:#e8e8e8;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}' +
        '.sc-linkcard-icon img{width:100%;height:100%;object-fit:cover;}' +
        '.sc-linkcard-icon svg{width:20px;height:20px;color:#888;fill:currentColor;}' +
        '.sc-linkcard-body{flex:1;min-width:0;}' +
        '.sc-linkcard-title{font-size:0.95rem;color:#222;font-weight:500;letter-spacing:0.2px;line-height:1.4;}' +
        '.sc-linkcard-desc{font-size:0.82rem;color:#999;margin-top:3px;line-height:1.5;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}' +
        '.sc-linkcard-arrow{color:#ccc;font-size:0.85rem;transition:color 0.2s;flex-shrink:0;}' +
        '.sc-linkcard-wrap .sc-linkcard:hover .sc-linkcard-arrow,.sc-linkcard:hover .sc-linkcard-arrow{color:#999;}' +
        '@media (max-width:480px){.sc-linkcard-wrap .sc-linkcard,.sc-linkcard{padding:12px 14px;gap:12px;}.sc-linkcard-icon{width:36px;height:36px;border-radius:8px;}.sc-linkcard-icon svg{width:18px;height:18px;}.sc-linkcard-title{font-size:0.9rem;}.sc-linkcard-desc{font-size:0.78rem;}}' +
        'ul.ul-square,ol.ol-gray-bg{list-style:none;margin:12px 0;}' +
        'ul.ul-square{padding-left:0;}' +
        'ol.ol-gray-bg{padding-left:0;counter-reset:num;}' +
        'ul.ul-square li,ol.ol-gray-bg li{list-style:none;position:relative;}' +
        'ul.ul-square li{margin:6px 0;padding-left:18px;line-height:18px;}' +
        'ul.ul-square li::before{content:"■";color:#666;font-size:9px;position:absolute;left:0;top:5px;line-height:1;}' +
        'ol.ol-gray-bg li{margin:8px 0;padding-left:24px;line-height:18px;}' +
        'ol.ol-gray-bg li::before{counter-increment:num;content:counter(num);position:absolute;left:0;top:0;background:#eeeeee;width:18px;height:18px;border-radius:99px;display:grid;place-items:center;font-size:10px;line-height:1;color:#555;}' +
        '.sc-ctag{display:inline-block;padding:2px 7px;border-radius:4px;color:#fff;font-size:12px;margin:0 3px;line-height:1.4;vertical-align:middle;}' +
        '.sc-graybox{background:#f5f5f5;padding:14px 18px;border-radius:7px;font-size:14px;color:#444;line-height:1.8;margin:16px 0;}' +
        '.sc-graybox p{margin:0 0 8px;}.sc-graybox p:last-child{margin-bottom:0;}' +
        '.sc-mark{background:#fff3a3;color:#333;padding:1px 4px;border-radius:3px;}' +
        '.sc-figure{margin:16px 0;text-align:center;}' +
        '.sc-figure img{max-width:100%;border-radius:12px;display:inline-block;}' +
        '.sc-figure figcaption{margin-top:4px;font-size:13px;color:#888;}' +
        '.sc-timeline{margin:16px 0;}' +
        '.sc-timeline ul.sc-timeline-list{list-style:none;margin:0;padding-left:20px;border-left:2px solid #e6e8ef;}' +
        '.sc-timeline li{position:relative;padding:8px 0;line-height:1.7;}' +
        '.sc-timeline li::before{content:"";position:absolute;left:-25px;top:14px;width:9px;height:9px;border-radius:50%;background:#5d6d9e;border:2px solid #fff;box-shadow:0 0 0 1px #e6e8ef;}' +
        '@keyframes scTabFadeIn{from{opacity:0;transform:translateY(4px);}to{opacity:1;transform:translateY(0);}}';
    shadow.appendChild(hlStyle);
    shadow.appendChild(style);
    shadow.appendChild(tmpl.content.cloneNode(true));
    function trimTail(node) {
        var VOID_TAGS = ['IMG','BR','HR','INPUT','META','LINK','AREA','BASE','COL','EMBED','PARAM','SOURCE','TRACK','WBR','IFRAME'];
        var children = node.childNodes;
        while (children.length > 0) {
            var last = children[children.length - 1];
            if (last.nodeType === 3 && !last.textContent.trim()) {
                last.remove();
            } else if (last.nodeType === 1) {
                trimTail(last);
                if (!last.textContent.trim() && last.children.length === 0 && VOID_TAGS.indexOf(last.tagName) === -1) {
                    last.remove();
                } else {
                    break;
                }
            } else {
                break;
            }
        }
    }
    trimTail(shadow);
    (function initShadowHL(){
        var KEYWORDS = {
            php: 'abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|die|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|eval|exit|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|__halt_compiler|true|false|null|self|parent|readonly|enum',
            javascript: 'break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|false|finally|for|function|if|import|in|instanceof|let|new|null|of|return|static|super|switch|this|throw|true|try|typeof|undefined|var|void|while|with|yield|async|await|class|const|let|var|function|return|if|else|for|while|do|switch|case|break|continue|new|this|try|catch|import|export|from|default|extends|yield',
            python: 'and|as|assert|async|await|break|class|continue|def|del|elif|else|except|false|finally|for|from|global|if|import|in|is|lambda|none|nonlocal|not|or|pass|raise|return|true|try|while|with|yield',
            sql: 'add|all|alter|and|as|asc|between|by|case|check|column|commit|constraint|create|cross|database|default|delete|desc|distinct|drop|else|end|escape|exists|foreign|from|full|grant|group|having|if|in|index|inner|insert|intersect|into|is|join|key|left|like|limit|not|null|offset|on|or|order|outer|primary|references|right|rollback|select|set|table|then|to|union|unique|update|using|values|view|when|where|with',
            java: 'abstract|assert|boolean|break|byte|case|catch|char|class|const|continue|default|do|double|else|enum|extends|false|final|finally|float|for|goto|if|implements|import|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|strictfp|super|switch|synchronized|this|throw|throws|transient|true|try|void|volatile|while',
            html: 'a|abbr|address|area|article|aside|audio|b|base|bdi|bdo|blockquote|body|br|button|canvas|caption|cite|code|col|colgroup|data|datalist|dd|del|details|dfn|dialog|div|dl|dt|em|embed|fieldset|figcaption|figure|footer|form|h1|h2|h3|h4|h5|h6|head|header|hr|html|i|iframe|img|input|ins|kbd|label|legend|li|link|main|map|mark|math|menu|menuitem|meta|meter|nav|noscript|object|ol|optgroup|option|output|p|param|picture|pre|progress|q|rb|rp|rt|rtc|ruby|s|samp|script|section|select|slot|small|source|span|strong|style|sub|summary|sup|svg|table|tbody|td|template|textarea|tfoot|th|thead|time|title|tr|track|u|ul|var|video|wbr'
        };

        function detectLang(code) {
            if (/\$[a-zA-Z_]/.test(code)) return 'php';
            if (/\b(import\s+|export\s+|const\s+|let\s+|var\s+|function\s*\(|=>|console\.|document\.|window\.)/.test(code)) return 'javascript';
            if (/^\s*\{/.test(code) && /"[^"]+":\s*"/.test(code)) return 'json';
            if (/\b(def\s+|class\s+.*:|import\s+[a-zA-Z_]|print\s*\(|__\w+__)/.test(code)) return 'python';
            if (/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|FROM|WHERE|JOIN|TABLE)\b/i.test(code)) return 'sql';
            if (/\b(public\s+|private\s+|static\s+|void\s+|System\.|String\s)/.test(code)) return 'java';
            if (/<!DOCTYPE\s+html/i.test(code) || (/<[a-zA-Z][^>]*>/).test(code) && (/<\/[a-zA-Z]+>/).test(code)) return 'html';
            return '';
        }

        function escHtml(t) {
            return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function highlightAll(code, lang) {
            var kwStr = KEYWORDS[lang] || '';
            var kwRe = kwStr ? new RegExp('^(?:' + kwStr + ')$', 'i') : null;
            var result = '';
            var i = 0;
            while (i < code.length) {
                var ch = code[i];
                if ((ch === '/' && code[i+1] === '/') || (ch === '#' && lang !== 'html')) {
                    var end = code.indexOf('\n', i);
                    if (end === -1) end = code.length;
                    result += '<span class="gc-c">' + escHtml(code.substring(i, end)) + '</span>';
                    i = end;
                    continue;
                }
                if (ch === '/' && code[i+1] === '*') {
                    var end = code.indexOf('*/', i+2);
                    if (end === -1) end = code.length; else end += 2;
                    result += '<span class="gc-c">' + escHtml(code.substring(i, end)) + '</span>';
                    i = end;
                    continue;
                }
                if (ch === '"' || ch === "'") {
                    var quote = ch;
                    var j = i + 1;
                    while (j < code.length && code[j] !== quote) {
                        if (code[j] === '\\') j++;
                        j++;
                    }
                    if (j < code.length) j++;
                    result += '<span class="gc-s">' + escHtml(code.substring(i, j)) + '</span>';
                    i = j;
                    continue;
                }
                if (ch === '`') {
                    var j = i + 1;
                    while (j < code.length && code[j] !== '`') {
                        if (code[j] === '\\') j++;
                        j++;
                    }
                    if (j < code.length) j++;
                    result += '<span class="gc-s">' + escHtml(code.substring(i, j)) + '</span>';
                    i = j;
                    continue;
                }

                if (/\d/.test(ch)) {
                    var j = i;
                    while (j < code.length && /[\d.]/.test(code[j])) j++;

                    result += '<span class="gc-l">' + escHtml(code.substring(i, j)) + '</span>';
                    i = j;
                    continue;
                }
                if (/[a-zA-Z_]/.test(ch)) {
                    var j = i;
                    while (j < code.length && /[a-zA-Z0-9_]/.test(code[j])) j++;
                    var word = code.substring(i, j);
                    if (kwRe && kwRe.test(word)) {
                        result += '<span class="gc-k">' + word + '</span>';
                    } else if (code[j] === '(') {
                        result += '<span class="gc-f">' + word + '</span>';
                    } else {
                        result += '<span class="gc-n">' + word + '</span>';
                    }
                    i = j;
                    continue;
                }
                if (ch === '$' && /[a-zA-Z_]/.test(code[i+1])) {
                    var j = i + 1;
                    while (j < code.length && /[a-zA-Z0-9_]/.test(code[j])) j++;
                    result += '<span class="gc-v">' + escHtml(code.substring(i, j)) + '</span>';
                    i = j;
                    continue;
                }
                if (ch === '<' && lang === 'html') {
                    var j = code.indexOf('>', i);
                    if (j === -1) j = code.length; else j++;
                    result += '<span class="gc-g">' + escHtml(code.substring(i, j)) + '</span>';
                    i = j;
                    continue;
                }
                result += escHtml(ch);
                i++;
            }
            return result;
        }

        var blocks = shadow.querySelectorAll('pre');
        blocks.forEach(function(pre){
            if(pre.closest('.mac-code-block')) return;
            var codeEl = pre.querySelector('code');
            if(!codeEl) return;
            var rawCode = codeEl.textContent || '';
            if(!rawCode.trim()) return;

            var lang = '';
            codeEl.className.split(/\s+/).forEach(function(cls){
                if(cls.indexOf('language-') === 0) lang = cls.replace('language-','');
            });
            if(!lang) lang = detectLang(rawCode);
            var highlighted = highlightAll(rawCode, lang);
            var lines = rawCode.replace(/\n$/,'').split('\n');
            if(lines.length === 0) lines = [''];
            var hlLines = highlighted.replace(/\n$/,'').split('\n');
            while(hlLines.length < lines.length) hlLines.push('');
            if(hlLines.length > lines.length) hlLines = hlLines.slice(0, lines.length);

            var nums = '';
            for(var i=0; i<lines.length; i++) {
                nums += '<span class="mac-line-num">' + (i+1) + '</span>';
            }

            var title = lang || 'code';
            var mac = document.createElement('div');
            mac.className = 'mac-code-block';
            mac.dataset.code = rawCode;
            mac.innerHTML = '<div class="mac-code-header">' +
                '<div class="mac-code-header-left">' +
                    '<span class="mac-dot mac-dot-red"></span>' +
                    '<span class="mac-dot mac-dot-yellow"></span>' +
                    '<span class="mac-dot mac-dot-green"></span>' +
                    '<span class="mac-code-title">' + title + '</span>' +
                '</div>' +
                '<button class="mac-code-copy">复制</button>' +
            '</div>' +
            '<div class="mac-code-body">' +
                '<div class="mac-line-numbers">' + nums + '</div>' +
                '<div class="mac-code-content"><code data-processed="block">' + hlLines.join('\n') + '</code></div>' +
            '</div>';
            mac.querySelector('.mac-code-copy').addEventListener('click', function(){
                var btn = this, c = mac.dataset.code || '';
                if(navigator.clipboard){
                    navigator.clipboard.writeText(c).then(function(){
                        btn.textContent = '已复制';
                        btn.classList.add('copied');
                        setTimeout(function(){ btn.textContent = '复制'; btn.classList.remove('copied'); }, 2000);
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = c; ta.style.cssText = 'position:fixed;left:-9999px;';
                    document.body.appendChild(ta); ta.select();
                    try { document.execCommand('copy'); btn.textContent = '已复制'; btn.classList.add('copied');
                        setTimeout(function(){ btn.textContent = '复制'; btn.classList.remove('copied'); }, 2000);
                    } catch(e){}
                    document.body.removeChild(ta);
                }
            });

            pre.parentNode.replaceChild(mac, pre);
        });

        var blockCodeSet = new Set();
        shadow.querySelectorAll('.mac-code-block code, .mac-code-content code').forEach(function(el){ blockCodeSet.add(el); });
        shadow.querySelectorAll('code').forEach(function(el){
            if(blockCodeSet.has(el)) return;
            if(el.closest('.mac-code-block')) return;
            if(el.closest('.mac-code-content')) return;
            if(el.closest('pre')) return;
            el.className = 'mac-inline-code';
        });

    })();
    shadow.querySelectorAll('.sc-tabs').forEach(function(tabsContainer){
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
                    if(t.dataset.tabs === tabsId){
                        t.classList.remove('active');
                    }
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
    tmpl.remove();
})();
