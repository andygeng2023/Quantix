const API_URL=window.QUANTIX_API_URL||"";
const signals=[
  ["NVDA","Momentum","Bullish","+2.8%","up"],["MSFT","Quality","Positive","+1.4%","up"],
  ["EURUSD","Macro","Neutral","0.1σ","neutral"],["CL1","Volatility","Watch","1.7σ","neutral"],["TLT","Rates","Negative","-1.2%","down"]
];
const signalList=document.querySelector("#signalList");
signalList.innerHTML=signals.map(s=>`<div class="signal"><span class="ticker">${s[0]}</span><div><b>${s[1]}</b><small>${s[3]}</small></div><b class="${s[4]}">${s[2]}</b></div>`).join("");

const chart=document.querySelector("#chart"),ctx=chart.getContext("2d");
function drawChart(){const r=chart.getBoundingClientRect(),d=window.devicePixelRatio||1;chart.width=r.width*d;chart.height=r.height*d;ctx.setTransform(d,0,0,d,0,0);const w=r.width,h=r.height;ctx.clearRect(0,0,w,h);ctx.strokeStyle="#172632";ctx.lineWidth=1;for(let i=1;i<6;i++){ctx.beginPath();ctx.moveTo(0,i*h/6);ctx.lineTo(w,i*h/6);ctx.stroke()}const path=(phase,amp)=>{ctx.beginPath();for(let i=0;i<90;i++){const x=i*w/89,y=h*.63-i*h*.0024+Math.sin(i*.22+phase)*amp+Math.sin(i*.07)*8;i?ctx.lineTo(x,y):ctx.moveTo(x,y)}ctx.stroke()};ctx.lineWidth=2;ctx.strokeStyle="#78a9ff";path(0,12);ctx.setLineDash([5,5]);ctx.strokeStyle="#65e0b1";path(.7,8);ctx.setLineDash([])}
drawChart();addEventListener("resize",drawChart);

function setApiState(online){const pill=document.querySelector("#apiPill");pill.classList.toggle("online",online);pill.innerHTML=`<i></i> ${online?"API online":"API offline"}`;document.querySelector("#dataStatus").textContent=online?"Live API":"Awaiting API"}
function renderMetrics(metrics){const rows=[["Accuracy",metrics.accuracy,"Directional classification hit rate"],["Balanced accuracy",metrics.balanced_accuracy,"Class-balanced validation score"],["ROC-AUC",metrics.roc_auc,"Ranking quality on held-out data"]];document.querySelector("#metricsTable").innerHTML=rows.map(r=>`<tr><td>${r[0]}</td><td><b>${typeof r[1]==="number"?r[1].toFixed(3):r[1]}</b></td><td>${r[2]}</td></tr>`).join("");if(typeof metrics.roc_auc==="number")document.querySelector("#auc").textContent=metrics.roc_auc.toFixed(3)}
async function loadModel(){if(!API_URL){setApiState(false);return}try{const res=await fetch(API_URL.replace(/\/$/,"")+"/v1/model");if(!res.ok)throw new Error();const data=await res.json();setApiState(true);if(data.status==="ready"){document.querySelector("#modelStatus").textContent="READY";document.querySelector("#modelStatus").classList.add("ready");document.querySelector("#trainProgress").style.width="100%";document.querySelector("#trainLabel").textContent="100%";document.querySelector("#horizon").textContent=`${data.horizon_days}D`;document.querySelector("#diagHorizon").textContent=`${data.horizon_days} days`;renderMetrics(data.metrics||{});document.querySelector("#updated").textContent="Model metadata loaded"}else{document.querySelector("#modelStatus").textContent="NOT TRAINED";document.querySelector("#trainLabel").textContent="0%"}document.querySelector("#chartUpdated").textContent="API checked "+new Date().toLocaleTimeString()}catch(e){setApiState(false)}}
document.querySelector("#refresh").onclick=loadModel;
document.querySelector("#apiHelp").onclick=()=>alert("Deploy the FastAPI service on authorized backend infrastructure, then set QUANTIX_API_URL before loading the website. Do not place Bloomberg credentials in this frontend.");
document.querySelector("#apiUrlLabel").textContent=API_URL||"API_URL not configured";
loadModel();