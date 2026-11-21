import { useState } from "react";
import { router } from "@inertiajs/react";
import { Trash2, AlertTriangle } from "lucide-react";
import { C, $m, card, inp, lbl, th, td, Tag, Btn, Field, Modal, statusOf } from "./ui";

// Primer mensaje de error que devolvió el servidor (validación o regla de negocio)
const firstError = errs => Object.values(errs || {})[0] || "";

const ErrorBox = ({errs}) => firstError(errs)
  ? <div style={{background:C.redBg,color:C.red,borderRadius:8,padding:"9px 12px",fontSize:12,fontWeight:600}}>{firstError(errs)}</div>
  : null;

// Opciones de inertia para acciones dentro de un modal: no pierden la pantalla ni el scroll.
const stay = extra => ({ preserveScroll:true, preserveState:true, ...extra });

export const MOV_TYPES = {
  entrada:    { label:"Entrada",    sign:"+", col:C.green,  bg:C.greenBg,  hint:"Llega material (compra o recepción)." },
  salida:     { label:"Salida",     sign:"−", col:C.red,    bg:C.redBg,    hint:"Se saca material sin ligarlo a una orden." },
  merma:      { label:"Merma",      sign:"−", col:C.yellow, bg:C.yellowBg, hint:"Desperdicio, daño o pérdida de material." },
  devolucion: { label:"Devolución", sign:"+", col:C.blue,   bg:C.blueBg,   hint:"Regresa material al inventario." },
};
const movType = t => MOV_TYPES[t] ?? { label:String(t), sign:"", col:C.muted, bg:"#F5F4F2" };
const OUT = ["salida","merma"];
const num = n => Number(n).toLocaleString("es-MX",{maximumFractionDigits:4});

const stockOf = (inv,itemId,locId) => inv.find(r=>r.item_id===itemId && r.location_id===locId)?.qty ?? 0;
// Ubicación con más existencias de un artículo (para preseleccionarla al sacar material)
const bestLoc = (inv,itemId,locations) => {
  const row = inv.filter(r=>r.item_id===itemId && r.qty>0).sort((a,b)=>b.qty-a.qty)[0];
  return row ? row.location_id : (locations[0]?.id ?? "");
};

