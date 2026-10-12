const $ = id => document.getElementById(id);
const fmt = n => n.toLocaleString('en-PH');
const samples = {
 daily: { total: 80, enforcers: 14, range: 'Hourly activity · October 9, 2026', label: 'October 9, 2026', labels: ['8 AM','10 AM','12 PM','2 PM','4 PM','6 PM'], violations:[8,18,9,21,16,8], users:[2,5,3,6,4,2] },
 monthly: { total:2480, enforcers:38, range:'Daily activity · October 1–31, 2026', label:'October 2026', labels:['Oct 1','Oct 6','Oct 11','Oct 16','Oct 21','Oct 26','Oct 31'], violations:[58,82,65,106,73,96,80], users:[12,23,16,31,19,25,21] },
 yearly: { total:24800, enforcers:52, range:'Monthly activity · January–December 2026', label:'2026', labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], violations:[1580,1720,1840,2090,1960,2180,2350,2240,2160,2480,2050,2150], users:[180,220,190,240,210,280,250,270,230,310,260,290] }
};
function render(){
 const s=samples[$('period').value];const pending=s.total/4;
 $('total').textContent=fmt(s.total);$('enforcers').textContent=fmt(s.enforcers);$('pending').textContent=fmt(pending);$('resolved').textContent=fmt(s.total-pending);$('range').textContent=s.range;$('footerPeriod').textContent=s.label;
 $('vehicleLegend').innerHTML=[['Motorcycle',.6,'#991b1b'],['Car',.25,'#315c77'],['Tricycle',.15,'#a36517']].map(([name,share,color])=>`<div><strong>${fmt(s.total*share)}</strong><span><i style="background:${color}"></i>${name} · ${share*100}%</span></div>`).join('');
 $('violations').innerHTML=[['Illegal parking',30],['Disregarding traffic signs',22],['Driving without a license',18],['Obstruction',12],['Failure to wear a helmet',8]].map(([name,share])=>{const total=Math.round(s.total*share/100),p=Math.round(total/4);return `<tr><td>${name}</td><td>${fmt(total)}</td><td><span class="share">${share}%<span class="share-track"><i style="width:${share/30*100}%"></i></span></span></td><td>${fmt(p)}</td><td>${fmt(total-p)}</td></tr>`;}).join('');
 const series=[{name:'Violators',values:s.violations,color:'#991b1b'},{name:'Users',values:s.users,color:'#2563eb'}].filter((_,i)=>$('view').value==='overall'||i===($('view').value==='violators'?0:1));
 document.querySelector('.chart-legend').innerHTML=series.map(x=>`<span><i style="background:${x.color}"></i>${x.name}</span>`).join('');
 const W=640,H=250,L=54,R=30,T=16,B=34,max=Math.ceil(Math.max(...series.flatMap(x=>x.values))/4)*4;const x=i=>L+i*(W-L-R)/(s.labels.length-1),y=v=>H-B-v/max*(H-T-B);
 let content='';
 if($('type').value==='doughnut'){
 const sums=series.map(a=>a.values.reduce((a,b)=>a+b,0)),total=sums.reduce((a,b)=>a+b,0),circ=2*Math.PI*78;let offset=0;
 content=series.map((a,i)=>{const amount=sums[i]/total*circ,result=`<circle cx="320" cy="125" r="78" fill="none" stroke="${a.color}" stroke-width="28" stroke-dasharray="${amount} ${circ-amount}" stroke-dashoffset="${-offset}" transform="rotate(-90 320 125)"><title>${a.name}: ${fmt(sums[i])} sampled activity</title></circle>`;offset+=amount;return result;}).join('')+`<text x="320" y="123" text-anchor="middle" fill="#263244" font-size="23" font-weight="600">${fmt(total)}</text><text x="320" y="145" text-anchor="middle" fill="#64748b" font-size="11">Sampled activity</text>`;
 }else{
 for(let i=0;i<=4;i++){const value=max*i/4;content+=`<line x1="${L}" x2="${W-R}" y1="${y(value)}" y2="${y(value)}" stroke="#e5e7eb"/><text x="${L-8}" y="${y(value)+4}" text-anchor="end" fill="#64748b" font-size="10">${fmt(value)}</text>`;}
 content+=s.labels.map((label,i)=>`<text x="${x(i)}" y="${H-10}" text-anchor="middle" fill="#64748b" font-size="10">${label}</text>`).join('');
 series.forEach((a,index)=>{if($('type').value==='bar'){const width=Math.min(20,(W-L-R)/s.labels.length/3);content+=a.values.map((v,i)=>`<rect x="${x(i)+(index-(series.length-1)/2)*width-width/2}" y="${y(v)}" width="${width-2}" height="${H-B-y(v)}" rx="3" fill="${a.color}"><title>${s.labels[i]} · ${a.name}: ${v}</title></rect>`).join('');}else{const points=a.values.map((v,i)=>`${x(i)},${y(v)}`).join(' ');content+=`<polygon points="${L},${H-B} ${points} ${W-R},${H-B}" fill="${a.color}" opacity=".06"/><polyline points="${points}" fill="none" stroke="${a.color}" stroke-width="2.4" stroke-linejoin="round"/>`+a.values.map((v,i)=>`<circle cx="${x(i)}" cy="${y(v)}" r="3" fill="${a.color}"><title>${s.labels[i]} · ${a.name}: ${v}</title></circle>`).join('');}});
 }
 $('chart').innerHTML=`<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${$('type').selectedOptions[0].text}: ${series.map(x=>x.name).join(' and ')}. ${s.range}. Illustrative sampled activity."><title>Activity preview</title>${content}</svg>`;
}
['period','view','type'].forEach(id=>$(id).addEventListener('change',render));$('printReport').addEventListener('click',()=>window.print());render();
