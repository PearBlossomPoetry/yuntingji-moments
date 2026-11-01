<?php header('Content-Type: application/javascript'); ?>
<?php require_once __DIR__ . '/../../config.php'; $settings = get_all_settings(); ?>
var viewerImages=[],viewerIndex=0,currentPage=1,isLoading=false,hasMore=(typeof _INIT!=='undefined'&&_INIT.hasMore)?_INIT.hasMore:false;
var currentMcCard=null,currentMcBtn=null;
var currentPostId=0,currentParentId=0,modalAvatarUrl='';
var currentBarId=null;

const STICKER_LIST=<?php echo json_encode(get_sticker_names(), JSON_UNESCAPED_UNICODE); ?>;
const STICKER_HEO_BASE='assets/stickers2/';
const STICKER_LIST_KAWA=<?php echo json_encode(get_sticker_names_kawa(), JSON_UNESCAPED_UNICODE); ?>;
const STICKER_KAWA_BASE='assets/stickers/';
const LIKE_PATH='M15.53 7.122c-2.979-4.166-9.174-4.162-12.146.009a7.46 7.46 0 0 0 .609 9.409l11.275 12.14a1 1 0 0 0 1.464.001L28 16.583a7.48 7.48 0 0 0 .584-9.485c-3.01-4.155-9.216-4.114-12.171.081l-.417.593z';
const LIKE_VIEWBOX='0 0 32 32';
const CANCEL_PATH='M12 18c0 1 .25 1.92.67 2.74l-.67.61l-1.45-1.32C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5c0 1.43-.5 2.76-1.38 4.11c-.79-.38-1.68-.61-2.62-.61c-3.31 0-6 2.69-6 6m9.54-2.12l-1.42-1.41L18 16.59l-2.12-2.12l-1.41 1.41L16.59 18l-2.12 2.12l1.41 1.42L18 19.41l2.12 2.13l1.42-1.42L19.41 18z';
const CANCEL_VIEWBOX='0 0 24 24';

function ajaxFetch(url,options){return fetch(url,options);}

function toggleTopMenu(ev){if(ev)ev.stopPropagation();var p=document.getElementById('topMenuPanel');var o=document.getElementById('menuOverlay');if(p.classList.contains('show')){closeTopMenu();}else{closeAllBars();closeStickerPanel();p.classList.add('show');o.classList.add('show');}}
function closeTopMenu(){document.getElementById('topMenuPanel').classList.remove('show');document.getElementById('menuOverlay').classList.remove('show');}

function toggleBar(id){var bar=document.getElementById('action-bar-'+id);if(!bar)return;var isShow=bar.classList.contains('show');closeAllBars();if(!isShow){bar.classList.add('show');currentBarId=id;}}
function closeAllBars(){document.querySelectorAll('.action-bar').forEach(function(b){b.classList.remove('show');});currentBarId=null;}

