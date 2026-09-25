const el=document.getElementById('priceChart');
if(el&&window.QUANTIX_PRICE){
 const range=Number(window.QUANTIX_SETTINGS?.range||250); const prices=window.QUANTIX_PRICE.slice(-range);
 new Chart(el,{type:'line',data:{labels:prices.map(x=>x.ts),datasets:[{label:'Close',data:prices.map(x=>Number(x.close)),tension:.2,pointRadius:0,borderWidth:2}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{display:false}}}});
}
const fc=document.getElementById('forecastChart');
if(fc&&window.QUANTIX_PRICE&&window.QUANTIX_FORECAST){
 const hist=window.QUANTIX_PRICE.slice(-60);
 const m=window.QUANTIX_FORECAST;
 const path=m.forecast_path_20d||[];
 const labels=hist.map(x=>x.ts).concat(path.map(x=>'Forecast +'+x.day));
 const actual=hist.map(x=>Number(x.close)).concat(Array(path.length).fill(null));
 const pred=Array(hist.length-1).fill(null).concat([Number(hist[hist.length-1].close)],path.map(x=>Number(x.value)));
 const low=Array(hist.length-1).fill(null).concat([Number(hist[hist.length-1].close)],path.map(x=>Number(x.low)));
 const high=Array(hist.length-1).fill(null).concat([Number(hist[hist.length-1].close)],path.map(x=>Number(x.high)));
 new Chart(fc,{type:'line',data:{labels,datasets:[
  {label:'Actual',data:actual,tension:.2,pointRadius:0,borderWidth:2},
  {label:'Forecast',data:pred,tension:.2,pointRadius:0,borderWidth:2,borderDash:[6,4]},
  {label:'Lower interval',data:low,tension:.2,pointRadius:0,borderWidth:1,borderDash:[3,5]},
  {label:'Upper interval',data:high,tension:.2,pointRadius:0,borderWidth:1,borderDash:[3,5]}
 ]},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true}},scales:{x:{display:false}}}});
}