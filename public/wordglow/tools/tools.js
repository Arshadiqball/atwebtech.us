(function(){
var P;
window.WG={
load:function(){return P||(P=fetch('/wordglow/tools/words.json').then(function(r){return r.json()}).then(function(d){
  var all=new Map();Object.keys(d.m).forEach(function(w){all.set(w,d.m[w])});
  d.x.split(' ').forEach(function(w){if(!all.has(w))all.set(w,null)});
  return{all:all,answers:d.a.split(' ')};}))},
esc:function(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})},
// words: array already in display order. Common words get their meaning; the rest are shown as chips.
render:function(words,all,max){
  max=max||150;var common=[],rare=[],i,w,m;
  for(i=0;i<words.length;i++){w=words[i];(all.get(w)?common:rare).push(w)}
  var h='',c=common.slice(0,max),r=rare.slice(0,max);
  if(c.length){h+='<ul class="def">';for(i=0;i<c.length;i++){m=all.get(c[i]);h+='<li><b>'+c[i].toUpperCase()+'</b> <i>'+WG.esc(m[0])+'</i> · '+WG.esc(m[1])+'</li>'}h+='</ul>'}
  if(r.length){h+='<p class="note">'+(c.length?'Less common words that also fit:':'Words that fit (less common):')+'</p><div class="res">';for(i=0;i<r.length;i++)h+='<span>'+r[i].toUpperCase()+'</span>';h+='</div>'}
  if(common.length>max||rare.length>max)h+='<p class="note">Showing the first '+max+' of each group. Add more clues to narrow the list.</p>';
  return h}
};
})();