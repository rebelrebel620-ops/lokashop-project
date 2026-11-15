const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const make=(data={})=>({dataset:data,attrs:{},textContent:'',value:'',hidden:false,addEventListener(name,fn){(this.events??={})[name]=fn;},setAttribute(name,value){this.attrs[name]=value;},removeAttribute(){}});
(async()=>{
 const price=make({basePrice:'100'}),stock=make({baseStock:'20'}),main=make(),info=make(),quantity=make(),buy=make();main.src='photo1';main.getAttribute=()=>main.src;quantity.value='2';
 const thumbs=['photo1','photo2'].map(src=>make({galleryImage:src}));
 const select=make();select.options=[Object.assign(make({}),{value:'',textContent:'Original'}),Object.assign(make({price:'90',stock:'8',image:''}),{value:'7',textContent:'Variant'})];select.value='';Object.defineProperty(select,'selectedOptions',{get(){return this.options.filter(o=>o.value===this.value);}});select.add=o=>select.options.push(o);
 const form=make({vacation:'0'});form.querySelector=s=>s.includes('variation_id')?select:quantity;form.querySelectorAll=()=>[buy];
 const timers=new Map();let id=0,mediaChanges=0;let src=main.src;Object.defineProperty(main,'src',{get:()=>src,set:value=>{src=value;mediaChanges++;}});
 const nodes={'live-product-price':price,'product-main-image':main,'selected-variant':info};const document={hidden:false,activeElement:null,getElementById:id=>nodes[id]||null,querySelector:s=>s==='[data-purchase-form]'?form:s==='[data-base-stock]'?stock:s==='[data-product-live-url]'?{dataset:{productLiveUrl:'/product/15/state'}}:null,querySelectorAll:s=>s==='[data-gallery-image]'?thumbs:[],addEventListener(){}};
 let response={available:true,price:200,stock:10,vacation:false,variations:[{id:7,price:80,stock:3,image:'',label:'Variant · new price'}]};
 const context={document,window:{addEventListener(){}},navigator:{onLine:true},AbortController,Intl,Option:function(label,value){return Object.assign(make(),{textContent:label,value});},fetch:async()=>({ok:true,json:async()=>response}),setTimeout:(fn,delay)=>{let key=++id;timers.set(key,{fn,delay});return key;},clearTimeout:key=>timers.delete(key)};
 vm.runInNewContext(fs.readFileSync(__dirname+'/../public/js/product-selection.js','utf8'),context);
 const tick=async()=>{const [key,timer]=[...timers].find(([,timer])=>timer.delay===10000);timers.delete(key);await timer.fn();};
 thumbs[1].events.click();const changes=mediaChanges;await tick();assert.equal(main.src,'photo2');assert.equal(mediaChanges,changes);assert.equal(thumbs[1].attrs['aria-pressed'],'true');assert.equal(stock.textContent,'10 units available');assert.ok(price.textContent.includes('200.00'));
 select.value='7';select.events.change();thumbs[1].events.click();const selectedChanges=mediaChanges;await tick();assert.equal(select.value,'7');assert.equal(main.src,'photo2');assert.equal(mediaChanges,selectedChanges);assert.equal(quantity.value,'2');assert.ok(price.textContent.includes('80.00'));assert.equal(stock.textContent,'3 units available');
 response={available:false};await tick();assert.equal(main.src,'photo2');assert.equal(buy.disabled,true);console.log('PASS: 13 in-place price/stock and gallery-preservation checks.');
})().catch(error=>{console.error(error);process.exitCode=1;});
