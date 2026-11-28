import { useState, useEffect } from "react";
import { router, usePage } from '@inertiajs/react';
import { LayoutDashboard, Package, Archive, FileText, DollarSign, BarChart2,
         AlertTriangle, Plus, Pencil, Trash2, X, Layers, Wrench,
         ShoppingBag, Zap, TrendingUp, CheckCircle, Menu, Users, Percent, ArrowLeftRight, ClipboardList, ShoppingCart } from "lucide-react";

import { MovementModal, MovementsTable, OrderDetailModal } from "../Components/Costeo";
import { PurchasesView, PurchaseFormModal, ReceiveModal, SuppliersModal } from "../Components/Compras";
import { C, ITEM_TYPES, ORDER_STATUS, statusOf, UNITS, $m, card, inp, lbl, th, td, Tag, Btn, Field, Modal } from "../Components/ui";

// Existencia y mínimo POR ARTÍCULO: suma de todas sus ubicaciones (almacenes). El mínimo del
// artículo es la suma de los mínimos de sus ubicaciones; hay alerta cuando la existencia total
// es menor o igual a ese mínimo.
const totalsByItem = inv => {
  const m = new Map();
  inv.forEach(r=>{
    const t = m.get(r.item_id) || {item_id:r.item_id,qty:0,min:0,reserved:0,rows:[]};
    t.qty += r.qty; t.min += r.min_qty; t.reserved += r.reserved; t.rows.push(r);
    m.set(r.item_id,t);
  });
  return m;
};
const lowItems = inv => [...totalsByItem(inv).values()].filter(t=>Math.round(t.qty*1e4)<=Math.round(t.min*1e4));

