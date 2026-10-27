"""Build an isolated fictional UI fixture from the real monitoring view and script.

No database, authentication, real map key, or production route is involved.
Run from the repository root; open public/demos/location-monitoring-preview.html.
"""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
view = (root / 'resources/views/location-monitoring/index.blade.php').read_text(encoding='utf-8')
content = view.split("@section('content')", 1)[1].split('@endsection', 1)[0]
content = re.sub(r'data-endpoint="[^"]*"', 'data-endpoint="/fictional-locations"', content)
content = re.sub(r'data-map-key="[^"]*"', 'data-map-key=""', content)
content = re.sub(r"data-center='[^']*'", "data-center='{\"latitude\":11.2445,\"longitude\":125.0031}'", content)
content = re.sub(r"data-thresholds='[^']*'", "data-thresholds='{\"active\":30,\"offline\":300}'", content)
source = (root / 'resources/js/location-monitoring.js').read_text(encoding='utf-8').replace("import './bootstrap';", '')
source = source.replace('loadLocations(); loadMap();', "loadLocations(); if (new URLSearchParams(location.search).has('no-map')) loadMap(); else window.initOfficerMap();")
fixture = r'''
const mode = new URLSearchParams(location.search).get('state') || 'normal';
const t = Date.now();
const rows = [
  ['Juan Santos','Downtown',0,true,false],['Maria Reyes','Market',120,true,true],
  ['Anton Cruz','Port',800,true,false],['Paolo Garcia','Downtown',900,false,false],
  ['Ana Dela Cruz With A Long Sample Name','Long assigned area name for overflow testing',0,true,false]
].map(([name,area,age,onDuty,lowAccuracy],i) => ({id:i+1,name,area,personnel_id:`ENF-00${i+1}`,team_id:i%2+1,team:i%2?'Team Reyes':'Team Santos',supervisor:i%2?'Reyes':'Santos',on_duty:onDuty,is_sharing:onDuty,received_at:new Date(t-age*1000).toISOString(),time_in:new Date(t-3600000).toISOString(),latitude:11.24+i*.001,longitude:125+i*.001,accuracy:lowAccuracy?150:8,low_accuracy:lowAccuracy,speed:0,details_url:'#sample-officer'}));
let fetchCount = 0;
window.fetch = async (url,options) => {
  fetchCount++;
  if (mode === 'slow') await new Promise(resolve => setTimeout(resolve,2000));
  if (mode === 'error' || (mode === 'refresh-error' && fetchCount>1)) throw Error('Fictional network failure');
  return {ok:true,status:200,json:async()=>({officers:mode==='empty'?[]:rows,teams:[{id:1,name:'Team Santos'},{id:2,name:'Team Reyes'}],areas:[...new Set(rows.map(row=>row.area))],server_time:new Date().toISOString()})};
};
let liveListener;
const connectionHandlers = {};
window.Echo = {private:()=>({listen:(event,callback)=>{liveListener=callback;}}),leave:()=>{},connector:{pusher:{connection:{state:'connected',bind:(event,callback)=>{connectionHandlers[event]=callback;}}}}};
document.getElementById('qaEvent').onclick=()=>{rows[0].received_at=new Date().toISOString();rows[0].area='Updated sample area';liveListener?.({officer:rows[0]});};
document.getElementById('qaDisconnect').onclick=()=>connectionHandlers.disconnected?.();
class MapStub {
  constructor(element){this.element=element;this.zoom=13;element.innerHTML=`<svg width="100%" height="100%" viewBox="0 0 1000 650" preserveAspectRatio="xMidYMid slice" aria-label="Illustrative map for testing"><defs><pattern id="roads" width="70" height="60" patternTransform="rotate(-20)" patternUnits="userSpaceOnUse"><rect width="70" height="60" fill="#edf0ed"/><path d="M0 0H70V60" fill="none" stroke="white" stroke-width="6"/></pattern></defs><rect width="1000" height="650" fill="url(#roads)"/><path d="M780 0C720 210 840 320 690 650H1000V0Z" fill="#c8e0ed"/><text x="465" y="240" fill="#64748b" font-size="26">Tacloban</text><text x="840" y="320" fill="#64748b" font-size="18">Bay</text><text x="390" y="550" fill="#64748b" font-size="12">Illustrative map · fictional data</text></svg>`;}
  panTo(){} setCenter(){} setZoom(z){this.zoom=z;} getZoom(){return this.zoom;} getProjection(){return null;} fitBounds(){}
}
class MarkerStub {
  constructor(options){this.position=options.position;this.map=options.map;this.button=document.createElement('button');this.button.className='qa-marker';this.button.textContent=options.title.split(' ').map(n=>n[0]).slice(0,2).join('');this.button.style.cssText=`left:${48+Math.random()*25}%;top:${25+Math.random()*40}%;border-color:${options.icon.fillColor}`;this.button.title=options.title;this.setMap(options.map);}
  addListener(event,callback){this.button.onclick=callback;} setMap(map){if(map)map.element.append(this.button);else this.button.remove();} setPosition(position){this.position=position;} getPosition(){return this.position;} setIcon(icon){if(icon.fillColor)this.button.style.borderColor=icon.fillColor;} setTitle(title){this.button.title=title;}
}
window.google={maps:{Map:MapStub,Marker:MarkerStub,SymbolPath:{CIRCLE:0},ControlPosition:{RIGHT_TOP:0,RIGHT_CENTER:1},LatLngBounds:class{extend(){}},Point:class{},Size:class{}}};
'''
html = '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Map First · fictional QA preview</title><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/location-monitoring.css"><style>.qa-bar{padding:8px 14px;background:#fff;font:12px 'Segoe UI';display:flex;gap:12px;flex-wrap:wrap;border-bottom:1px solid #ddd}.qa-bar a{color:#8f1d1d}.qa-marker{position:absolute;width:38px;height:38px;border:4px solid;border-radius:50%;background:white;cursor:pointer;font:600 12px 'Segoe UI'}.qa-marker:focus-visible{outline:3px solid #8f1d1d}</style></head><body><div class="qa-bar"><strong>Fictional QA preview</strong><a href="?">Normal</a><a href="?state=empty">Empty</a><a href="?state=error">Error</a><a href="?state=refresh-error">Refresh error</a><a href="?state=slow">Slow</a><a href="?no-map">Map unavailable</a><button id="qaEvent">Simulate location update</button><button id="qaDisconnect">Simulate disconnect</button></div><main class="workspace location-monitor-workspace">'''
html += content + '</main><script>' + fixture + source + '</script></body></html>'
for name in ('dashboard.css', 'location-monitoring.css'):
    html = html.replace(f'../css/{name}"', f'../css/{name}?v={(root / "public/css" / name).stat().st_mtime_ns}"')
out = root / 'public/demos/location-monitoring-preview.html'
out.parent.mkdir(exist_ok=True)
out.write_text(html, encoding='utf-8')
print(out)
