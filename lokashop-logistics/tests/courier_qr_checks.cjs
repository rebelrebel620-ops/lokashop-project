const fs=require('fs'),vm=require('vm'),assert=require('assert');
const path=require('path');
const logi=path.resolve(__dirname,'..');
const ecom=path.resolve(logi,'../lokashop-ecommerce');
const ctx={document:{documentElement:{tagName:'html'}},navigator:{userAgent:'test'}};
vm.createContext(ctx);vm.runInContext(fs.readFileSync(ecom+'/public/js/vendor/qrcode.min.js','utf8'),ctx);
const jsQR=require(logi+'/public/js/vendor/jsQR.js');
for(const value of ['LKS-TEST123456','http://127.0.0.1:8000/track/LKS-TEST123456','https://lokashop.trade/track?code=LKS-TEST123456']){
 const el={innerHTML:'',childNodes:[{offsetWidth:100,offsetHeight:100,style:{}}]};
 const qr=new ctx.QRCode(el,{text:value,width:256,height:256,correctLevel:ctx.QRCode.CorrectLevel.M});
 const m=qr._oQRCode,n=m.getModuleCount(),scale=6,quiet=4,w=(n+quiet*2)*scale;
 const pixels=new Uint8ClampedArray(w*w*4);pixels.fill(255);
 for(let y=0;y<n;y++)for(let x=0;x<n;x++)if(m.isDark(y,x))for(let dy=0;dy<scale;dy++)for(let dx=0;dx<scale;dx++){const i=(((y+quiet)*scale+dy)*w+(x+quiet)*scale+dx)*4;pixels[i]=pixels[i+1]=pixels[i+2]=0;}
 const decoded=jsQR(pixels,w,w);assert.equal(decoded.data,value);
}
console.log('PASS: 3 real QR label-to-scanner round trips (local QRCode + jsQR)');
