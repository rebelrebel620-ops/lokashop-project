const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(process.argv[2] || require('path').join(__dirname,'../resources/views/app.blade.php'),'utf8').match(/<script id="workspace-drawer-controls">([\s\S]*?)<\/script>/)[1];
for(const width of [375,600,768,1024,1280]){
 let focus;const keys={},classes=new Set();
 const make=()=>({attrs:{},hidden:true,disabled:false,events:{},setAttribute(k,v){this.attrs[k]=v},getAttribute(k){return this.attrs[k]},removeAttribute(k){delete this.attrs[k]},addEventListener(k,f){this.events[k]=f},focus(){focus=this},getClientRects(){return [1]},classList:{remove(){}}});
 const sidebar=make(),toggle=make(),close=make(),backdrop=make(),link=make();sidebar.querySelectorAll=()=>[close,link];const media={matches:width<=1024,addEventListener(k,f){this.change=f}};
 const document={get activeElement(){return focus},getElementById:()=>sidebar,querySelectorAll:()=>[toggle],querySelector:s=>s.includes('close')?close:backdrop,body:{classList:{toggle(k,v){v?classes.add(k):classes.delete(k)},contains:k=>classes.has(k)}},addEventListener(k,f){keys[k]=f}};
 vm.runInNewContext(source,{document,window:{matchMedia:()=>media}});
 assert.equal(sidebar.inert,width<=1024);toggle.events.click();assert.equal(backdrop.hidden,width>1024);
 if(width<=1024){assert.equal(focus,close);close.events.click();assert(backdrop.hidden);assert.equal(focus,toggle);toggle.events.click();backdrop.events.click();assert(backdrop.hidden);toggle.events.click();keys.keydown({key:'Escape',preventDefault(){}});assert(backdrop.hidden);toggle.events.click();sidebar.events.click({target:{closest:()=>null}});assert(!backdrop.hidden);sidebar.events.click({target:{closest:()=>link}});assert(backdrop.hidden);toggle.events.click();focus=link;keys.keydown({key:'Tab',shiftKey:false,preventDefault(){}});assert.equal(focus,close);media.matches=false;media.change();assert(!sidebar.inert);assert(!sidebar.attrs['aria-hidden']);assert(backdrop.hidden);}
}
console.log('PASS: ecommerce drawer close button, backdrop, Escape, keyboard focus, link dismissal and viewport reset at phone/tablet/desktop widths');