function toggleStickerPanel(){var p=document.getElementById('stickerPanel');if(p.classList.contains('show')){p.classList.remove('show');}else{var g=document.getElementById('stickerGrid');if(!g.dataset.loaded){switchStickerTab('kawa');g.dataset.loaded='1';}p.classList.add('show');}}
function switchStickerTab(tab){var g=document.getElementById('stickerGrid');var h='';if(tab==='heo'){STICKER_LIST.forEach(function(s){h+='<div class="sticker-grid-item" onclick="insertSticker(\''+s+'\',\'heo\')" title="'+s+'"><img src="'+STICKER_HEO_BASE+stickerNameToFilename(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}else{STICKER_LIST_KAWA.forEach(function(s){var name=s.replace('kawa-','');h+='<div class="sticker-grid-item" onclick="insertSticker(\''+name+'\',\'kawa\')" title="'+s+'"><img src="'+STICKER_KAWA_BASE+encodeURIComponent(s)+'.png" alt="'+s+'" loading="lazy"></div>';});}g.innerHTML=h;document.querySelectorAll('.sticker-tab').forEach(function(t){t.classList.toggle('active',t.dataset.tab===tab);});}
function insertSticker(name,type){var ta=document.getElementById('modalContent');ta.focus();var code=type==='kawa'?':kawa-'+name+':':':heo-'+name+':';var start=ta.selectionStart,end=ta.selectionEnd,taVal=ta.value;ta.value=taVal.substring(0,start)+code+taVal.substring(end);ta.selectionStart=ta.selectionEnd=start+code.length;closeStickerPanel();}
function closeStickerPanel(){document.getElementById('stickerPanel').classList.remove('show');}
function parseStickers(text){return text.replace(/:heo-([^:]+?):/g,function(m,name){return '<img src="'+STICKER_HEO_BASE+stickerNameToFilename(name)+'.png" class="heo-sticker" alt="'+name+'">';}).replace(/:kawa-([^:]+?):/g,function(m,name){return '<img src="'+STICKER_KAWA_BASE+encodeURIComponent('kawa-'+name)+'.png" class="heo-sticker" alt="kawa-'+name+'">';});}

function stickerNameToFilename(name){return name.replace(/./g,function(ch){var code=ch.charCodeAt(0);return code>127?'#U'+code.toString(16):ch;}).replace(/#/g,'%23');}

function md5(string){function rotateLeft(lValue,iShiftBits){return(lValue<<iShiftBits)|(lValue>>>(32-iShiftBits));}function addUnsigned(lX,lY){var lX4,lY4,lX8,lY8,lResult;lX8=(lX&0x80000000);lY8=(lY&0x80000000);lX4=(lX&0x40000000);lY4=(lY&0x40000000);lResult=(lX&0x3FFFFFFF)+(lY&0x3FFFFFFF);if(lX4&lY4)return(lResult^0x80000000^lX8^lY8);if(lX4|lY4){if(lResult&0x40000000)return(lResult^0xC0000000^lX8^lY8);else return(lResult^0x40000000^lX8^lY8);}else{return(lResult^lX8^lY8);}}function f(x,y,z){return(x&y)|((~x)&z);}function g(x,y,z){return(x&z)|(y&(~z));}function h(x,y,z){return(x^y^z);}function i(x,y,z){return(y^(x|(~z)));}function ff(a,b,c,d,x,s,ac){a=addUnsigned(a,addUnsigned(addUnsigned(f(b,c,d),x),ac));return addUnsigned(rotateLeft(a,s),b);}function gg(a,b,c,d,x,s,ac){a=addUnsigned(a,addUnsigned(addUnsigned(g(b,c,d),x),ac));return addUnsigned(rotateLeft(a,s),b);}function hh(a,b,c,d,x,s,ac){a=addUnsigned(a,addUnsigned(addUnsigned(h(b,c,d),x),ac));return addUnsigned(rotateLeft(a,s),b);}function ii(a,b,c,d,x,s,ac){a=addUnsigned(a,addUnsigned(addUnsigned(i(b,c,d),x),ac));return addUnsigned(rotateLeft(a,s),b);}function convertToWordArray(string){var lWordCount;var lMessageLength=string.length;var lNumberOfWordsTemp1=lMessageLength+8;var lNumberOfWordsTemp2=(lNumberOfWordsTemp1-(lNumberOfWordsTemp1%64))/64;var lNumberOfWords=(lNumberOfWordsTemp2+1)*16;var lWordArray=new Array(lNumberOfWords-1);var lBytePosition=0;var lByteCount=0;while(lByteCount<lMessageLength){lWordCount=(lByteCount-(lByteCount%4))/4;lBytePosition=(lByteCount%4)*8;lWordArray[lWordCount]=(lWordArray[lWordCount]|(string.charCodeAt(lByteCount)<<lBytePosition));lByteCount++;}lWordCount=(lByteCount-(lByteCount%4))/4;lBytePosition=(lByteCount%4)*8;lWordArray[lWordCount]=lWordArray[lWordCount]|(0x80<<lBytePosition);lWordArray[lNumberOfWords-2]=lMessageLength<<3;lWordArray[lNumberOfWords-1]=lMessageLength>>>29;return lWordArray;}function wordToHex(lValue){var wordToHexValue='',wordToHexValueTemp='',lByte,lCount;for(lCount=0;lCount<=3;lCount++){lByte=(lValue>>>(lCount*8))&255;wordToHexValueTemp='0'+lByte.toString(16);wordToHexValue=wordToHexValue+wordToHexValueTemp.substr(wordToHexValueTemp.length-2,2);}return wordToHexValue;}function utf8Encode(string){string=string.replace(/\r\n/g,'\n');var utftext='';for(var n=0;n<string.length;n++){var c=string.charCodeAt(n);if(c<128){utftext+=String.fromCharCode(c);}else if((c>127)&&(c<2048)){utftext+=String.fromCharCode((c>>6)|192);utftext+=String.fromCharCode((c&63)|128);}else{utftext+=String.fromCharCode((c>>12)|224);utftext+=String.fromCharCode(((c>>6)&63)|128);utftext+=String.fromCharCode((c&63)|128);}}return utftext;}return function(string){string=utf8Encode(string);var x=Array();var k,AA,BB,CC,DD,a,b,c,d;var S11=7,S12=12,S13=17,S14=22;var S21=5,S22=9,S23=14,S24=20;var S31=4,S32=11,S33=16,S34=23;var S34=4,S32=11,S33=16,S34=23;var S41=6,S42=10,S43=15,S44=21;string=utf8Encode(string);x=convertToWordArray(string);a=0x67452301;b=0xEFCDAB89;c=0x98BADCFE;d=0x10325476;for(k=0;k<x.length;k+=16){AA=a;BB=b;CC=c;DD=d;a=ff(a,b,c,d,x[k+0],S11,0xD76AA478);d=ff(d,a,b,c,x[k+1],S12,0xE8C7B756);c=ff(c,d,a,b,x[k+2],S13,0x242070DB);b=ff(b,c,d,a,x[k+3],S14,0xC1BDCEEE);a=ff(a,b,c,d,x[k+4],S11,0xF57C0FAF);d=ff(d,a,b,c,x[k+5],S12,0x4787C62A);c=ff(c,d,a,b,x[k+6],S13,0xA8304613);b=ff(b,c,d,a,x[k+7],S14,0xFD469501);a=ff(a,b,c,d,x[k+8],S11,0x698098D8);d=ff(d,a,b,c,x[k+9],S12,0x8B44F7AF);c=ff(c,d,a,b,x[k+10],S13,0xFFFF5BB1);b=ff(b,c,d,a,x[k+11],S14,0x895CD7BE);a=ff(a,b,c,d,x[k+12],S11,0x6B901122);d=ff(d,a,b,c,x[k+13],S12,0xFD987193);c=ff(c,d,a,b,x[k+14],S13,0xA679438E);b=ff(b,c,d,a,x[k+15],S14,0x49B40821);a=gg(a,b,c,d,x[k+1],S21,0xF61E2562);d=gg(d,a,b,c,x[k+6],S22,0xC040B340);c=gg(c,d,a,b,x[k+11],S23,0x265E5A51);b=gg(b,c,d,a,x[k+0],S24,0xE9B6C7AA);a=gg(a,b,c,d,x[k+5],S21,0xD62F105D);d=gg(d,a,b,c,x[k+10],S22,0x02441453);c=gg(c,d,a,b,x[k+15],S23,0xD8A1E681);b=gg(b,c,d,a,x[k+4],S24,0xE7D3FBC8);a=gg(a,b,c,d,x[k+9],S21,0x21E1CDE6);d=gg(d,a,b,c,x[k+14],S22,0xC33707D6);c=gg(c,d,a,b,x[k+3],S23,0xF4D50D87);b=gg(b,c,d,a,x[k+8],S24,0x455A14ED);a=gg(a,b,c,d,x[k+13],S21,0xA9E3E905);d=gg(d,a,b,c,x[k+2],S22,0xFCEFA3F8);c=gg(c,d,a,b,x[k+7],S23,0x676F02D9);b=gg(b,c,d,a,x[k+12],S24,0x8D2A4C8A);a=hh(a,b,c,d,x[k+5],S31,0xFFFA3942);d=hh(d,a,b,c,x[k+8],S32,0x8771F681);c=hh(c,d,a,b,x[k+11],S33,0x6D9D6122);b=hh(b,c,d,a,x[k+14],S34,0xFDE5380C);a=hh(a,b,c,d,x[k+1],S31,0xA4BEEA44);d=hh(d,a,b,c,x[k+4],S32,0x4BDECFA9);c=hh(c,d,a,b,x[k+7],S33,0xF6BB4B60);b=hh(b,c,d,a,x[k+10],S34,0xBEBFBC70);a=hh(a,b,c,d,x[k+13],S31,0x289B7EC6);d=hh(d,a,b,c,x[k+0],S32,0xEAA127FA);c=hh(c,d,a,b,x[k+3],S33,0xD4EF3085);b=hh(b,c,d,a,x[k+6],S34,0x04881D05);a=hh(a,b,c,d,x[k+9],S31,0xD9D4D039);d=hh(d,a,b,c,x[k+12],S32,0xE6DB99E5);c=hh(c,d,a,b,x[k+15],S33,0x1FA27CF8);b=hh(b,c,d,a,x[k+2],S34,0xC4AC5665);a=ii(a,b,c,d,x[k+0],S41,0xF4292244);d=ii(d,a,b,c,x[k+7],S42,0x432AFF97);c=ii(c,d,a,b,x[k+14],S43,0xAB9423A7);b=ii(b,c,d,a,x[k+5],S44,0xFC93A039);a=ii(a,b,c,d,x[k+12],S41,0x655B59C3);d=ii(d,a,b,c,x[k+3],S42,0x8F0CCC92);c=ii(c,d,a,b,x[k+10],S43,0xFFEFF47D);b=ii(b,c,d,a,x[k+1],S44,0x85845DD1);a=ii(a,b,c,d,x[k+8],S41,0x6FA87E4F);d=ii(d,a,b,c,x[k+15],S42,0xFE2CE6E0);c=ii(c,d,a,b,x[k+6],S43,0xA3014314);b=ii(b,c,d,a,x[k+13],S44,0x4E0811A1);a=ii(a,b,c,d,x[k+4],S41,0xF7537E82);d=ii(d,a,b,c,x[k+11],S42,0xBD3AF235);c=ii(c,d,a,b,x[k+2],S43,0x2AD7D2BB);b=ii(b,c,d,a,x[k+9],S44,0xEB86D391);a=addUnsigned(a,AA);b=addUnsigned(b,BB);c=addUnsigned(c,CC);d=addUnsigned(d,DD);}var tempValue=wordToHex(a)+wordToHex(b)+wordToHex(c)+wordToHex(d);return tempValue.toLowerCase();}}

function openAdLink(card){var ev=window.event;if(ev&&ev.target){if(ev.target.closest('.action-bar')||ev.target.closest('.menu-btn')||ev.target.closest('.ad-actions'))return;}var link=card.dataset.link;if(link&&link!=='#')window.open(link,'_blank');}

function doLike(postId,el){
    if(!el){
        var barId=postId<0?'action-bar-ad-'+(Math.abs(postId)):'action-bar-'+postId;
        el=document.querySelector('#'+barId+' .action-bar-item');
        if(!el)return;
    }
    var textSpan=el.querySelector('.like-text');
    var svgEl=el.querySelector('svg');
    var svgPath=el.querySelector('svg path');
    if(!textSpan||!svgPath||!svgEl)return;
    var isCancel=textSpan.textContent==='取消';
    ajaxFetch('api/like.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'post_id='+postId+'&action='+(isCancel?'cancel':'like')})
    .then(function(r){return r.json();}).then(function(data){
        if(data.code===1){
            textSpan.textContent=isCancel?'赞':'取消';
            svgEl.setAttribute('viewBox',isCancel?LIKE_VIEWBOX:CANCEL_VIEWBOX);
            svgPath.setAttribute('d',isCancel?LIKE_PATH:CANCEL_PATH);
            refreshInteraction(postId);
        }
    });
}

function closeCommentModal(){
    document.getElementById('commentOverlay').classList.remove('show');
    document.getElementById('commentModal').classList.remove('show');
    closeStickerPanel();
    currentPostId=0;currentParentId=0;
    document.querySelectorAll('.action-bar').forEach(function(b){b.style.display='';});
    isCommentSubmitting=false;
    var sendBtn=document.querySelector('.comment-modal-btn');
    if(sendBtn){sendBtn.classList.remove('disabled');sendBtn.textContent='发送';}
}
function openCommentModal(postId){
    try{var savedEmail=localStorage.getItem('comment_email');if(savedEmail)document.getElementById('modalEmail').value=savedEmail;}catch(e){}
    try{var savedNick=localStorage.getItem('comment_nick');if(savedNick)document.getElementById('modalNick').value=savedNick;}catch(e){}
    try{var savedSite=localStorage.getItem('comment_site');if(savedSite)document.getElementById('modalSite').value=savedSite;}catch(e){}
    try{var savedAvatar=localStorage.getItem('comment_avatar');if(savedAvatar){modalAvatarUrl=savedAvatar;var aPrev=document.getElementById('modalPreviewAvatar');if(aPrev){aPrev.src=modalAvatarUrl;aPrev.style.opacity='1';}}}catch(e){}
    closeAllBars();
    document.querySelectorAll('.action-bar').forEach(function(b){b.style.display='none';});
    currentPostId=postId;currentParentId=0;
    document.getElementById('replyHint').classList.remove('show');
    var mc=document.getElementById('modalContent');
    mc.value='';mc.placeholder='说点什么...';mc.disabled=false;mc.readOnly=false;
    mc.style.display='block';mc.style.pointerEvents='auto';
    document.getElementById('commentOverlay').classList.add('show');
    document.getElementById('commentModal').classList.add('show');
    isCommentSubmitting=false;
    var sendBtn=document.querySelector('.comment-modal-btn');
    if(sendBtn){sendBtn.classList.remove('disabled');sendBtn.textContent='发送';}
    initCommentNotify();
    setTimeout(function(){document.getElementById('modalEmail').focus();},100);
}
function replyComment(postId,nickname,commentId){
    closeAllBars();
    document.querySelectorAll('.action-bar').forEach(function(b){b.style.display='none';});
    currentPostId=postId;currentParentId=commentId;
    document.getElementById('replyToName').textContent=nickname;
    document.getElementById('replyHint').classList.add('show');
    var mc=document.getElementById('modalContent');
    mc.value='';mc.placeholder='回复 '+nickname+'...';mc.disabled=false;mc.readOnly=false;
    mc.style.display='block';mc.style.pointerEvents='auto';
    document.getElementById('commentOverlay').classList.add('show');
    document.getElementById('commentModal').classList.add('show');
    isCommentSubmitting=false;
    var sendBtn=document.querySelector('.comment-modal-btn');
    if(sendBtn){sendBtn.classList.remove('disabled');sendBtn.textContent='发送';}
    initCommentNotify();
    setTimeout(function(){mc.focus();},100);
}
function modalFetchAvatar(){
    var email=document.getElementById('modalEmail').value.trim();
    var previewImg=document.getElementById('modalPreviewAvatar');
    if(!email||!email.includes('@')){
        modalAvatarUrl='';
        previewImg.src='https://cravatar.cn/avatar/00000000000000000000000000000000?s=100&d=mp';
        previewImg.style.opacity='1';
        return;
    }
    if(email.endsWith('@qq.com')){
        var qq=email.replace('@qq.com','');
        if(/^\d+$/.test(qq)){
            modalAvatarUrl='https://q2.qlogo.cn/headimg_dl?dst_uin='+qq+'&spec=100';
            previewImg.src=modalAvatarUrl;
            previewImg.style.opacity='1';
            return;
        }
    }
    modalAvatarUrl='https://cravatar.cn/avatar/'+md5(email)+'?s=100&d=mp';
    previewImg.src=modalAvatarUrl;
    previewImg.style.opacity='1';
}
var isCommentSubmitting=false;

var commentNotifyEnabled=localStorage.getItem('comment_notify_enabled')==='1';
function toggleCommentNotify(){
    commentNotifyEnabled=!commentNotifyEnabled;
    localStorage.setItem('comment_notify_enabled',commentNotifyEnabled?'1':'0');
    var btn=document.getElementById('notifyBtn');
    var iconOff=document.getElementById('notifyIconOff');
    var iconOn=document.getElementById('notifyIconOn');
    if(commentNotifyEnabled){btn.classList.add('on');iconOff.style.display='none';iconOn.style.display='block';}
    else{btn.classList.remove('on');iconOff.style.display='block';iconOn.style.display='none';}
}
function initCommentNotify(){
    var iconOff=document.getElementById('notifyIconOff');
    var iconOn=document.getElementById('notifyIconOn');
    var btn=document.getElementById('notifyBtn');
    if(!iconOff||!iconOn||!btn)return;
    if(commentNotifyEnabled){btn.classList.add('on');iconOff.style.display='none';iconOn.style.display='block';}
    else{btn.classList.remove('on');iconOff.style.display='block';iconOn.style.display='none';}
}

function submitModalComment(){
    if(!currentPostId){showToast('错误');return;}
    if(isCommentSubmitting){showToast('已提交评论，耐心等待完成');return;}
    var nick=document.getElementById('modalNick').value.trim();
    var email=document.getElementById('modalEmail').value.trim();
    var site=document.getElementById('modalSite').value.trim();
    var content=document.getElementById('modalContent').value.trim();
    if(!nick||!email||!content){showToast('请填写昵称、邮箱和内容');return;}
    if(!email.includes('@')){showToast('请填写正确的邮箱');return;}
    isCommentSubmitting=true;
    var sendBtn=document.querySelector('.comment-modal-btn');
    if(sendBtn){sendBtn.classList.add('disabled');sendBtn.textContent='发送中...';}
    var body='post_id='+currentPostId+'&nickname='+encodeURIComponent(nick)+'&email='+encodeURIComponent(email)+'&website='+encodeURIComponent(site)+'&content='+encodeURIComponent(content)+'&avatar='+encodeURIComponent(modalAvatarUrl);
    if(currentParentId>0)body+='&parent_id='+currentParentId;
    if(commentNotifyEnabled)body+='&notify=1';
    ajaxFetch('api/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
    .then(function(r){return r.json();}).then(function(data){
        isCommentSubmitting=false;
        if(sendBtn){sendBtn.classList.remove('disabled');sendBtn.textContent='发送';}
        if(data.code===1){
            var savedPostId=currentPostId;
            var savedEmail2=document.getElementById('modalEmail').value.trim();if(savedEmail2)localStorage.setItem('comment_email',savedEmail2);
            var savedNick2=document.getElementById('modalNick').value.trim();if(savedNick2)localStorage.setItem('comment_nick',savedNick2);
            var savedSite2=document.getElementById('modalSite').value.trim();if(savedSite2)localStorage.setItem('comment_site',savedSite2);
            if(modalAvatarUrl)localStorage.setItem('comment_avatar',modalAvatarUrl);
            closeCommentModal();
            if(data.msg&&data.msg.indexOf('审核')!==-1){
                showToast('评论已提交，等待审核');
                document.getElementById('modalNick').value='';
                document.getElementById('modalSite').value='';
                document.getElementById('modalContent').value='';
                modalAvatarUrl='';
            }else{
                showToast('评论成功');
                document.getElementById('modalNick').value='';
                document.getElementById('modalSite').value='';
                document.getElementById('modalContent').value='';
                modalAvatarUrl='';
                refreshInteraction(savedPostId);
            }
        }else{showToast(data.msg||'评论失败');}
    }).catch(function(){isCommentSubmitting=false;if(sendBtn){sendBtn.classList.remove('disabled');sendBtn.textContent='发送';}showToast('评论失败');});
}

function renderJsCommentFlat(c,postId){
    var h=[];
    var Q=String.fromCharCode(39);
    var website=c.website||"";
    var avatar=c.avatar||"";
    var nickname=c.nickname||"";
    var content=c.content||"";
    var parentName=c.parent_nickname||"";
    var parentAvatar=c.parent_avatar||"";
    var parentWebsite=c.parent_website||"";
    var parentId=c.parent_id||0;
    var isReply=!!parentName;
    if(isReply){
        var rpAvatar=avatar?"<img src=\""+esc(avatar)+"\" class=\"reply-avatar\" onerror=\"this.style.background="+Q+"#e0e0e0"+Q+";this.src="+Q+Q+"\">":"<div class=\"reply-avatar\"></div>";
        var rpAvatarLink=website?"<a href=\""+esc(website)+"\" target=\"_blank\" class=\"reply-avatar-link\" onclick=\"event.stopPropagation()\">"+rpAvatar+"</a>":rpAvatar;
        var authorLink=website?"<a href=\""+esc(website)+"\" target=\"_blank\" class=\"reply-author-link\" onclick=\"event.stopPropagation()\"><span class=\"reply-author\">"+esc(nickname)+"</span></a>":"<span class=\"reply-author\">"+esc(nickname)+"</span>";
        var toAvatar=parentAvatar?"<a href=\""+esc(parentWebsite||"")+"\" target=\"_blank\" class=\"reply-to-avatar-link\" onclick=\"event.stopPropagation()\"><img src=\""+esc(parentAvatar)+"\" class=\"reply-to-avatar-inline\" onerror=\"this.style.display="+Q+"none"+Q+"\"></a>":"";
        var toLink="<span class=\"reply-to-name\">"+esc(parentName)+":</span>";
        h.push("<div class=\"reply-item\" onclick=\"replyComment("+postId+","+Q+esc(nickname)+Q+","+c.id+")\">"+rpAvatarLink+authorLink+"<span class=\"reply-label\">回复</span>"+toAvatar+toLink+" <span class=\"reply-content\">"+parseStickers(esc(content))+"</span></div>");
    }else{
        var rpAvatar=avatar?"<img src=\""+esc(avatar)+"\" class=\"reply-avatar\" onerror=\"this.style.background="+Q+"#e0e0e0"+Q+";this.src="+Q+Q+"\">":"<div class=\"reply-avatar\"></div>";
        var rpAvatarLink=website?"<a href=\""+esc(website)+"\" target=\"_blank\" class=\"reply-avatar-link\" onclick=\"event.stopPropagation()\">"+rpAvatar+"</a>":rpAvatar;
        h.push("<div class=\"reply-item\" onclick=\"replyComment("+postId+","+Q+esc(nickname)+Q+","+c.id+")\">"+rpAvatarLink+"<span class=\"reply-author\">"+esc(nickname)+":</span> <span class=\"reply-content\">"+parseStickers(esc(content))+"</span></div>");
    }
    return h.join("");
}

function toggleCommentFold(postId,btn){
    var rest=document.getElementById('comment-rest-'+postId);
    if(!rest||!btn)return;
    var isHidden=rest.style.display==='none';
    if(isHidden){
        rest.style.display='flex';
        btn.textContent='收起评论';
    }else{
        rest.style.display='none';
        btn.textContent='查看全部';
    }
}

function refreshInteraction(postId){
    ajaxFetch('api/get_interaction.php?post_id='+postId).then(function(r){return r.json();}).then(function(d){
        if(d.code!==1)return;
        var isAd=postId<0;
        var absId=Math.abs(postId);
        var areaId=isAd?'interact-ad-'+absId:'interact-'+postId;
        var area=document.getElementById(areaId);
        if(!area){
            area=document.createElement('div');
            area.className='interaction-area';
            area.id=areaId;
            var container;
            if(isAd){
                container=document.querySelector('.ad-item[data-id="'+absId+'"]');
            }else{
                var mi=document.querySelector('.moment-item[data-type="post"][data-id="'+postId+'"]');
                if(mi)container=mi.querySelector('.moment-body')||mi;
            }
            if(container){
                var meta=container.querySelector('.moment-meta, .moment-meta-only');
                if(meta&&meta.nextSibling)container.insertBefore(area,meta.nextSibling);
                else container.appendChild(area);
            }
        }
        var html='';
        if(d.data.likes&&d.data.likes.length>0){
            var displayLikes=d.data.likes.slice(0,5);
            html+='<div class="likes"><svg class="like-icon" viewBox="0 0 32 32" style="width:13px;height:13px;display:block;align-self:center;"><path fill="currentColor" d="'+LIKE_PATH+'"/></svg><span class="like-names">'+displayLikes.join('、');
            if(d.data.like_count>5)html+='、访客+'+(d.data.like_count-5);
            html+='</span></div>';
        }
        function renderJsComments(comments,parentNickname,parentAvatar,parentWebsite,parentId,level){
            var h='';
            comments.forEach(function(c){
                if(level===0){
                    var avatar=c.avatar?'<img src="'+esc(c.avatar)+'" class="comment-avatar-circle" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">':'<div class="comment-avatar-circle"></div>';
                    var avatarLink=c.website?'<a href="'+esc(c.website)+'" target="_blank" class="comment-avatar-link" onclick="event.stopPropagation()">'+avatar+'</a>':avatar;
                    h+='<div class="comment-item" onclick="replyComment('+postId+',\''+esc(c.nickname)+'\','+c.id+')">'+avatarLink+'<span class="comment-text"><span class="author-name">'+esc(c.nickname)+':</span> '+parseStickers(esc(c.content))+'</span></div>';
                }else{
                    var rpAvatar=c.avatar?'<img src="'+esc(c.avatar)+'" class="reply-avatar" onerror="this.style.background=\'#e0e0e0\';this.src=\'\'">':'<div class="reply-avatar"></div>';
                    var rpAvatarLink=c.website?'<a href="'+esc(c.website)+'" target="_blank" class="reply-avatar-link" onclick="event.stopPropagation()">'+rpAvatar+'</a>':rpAvatar;
                    var authorLink=c.website?'<a href="'+esc(c.website)+'" target="_blank" class="reply-author-link" onclick="event.stopPropagation()"><span class="reply-author">'+esc(c.nickname)+'</span></a>':'<span class="reply-author">'+esc(c.nickname)+'</span>';
                    var toAvatar=parentAvatar?'<a href="'+esc(parentWebsite||'')+'" target="_blank" class="reply-to-avatar-link" onclick="event.stopPropagation()"><img src="'+esc(parentAvatar)+'" class="reply-to-avatar-inline" onerror="this.style.display=\'none\'"></a>':'';
                    var toLink=parentWebsite?'<a href="'+esc(parentWebsite)+'" target="_blank" class="reply-to-name-link" onclick="event.stopPropagation()"><span class="reply-to-name">'+esc(parentNickname)+':</span></a>':'<span class="reply-to-name">'+esc(parentNickname)+':</span>';
                    h+='<div class="reply-item" onclick="replyComment('+postId+',\''+esc(c.nickname)+'\','+c.id+')">'+rpAvatarLink+authorLink+'<span class="reply-label\">回复</span>'+toAvatar+toLink+' <span class="reply-content">'+parseStickers(esc(c.content))+'</span></div>';
                }
                if(c.replies&&c.replies.length>0){
                    h+=renderJsComments(c.replies,c.nickname,c.avatar,c.website,c.id,level+1);
                }
            });
            return h;
        }
        if(d.data.comments&&d.data.comments.length>0){
            var totalCommentCnt=d.data.comments.length;
            var cmtLimit=((_INIT&&_INIT.commentLimit)||5);
            cmtLimit=Math.max(1,parseInt(cmtLimit));
            var cmtHasFold=totalCommentCnt>cmtLimit;
            var wasFolded=false;
            var oldRest=document.getElementById('comment-rest-'+postId);
            if(oldRest){wasFolded=oldRest.style.display==='none';}
            var previewItems=d.data.comments.slice(0,cmtLimit);
            var foldItems=d.data.comments.slice(cmtLimit);
            var previewHtml='';
            previewItems.forEach(function(c){previewHtml+=renderJsCommentFlat(c,postId);});
            var foldHtml='';
            foldItems.forEach(function(c){foldHtml+=renderJsCommentFlat(c,postId);});
            html+='<div class="comments">';
            html+='<div class="comment-header-bar">';
            html+='<span class="comment-count-text">共 '+totalCommentCnt+' 条评论</span>';
            if(cmtHasFold){html+='<span class="comment-toggle-btn" data-post-id="'+postId+'">'+(wasFolded?'查看全部':'收起评论')+'</span>';}
            html+='</div>';
            html+='<div class="comment-list-wrap">';
            html+='<div class="comment-preview">'+previewHtml+'</div>';
            if(cmtHasFold){html+='<div class="comment-rest" id="comment-rest-'+postId+'" style="display:'+(wasFolded?'none':'flex')+';">'+foldHtml+'</div>';}
            html+='</div></div>';
        }
        var disabledHtml='';
        var likeEnabled=d.data.like_enabled!==undefined?d.data.like_enabled:true;
        var commentEnabled=d.data.comment_enabled!==undefined?d.data.comment_enabled:true;
        if(!likeEnabled&&!commentEnabled){
            disabledHtml='<div class="interaction-disabled"><span>已关闭点赞</span><span style="margin:0 6px;">·</span><span>已关闭评论</span></div>';
        }else if(!likeEnabled||!commentEnabled){
            disabledHtml='<div class="interaction-disabled">';
            if(!likeEnabled)disabledHtml+='<span>已关闭点赞</span>';
            if(!commentEnabled)disabledHtml+='<span>已关闭评论</span>';
            disabledHtml+='</div>';
        }
        html+=disabledHtml;
        if(html===''&&disabledHtml===''){if(area)area.style.display='none';}else{if(area){area.innerHTML=html;area.style.display='block';}}
    });
}
function esc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}

function openViewer(src,images,el){viewerImages=Array.isArray(images)?images:[src];viewerIndex=viewerImages.indexOf(src);if(viewerIndex<0)viewerIndex=0;updateViewer();document.getElementById('imageViewer').classList.add('show');document.body.style.overflow='hidden';}
function updateViewer(){var img=document.getElementById('viewerImg'),iframe=document.getElementById('viewerIframe'),url=viewerImages[viewerIndex];if(url.match(/\.(mp4|webm|ogg)(\?.*)?$/i)){img.style.display='none';iframe.style.display='block';iframe.src=url;}else{iframe.style.display='none';iframe.src='';img.style.display='block';img.src=url;}document.getElementById('viewerCounter').textContent=(viewerIndex+1)+' / '+viewerImages.length;}
function viewerPrev(e){e&&e.stopPropagation();viewerIndex=(viewerIndex-1+viewerImages.length)%viewerImages.length;updateViewer();}
function viewerNext(e){e&&e.stopPropagation();viewerIndex=(viewerIndex+1)%viewerImages.length;updateViewer();}
function closeViewer(e){if(e.target.id==='imageViewer')closeViewerForce();}
function closeViewerForce(){document.getElementById('imageViewer').classList.remove('show');document.body.style.overflow='';var iframe=document.getElementById('viewerIframe');iframe.src='';iframe.style.display='none';}
document.addEventListener('keydown',function(e){if(!document.getElementById('imageViewer').classList.contains('show'))return;if(e.key==='ArrowLeft')viewerPrev();if(e.key==='ArrowRight')viewerNext();if(e.key==='Escape')closeViewerForce();});
var touchStartX=0;document.getElementById('imageViewer').addEventListener('touchstart',function(e){touchStartX=e.changedTouches[0].screenX;},{passive:true});document.getElementById('imageViewer').addEventListener('touchend',function(e){var dx=e.changedTouches[0].screenX-touchStartX;if(dx>50)viewerPrev();else if(dx<-50)viewerNext();},{passive:true});

var videoList=[],videoIndex=0;
function playVideo(src,videos,index){videoList=videos||[src];videoIndex=index||0;updateVideoPlayer();document.getElementById('videoPlayer').classList.add('show');document.body.style.overflow='hidden';}
function updateVideoPlayer(){var v=document.getElementById('videoEl');v.src=videoList[videoIndex];v.play();document.getElementById('videoCounter').textContent=(videoIndex+1)+' / '+videoList.length;document.getElementById('videoPrev').style.visibility=videoList.length>1?'visible':'hidden';document.getElementById('videoNext').style.visibility=videoList.length>1?'visible':'hidden';}
function videoPrev(e){e&&e.stopPropagation();if(videoList.length<=1)return;videoIndex=(videoIndex-1+videoList.length)%videoList.length;updateVideoPlayer();}
function videoNext(e){e&&e.stopPropagation();if(videoList.length<=1)return;videoIndex=(videoIndex+1)%videoList.length;updateVideoPlayer();}
function closeVideo(e){if(e.target.id==='videoPlayer')closeVideoForce();}
function closeVideoForce(){document.getElementById('videoEl').pause();document.getElementById('videoPlayer').classList.remove('show');document.body.style.overflow='';videoList=[];videoIndex=0;}
document.addEventListener('keydown',function(e){if(!document.getElementById('videoPlayer').classList.contains('show'))return;if(e.key==='ArrowLeft')videoPrev();if(e.key==='ArrowRight')videoNext();if(e.key==='Escape')closeVideoForce();});
function formatTime(s){if(!s)return'00:00';var m=Math.floor(s/60),sec=Math.floor(s%60);return String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');}

function playMcCard(card){
    var url=card.dataset.murl;if(!url)return;
    var mid=card.dataset.mid;
    var btn=document.getElementById('mp-'+mid);
    var audio=document.getElementById('globalAudio');
    if(currentMcCard===card){
        if(audio.paused){audio.play();btn.classList.add('on');}
        else{audio.pause();btn.classList.remove('on');}
        return;
    }
    if(currentMcCard){document.getElementById('mp-'+currentMcCard.dataset.mid).classList.remove('on');}
    audio.src=url;
    audio.play().catch(function(){showToast('播放失败');});
    btn.classList.add('on');
    currentMcCard=card;currentMcBtn=btn;
    audio.onended=function(){document.getElementById('mp-'+mid).classList.remove('on');currentMcCard=null;currentMcBtn=null;};
}

var MOMENT_EXPIRY=20*60*1000;
function saveLoadState(){try{localStorage.setItem('mp_s',JSON.stringify({p:currentPage,t:Date.now()}));}catch(e){}}
function clearLoadState(){try{localStorage.removeItem('mp_s');}catch(e){}}
function getSavedPage(){try{var raw=localStorage.getItem('mp_s');if(!raw)return 0;var s=JSON.parse(raw);if(!s||!s.p)return 0;if(Date.now()-s.t>MOMENT_EXPIRY){clearLoadState();return 0;}return parseInt(s.p,10)||0;}catch(e){return 0;}}

function loadMore(){return new Promise(function(resolve){if(isLoading||!hasMore){resolve(false);return;}isLoading=true;currentPage++;var btn=document.getElementById('loadMoreBtn');if(btn)btn.innerHTML='<span class="loading-spinner"></span>加载中...';ajaxFetch('api/load_more.php?page='+currentPage).then(function(r){return r.json();}).then(function(data){var inserted=false;if(data.code===1&&data.data.items&&data.data.items.length>0){var list=document.getElementById('momentsList');data.data.items.forEach(function(item){var wrapper=document.createElement('div');wrapper.innerHTML=item.html;var el=wrapper.firstElementChild;if(el){list.insertBefore(el,document.getElementById('loadMoreSection'));inserted=true;}});initTextExpand();initVideoDurations();if(inserted){saveLoadState();}if(!data.data.has_more){hasMore=false;clearLoadState();document.getElementById('loadMoreSection').innerHTML='<div class="load-more"><span class="end-tip"><?php echo htmlspecialchars($settings['end_tip_text'] ?? '没有更多啦'); ?></span></div>';}}else{hasMore=false;clearLoadState();document.getElementById('loadMoreSection').innerHTML='<div class="load-more"><span class="end-tip"><?php echo htmlspecialchars($settings['end_tip_text'] ?? '没有更多啦'); ?></span></div>';}isLoading=false;if(btn&&hasMore)btn.innerHTML='<?php echo htmlspecialchars($settings['load_more_text'] ?? '加载更多'); ?>';resolve(inserted);}).catch(function(){isLoading=false;if(btn)btn.innerHTML='<?php echo htmlspecialchars($settings['load_more_text'] ?? '加载更多'); ?>';resolve(false);});});}

function showToast(msg){var t=document.getElementById('layuiToast');if(t){t.textContent=msg;t.classList.add('show');setTimeout(function(){t.classList.remove('show');},2000);}}

function escHtml(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function initTextExpand(){document.querySelectorAll('.moment-text').forEach(function(el){if(el.dataset.expandInit)return;var text=el.textContent.trim();if(!text||text.length<=120)return;el.dataset.expandInit='1';el.dataset.fullHtml=el.innerHTML;el.dataset.collapsedText=text.substring(0,120);el.innerHTML=escHtml(el.dataset.collapsedText)+'<span class="expand-dots">...</span><span class="expand-btn" onclick="toggleTextExpand(this)">展开</span>';});}
function toggleTextExpand(btn){var el=btn.closest('.moment-text');var expanded=el.dataset.expanded==='1';if(expanded){el.innerHTML=escHtml(el.dataset.collapsedText)+'<span class="expand-dots">...</span><span class="expand-btn" onclick="toggleTextExpand(this)">展开</span>';el.dataset.expanded='0';}else{el.innerHTML=el.dataset.fullHtml+'<span class="expand-btn" onclick="toggleTextExpand(this)">关闭</span>';el.dataset.expanded='1';}}

function initEventDelegation(){
    document.addEventListener('click',function(e){
        var menuBtn=e.target.closest('.menu-btn');
        if(menuBtn){
            e.stopPropagation();
            var id=menuBtn.getAttribute('data-toggle');
            if(id)toggleBar(id);
            return;
        }
        var actionItem=e.target.closest('.action-bar-item');
        if(actionItem){
            e.stopPropagation();
            var onclickAttr=actionItem.getAttribute('onclick');
            if(!onclickAttr)return;
            if(onclickAttr.indexOf('doLike')!==-1){
                var match=onclickAttr.match(/doLike\((-?\d+)/);
                if(match)doLike(parseInt(match[1]),actionItem);
            }else if(onclickAttr.indexOf('openCommentModal')!==-1){
                var match=onclickAttr.match(/openCommentModal\((-?\d+)\)/);
                if(match)openCommentModal(parseInt(match[1]));
            }
            return;
        }
        var commentItem=e.target.closest('.comment-item');
        if(commentItem){
            var onclickAttr=commentItem.getAttribute('onclick');
            if(onclickAttr){
                var match=onclickAttr.match(/replyComment\((-?\d+),\s*['"](.+?)['"]\s*,\s*(\d+)\)/);
                if(match)replyComment(parseInt(match[1]),match[2],parseInt(match[3]));
            }
            return;
        }
        var replyItem=e.target.closest('.reply-item');
        if(replyItem){
            var onclickAttr=replyItem.getAttribute('onclick');
            if(onclickAttr){
                var match=onclickAttr.match(/replyComment\((-?\d+),\s*['"](.+?)['"]\s*,\s*(\d+)\)/);
                if(match)replyComment(parseInt(match[1]),match[2],parseInt(match[3]));
            }
            return;
        }
        var toggleBtn=e.target.closest('.comment-toggle-btn');
        if(toggleBtn){
            e.stopPropagation();
            var pid=toggleBtn.getAttribute('data-post-id');
            if(pid)toggleCommentFold(parseInt(pid),toggleBtn);
            return;
        }
    });

    document.addEventListener('click',function(e){
        if(e.target.closest('.action-bar')||e.target.closest('.comment-modal')||e.target.closest('.comment-item')||e.target.closest('.reply-item')||e.target.closest('.sticker-panel')||e.target.closest('.sticker-btn')||e.target.closest('.top-menu-panel')||e.target.closest('.top-menu-btn')||e.target.closest('.menu-btn'))return;
        closeAllBars();closeStickerPanel();closeTopMenu();
        if(!e.target.closest('.comment-modal-overlay'))closeCommentModal();
    });
}

window.addEventListener('scroll',function(){
    var sy=window.scrollY;
    var menuBtn=document.getElementById('topMenuBtn');
    if(menuBtn){
        if(sy>120){menuBtn.classList.remove('dark');menuBtn.classList.add('light');}
        else{menuBtn.classList.remove('light');menuBtn.classList.add('dark');}
    }
});

function updateMenuHighlight(targetUrl){
    var url=targetUrl || window.location.href;
    var isPage=url.indexOf('page.php')>=0;
    var panel=document.getElementById('topMenuPanel');
    if(!panel)return;
    var items=panel.querySelectorAll('.top-menu-item');
    items.forEach(function(item){
        var href=item.getAttribute('href')||'';
        if(href==='./' || href==='index.php' || href==='./index.php' || href==='/'){
            item.classList.toggle('active',!isPage);
        }
        else if(href.indexOf('page.php')>=0){
            var isActive=isPage && url.indexOf(href)>=0;
            item.classList.toggle('active',isActive);
        }
        else{
            item.classList.remove('active');
        }
    });
}

function initAutoLoad(){var loadMode='<?php echo htmlspecialchars($settings['load_mode'] ?? 'click'); ?>';if(loadMode!=='auto')return;var loadMoreSection=document.getElementById('loadMoreSection');if(!loadMoreSection||!hasMore)return;if('IntersectionObserver' in window){var observer=new IntersectionObserver(function(entries){if(entries[0].isIntersecting&&!isLoading&&hasMore){loadMore();}},{rootMargin:'100px'});observer.observe(loadMoreSection);}else{window.addEventListener('scroll',function(){var rect=loadMoreSection.getBoundingClientRect();if(rect.top<window.innerHeight+200&&!isLoading&&hasMore){loadMore();}});}}

(function(){
    var savedScrollY = 0;
    var isFs = false;
    function onFullscreenChange(){
        var fsEl = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
        if(fsEl){
            savedScrollY = window.scrollY || window.pageYOffset;
            isFs = true;
            if(screen.orientation && screen.orientation.lock){
                screen.orientation.lock('portrait').catch(function(){});
            }
        } else {
            if(isFs){
                setTimeout(function(){
                    window.scrollTo(0, savedScrollY);
                }, 50);
                isFs = false;
            }
            if(screen.orientation && screen.orientation.unlock){
                screen.orientation.unlock();
            }
        }
    }
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    document.addEventListener('mozfullscreenchange', onFullscreenChange);
    document.addEventListener('MSFullscreenChange', onFullscreenChange);
})();

function initVideoDurations(){
    document.querySelectorAll('.moment-video video').forEach(function(video){
        var wrapper = video.closest('.moment-video');
        if(!wrapper) return;
        var durEl = wrapper.querySelector('.video-duration');
        if(!durEl) return;
        if(durEl.textContent && durEl.textContent !== '00:00') return;

        function setDuration(){
            var d = video.duration;
            if(!isNaN(d) && isFinite(d) && d > 0){
                durEl.textContent = formatTime(d);
                durEl.style.display = '';
            }else{
                durEl.style.display = 'none';
            }
        }
        if(video.readyState >= 1){
            setDuration();
        }else{
            video.addEventListener('loadedmetadata', setDuration, {once:true});
            setTimeout(function(){
                if(!durEl.textContent || durEl.textContent === '00:00'){
                    setDuration();
                }
            }, 3000);
        }
        video.addEventListener('error', function(){
            durEl.style.display = 'none';
        }, {once:true});
    });
}

initEventDelegation();
initTextExpand();
initVideoDurations(); // 页面初始化
function restoreLoadedPages(callback){var target=getSavedPage();if(target<=currentPage){if(callback)callback();return;}function doNext(){if(currentPage>=target||!hasMore){if(callback)callback();return;}loadMore().then(function(ok){setTimeout(doNext,ok?200:500);});}doNext();}
restoreLoadedPages(function(){initAutoLoad();});