// ── DASHBOARD ─────────────────────────────────────────────────────────────────
function Dashboard({items,inv,orders,setView,isAdmin,movements,myHours}){
  const low   = lowItems(inv);
  const val   = inv.reduce((s,r)=>{const i=items.find(x=>x.id===r.item_id);return s+(i?i.cost*r.qty:0);},0);
  const comp  = orders.filter(o=>o.status==="completada");
  const rev   = comp.reduce((s,o)=>s+Number(o.quoted),0);
  const cost  = comp.reduce((s,o)=>s+Number(o.mat)+Number(o.labor)+Number(o.overhead),0);
  const mrg   = rev===0?"0.0":((rev-cost)/rev*100).toFixed(1);

  const hoy = new Date().toLocaleDateString("sv-SE");   // AAAA-MM-DD en hora local
  const movsHoy = movements.filter(m=>m.date?.startsWith(hoy)).length;
  const activas = {label:"Órdenes Activas", val:orders.filter(o=>o.status==="en_proceso").length,
                   sub:`${orders.filter(o=>o.status==="pendiente").length} pendientes`, accent:C.blue};
  const bajo    = {label:"Artículos Stock Bajo", val:low.length, sub:low.length?"Requieren reabasto":"Sin alertas", accent:low.length?C.red:C.green};

  // Los operarios no ven importes: el servidor ni siquiera se los envía.
  const kpis = isAdmin ? [
    {label:"Valor en Inventario", val:$m(val), sub:`${inv.length} registros activos`, accent:C.amber},
    activas, bajo,
    {label:"Margen Promedio OTs", val:`${mrg}%`, sub:`${comp.length} órdenes completadas`, accent:C.green},
  ] : [
    activas, bajo,
    {label:"Movimientos Hoy", val:movsHoy, sub:"entradas, salidas y mermas", accent:C.amber},
    {label:"Mis Horas Hoy", val:`${myHours.toLocaleString("es-MX",{maximumFractionDigits:2})} h`, sub:"registradas a tu nombre", accent:C.green},
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
            <thead><tr>{["OT","Cliente","Estado",...(isAdmin?["Cotizado"]:[])].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
            <tbody>
              {orders.slice(0,4).map(o=>{
                const s=statusOf(o.status);
                return(
                  <tr key={o.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                    <td style={{...td,fontSize:11,fontWeight:700,color:C.amber,fontFamily:"monospace"}}>{o.code}</td>
                    <td style={{...td,fontSize:12,fontWeight:600,color:C.text}}>{o.client}<br/><span style={{fontSize:10,color:C.faint}}>{o.desc}</span></td>
                    <td style={td}><Tag label={s.label} col={s.col} bg={s.bg}/></td>
                    {isAdmin&&<td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{$m(o.quoted)}</td>}
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
                  <div key={r.item_id} style={{display:"flex",justifyContent:"space-between",padding:"8px 0",borderBottom:`1px solid ${C.border}`}}>
                    <div>
                      <p style={{fontSize:12,fontWeight:600,color:C.text}}>{item?.name}</p>
                      <p style={{fontSize:11,color:C.muted}}>{r.rows.map(x=>`${x.location} ${x.qty}`).join(" · ")} · Mín: {r.min} {item?.unit}</p>
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
                {isAdmin&&<p style={{fontSize:11,color:t.col,opacity:.8,marginTop:2}}>{$m(v)} en stock</p>}
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
  const totals     = totalsByItem(inv);
  const stockFor   = id=>totals.get(id);
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
          <thead><tr>{["SKU","Nombre","Tipo","Categoría","Unidad",...(canEdit?["Costo Unit."]:[]),"Stock",""].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
          <tbody>
            {filtered.map(item=>{
              const stk=stockFor(item.id); const low=stk&&Math.round(stk.qty*1e4)<=Math.round(stk.min*1e4); const t=ITEM_TYPES[item.type];
              return(
                <tr key={item.id} onMouseEnter={e=>e.currentTarget.style.background="#FFFBF5"} onMouseLeave={e=>e.currentTarget.style.background=""}>
                  <td style={{...td,fontSize:11,fontFamily:"monospace",fontWeight:700,color:C.amber}}>{item.sku}</td>
                  <td style={td}><p style={{fontSize:13,fontWeight:600,color:C.text}}>{item.name}</p>{item.notes&&<p style={{fontSize:11,color:C.faint}}>{item.notes}</p>}</td>
                  <td style={td}><Tag label={t.label} col={t.col} bg={t.bg}/></td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{item.category}</td>
                  <td style={{...td,fontSize:12,color:C.muted}}>{item.unit}</td>
                  {canEdit&&<td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{item.cost>0?$m(item.cost):"—"}</td>}
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
            {!filtered.length&&<tr><td colSpan={canEdit?8:7} style={{textAlign:"center",padding:32,color:C.faint,fontSize:13}}>No se encontraron artículos.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── INVENTORY VIEW ────────────────────────────────────────────────────────────
function InventoryView({items,inv,onNew,onMove,movements,canEdit}){
  const low=lowItems(inv);
  const totals=totalsByItem(inv);
  return(
    <div style={{display:"flex",flexDirection:"column",gap:14}}>
      {low.length>0&&(
        <div style={{background:C.redBg,borderRadius:10,padding:"11px 16px",border:`1px solid #FECACA`,display:"flex",gap:10,alignItems:"center"}}>
          <AlertTriangle size={16} color={C.red}/>
          <span style={{fontSize:13,color:C.red,fontWeight:700}}>{low.length} artículo(s) con stock menor al mínimo — se requiere reabasto.</span>
        </div>
      )}
      <div style={{display:"flex",justifyContent:"flex-end",gap:8,flexWrap:"wrap"}}>
        {canEdit&&<Btn secondary label="Ajustar existencias" Icon={Pencil} onClick={onNew}/>}
        <Btn label="Registrar movimiento" Icon={ArrowLeftRight} onClick={onMove}/>
      </div>
      <div style={{...card,padding:0,overflowX:"auto"}}>
        <table style={{width:"100%",borderCollapse:"collapse",minWidth:560}}>
          <thead><tr>{["Artículo","Tipo","Ubicación","Total","Disponible","Reservado","Stock Mín.","Estado"].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
          <tbody>
            {inv.map(r=>{
              const item=items.find(i=>i.id===r.item_id); if(!item) return null;
              const avail=r.qty-r.reserved; const t=ITEM_TYPES[item.type];
              const tot=totals.get(r.item_id);
              const isCrit=tot.qty===0; const isLow=Math.round(tot.qty*1e4)<=Math.round(tot.min*1e4);
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
      <p style={{fontSize:11,color:C.muted,margin:0}}>La alerta es por artículo: se suma la existencia de todas las ubicaciones y se compara con la suma de sus mínimos.</p>
      <MovementsTable movements={movements} isAdmin={canEdit}/>
    </div>
  );
}

// ── ORDERS VIEW ───────────────────────────────────────────────────────────────
function OrdersView({orders,onEdit,onNew,onStatus,onDetail,canEdit}){
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
                <Btn small label="Materiales y horas" Icon={ClipboardList} onClick={()=>onDetail(o.id)}/>
                {canEdit&&<Btn small secondary label="Editar" Icon={Pencil} onClick={()=>onEdit(o)}/>}
              </div>
            </div>
            {!canEdit&&(
              <p style={{fontSize:12,color:C.muted,marginTop:12,paddingTop:12,borderTop:`1px solid ${C.border}`}}>
                {o.materials.length} material(es) · {o.labor_records.reduce((a,l)=>a+l.hours,0).toLocaleString("es-MX",{maximumFractionDigits:2})} h registradas
              </p>
            )}
            {canEdit&&<div className="rg2" style={{display:"grid",gridTemplateColumns:"repeat(5,1fr)",gap:8,marginTop:14,paddingTop:14,borderTop:`1px solid ${C.border}`}}>
              {[{l:"Materiales",v:$m(o.mat),c:C.amber},{l:"Mano de Obra",v:$m(o.labor),c:C.purple},
                {l:"Gastos Ind.",v:$m(o.overhead),c:C.teal},{l:"Costo Total",v:$m(cost),c:C.text},
                {l:"Margen",v:`${margin}%`,c:profit>=0?C.green:C.red}].map(x=>(
                <div key={x.l} style={{textAlign:"center"}}>
                  <p style={{fontSize:10,color:C.muted,fontWeight:600}}>{x.l}</p>
                  <p style={{fontSize:16,fontWeight:700,color:x.c,marginTop:2}}>{x.v}</p>
                </div>
              ))}
            </div>}
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
function ReportsView({items,inv,orders,weekly,daily,variance}){
  const [period,setPeriod]=useState("semana");
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
      {weekly&&(
        <div style={{...card,padding:0,gridColumn:"1 / -1"}}>
          <div style={{padding:"16px 18px 12px",display:"flex",gap:12,alignItems:"flex-start",justifyContent:"space-between",flexWrap:"wrap"}}>
            <div style={{minWidth:0,flex:"1 1 280px"}}>
              <p style={{fontWeight:700,fontSize:14,color:C.text}}>Movimientos por {period==="semana"?"semana":"día"}</p>
              <p style={{fontSize:12,color:C.muted,marginTop:4}}>
                Cuántas salidas de material quedaron registradas en CarpiSys cada {period==="semana"?"semana (lunes a domingo)":"día"}. Son solo conteos, sin importes ni nombres.
              </p>
            </div>
            <div style={{display:"flex",gap:8,alignItems:"center",flexWrap:"wrap"}}>
              {[["semana","Por semana"],["dia","Por día"]].map(([k,l])=>(
                <button key={k} onClick={()=>setPeriod(k)} style={{
                  background:period===k?C.amber:"#F5F4F2",color:period===k?"#fff":C.muted,border:"none",cursor:"pointer",
                  borderRadius:8,padding:"7px 12px",fontSize:12,fontWeight:700,fontFamily:"inherit"}}>{l}</button>
              ))}
              <a href={period==="semana"?"/reports/movimientos-semanales.csv?semanas=12":"/reports/movimientos-diarios.csv?dias=14"}
                 style={{fontSize:12,fontWeight:700,color:C.amber,textDecoration:"none",border:`1px solid ${C.border}`,borderRadius:8,padding:"7px 12px"}}>Descargar CSV</a>
            </div>
          </div>
          <div style={{overflowX:"auto"}}>
            <table style={{width:"100%",borderCollapse:"collapse",minWidth:620}}>
              <thead><tr>{[period==="semana"?"Semana (lunes)":"Día","Salidas","Con orden","Manuales","Mermas","Devoluciones","Entradas","Órdenes con salidas",...(period==="semana"?["Días con salidas"]:[])].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
              <tbody>
                {[...(period==="semana"?weekly:(daily??[]))].reverse().map(w=>{
                  const k=w.week??w.day;
                  return(
                  <tr key={k}>
                    <td style={{...td,fontSize:12,fontFamily:"monospace",color:C.text,whiteSpace:"nowrap"}}>{k}</td>
                    <td style={{...td,fontSize:13,fontWeight:700,color:w.salidas?C.text:C.faint}}>{w.salidas}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.salidas_orden}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.salidas_manuales}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.mermas}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.devoluciones}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.entradas}</td>
                    <td style={{...td,fontSize:12,color:C.muted}}>{w.ordenes}</td>
                    {period==="semana"&&<td style={{...td,fontSize:12,color:C.muted}}>{w.dias_con_salidas}</td>}
                  </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p style={{fontSize:11,color:C.faint,padding:"10px 18px 14px"}}>
            «Con orden» son salidas ligadas a una orden de trabajo; «Manuales», las hechas desde Inventario sin orden. El total real de salidas lo mides tú en el taller.
          </p>
        </div>
      )}
      {variance&&(
        <div style={{...card,padding:0,gridColumn:"1 / -1"}}>
          <div style={{padding:"16px 18px 12px"}}>
            <p style={{fontWeight:700,fontSize:14,color:C.text}}>Material estimado vs. registrado</p>
            <p style={{fontSize:12,color:C.muted,marginTop:4}}>
              Órdenes completadas que tienen material estimado. «Cobertura» es el porcentaje de líneas estimadas cuyo registro llegó al menos al 90 % de lo estimado.
            </p>
          </div>
          {variance.orders.length>0?(
            <div style={{overflowX:"auto"}}>
              <table style={{width:"100%",borderCollapse:"collapse",minWidth:560}}>
                <thead><tr>{["Orden","Líneas estimadas","Dentro de lo esperado","Menos de lo estimado","Más de lo estimado","Sin estimado","Cobertura"].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
                <tbody>
                  {variance.orders.map(r=>(
                    <tr key={r.id}>
                      <td style={{...td,fontSize:12,fontFamily:"monospace",color:C.text}}>{r.code}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{r.lines}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{r.ok}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{r.low}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{r.excess}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{r.unplanned}</td>
                      <td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{r.coverage===null?"—":`${r.coverage}%`}</td>
                    </tr>
                  ))}
                  <tr>
                    <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>Total ({variance.total.orders})</td>
                    <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{variance.total.lines}</td>
                    <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{variance.total.ok}</td>
                    <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{variance.total.low}</td>
                    <td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{variance.total.excess}</td>
                    <td style={td}></td>
                    <td style={{...td,fontSize:13,fontWeight:700,color:C.text}}>{variance.total.coverage===null?"—":`${variance.total.coverage}%`}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          ):<p style={{fontSize:12,color:C.faint,padding:"0 18px 14px"}}>Aún no hay órdenes completadas con material estimado. Indica el estimado en «Materiales y horas» de cada orden.</p>}
          <p style={{fontSize:11,color:C.faint,padding:"10px 18px 14px"}}>
            Es una aproximación: menos de lo estimado puede ser salidas sin registrar o menor uso; más, desperdicio o un estimado corto. Confírmalo con la observación o el conteo físico.
          </p>
        </div>
      )}
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
          <Field label="Stock Mínimo (de esta ubicación; el del artículo es la suma)" type="number" value={f.min_qty} onChange={v=>set("min_qty",Number(v))}/>
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
  const blank={client:"",desc:"",status:"pendiente",date:new Date().toISOString().slice(0,10),mat:0,labor:0,overhead:0,quoted:0,machine_hours:0,auto:false};
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
          {f.auto
            ? <div style={{background:C.tealBg,color:C.teal,borderRadius:8,padding:"10px 12px",fontSize:12,marginBottom:10,lineHeight:1.5}}>
                Los costos de esta orden se calculan solos con los materiales y horas registrados
                (botón «Materiales y horas»): materiales <strong>{$m(f.mat)}</strong> · mano de obra <strong>{$m(f.labor)}</strong> · CIF <strong>{$m(f.overhead)}</strong>.
              </div>
            : <div className="rg1" style={{display:"grid",gridTemplateColumns:"repeat(3,1fr)",gap:10,marginBottom:10}}>
                <Field label="Materiales ($)" type="number" value={f.mat} onChange={v=>set("mat",Number(v))}/>
                <Field label="Mano de Obra ($)" type="number" value={f.labor} onChange={v=>set("labor",Number(v))}/>
                <Field label="CIF / Gastos Ind. ($)" type="number" value={f.overhead} onChange={v=>set("overhead",Number(v))}/>
              </div>}
          <div style={{marginBottom:10}}>
            <Field label="Horas de máquina (para tasas CIF por hora máquina)" type="number" value={f.machine_hours??0} onChange={v=>set("machine_hours",Number(v))}/>
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
// Costeo y Reportes muestran importes y márgenes: solo para el administrador.
const ADMIN_ONLY = ["costing","reports"];
const NAV = [
  {id:"dashboard", Icon:LayoutDashboard, label:"Dashboard"},
  {id:"items",     Icon:Package,         label:"Artículos"},
  {id:"inventory", Icon:Archive,         label:"Inventario"},
  {id:"orders",    Icon:FileText,        label:"Órdenes de Trabajo"},
  {id:"purchases", Icon:ShoppingCart,    label:"Compras"},
  {id:"costing",  Icon:DollarSign,      label:"Costeo"},
  {id:"reports",   Icon:BarChart2,       label:"Reportes"},
];
const TITLES = {dashboard:"Dashboard",items:"Artículos",inventory:"Inventario",orders:"Órdenes de Trabajo",purchases:"Compras",costing:"Costeo & Márgenes",reports:"Reportes"};

export default function App({ itemsData=[], invData=[], ordersData=[], movementsData=[], categoriesData=[], locationsData=[], myHoursToday=0, hasOverheadRates=null, purchasesData=[], suppliersData=[], weeklyMovements=null, dailyMovements=null, varianceSummary=null }){
  const user    = usePage().props.auth.user;
  const isAdmin = user.role === "admin";
  const [view,   setView]   = useState("dashboard");
  const [menuOpen, setMenuOpen] = useState(false);
  const [detailId, setDetailId] = useState(null);
  const page  = usePage();
  const flash = page.props.flash;
  const [toast, setToast] = useState(null);
  useEffect(()=>{
    if(flash?.success){ setToast(flash.success); const t=setTimeout(()=>setToast(null),4000); return ()=>clearTimeout(t); }
  },[flash]);
  // Los datos se leen siempre de las props del servidor (Inertia). Antes se copiaban a
  // useState, que solo usa el valor inicial, y la pantalla no mostraba lo recién guardado.
  // La copia [...] evita modificar el arreglo original al ordenar.
  const items  = itemsData;
  const inv    = invData;
  const orders = [...ordersData].sort((a,b) => new Date(b.date) - new Date(a.date));
  const [modal,  setModal]  = useState(null);
  const [editing,setEditing]= useState(null);

  const lowCount = lowItems(inv).length;

  const closeModal = ()=>{ setModal(null); setEditing(null); };
  const detailOrder = detailId!==null ? orders.find(o=>o.id===detailId) : null;

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
    ...(f.auto ? {} : {
      materials_cost: Number(f.mat),
      labor_cost:     Number(f.labor),
      overhead_cost:  Number(f.overhead),
    }),
    quoted_price:   Number(f.quoted),
    machine_hours:  Number(f.machine_hours||0),
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
          {NAV.filter(n=>isAdmin||!ADMIN_ONLY.includes(n.id)).map(n=>{
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
          {isAdmin&&[["/overhead-rates",Percent,"Tasas CIF"],["/users",Users,"Usuarios"]].map(([href,Ic,label])=>(
            <button key={href} onClick={()=>router.visit(href)} style={{background:"transparent",color:"#A8A29E",
              border:"none",cursor:"pointer",borderRadius:8,padding:"9px 12px",display:"flex",alignItems:"center",
              gap:9,fontSize:13,textAlign:"left",width:"100%"}}>
              <Ic size={15}/>{label}
            </button>
          ))}
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
        {view==="dashboard" && <Dashboard items={items} inv={inv} orders={orders} setView={setView} isAdmin={isAdmin} movements={movementsData} myHours={myHoursToday}/>}
        {view==="items"     && <ItemsView canEdit={isAdmin} items={items} inv={inv} onEdit={i=>{setEditing(i);setModal("item");}} onDelete={deleteItem} onNew={()=>{setEditing(null);setModal("item");}}/>}
        {view==="inventory" && <InventoryView canEdit={isAdmin} items={items} inv={inv} movements={movementsData} onNew={()=>{setEditing(null);setModal("inv");}} onMove={()=>setModal("move")}/>}
        {view==="orders"    && <OrdersView canEdit={isAdmin} onDetail={setDetailId} orders={orders} onEdit={o=>{setEditing(o);setModal("order");}} onNew={()=>{setEditing(null);setModal("order");}} onStatus={changeStatus}/>}
        {view==="purchases" && <PurchasesView purchases={purchasesData} isAdmin={isAdmin}
          onNew={()=>{setEditing(null);setModal("purchase");}} onEdit={p=>{setEditing(p);setModal("purchase");}}
          onReceive={p=>{setEditing(p);setModal("receive");}} onSuppliers={()=>setModal("suppliers")}/>}
        {isAdmin && view==="costing" &&<CostingView orders={orders}/>}
        {isAdmin && view==="reports" && <ReportsView items={items} inv={inv} orders={orders} weekly={weeklyMovements} daily={dailyMovements} variance={varianceSummary}/>}
      </main>

      {/* ── MODALS ── */}
      {modal==="item"  && <ItemModal  editing={editing} categories={categoriesData} onSave={saveItem} onClose={closeModal}/>}
      {modal==="inv"   && <InvModal   editing={editing} items={items} locations={locationsData} onSave={saveInv} onClose={closeModal}/>}
      {modal==="order" && <OrderModal editing={editing}                    onSave={saveOrder} onClose={closeModal}/>}
      {modal==="move"  && <MovementModal items={items} inv={inv} locations={locationsData} isAdmin={isAdmin} onClose={closeModal}/>}
      {modal==="purchase"  && isAdmin && <PurchaseFormModal editing={editing} suppliers={suppliersData} items={items} inv={inv} onClose={closeModal}/>}
      {modal==="receive"   && editing && <ReceiveModal key={editing.id} po={purchasesData.find(p=>p.id===editing.id) ?? editing} inv={inv} locations={locationsData} isAdmin={isAdmin} onClose={closeModal}/>}
      {modal==="suppliers" && isAdmin && <SuppliersModal suppliers={suppliersData} onClose={closeModal}/>}
      {detailOrder     && <OrderDetailModal order={detailOrder} items={items} inv={inv} locations={locationsData} isAdmin={isAdmin} hasOverheadRates={hasOverheadRates} onClose={()=>setDetailId(null)}/>}

      {/* ── AVISO DE CONFIRMACIÓN ── */}
      {toast&&<div role="status" style={{position:"fixed",top:16,right:16,zIndex:200,background:"#064E3B",color:"#fff",
        padding:"10px 16px",borderRadius:10,fontSize:13,fontWeight:600,boxShadow:"0 6px 20px rgba(0,0,0,.25)",maxWidth:"calc(100vw - 32px)"}}>{toast}</div>}
    </div>
  );
}

