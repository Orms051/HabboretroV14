<?php
/**
 * HabboretroV14 — Traqueur d'erreurs JS (porté de Cadurix)
 * Capture les erreurs de la page (JS, promesses, fetch, ressources, console.error)
 * et les envoie à /log_js.php (sendBeacon). Injecté sur le site ET l'admin.
 */
if (!function_exists('error_tracker_html')) {
    function error_tracker_html(): string {
        return <<<'HTML'
<script>
(function(){
  'use strict';
  var EP='/log_js.php', PAGE=document.title||location.pathname.split('/').pop(), URL=location.href;
  function send(p){
    try{
      var d=JSON.stringify(p);
      if(navigator.sendBeacon){ navigator.sendBeacon(EP,new Blob([d],{type:'application/json'})); }
      else{ var x=new XMLHttpRequest(); x.open('POST',EP,true); x.setRequestHeader('Content-Type','application/json'); x.send(d); }
    }catch(e){}
  }
  function track(type,message,source,line,col,stack){
    send({type:type,message:String(message||'').slice(0,500),source:String(source||'').slice(0,250),
      line:line||0,col:col||0,stack:String(stack||'').slice(0,1000),page_url:URL,page_name:PAGE});
  }
  window.onerror=function(msg,src,line,col,error){ track('JS_ERROR',msg,src,line,col,error?error.stack:''); return false; };
  window.addEventListener('unhandledrejection',function(e){ var r=e.reason;
    track('UNHANDLED_PROMISE', r instanceof Error?r.message:String(r), '',0,0, r instanceof Error?r.stack:''); });
  var _f=window.fetch;
  if(_f){ window.fetch=function(url,opts){
    if(String(url).indexOf(EP)!==-1) return _f.apply(this,arguments);
    return _f.apply(this,arguments).then(function(res){
      if(res && !res.ok && res.status>=400) track('FETCH_HTTP_ERROR','HTTP '+res.status+' '+res.statusText,String(url),0,0,'');
      return res;
    }).catch(function(err){ track('FETCH_NETWORK_ERROR',err&&err.message?err.message:String(err),String(url),0,0,err&&err.stack?err.stack:''); throw err; });
  }; }
  var _ce=console.error;
  console.error=function(){ _ce.apply(console,arguments);
    var m=Array.prototype.slice.call(arguments).map(function(a){ try{return typeof a==='object'?JSON.stringify(a):String(a);}catch(e){return String(a);} }).join(' ');
    track('CONSOLE_ERROR',m,'',0,0,''); };
  window.addEventListener('error',function(e){ var t=e.target;
    if(t && t!==window && (t.src||t.href)) track('RESOURCE_ERROR','Ressource non chargee: '+(t.src||t.href),t.src||t.href,0,0,''); }, true);
})();
</script>
HTML;
    }
}
