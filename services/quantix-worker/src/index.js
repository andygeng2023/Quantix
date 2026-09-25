const json=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{"content-type":"application/json; charset=utf-8","cache-control":"no-store","access-control-allow-origin":"*"}});
function symbols(env){return(env.DEFAULT_SYMBOLS||"").split(",").map(s=>s.trim().toUpperCase()).filter(Boolean).slice(0,1500);}
export default{async fetch(request,env){
  const url=new URL(request.url);
  if(url.pathname==="/health")return json({status:"ok",runtime:"cloudflare-workers",websocket:true,provider:env.TWELVEDATA_API_KEY?"twelvedata":"not-configured",universe_size:symbols(env).length});
  if(url.pathname==="/v1/universe")return json({count:symbols(env).length,symbols:symbols(env)});
  const id=env.MARKET_STREAM.idFromName("global"),stub=env.MARKET_STREAM.get(id);
  if(["/v1/status","/v1/trending","/v1/scanner","/v1/summary","/v1/model"].includes(url.pathname))return stub.fetch(new Request(new URL(url.pathname,request.url)));
  if(url.pathname==="/ws")return stub.fetch(request);
  return json({name:"Quantix API",version:"serverless-1.1"});
}};
export class MarketStream extends DurableObject{
  constructor(ctx,env){super(ctx,env);this.env=env;this.clients=new Set();this.providerSocket=null;this.started=false;this.latest={};this.history={};}
  async fetch(request){
    const url=new URL(request.url);
    if(url.pathname==="/status")return json({status:"ok",provider:this.env.TWELVEDATA_API_KEY?"twelvedata":"not-configured",universe_size:(this.env.DEFAULT_SYMBOLS||"").split(",").filter(Boolean).length,live_symbols:Object.keys(this.latest).length,latest:this.latest});
    if(url.pathname==="/trending")return json({results:this.rows().sort((a,b)=>Math.abs(b.change_5m)-Math.abs(a.change_5m)).slice(0,50)});
    if(url.pathname==="/scanner")return json({count:0,results:[]});
    if(url.pathname==="/summary")return json({headline:Object.keys(this.latest).length?Object.keys(this.latest).length+" live instruments":"Waiting for live data",points:["Live prices are provider data, not guarantees.","The serverless feed is designed for personal quantitative research.","A model is not claimed to be trained until a verified artifact is deployed."]});
    if(url.pathname==="/model")return json({status:"not_trained",features:[]});
    if(request.headers.get("Upgrade")==="websocket"){
      const pair=new WebSocketPair(),client=pair[0],server=pair[1];this.ctx.acceptWebSocket(server);this.clients.add(server);
      server.addEventListener("close",()=>this.clients.delete(server));server.addEventListener("error",()=>this.clients.delete(server));
      await this.ensureProvider();server.send(JSON.stringify({type:"snapshot",latest:this.latest,server_time:new Date().toISOString()}));
      return new Response(null,{status:101,webSocket:client});
    }
    return json({error:"Not found"},404);
  }
  rows(){return Object.values(this.latest).map(x=>{const h=this.history[x.symbol]||[],first=h.length>5?h[h.length-6]:h[0]||x;return{ticker:x.symbol,price:x.price,provider:x.provider,timestamp:x.timestamp_ms,change_5m:first?x.price/first.price-1:0};});}
  async ensureProvider(){
    if(this.started||!this.env.TWELVEDATA_API_KEY)return;this.started=true;
    try{
      const socket=new WebSocket("wss://ws.twelvedata.com/v1/quotes/price?apikey="+encodeURIComponent(this.env.TWELVEDATA_API_KEY));this.providerSocket=socket;
      socket.addEventListener("open",()=>{const requested=(this.env.LIVE_SYMBOLS||this.env.DEFAULT_SYMBOLS||"").split(",").map(s=>s.trim().toUpperCase()).filter(Boolean).slice(0,1500);socket.send(JSON.stringify({action:"subscribe",params:{symbols:requested.join(",")}}));});
      socket.addEventListener("message",event=>{try{const m=JSON.parse(event.data),symbol=m.symbol,price=Number(m.price);if(!symbol||!Number.isFinite(price))return;const tick={symbol,price,volume:Number(m.day_volume||0),timestamp_ms:Number(m.timestamp||Date.now()/1000)*1000,provider:"twelvedata"};this.latest[symbol]=tick;const h=this.history[symbol]||(this.history[symbol]=[]);h.push({price,time:tick.timestamp_ms});if(h.length>30)h.shift();const payload=JSON.stringify({type:"tick",tick});for(const client of this.clients){try{client.send(payload)}catch{}}}catch{}});
      socket.addEventListener("close",()=>{this.providerSocket=null;this.started=false});socket.addEventListener("error",()=>{this.providerSocket=null;this.started=false});
    }catch{this.started=false}
  }
  async webSocketMessage(){} async webSocketClose(){} async webSocketError(){}
}