// ── REGISTRAR MOVIMIENTO DE INVENTARIO ───────────────────────────────────────
export function MovementModal({items,inv,locations,isAdmin,onClose}){
  const [f,setF]=useState({item_id:items[0]?.id??"",location_id:locations[0]?.id??"",movement_type:"entrada",quantity:"",unit_cost:"",notes:""});
  const [errs,setErrs]=useState({});
  const [busy,setBusy]=useState(false);
  const set=(k,v)=>setF(p=>({...p,[k]:v}));
  const item=items.find(i=>i.id===f.item_id);
  const avail=stockOf(inv,f.item_id,f.location_id);
  const t=movType(f.movement_type);
  const out=OUT.includes(f.movement_type);
  const over=out && Number(f.quantity)>avail;

  const save=()=>{
    router.post('/inventory/movement',{
      item_id:f.item_id, location_id:f.location_id, movement_type:f.movement_type,
      quantity:f.quantity, notes:f.notes,
      ...(isAdmin && f.movement_type==="entrada" && f.unit_cost!=="" ? {unit_cost:f.unit_cost} : {}),
    },stay({onSuccess:onClose,onError:setErrs,onStart:()=>setBusy(true),onFinish:()=>setBusy(false)}));
  };

  return(
    <Modal title="Registrar movimiento de inventario" onClose={onClose}>
      <div style={{display:"flex",flexDirection:"column",gap:12}}>
        <div>
          <label style={lbl}>Artículo *</label>
          <select value={f.item_id} onChange={e=>{const id=Number(e.target.value); setF(p=>({...p,item_id:id,...(p.movement_type==="entrada"?{}:{location_id:bestLoc(inv,id,locations)})}));}} style={inp}>
            {items.map(i=><option key={i.id} value={i.id}>{i.sku} — {i.name}</option>)}
          </select>
        </div>
        <Field label="Ubicación" value={f.location_id} onChange={v=>set("location_id",Number(v))} opts={locations.map(l=>({v:l.id,l:l.name}))}/>
        <div>
          <label style={lbl}>Tipo de movimiento</label>
          <div style={{display:"flex",gap:6,flexWrap:"wrap"}}>
            {Object.entries(MOV_TYPES).map(([k,x])=>(
              <button key={k} onClick={()=>set("movement_type",k)} style={{
                background:f.movement_type===k?x.col:"#F5F4F2",color:f.movement_type===k?"#fff":C.muted,
                border:"none",cursor:"pointer",borderRadius:8,padding:"7px 13px",fontSize:12,fontWeight:700,fontFamily:"inherit"}}>{x.label}</button>
            ))}
          </div>
          <p style={{fontSize:11,color:C.faint,marginTop:5}}>{t.hint}</p>
        </div>
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
          <Field label={`Cantidad${item?` (${item.unit})`:""} *`} type="number" value={f.quantity} onChange={v=>set("quantity",v)}/>
          {isAdmin && f.movement_type==="entrada"
            ? <Field label="Costo unitario de esta compra ($)" type="number" value={f.unit_cost} onChange={v=>set("unit_cost",v)}/>
            : <div/>}
        </div>
        <p style={{fontSize:12,color:over?C.red:C.muted,fontWeight:over?700:400}}>
          Disponible en esta ubicación: <strong>{num(avail)} {item?.unit}</strong>
          {over && " — la cantidad pedida es mayor a la disponible."}
        </p>
        {isAdmin && f.movement_type==="entrada" &&
          <p style={{fontSize:11,color:C.faint}}>Si capturas el costo, el costo promedio del artículo se actualiza automáticamente. Déjalo vacío para no cambiarlo.</p>}
        <Field label="Notas (proveedor, motivo…)" value={f.notes} onChange={v=>set("notes",v)}/>
        <ErrorBox errs={errs}/>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8,paddingTop:8,borderTop:`1px solid ${C.border}`}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label={busy?"Guardando…":"Registrar"} onClick={()=>{ if(!busy) save(); }}/>
        </div>
      </div>
    </Modal>
  );
}

