const el=document.getElementById('priceChart');
if(el&&window.QUANTIX_PRICE){
 new Chart(el,{type:'line',data:{labels:QUANTIX_PRICE.map(x=>x.ts),datasets:[{label:'Close',data:QUANTIX_PRICE.map(x=>Number(x.close)),tension:.2,pointRadius:0,borderWidth:2}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{display:false}}}});
}
const fc=document.getElementById('forecastChart');
if(fc&&window.QUANTIX_PRICE&&window.QUANTIX_FORECAST){
 const hist=window.QUANTIX_PRICE.slice(-60);
 const last=hist[hist.length-1];
 const m=window.QUANTIX_FORECAST;
 const labels=hist.map(x=>x.ts).concat(Array.from({length:20},(_,i)=>'Forecast +'+(i+1)));
 const actual=hist.map(x=>Number(x.close)).concat(Array(20).fill(null));
 const forecast=Array(hist.length-1).fill(null).concat([Number(last.close)],Array(19).fill(null));
 forecast[forecast.length-1]=Number(m.forecast_20d);
 const low=Array(hist.length-1).fill(null).concat([Number(last.close)],Array(19).fill(null)); low[low.length-1]=Number(m.forecast_20d_low);
 const high=Array(hist.length-1).fill(null).concat([Number(last.close)],Array(19).fill(null)); high[high.length-1]=Number(m.forecast_20d_high);
 new Chart(fc,{type:'line',data:{labels,datasets:[
  {label:'Actual',data:actual,tension:.2,pointRadius:0,borderWidth:2},
  {label:'Forecast endpoint',data:forecast,tension:.2,pointRadius:2,borderWidth:2,borderDash:[6,4]},
  {label:'Lower interval',data:low,tension:.2,pointRadius:0,borderWidth:1,borderDash:[3,5]},
  {label:'Upper interval',data:high,tension:.2,pointRadius:0,borderWidth:1,borderDash:[3,5]}
 ]},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true}},scales:{x:{display:false}}}});
}