// Tests the actual polling script with a controlled clock/network; no browser or live service.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(__dirname+'/../public/js/live-updates.js','utf8');
async function scenario(mode){
 let now=0,nextId=0;const timers=new Map(),replaced=[],badges=[],events={};
 const script={dataset:{refreshMode:mode,endpoint:'/live/state',revision:'old',authenticated:'1'}};
 const node=()=>({style:{},children:[],setAttribute(){},append(...items){this.children.push(...items);}});
 const document={hidden:false,activeElement:null,getElementById:()=>script,querySelector:()=>null,querySelectorAll:()=>[],createElement:node,createTextNode:text=>({text}),body:{append:n=>badges.push(n)},addEventListener:(event,fn)=>{(events[event]??=[]).push(fn);}};
 const context={document,navigator:{onLine:true},location:{href:'https://test.local/product/15',origin:'https://test.local',replace:url=>replaced.push(url)},history:{replaceState(){}},sessionStorage:{getItem:()=>null,removeItem(){},setItem(){}},URL,AbortController,WeakMap,Date:{now:()=>now},scrollX:0,scrollY:0,requestAnimationFrame:fn=>fn(),setTimeout:(fn,delay)=>{const id=++nextId;timers.set(id,{fn,delay});return id;},clearTimeout:id=>timers.delete(id),fetch:async()=>({status:200,ok:true,json:async()=>({revision:'changed',authenticated:true})}),confirm:()=>true};context.window={addEventListener(){},scrollTo(){}};
 vm.runInNewContext(source,context);
 async function tick(){now+=15000;const entry=[...timers].find(([,v])=>v.delay===10000);assert.ok(entry,'Polling remains active');timers.delete(entry[0]);await entry[1].fn();}
 if(mode!=='product')await tick();
 return {replaced,badges,events,tick,timers};
}
(async()=>{
 const product=await scenario('product');assert.equal(product.replaced.length,0,'Product does not auto-reload');assert.equal(product.badges.length,0,'Product shows no Update now notice');assert.equal(product.timers.size,0,'Generic page reloader does not poll on products');
 const other=await scenario('automatic');assert.equal(other.replaced.length,1,'Other pages retain automatic live updates');console.log('PASS: 4 polling/reset regression checks.');
})().catch(error=>{console.error(error);process.exitCode=1;});