// ── HISTORIAL DE MOVIMIENTOS ─────────────────────────────────────────────────
export function MovementsTable({movements,isAdmin}){
  return(
    <div style={{...card,padding:0,overflowX:"auto"}}>
      <div style={{padding:"13px 16px",borderBottom:`1px solid ${C.border}`}}>
        <p style={{fontWeight:700,fontSize:14,color:C.text}}>Últimos movimientos</p>
      </div>
      <table style={{width:"100%",borderCollapse:"collapse",minWidth:640}}>
        <thead><tr>{["Fecha","Tipo","Artículo","Cantidad","Ubicación","Registró","Orden",...(isAdmin?["Costo unit."]:[])].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
        <tbody>
          {movements.map(m=>{
            const t=movType(m.type);
            return(
              <tr key={m.id}>
                <td style={{...td,fontSize:11,color:C.muted,whiteSpace:"nowrap"}}>{m.date}</td>
                <td style={td}><Tag label={t.label} col={t.col} bg={t.bg}/></td>
                <td style={{...td,fontSize:12,fontWeight:600,color:C.text}}>{m.item}</td>
                <td style={{...td,fontSize:13,fontWeight:700,color:t.col,whiteSpace:"nowrap"}}>{t.sign}{num(m.qty)} <span style={{fontSize:11,fontWeight:400,color:C.muted}}>{m.unit}</span></td>
                <td style={{...td,fontSize:12,color:C.muted}}>{m.location}</td>
                <td style={{...td,fontSize:12,color:C.muted}}>{m.by}</td>
                <td style={{...td,fontSize:11,fontWeight:700,color:C.amber,fontFamily:"monospace"}}>{m.order||"—"}</td>
                {isAdmin&&<td style={{...td,fontSize:12,color:C.muted}}>{$m(m.cost??0)}</td>}
              </tr>
            );
          })}
          {!movements.length&&<tr><td colSpan={isAdmin?8:7} style={{textAlign:"center",padding:28,color:C.faint,fontSize:13}}>Todavía no hay movimientos registrados.</td></tr>}
        </tbody>
      </table>
    </div>
  );
}

// ── DETALLE DE ORDEN: MATERIALES Y HORAS ─────────────────────────────────────
function Section({title,children,right}){
  return(
    <div style={{border:`1px solid ${C.border}`,borderRadius:10,overflow:"hidden"}}>
      <div style={{background:"#FAFAF9",padding:"9px 14px",display:"flex",justifyContent:"space-between",alignItems:"center",gap:8,flexWrap:"wrap"}}>
        <p style={{fontSize:12,fontWeight:700,color:C.muted,textTransform:"uppercase",letterSpacing:.4}}>{title}</p>{right}
      </div>
      <div style={{padding:14,display:"flex",flexDirection:"column",gap:12}}>{children}</div>
    </div>
  );
}

export function OrderDetailModal({order,items,inv,locations,isAdmin,hasOverheadRates,onClose}){
  const today=new Date().toISOString().slice(0,10);
  const usable=items.filter(i=>i.type!=="herramienta");     // las herramientas no se consumen
  const [mat,setMat]=useState({item_id:usable[0]?.id??"",location_id:usable[0]?bestLoc(inv,usable[0].id,locations):(locations[0]?.id??""),quantity:"",notes:""});
  const [lab,setLab]=useState({hours:"",work_date:today,task_description:"",worker_name:"",hourly_rate:""});
  const [matErr,setMatErr]=useState({});
  const [labErr,setLabErr]=useState({});
  const [busy,setBusy]=useState(false);

  const s=statusOf(order.status);
  const base=`/work-orders/${order.id}`;
  const cost=Number(order.mat)+Number(order.labor)+Number(order.overhead);
  const profit=Number(order.quoted)-cost;
  const margin=Number(order.quoted)===0?"0.0":(profit/Number(order.quoted)*100).toFixed(1);
  const mItem=usable.find(i=>i.id===mat.item_id);
  const avail=stockOf(inv,mat.item_id,mat.location_id);
  const over=Number(mat.quantity)>avail;
  const matHours=order.labor_records.reduce((a,l)=>a+l.hours,0);

  const opts=ex=>stay({onStart:()=>setBusy(true),onFinish:()=>setBusy(false),...ex});

  const addMaterial=()=>router.post(`${base}/materials`,mat,opts({
    onSuccess:()=>{setMat(p=>({...p,quantity:"",notes:""}));setMatErr({});},onError:setMatErr}));
  const removeMaterial=m=>{
    if(!window.confirm(`¿Devolver ${num(m.qty)} ${m.unit} de "${m.name}" al inventario?`)) return;
    router.delete(`${base}/materials/${m.id}`,opts({onError:setMatErr}));
  };
  const addLabor=()=>router.post(`${base}/labor`,{
      hours:lab.hours, work_date:lab.work_date, task_description:lab.task_description,
      ...(isAdmin ? {worker_name:lab.worker_name, hourly_rate:lab.hourly_rate} : {}),
    },opts({onSuccess:()=>{setLab(p=>({...p,hours:"",task_description:"",worker_name:"",hourly_rate:""}));setLabErr({});},onError:setLabErr}));
  const removeLabor=l=>{
    if(!window.confirm(`¿Eliminar ${l.hours} h de ${l.worker}?`)) return;
    router.delete(`${base}/labor/${l.id}`,opts({onError:setLabErr}));
  };

  return(
    <Modal title={`${order.code} · ${order.client}`} onClose={onClose} width={760}>
      <div style={{display:"flex",flexDirection:"column",gap:14}}>
        <div style={{display:"flex",gap:10,alignItems:"center",flexWrap:"wrap"}}>
          <Tag label={s.label} col={s.col} bg={s.bg}/>
          <span style={{fontSize:12,color:C.muted}}>{order.desc}</span>
        </div>

        {!order.is_open&&(
          <div style={{background:C.yellowBg,color:C.yellow,borderRadius:8,padding:"9px 12px",fontSize:12,fontWeight:600,display:"flex",gap:8,alignItems:"center"}}>
            <AlertTriangle size={14}/>Orden {s.label.toLowerCase()}: ya no admite cambios de materiales ni horas. Cámbiala a «En proceso» si necesitas corregirla.
          </div>
        )}

        {isAdmin&&(
          <div>
            <div className="rg2" style={{display:"grid",gridTemplateColumns:"repeat(5,1fr)",gap:8}}>
              {[{l:"Materiales",v:$m(order.mat),c:C.amber},{l:"Mano de obra",v:$m(order.labor),c:C.purple},{l:"CIF",v:$m(order.overhead),c:C.teal},
                {l:"Costo total",v:$m(cost),c:C.text},{l:"Margen",v:`${margin}%`,c:profit>=0?C.green:C.red}].map(x=>(
                <div key={x.l} style={{background:"#FAFAF9",borderRadius:8,padding:"9px 6px",textAlign:"center"}}>
                  <p style={{fontSize:10,color:C.muted,fontWeight:700}}>{x.l}</p>
                  <p style={{fontSize:15,fontWeight:700,color:x.c,marginTop:2}}>{x.v}</p>
                </div>
              ))}
            </div>
            <p style={{fontSize:11,color:C.faint,marginTop:6}}>
              {order.auto
                ? "Costos calculados automáticamente con los materiales y horas registrados."
                : "Costos estimados (capturados a mano). Al registrar el primer material u hora, el sistema los reemplaza por el cálculo real."}
              {order.auto && hasOverheadRates===false && <strong style={{color:C.red}}> No hay tasas CIF activas: el CIF es $0. Defínelas en «Tasas CIF».</strong>}
            </p>
          </div>
        )}

        {/* MATERIALES */}
        <Section title="Materiales usados">
          {order.materials.length>0?(
            <div style={{overflowX:"auto"}}>
              <table style={{width:"100%",borderCollapse:"collapse",minWidth:isAdmin?560:420}}>
                <thead><tr>{["Material","Cantidad","Ubicación","Registró",...(isAdmin?["Costo unit.","Total"]:[]),""].map((h,i)=><th key={i} style={th}>{h}</th>)}</tr></thead>
                <tbody>
                  {order.materials.map(m=>(
                    <tr key={m.id}>
                      <td style={{...td,fontSize:12,fontWeight:600,color:C.text}}>{m.name}<br/><span style={{fontSize:10,color:C.faint,fontWeight:400}}>{m.date}</span></td>
                      <td style={{...td,fontSize:12,color:C.text,whiteSpace:"nowrap"}}>{num(m.qty)} {m.unit}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{m.location}</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{m.by}</td>
                      {isAdmin&&<td style={{...td,fontSize:12,color:C.muted}}>{$m(m.cost)}</td>}
                      {isAdmin&&<td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{$m(m.total)}</td>}
                      <td style={{...td,textAlign:"right"}}>{order.is_open&&m.can_remove&&<Btn small danger Icon={Trash2} onClick={()=>removeMaterial(m)}/>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ):<p style={{fontSize:12,color:C.faint}}>Aún no se ha registrado material en esta orden.</p>}

          {order.is_open&&(
            <div style={{background:"#FAFAF9",borderRadius:8,padding:12,display:"flex",flexDirection:"column",gap:10}}>
              <div className="rg1" style={{display:"grid",gridTemplateColumns:"2fr 1.3fr 1fr",gap:10}}>
                <div>
                  <label style={lbl}>Material</label>
                  <select value={mat.item_id} onChange={e=>{const id=Number(e.target.value); setMat(p=>({...p,item_id:id,location_id:bestLoc(inv,id,locations)}));}} style={inp}>
                    {usable.map(i=><option key={i.id} value={i.id}>{i.sku} — {i.name}</option>)}
                  </select>
                </div>
                <Field label="Ubicación" value={mat.location_id} onChange={v=>setMat(p=>({...p,location_id:Number(v)}))} opts={locations.map(l=>({v:l.id,l:l.name}))}/>
                <Field label={`Cantidad${mItem?` (${mItem.unit})`:""}`} type="number" value={mat.quantity} onChange={v=>setMat(p=>({...p,quantity:v}))}/>
              </div>
              <p style={{fontSize:12,color:over&&mat.quantity!==""?C.red:C.muted,fontWeight:over&&mat.quantity!==""?700:400}}>
                Disponible aquí: <strong>{num(avail)} {mItem?.unit}</strong>{over&&mat.quantity!==""&&" — no alcanza para esa cantidad."}
              </p>
              <Field label="Notas (opcional)" value={mat.notes} onChange={v=>setMat(p=>({...p,notes:v}))}/>
              <ErrorBox errs={matErr}/>
              <div style={{display:"flex",justifyContent:"flex-end"}}>
                <Btn label={busy?"Guardando…":"Usar material (descuenta inventario)"} onClick={()=>{if(!busy)addMaterial();}}/>
              </div>
            </div>
          )}
        </Section>

        {/* HORAS */}
        <Section title="Horas de trabajo" right={<span style={{fontSize:11,color:C.muted,fontWeight:700}}>{num(matHours)} h en total</span>}>
          {order.labor_records.length>0?(
            <div style={{overflowX:"auto"}}>
              <table style={{width:"100%",borderCollapse:"collapse",minWidth:isAdmin?560:380}}>
                <thead><tr>{["Persona","Fecha","Horas","Tarea",...(isAdmin?["Tarifa","Total",""]:[])].map((h,i)=><th key={i} style={th}>{h}</th>)}</tr></thead>
                <tbody>
                  {order.labor_records.map(l=>(
                    <tr key={l.id}>
                      <td style={{...td,fontSize:12,fontWeight:600,color:C.text}}>{l.worker}</td>
                      <td style={{...td,fontSize:12,color:C.muted,whiteSpace:"nowrap"}}>{l.date}</td>
                      <td style={{...td,fontSize:12,color:C.text}}>{num(l.hours)} h</td>
                      <td style={{...td,fontSize:12,color:C.muted}}>{l.task||"—"}</td>
                      {isAdmin&&<td style={{...td,fontSize:12,color:C.muted}}>{$m(l.rate)}/h</td>}
                      {isAdmin&&<td style={{...td,fontSize:12,fontWeight:700,color:C.text}}>{$m(l.total)}</td>}
                      {isAdmin&&<td style={{...td,textAlign:"right"}}>{order.is_open&&<Btn small danger Icon={Trash2} onClick={()=>removeLabor(l)}/>}</td>}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ):<p style={{fontSize:12,color:C.faint}}>Aún no se han registrado horas en esta orden.</p>}

          {order.is_open&&(
            <div style={{background:"#FAFAF9",borderRadius:8,padding:12,display:"flex",flexDirection:"column",gap:10}}>
              <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr 2fr",gap:10}}>
                <Field label="Horas trabajadas" type="number" value={lab.hours} onChange={v=>setLab(p=>({...p,hours:v}))}/>
                <Field label="Fecha" type="date" value={lab.work_date} onChange={v=>setLab(p=>({...p,work_date:v}))}/>
                <Field label="Tarea (opcional)" value={lab.task_description} onChange={v=>setLab(p=>({...p,task_description:v}))}/>
              </div>
              {isAdmin&&(
                <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
                  <Field label="Persona (vacío = tú)" value={lab.worker_name} onChange={v=>setLab(p=>({...p,worker_name:v}))}/>
                  <Field label="Tarifa por hora ($) (vacío = la tuya)" type="number" value={lab.hourly_rate} onChange={v=>setLab(p=>({...p,hourly_rate:v}))}/>
                </div>
              )}
              {!isAdmin&&<p style={{fontSize:11,color:C.faint}}>Las horas se registran a tu nombre.</p>}
              <ErrorBox errs={labErr}/>
              <div style={{display:"flex",justifyContent:"flex-end"}}>
                <Btn label={busy?"Guardando…":"Registrar horas"} onClick={()=>{if(!busy)addLabor();}}/>
              </div>
            </div>
          )}
        </Section>

        <div style={{display:"flex",justifyContent:"flex-end",paddingTop:8,borderTop:`1px solid ${C.border}`}}>
          <Btn secondary label="Cerrar" onClick={onClose}/>
        </div>
      </div>
    </Modal>
  );
}
