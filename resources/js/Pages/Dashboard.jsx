import { useState } from "react";
import { router, usePage } from '@inertiajs/react';
import { LayoutDashboard, Package, Archive, FileText, DollarSign, BarChart2,
         AlertTriangle, Plus, Pencil, Trash2, X, Layers, Wrench,
         ShoppingBag, Zap, TrendingUp, CheckCircle, Menu, Users } from "lucide-react";

// ── COLOR SYSTEM ──────────────────────────────────────────────────────────────
const C = {
  bg:"#FAF7F4", card:"#FFFFFF", sidebar:"#1C1917", border:"#E7E5E4",
  amber:"#D97706", amberBg:"#FEF3C7", text:"#1C1917", muted:"#78716C", faint:"#A8A29E",
  green:"#059669", greenBg:"#D1FAE5", red:"#DC2626", redBg:"#FEE2E2",
  yellow:"#B45309", yellowBg:"#FEF3C7", blue:"#1D4ED8", blueBg:"#DBEAFE",
  purple:"#6D28D9", purpleBg:"#EDE9FE", teal:"#0E7490", tealBg:"#CFFAFE",
};
const ITEM_TYPES = {
  materia_prima:{ label:"Materia Prima", Icon:Layers,      col:C.amber,  bg:C.amberBg  },
  herramienta:  { label:"Herramienta",   Icon:Wrench,      col:C.purple, bg:C.purpleBg },
  producto:     { label:"Producto",      Icon:ShoppingBag, col:C.green,  bg:C.greenBg  },
  consumible:   { label:"Consumible",    Icon:Zap,         col:C.teal,   bg:C.tealBg   },
};
const ORDER_STATUS = {
  pendiente: { label:"Pendiente",  col:C.yellow, bg:C.yellowBg },
  en_proceso:{ label:"En proceso", col:C.blue,   bg:C.blueBg   },
  pausada:   { label:"Pausada",    col:C.muted,  bg:"#F5F4F2"  },
  completada:{ label:"Completada", col:C.green,  bg:C.greenBg  },
  cancelada: { label:"Cancelada",  col:C.red,    bg:C.redBg    },
};
// Si llega un estado desconocido, se muestra tal cual en vez de romper la pantalla.
const statusOf = st => ORDER_STATUS[st] ?? { label:String(st), col:C.muted, bg:"#F5F4F2" };
const UNITS = ["pieza","metro","m²","kg","litro","lata","caja","par","rollo","galón","paquete","metro lineal"];

// ── UTILS ─────────────────────────────────────────────────────────────────────
const $m = n=>`$${Number(n).toLocaleString("es-MX",{minimumFractionDigits:0})}`;

// ── STYLE SHORTCUTS ───────────────────────────────────────────────────────────
const card = {background:C.card,borderRadius:12,border:`1px solid ${C.border}`,padding:"18px 20px"};
const inp  = {padding:"8px 12px",borderRadius:8,border:`1.5px solid ${C.border}`,fontSize:13,color:C.text,width:"100%",background:"#fff",fontFamily:"inherit",boxSizing:"border-box"};
const lbl  = {fontSize:12,fontWeight:600,color:C.muted,display:"block",marginBottom:4};
const th   = {textAlign:"left",fontSize:11,fontWeight:700,color:C.muted,padding:"10px 14px",textTransform:"uppercase",letterSpacing:.5,borderBottom:`1px solid ${C.border}`,background:"#FAFAF9"};
const td   = {padding:"11px 14px",borderBottom:`1px solid #F8F7F6`};

// ── ATOMS ─────────────────────────────────────────────────────────────────────
const Tag  = ({label,col,bg})=><span style={{background:bg,color:col,fontSize:11,fontWeight:700,padding:"2px 9px",borderRadius:99,whiteSpace:"nowrap"}}>{label}</span>;

function Btn({label,onClick,secondary,danger,small,Icon}){
  const bg=danger?C.redBg:secondary?"#F5F4F2":C.amber;
  const cl=danger?C.red:secondary?C.muted:"#fff";
  return(
    <button onClick={onClick} style={{background:bg,color:danger?C.red:secondary?C.muted:"#fff",border:"none",cursor:"pointer",
      borderRadius:8,padding:small?"5px 11px":"8px 16px",fontSize:small?11:13,fontWeight:700,fontFamily:"inherit",
      display:"flex",alignItems:"center",gap:5,whiteSpace:"nowrap"}}>
      {Icon&&<Icon size={13}/>}{label}
    </button>
  );
}

function Field({label,type="text",value,onChange,opts}){
  return(
    <div>
      <label style={lbl}>{label}</label>
      {opts
        ? <select value={value} onChange={e=>onChange(e.target.value)} style={inp}>
            {opts.map(o=>{const v=o.v??o, l=o.l??o; return <option key={v} value={v}>{l}</option>;})}
          </select>
        : <input type={type} value={value} onChange={e=>onChange(e.target.value)} style={inp}/>}
    </div>
  );
}

function Modal({title,onClose,children,width=500}){
  return(
    <div style={{position:"fixed",inset:0,background:"rgba(0,0,0,.5)",zIndex:130,overflowY:"auto",
      display:"flex",alignItems:"flex-start",justifyContent:"center",
      padding:"28px 16px",boxSizing:"border-box"}}>
      <div style={{background:C.card,borderRadius:14,width:"100%",maxWidth:width,border:`1px solid ${C.border}`}}>
        <div style={{padding:"15px 20px",borderBottom:`1px solid ${C.border}`,display:"flex",justifyContent:"space-between",alignItems:"center"}}>
          <p style={{fontSize:16,fontWeight:700,color:C.text,margin:0}}>{title}</p>
          <button onClick={onClose} style={{background:"none",border:"none",cursor:"pointer",color:C.muted}}><X size={18}/></button>
        </div>
        <div style={{padding:20}}>{children}</div>
      </div>
    </div>
  );
}

// ── DASHBOARD ─────────────────────────────────────────────────────────────────
function Dashboard({items,inv,orders,setView}){
  const low   = inv.filter(r=>r.qty<=r.min_qty);
  const val   = inv.reduce((s,r)=>{const i=items.find(x=>x.id===r.item_id);return s+(i?i.cost*r.qty:0);},0);
  const comp  = orders.filter(o=>o.status==="completada");
  const rev   = comp.reduce((s,o)=>s+Number(o.quoted),0);
  const cost  = comp.reduce((s,o)=>s+Number(o.mat)+Number(o.labor)+Number(o.overhead),0);
  const mrg   = rev===0?"0.0":((rev-cost)/rev*100).toFixed(1);

  const kpis = [
    {label:"Valor en Inventario",  val:$m(val),  sub:`${inv.length} registros activos`,       accent:C.amber},
    {label:"Órdenes Activas",      val:orders.filter(o=>o.status==="en_proceso").length,
                                   sub:`${orders.filter(o=>o.status==="pendiente").length} pendientes`, accent:C.blue},
    {label:"Artículos Stock Bajo", val:low.length,sub:low.length?"Requieren reabasto":"Sin alertas", accent:low.length?C.red:C.green},
    {label:"Margen Promedio OTs",  val:`${mrg}%`, sub:`${comp.length} órdenes completadas`,   accent:C.green},
  ];

  return(
    <div style={{display:"flex",flexDirection:"column",gap:16}}>
      {/* KPIs */}
      <div className="rg2" style={{display:"grid",gridTemplateColumns:"repeat(4,1fr)",gap:12}}>
        {kpis.map(k=>(
          <div key={k.label} style={{...card,borderTop:`3px solid ${k.accent}`}}>
            <p style={{fontSize:11,fontWeight:600,color:C.muted,textTransform:"uppercase",letterSpacing:.4}}>{k.label}</p>
            <p style={{fontSize:26,fontWeight:700,color:k.accent,margin:"6px 0 2px"}}>{k.val}</p>
            <p style={{fontSize:11,color:C.faint}}>{k.sub}</p>
          </div>
        ))}
      </div>

      <div className="rg1" style={{display:"grid",gridTemplateColumns:"3fr 2fr",gap:14}}>
        {/* Recent orders */}
        <div style={card}>
          <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:14}}>
            <p style={{fontWeight:700,fontSize:14,color:C.text}}>Órdenes Recientes</p>
            <button onClick={()=>setView("orders")} style={{background:"none",border:"none",cursor:"pointer",fontSize:11,fontWeight:700,color:C.amber}}>Ver todas →</button>
          </div>
          <div style={{overflowX:"auto"}}>
          <table style={{width:"100%",borderCollapse:"collapse",minWidth:420}}>
            <thead><tr>{["OT","Cliente","Estado","Cotizado"].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
            <tbody>
              {orders.slice(0,4).map(o=>{
                const s=statusOf(o.status);
                return(
                  <tr key={o.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                    <td style={{...td,fontSize:11,fontWeight:700,color:C.amber,fontFamily:"monospace"}}>{o.code}</td>
                    <td style={{...td,fontSize:12,fontWeight:600,color:C.text}}>{o.client}<br/><span style={{fontSize:10,color:C.faint}}>{o.desc}</span></td>
                    <td style={td}><Tag label={s.label} col={s.col} bg={s.bg}/></td>
                    <td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{$m(o.quoted)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          </div>
        </div>

        {/* Alerts */}
        <div style={card}>
          <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:14}}>
            <p style={{fontWeight:700,fontSize:14,color:C.text}}>Alertas de Stock</p>
            <button onClick={()=>setView("inventory")} style={{background:"none",border:"none",cursor:"pointer",fontSize:11,fontWeight:700,color:C.amber}}>Inventario →</button>
          </div>
          {low.length===0
            ? <div style={{textAlign:"center",padding:"20px 0"}}>
                <CheckCircle size={28} color={C.green} style={{margin:"0 auto 8px"}}/>
                <p style={{fontSize:12,color:C.muted}}>Inventario en buen estado</p>
              </div>
            : low.map(r=>{
                const item=items.find(i=>i.id===r.item_id);
                return(
                  <div key={r.id} style={{display:"flex",justifyContent:"space-between",padding:"8px 0",borderBottom:`1px solid ${C.border}`}}>
                    <div>
                      <p style={{fontSize:12,fontWeight:600,color:C.text}}>{item?.name}</p>
                      <p style={{fontSize:11,color:C.muted}}>{r.location} · Mín: {r.min_qty} {item?.unit}</p>
                    </div>
                    <span style={{background:C.redBg,color:C.red,padding:"3px 9px",borderRadius:99,fontSize:11,fontWeight:700,alignSelf:"center"}}>{r.qty}</span>
                  </div>
                );
              })
          }
        </div>
      </div>

      {/* Type summary */}
      <div style={card}>
        <p style={{fontWeight:700,fontSize:14,color:C.text,marginBottom:14}}>Artículos por Tipo</p>
        <div className="rg2" style={{display:"grid",gridTemplateColumns:"repeat(4,1fr)",gap:10}}>
          {Object.entries(ITEM_TYPES).map(([key,t])=>{
            const cnt=items.filter(i=>i.type===key).length;
            const v=inv.filter(r=>items.find(i=>i.id===r.item_id)?.type===key).reduce((s,r)=>{const i=items.find(x=>x.id===r.item_id);return s+(i?.cost||0)*r.qty;},0);
            return(
              <div key={key} style={{background:t.bg,borderRadius:10,padding:14}}>
                <t.Icon size={18} color={t.col}/>
                <p style={{fontSize:22,fontWeight:700,color:t.col,margin:"6px 0 2px"}}>{cnt}</p>
                <p style={{fontSize:11,fontWeight:700,color:t.col}}>{t.label}</p>
                <p style={{fontSize:11,color:t.col,opacity:.8,marginTop:2}}>{$m(v)} en stock</p>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

// ── ITEMS VIEW ────────────────────────────────────────────────────────────────
function ItemsView({items,inv,onEdit,onDelete,onNew,canEdit}){
  const [q,setQ]   = useState("");
  const [ft,setFt] = useState("all");
  const stockFor   = id=>inv.find(r=>r.item_id===id);
  const filtered   = items.filter(i=>(ft==="all"||i.type===ft)&&
    (i.name.toLowerCase().includes(q.toLowerCase())||i.sku.toLowerCase().includes(q.toLowerCase())));
  return(
    <div style={{display:"flex",flexDirection:"column",gap:14}}>
      <div style={{...card,padding:"12px 16px",display:"flex",gap:10,alignItems:"center",flexWrap:"wrap"}}>
        <input placeholder="🔍  Buscar nombre o SKU..." value={q} onChange={e=>setQ(e.target.value)} style={{...inp,maxWidth:260,flex:"none"}}/>
        <div style={{display:"flex",gap:6,flexWrap:"wrap"}}>
          {[["all","Todos"],...Object.entries(ITEM_TYPES).map(([k,v])=>[k,v.label])].map(([v,l])=>(
            <button key={v} onClick={()=>setFt(v)} style={{background:ft===v?C.amber:"#F5F4F2",
              color:ft===v?"#fff":C.muted,border:"none",cursor:"pointer",borderRadius:6,
              padding:"5px 11px",fontSize:11,fontWeight:700,fontFamily:"inherit"}}>{l}</button>
          ))}
        </div>
        {canEdit&&<div style={{marginLeft:"auto"}}><Btn label="Nuevo Artículo" Icon={Plus} onClick={onNew}/></div>}
      </div>
      <div style={{...card,padding:0,overflowX:"auto"}}>
        <table style={{width:"100%",borderCollapse:"collapse",minWidth:560}}>
          <thead><tr>{["SKU","Nombre","Tipo","Categoría","Unidad","Costo Unit.","Stock",""].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
          <tbody>
            {filtered.map(item=>{
              const stk=stockFor(item.id); const low=stk&&stk.qty<=stk.min_qty; const t=ITEM_TYPES[item.type];
              return(
                <tr key={item.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                  <td style={{...td,fontSize:11,fontFamily:"monospace",fontWeight:700,color:C.amber}}>{item.sku}</td>
                  <td style={td}><p style={{fontSize:13,fontWeight:600,color:C.text}}>{item.name}</p>{item.notes&&<p style={{fontSize:11,color:C.faint}}>{item.notes}</p>}</td>
                  <td style={td}><Tag label={t.label} col={t.col} bg={t.bg}/></td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{item.category}</td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{item.unit}</td>
                  <td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{item.cost>0?$m(item.cost):"—"}</td>
                  <td style={td}>{stk?<span style={{background:low?C.redBg:C.greenBg,color:low?C.red:C.green,padding:"3px 9px",borderRadius:99,fontSize:11,fontWeight:700}}>{stk.qty} {item.unit}</span>:<span style={{color:C.faint,fontSize:11}}>Sin registro</span>}</td>
                  <td style={{...td,textAlign:"right"}}>
                    {canEdit&&<div style={{display:"flex",gap:6,justifyContent:"flex-end"}}>
                      <Btn small secondary label="Editar" Icon={Pencil} onClick={()=>onEdit(item)}/>
                      <Btn small danger Icon={Trash2} onClick={()=>onDelete(item.id)}/>
                    </div>}
                  </td>
                </tr>
              );
            })}
            {!filtered.length&&<tr><td colSpan={8} style={{textAlign:"center",padding:32,color:C.faint,fontSize:13}}>No se encontraron artículos.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── INVENTORY VIEW ────────────────────────────────────────────────────────────
function InventoryView({items,inv,onNew,canEdit}){
  const low=inv.filter(r=>r.qty<=r.min_qty);
  return(
    <div style={{display:"flex",flexDirection:"column",gap:14}}>
      {low.length>0&&(
        <div style={{background:C.redBg,borderRadius:10,padding:"11px 16px",border:`1px solid #FECACA`,display:"flex",gap:10,alignItems:"center"}}>
          <AlertTriangle size={16} color={C.red}/>
          <span style={{fontSize:13,color:C.red,fontWeight:700}}>{low.length} artículo(s) con stock menor al mínimo — se requiere reabasto.</span>
        </div>
      )}
      {canEdit&&<div style={{display:"flex",justifyContent:"flex-end"}}><Btn label="Agregar Registro" Icon={Plus} onClick={onNew}/></div>}
      <div style={{...card,padding:0,overflowX:"auto"}}>
        <table style={{width:"100%",borderCollapse:"collapse",minWidth:560}}>
          <thead><tr>{["Artículo","Tipo","Ubicación","Total","Disponible","Reservado","Stock Mín.","Estado"].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
          <tbody>
            {inv.map(r=>{
              const item=items.find(i=>i.id===r.item_id); if(!item) return null;
              const avail=r.qty-r.reserved; const t=ITEM_TYPES[item.type];
              const isCrit=r.qty===0; const isLow=r.qty<=r.min_qty;
              const sc=isCrit?C.red:isLow?C.yellow:C.green;
              const sb=isCrit?C.redBg:isLow?C.yellowBg:C.greenBg;
              const sl=isCrit?"Sin stock":isLow?"Stock bajo":"OK";
              return(
                <tr key={r.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                  <td style={td}><p style={{fontSize:13,fontWeight:600,color:C.text}}>{item.name}</p><p style={{fontSize:11,color:C.faint}}>{item.sku}</p></td>
                  <td style={td}><Tag label={t.label} col={t.col} bg={t.bg}/></td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{r.location}</td>
                  <td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{r.qty} <span style={{fontSize:11,fontWeight:400,color:C.muted}}>{item.unit}</span></td>
                  <td style={{...td,fontSize:13,fontWeight:700,color:avail>0?C.text:C.red}}>{avail} <span style={{fontSize:11,fontWeight:400,color:C.muted}}>{item.unit}</span></td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{r.reserved} {item.unit}</td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{r.min_qty} {item.unit}</td>
                  <td style={td}><Tag label={sl} col={sc} bg={sb}/></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── ORDERS VIEW ───────────────────────────────────────────────────────────────
function OrdersView({orders,onEdit,onNew,onStatus,canEdit}){
  return(
    <div style={{display:"flex",flexDirection:"column",gap:14}}>
      {canEdit&&<div style={{display:"flex",justifyContent:"flex-end"}}><Btn label="Nueva Orden" Icon={Plus} onClick={onNew}/></div>}
      {orders.map(o=>{
        const cost=Number(o.mat)+Number(o.labor)+Number(o.overhead);
        const profit=Number(o.quoted)-cost;
        const margin=Number(o.quoted)===0?"0.0":(profit/Number(o.quoted)*100).toFixed(1);
        const s=statusOf(o.status);
        return(
          <div key={o.id} style={card}>
            <div style={{display:"flex",justifyContent:"space-between",alignItems:"flex-start",flexWrap:"wrap",gap:12}}>
              <div style={{display:"flex",gap:14,alignItems:"center"}}>
                <div style={{background:C.amberBg,borderRadius:8,padding:"8px 12px",textAlign:"center",minWidth:74}}>
                  <p style={{fontSize:10,fontWeight:700,color:C.amber,fontFamily:"monospace"}}>{o.code}</p>
                  <p style={{fontSize:10,color:C.muted,marginTop:2}}>{o.date}</p>
                </div>
                <div>
                  <p style={{fontSize:14,fontWeight:700,color:C.text}}>{o.client}</p>
                  <p style={{fontSize:12,color:C.muted}}>{o.desc}</p>
                </div>
              </div>
              <div style={{display:"flex",gap:8,alignItems:"center",flexWrap:"wrap"}}>
                <Tag label={s.label} col={s.col} bg={s.bg}/>
                <select value={o.status} onChange={e=>onStatus(o.id,e.target.value)}
                  style={{...inp,width:"auto",fontSize:11,padding:"5px 8px"}}>
                  {Object.entries(ORDER_STATUS).map(([v,x])=><option key={v} value={v}>{x.label}</option>)}
                </select>
                {canEdit&&<Btn small secondary label="Editar" Icon={Pencil} onClick={()=>onEdit(o)}/>}
              </div>
            </div>
            <div className="rg2" style={{display:"grid",gridTemplateColumns:"repeat(5,1fr)",gap:8,marginTop:14,paddingTop:14,borderTop:`1px solid ${C.border}`}}>
              {[{l:"Materiales",v:$m(o.mat),c:C.amber},{l:"Mano de Obra",v:$m(o.labor),c:C.purple},
                {l:"Gastos Ind.",v:$m(o.overhead),c:C.teal},{l:"Costo Total",v:$m(cost),c:C.text},
                {l:"Margen",v:`${margin}%`,c:profit>=0?C.green:C.red}].map(x=>(
                <div key={x.l} style={{textAlign:"center"}}>
                  <p style={{fontSize:10,color:C.muted,fontWeight:600}}>{x.l}</p>
                  <p style={{fontSize:16,fontWeight:700,color:x.c,marginTop:2}}>{x.v}</p>
                </div>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

// ── COSTING VIEW ──────────────────────────────────────────────────────────────
function CostingView({orders}){
  const comp=orders.filter(o=>o.status==="completada");
  const tQ=comp.reduce((s,o)=>s+Number(o.quoted),0),tM=comp.reduce((s,o)=>s+Number(o.mat),0);
  const tL=comp.reduce((s,o)=>s+Number(o.labor),0),tO=comp.reduce((s,o)=>s+Number(o.overhead),0);
  const tC=tM+tL+tO, profit=tQ-tC;
  const avg=tQ===0?"0.0":((profit/tQ)*100).toFixed(1);
  return(
    <div style={{display:"flex",flexDirection:"column",gap:16}}>
      <div className="rg1" style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:12}}>
        {[{l:"Total Cotizado",v:$m(tQ),c:C.green},{l:"Costo Real Total",v:$m(tC),c:C.red},
          {l:"Utilidad Bruta",v:$m(profit),sub:`Margen: ${avg}%`,c:profit>=0?C.green:C.red}].map(k=>(
          <div key={k.l} style={{...card,borderLeft:`4px solid ${k.c}`}}>
            <p style={{fontSize:11,fontWeight:700,color:C.muted,textTransform:"uppercase",letterSpacing:.4}}>{k.l}</p>
            <p style={{fontSize:24,fontWeight:700,color:k.c,margin:"4px 0"}}>{k.v}</p>
            {k.sub&&<p style={{fontSize:12,color:C.muted}}>{k.sub}</p>}
          </div>
        ))}
      </div>
      <div style={card}>
        <p style={{fontWeight:700,fontSize:14,color:C.text,marginBottom:14}}>Estructura de Costos (Órdenes Completadas)</p>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:12}}>
          {[{l:"Materiales Directos",v:tM,c:C.amber},{l:"Mano de Obra Directa",v:tL,c:C.purple},{l:"Costos Indirectos (CIF)",v:tO,c:C.teal}].map(r=>{
            const pct=tC===0?0:(r.v/tC*100).toFixed(1);
            return(
              <div key={r.l} style={{background:"#FAFAF9",borderRadius:10,padding:14}}>
                <p style={{fontSize:11,color:C.muted,fontWeight:700}}>{r.l}</p>
                <p style={{fontSize:22,fontWeight:700,color:r.c,margin:"4px 0"}}>{$m(r.v)}</p>
                <div style={{height:5,background:C.border,borderRadius:3,marginTop:8}}>
                  <div style={{width:`${pct}%`,height:"100%",background:r.c,borderRadius:3}}/>
                </div>
                <p style={{fontSize:11,color:C.faint,marginTop:4}}>{pct}% del costo total</p>
              </div>
            );
          })}
        </div>
      </div>
      <div style={{...card,padding:0,overflowX:"auto"}}>
        <div style={{padding:"13px 16px",borderBottom:`1px solid ${C.border}`}}>
          <p style={{fontWeight:700,fontSize:14,color:C.text}}>Análisis por Orden</p>
        </div>
        <table style={{width:"100%",borderCollapse:"collapse",minWidth:560}}>
          <thead><tr>{["OT","Cliente","Cotizado","Costo Real","Utilidad","Margen","Estado"].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
          <tbody>
            {orders.map(o=>{
              const c=Number(o.mat)+Number(o.labor)+Number(o.overhead);
              const p=Number(o.quoted)-c;
              const m=Number(o.quoted)===0?"0.0":(p/Number(o.quoted)*100).toFixed(1);
              const mc=Number(m)>=20?C.green:Number(m)>=10?C.yellow:C.red;
              const mb=Number(m)>=20?C.greenBg:Number(m)>=10?C.yellowBg:C.redBg;
              const s=statusOf(o.status);
              return(
                <tr key={o.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                  <td style={{...td,fontSize:11,fontWeight:700,color:C.amber,fontFamily:"monospace"}}>{o.code}</td>
                  <td style={{...td,fontSize:12,color:C.text}}>{o.client}</td>
                  <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{$m(o.quoted)}</td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{$m(c)}</td>
                  <td style={{...td,fontSize:12,fontWeight:700,color:p>=0?C.green:C.red}}>{$m(p)}</td>
                  <td style={td}><span style={{background:mb,color:mc,padding:"2px 8px",borderRadius:99,fontSize:11,fontWeight:700}}>{m}%</span></td>
                  <td style={td}><Tag label={s.label} col={s.col} bg={s.bg}/></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── REPORTS VIEW ──────────────────────────────────────────────────────────────
function ReportsView({items,inv,orders}){
  const totalVal=inv.reduce((s,r)=>{const i=items.find(x=>x.id===r.item_id);return s+(i?i.cost*r.qty:0);},0);
  return(
    <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:14}}>
      <div style={card}>
        <p style={{fontWeight:700,fontSize:14,color:C.text,marginBottom:14}}>Inventario por Tipo de Artículo</p>
        {Object.entries(ITEM_TYPES).map(([key,t])=>{
          const cnt=items.filter(i=>i.type===key).length;
          const val=inv.filter(r=>items.find(i=>i.id===r.item_id)?.type===key)
                       .reduce((s,r)=>{const i=items.find(x=>x.id===r.item_id);return s+(i?.cost||0)*r.qty;},0);
          return(
            <div key={key} style={{display:"flex",justifyContent:"space-between",padding:"9px 0",borderBottom:`1px solid ${C.border}`}}>
              <div style={{display:"flex",gap:8,alignItems:"center"}}>
                <span style={{width:8,height:8,borderRadius:"50%",background:t.col,display:"inline-block"}}/>
                <span style={{fontSize:13,color:C.text}}>{t.label}</span>
              </div>
              <div style={{textAlign:"right"}}>
                <p style={{fontSize:13,fontWeight:700,color:t.col}}>{$m(val)}</p>
                <p style={{fontSize:11,color:C.faint}}>{cnt} artículo(s)</p>
              </div>
            </div>
          );
        })}
        <div style={{display:"flex",justifyContent:"space-between",paddingTop:10}}>
          <span style={{fontSize:13,fontWeight:700,color:C.text}}>TOTAL EN INVENTARIO</span>
          <span style={{fontSize:16,fontWeight:700,color:C.amber}}>{$m(totalVal)}</span>
        </div>
      </div>
      <div style={card}>
        <p style={{fontWeight:700,fontSize:14,color:C.text,marginBottom:14}}>Resumen de Órdenes de Trabajo</p>
        {Object.entries(ORDER_STATUS).map(([key,s])=>{
          const f=orders.filter(o=>o.status===key);
          const t=f.reduce((sum,o)=>sum+Number(o.quoted),0);
          return(
            <div key={key} style={{display:"flex",justifyContent:"space-between",padding:"9px 0",borderBottom:`1px solid ${C.border}`}}>
              <div style={{display:"flex",gap:8,alignItems:"center"}}>
                <span style={{width:8,height:8,borderRadius:"50%",background:s.col,display:"inline-block"}}/>
                <span style={{fontSize:13,color:C.text}}>{s.label}</span>
              </div>
              <div style={{textAlign:"right"}}>
                <p style={{fontSize:13,fontWeight:700,color:s.col}}>{f.length} orden(es)</p>
                <p style={{fontSize:11,color:C.faint}}>{$m(t)}</p>
              </div>
            </div>
          );
        })}
        <div style={{display:"flex",justifyContent:"space-between",paddingTop:10}}>
          <span style={{fontSize:13,fontWeight:700,color:C.text}}>TOTAL COTIZADO</span>
          <span style={{fontSize:16,fontWeight:700,color:C.green}}>{$m(orders.reduce((s,o)=>s+Number(o.quoted),0))}</span>
        </div>
      </div>
    </div>
  );
}

// ── ITEM MODAL ────────────────────────────────────────────────────────────────
function ItemModal({editing,categories,onSave,onClose}){
  const [f,setF]=useState(editing||{name:"",sku:"",type:"materia_prima",category_id:categories[0]?.id??null,unit:"pieza",cost:0,notes:""});
  const catOpts=[{v:"",l:"Sin categoría"},...categories.map(c=>({v:c.id,l:c.name}))];
  const set=(k,v)=>setF(p=>({...p,[k]:v}));
  const t=ITEM_TYPES[f.type];
  const typeOpts=Object.entries(ITEM_TYPES).map(([k,v])=>({v:k,l:v.label}));
  return(
    <Modal title={f.id?"Editar Artículo":"Nuevo Artículo"} onClose={onClose}>
      <div style={{display:"flex",flexDirection:"column",gap:12}}>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
          <Field label="Nombre del Artículo *" value={f.name} onChange={v=>set("name",v)}/>
          <Field label="SKU / Código *" value={f.sku} onChange={v=>set("sku",v)}/>
        </div>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
          <Field label="Tipo de Artículo" value={f.type} onChange={v=>set("type",v)} opts={typeOpts}/>
          <Field label="Categoría" value={f.category_id??""} onChange={v=>set("category_id",v===""?null:Number(v))} opts={catOpts}/>
        </div>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
          <Field label="Unidad de Medida" value={f.unit} onChange={v=>set("unit",v)} opts={UNITS.includes(f.unit)?UNITS:[f.unit,...UNITS]}/>
          <Field label="Costo Unitario ($)" type="number" value={f.cost} onChange={v=>set("cost",Number(v))}/>
        </div>
        <Field label="Notas / Especificaciones" value={f.notes} onChange={v=>set("notes",v)}/>
        <div style={{background:t.bg,borderRadius:8,padding:"10px 12px",display:"flex",gap:8,alignItems:"center"}}>
          <t.Icon size={14} color={t.col}/>
          <span style={{fontSize:12,color:t.col,fontWeight:500}}>Clasificado como <strong>{t.label}</strong> · El tipo puede cambiarse en cualquier momento.</span>
        </div>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8,paddingTop:8,borderTop:`1px solid ${C.border}`}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label="Guardar Artículo" onClick={()=>{if(!f.name||!f.sku){alert("Nombre y SKU son obligatorios");return;}onSave(f);}}/>
        </div>
      </div>
    </Modal>
  );
}

// ── INV MODAL ─────────────────────────────────────────────────────────────────
function InvModal({editing,items,locations,onSave,onClose}){
  const [f,setF]=useState(editing||{item_id:items[0]?.id??"",location_id:locations[0]?.id??"",qty:0,min_qty:0,reserved:0});
  const set=(k,v)=>setF(p=>({...p,[k]:v}));
  return(
    <Modal title={f.id?"Editar Registro":"Nuevo Registro de Inventario"} onClose={onClose}>
      <div style={{display:"flex",flexDirection:"column",gap:12}}>
        <div>
          <label style={lbl}>Artículo *</label>
          <select value={f.item_id} onChange={e=>set("item_id",Number(e.target.value))} style={inp}>
            {items.map(i=><option key={i.id} value={i.id}>{i.sku} — {i.name}</option>)}
          </select>
        </div>
        <Field label="Ubicación" value={f.location_id} onChange={v=>set("location_id",Number(v))} opts={locations.map(l=>({v:l.id,l:l.name}))}/>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:10}}>
          <Field label="Cantidad Total" type="number" value={f.qty} onChange={v=>set("qty",Number(v))}/>
          <Field label="Stock Mínimo" type="number" value={f.min_qty} onChange={v=>set("min_qty",Number(v))}/>
          <Field label="Reservado" type="number" value={f.reserved} onChange={v=>set("reserved",Number(v))}/>
        </div>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8,paddingTop:8,borderTop:`1px solid ${C.border}`}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label="Guardar Registro" onClick={()=>{if(!f.item_id||!f.location_id){alert("Selecciona un artículo y una ubicación");return;}onSave(f);}}/>
        </div>
      </div>
    </Modal>
  );
}

// ── ORDER MODAL ───────────────────────────────────────────────────────────────
function OrderModal({editing,onSave,onClose}){
  const blank={client:"",desc:"",status:"pendiente",date:new Date().toISOString().slice(0,10),mat:0,labor:0,overhead:0,quoted:0};
  const [f,setF]=useState(editing||blank);
  const set=(k,v)=>setF(p=>({...p,[k]:v}));
  const totalCost=Number(f.mat)+Number(f.labor)+Number(f.overhead);
  const profit=Number(f.quoted)-totalCost;
  const margin=f.quoted==0?"0.0":(profit/Number(f.quoted)*100).toFixed(1);
  const mc=Number(margin)>=20?C.green:Number(margin)>=10?C.yellow:C.red;
  const stOpts=Object.entries(ORDER_STATUS).map(([k,v])=>({v:k,l:v.label}));
  return(
    <Modal title={f.id?"Editar Orden de Trabajo":"Nueva Orden de Trabajo"} onClose={onClose} width={520}>
      <div style={{display:"flex",flexDirection:"column",gap:12}}>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
          <Field label="Cliente *" value={f.client} onChange={v=>set("client",v)}/>
          <Field label="Fecha" type="date" value={f.date} onChange={v=>set("date",v)}/>
        </div>
        <Field label="Descripción del Trabajo *" value={f.desc} onChange={v=>set("desc",v)}/>
        <Field label="Estado" value={f.status} onChange={v=>set("status",v)} opts={stOpts}/>
        <div style={{background:"#FAFAF9",borderRadius:10,padding:14}}>
          <p style={{fontSize:12,fontWeight:700,color:C.muted,marginBottom:10}}>Estructura de Costos (Costeo por Orden de Producción)</p>
          <div className="rg1" style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:10,marginBottom:10}}>
            <Field label="Materiales ($)" type="number" value={f.mat} onChange={v=>set("mat",Number(v))}/>
            <Field label="Mano de Obra ($)" type="number" value={f.labor} onChange={v=>set("labor",Number(v))}/>
            <Field label="CIF / Gastos Ind. ($)" type="number" value={f.overhead} onChange={v=>set("overhead",Number(v))}/>
          </div>
          <Field label="Precio Cotizado al Cliente ($)" type="number" value={f.quoted} onChange={v=>set("quoted",Number(v))}/>
          {/* Live margin preview */}
          <div style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:8,marginTop:12,paddingTop:12,borderTop:`1px solid ${C.border}`}}>
            {[{l:"Costo Total",v:$m(totalCost),c:C.text},{l:"Utilidad",v:$m(profit),c:profit>=0?C.green:C.red},{l:"Margen %",v:`${margin}%`,c:mc}].map(x=>(
              <div key={x.l} style={{textAlign:"center"}}>
                <p style={{fontSize:10,color:C.muted,fontWeight:700}}>{x.l}</p>
                <p style={{fontSize:18,fontWeight:700,color:x.c,marginTop:2}}>{x.v}</p>
              </div>
            ))}
          </div>
        </div>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8,paddingTop:8,borderTop:`1px solid ${C.border}`}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label="Guardar Orden" onClick={()=>{if(!f.client||!f.desc){alert("Cliente y descripción son obligatorios");return;}onSave(f);}}/>
        </div>
      </div>
    </Modal>
  );
}

// ── MAIN APP ──────────────────────────────────────────────────────────────────
const NAV = [
  {id:"dashboard", Icon:LayoutDashboard, label:"Dashboard"},
  {id:"items",     Icon:Package,         label:"Artículos"},
  {id:"inventory", Icon:Archive,         label:"Inventario"},
  {id:"orders",    Icon:FileText,        label:"Órdenes de Trabajo"},
  {id:"costing",   Icon:DollarSign,      label:"Costeo"},
  {id:"reports",   Icon:BarChart2,       label:"Reportes"},
];
const TITLES = {dashboard:"Dashboard",items:"Artículos",inventory:"Inventario",orders:"Órdenes de Trabajo",costing:"Costeo & Márgenes",reports:"Reportes"};

export default function App({ itemsData=[], invData=[], ordersData=[], categoriesData=[], locationsData=[] }){
  const user    = usePage().props.auth.user;
  const isAdmin = user.role === "admin";
  const [view,   setView]   = useState("dashboard");
  const [menuOpen, setMenuOpen] = useState(false);
  // Los datos se leen siempre de las props del servidor (Inertia). Antes se copiaban a
  // useState, que solo usa el valor inicial, y la pantalla no mostraba lo recién guardado.
  // La copia [...] evita modificar el arreglo original al ordenar.
  const items  = itemsData;
  const inv    = invData;
  const orders = [...ordersData].sort((a,b) => new Date(b.date) - new Date(a.date));
  const [modal,  setModal]  = useState(null);
  const [editing,setEditing]= useState(null);

  const lowCount = inv.filter(r=>r.qty<=r.min_qty).length;

  const closeModal = ()=>{ setModal(null); setEditing(null); };

  const saveItem = f => {
    const payload = {
        name: f.name, sku: f.sku, item_type: f.type,
        category_id: f.category_id ?? null,
        unit: f.unit, unit_cost: f.cost, notes: f.notes,
        is_active: true,
      };
      if(f.id){
        router.put(`/items/${f.id}`, payload, { preserveScroll:true, onSuccess: closeModal });
      } else {
        router.post('/items', payload, { preserveScroll:true, onSuccess: closeModal });
      }
  };
  const saveInv = f => {
    router.post('/inventory/stock', {
      item_id:     f.item_id,
      location_id: f.location_id,
      qty:         Number(f.qty),
      min_qty:     Number(f.min_qty),
      reserved:    Number(f.reserved),
    }, { preserveScroll:true, onSuccess: closeModal });
  };
  const saveOrder = f => {
  const payload = {
    client_name:    f.client,
    description:    f.desc,
    status:         f.status,
    start_date:     f.date,
    materials_cost: Number(f.mat),
    labor_cost:     Number(f.labor),
    overhead_cost:  Number(f.overhead),
    quoted_price:   Number(f.quoted),
  };
  if(f.id){
    router.put(`/work-orders/${f.id}`, payload, { preserveScroll:true, onSuccess: closeModal });
  } else {
    router.post('/work-orders', payload, { preserveScroll:true, onSuccess: closeModal });
  }
};
  const deleteItem = id => {
    if(!window.confirm("¿Desactivar este artículo? Se conserva su historial de movimientos.")) return;
    router.delete(`/items/${id}`, { preserveScroll:true });
  };
  // Guarda el nuevo estado de la orden en el servidor (antes solo cambiaba en memoria).
  const changeStatus = (id, status) => {
    router.put(`/work-orders/${id}`, { status }, { preserveScroll:true });
  };
  return(
    <div style={{display:"flex",minHeight:"100vh",fontFamily:"'DM Sans',system-ui,sans-serif",background:C.bg,position:"relative"}}>
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
        * { box-sizing:border-box; margin:0; padding:0; }
        select,input { font-family:inherit; }
        select:focus,input:focus { border-color:#D97706 !important; outline:none; }
        ::-webkit-scrollbar { width:4px; }
        ::-webkit-scrollbar-thumb { background:#D97706; border-radius:2px; }
        /* Barra superior y menú lateral deslizable solo en pantallas pequeñas */
        .mbar,.sbov { display:none; }
        @media (max-width:768px) {
          .sb { position:fixed !important; left:0; top:0; z-index:120; transform:translateX(-100%); transition:transform .2s; }
          .sb.open { transform:none; }
          .sbov { display:block; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:110; }
          .mbar { display:flex; position:fixed; top:0; left:0; right:0; height:52px; background:#1C1917;
                  color:#FAF7F4; align-items:center; gap:12px; padding:0 14px; z-index:100; }
          .mainp { padding:68px 14px 24px !important; }
          .rg1 { grid-template-columns:minmax(0,1fr) !important; }
          .rg2 { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
          .rg1 > *, .rg2 > * { min-width:0; }
        }
      `}</style>

      {/* ── BARRA SUPERIOR (móvil) ── */}
      <div className="mbar">
        <button onClick={()=>setMenuOpen(true)} aria-label="Abrir menú" style={{background:"none",border:"none",color:"#FAF7F4",cursor:"pointer",display:"flex"}}><Menu size={22}/></button>
        <span style={{fontWeight:700,fontSize:15}}>CarpiSys</span>
        <span style={{marginLeft:"auto",fontSize:12,color:"#A8A29E"}}>{TITLES[view]}</span>
      </div>
      {menuOpen && <div className="sbov" onClick={()=>setMenuOpen(false)}/>}

      {/* ── SIDEBAR ── */}
      <aside className={"sb"+(menuOpen?" open":"")} style={{width:224,background:C.sidebar,display:"flex",flexDirection:"column",position:"sticky",top:0,height:"100vh",flexShrink:0}}>
        <div style={{padding:"20px 16px 16px",borderBottom:"1px solid #292524"}}>
          <div style={{display:"flex",gap:10,alignItems:"center"}}>
            <div style={{width:34,height:34,background:C.amber,borderRadius:8,display:"flex",alignItems:"center",justifyContent:"center",fontSize:17}}>🪵</div>
            <div>
              <p style={{fontSize:13,fontWeight:700,color:"#FAF7F4",letterSpacing:.3}}>CarpiSys</p>
              <p style={{fontSize:10,color:"#57534E"}}>Real Carranza · UAN</p>
            </div>
          </div>
        </div>
        <nav style={{flex:1,padding:"10px 8px",display:"flex",flexDirection:"column",gap:2}}>
          {NAV.map(n=>{
            const active=view===n.id;
            return(
              <button key={n.id} onClick={()=>{setView(n.id);setMenuOpen(false);}} style={{
                background:active?"#292524":"transparent",color:active?C.amber:"#A8A29E",
                border:"none",cursor:"pointer",borderRadius:8,padding:"9px 12px",
                display:"flex",alignItems:"center",gap:9,fontSize:13,
                fontWeight:active?700:400,textAlign:"left",width:"100%",transition:"background .1s"}}>
                <n.Icon size={15}/>
                {n.label}
                {n.id==="inventory"&&lowCount>0&&(
                  <span style={{marginLeft:"auto",background:C.red,color:"#fff",borderRadius:99,fontSize:10,fontWeight:700,padding:"1px 6px"}}>{lowCount}</span>
                )}
              </button>
            );
          })}
          {isAdmin&&(
            <button onClick={()=>router.visit('/users')} style={{background:"transparent",color:"#A8A29E",
              border:"none",cursor:"pointer",borderRadius:8,padding:"9px 12px",display:"flex",alignItems:"center",
              gap:9,fontSize:13,textAlign:"left",width:"100%"}}>
              <Users size={15}/>Usuarios
            </button>
          )}
        </nav>
        <div style={{padding:"12px 14px",borderTop:"1px solid #292524"}}>
          <p style={{fontSize:12,fontWeight:700,color:"#FAF7F4",overflow:"hidden",textOverflow:"ellipsis",whiteSpace:"nowrap"}}>{user.name}</p>
          <p style={{fontSize:10,color:"#78716C",marginBottom:8}}>{isAdmin?"Administrador":"Operario"}</p>
          <div style={{display:"flex",gap:6}}>
            {[["Mi perfil",()=>router.visit('/profile')],["Salir",()=>router.post('/logout')]].map(([l,fn])=>(
              <button key={l} onClick={fn} style={{flex:1,background:"#292524",color:"#D6D3D1",border:"none",cursor:"pointer",
                borderRadius:6,padding:"6px 0",fontSize:11,fontWeight:600,fontFamily:"inherit"}}>{l}</button>
            ))}
          </div>
        </div>
      </aside>

      {/* ── MAIN ── */}
      <main className="mainp" style={{flex:1,overflowY:"auto",padding:24,minWidth:0}}>
        <div style={{marginBottom:20}}>
          <h1 style={{fontSize:22,fontWeight:700,color:C.text}}>{TITLES[view]}</h1>
          <p style={{fontSize:12,color:C.muted,marginTop:2}}>Sistema de Inventario y Costeo · Carpintería Real Carranza</p>
        </div>
        {view==="dashboard" && <Dashboard items={items} inv={inv} orders={orders} setView={setView}/>}
        {view==="items"     && <ItemsView canEdit={isAdmin} items={items} inv={inv} onEdit={i=>{setEditing(i);setModal("item");}} onDelete={deleteItem} onNew={()=>{setEditing(null);setModal("item");}}/>}
        {view==="inventory" && <InventoryView canEdit={isAdmin} items={items} inv={inv} onNew={()=>{setEditing(null);setModal("inv");}}/>}
        {view==="orders"    && <OrdersView canEdit={isAdmin} orders={orders} onEdit={o=>{setEditing(o);setModal("order");}} onNew={()=>{setEditing(null);setModal("order");}} onStatus={changeStatus}/>}
        {view==="costing"   && <CostingView orders={orders}/>}
        {view==="reports"   && <ReportsView items={items} inv={inv} orders={orders}/>}
      </main>

      {/* ── MODALS ── */}
      {modal==="item"  && <ItemModal  editing={editing} categories={categoriesData} onSave={saveItem} onClose={closeModal}/>}
      {modal==="inv"   && <InvModal   editing={editing} items={items} locations={locationsData} onSave={saveInv} onClose={closeModal}/>}
      {modal==="order" && <OrderModal editing={editing}                    onSave={saveOrder} onClose={closeModal}/>}
    </div>
  );
}

