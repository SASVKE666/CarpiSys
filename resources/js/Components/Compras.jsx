import { useState } from "react";
import { router } from "@inertiajs/react";
import { Plus, Pencil, Trash2, Send, PackageCheck, ChevronDown, ChevronRight, XCircle, Store, AlertTriangle } from "lucide-react";
import { C, $m, card, inp, lbl, th, td, Tag, Btn, Field, Modal, ErrorBox, stay, firstError } from "./ui";

// Pantalla de Compras: órdenes de compra a proveedores, recepción de mercancía y proveedores.
// Los operarios ven qué se pidió y qué falta por llegar (sin importes) y pueden recibir mercancía.

export const PO_STATUS = {
  borrador:  { label:"Borrador",          col:C.muted,  bg:"#F5F4F2" },
  enviada:   { label:"Enviada",           col:C.blue,   bg:C.blueBg },
  parcial:   { label:"Recepción parcial", col:C.yellow, bg:C.yellowBg },
  recibida:  { label:"Recibida",          col:C.green,  bg:C.greenBg },
  cancelada: { label:"Cancelada",         col:C.red,    bg:C.redBg },
};

const num = n => Number(n).toLocaleString("es-MX",{maximumFractionDigits:4});
const round4 = n => Math.round(n*10000)/10000;
const waiting = p => p.status==="enviada" || p.status==="parcial";
const pendingOf = l => round4(l.ordered - l.received);

// Ubicación con más existencias de un artículo (para preseleccionarla al recibir)
const bestLoc = (inv,itemId,locations) => {
  const row = inv.filter(r=>r.item_id===itemId && r.qty>0).sort((a,b)=>b.qty-a.qty)[0];
  return row ? row.location_id : (locations[0]?.id ?? "");
};

// Campo numérico de una celda: selecciona todo al hacer clic (igual que Field)
function Num({value,onChange,placeholder,step="any"}){
  return <input type="number" min="0" step={step} inputMode="decimal" value={value} placeholder={placeholder}
    onChange={e=>onChange(e.target.value)}
    onFocus={e=>{const t=e.target; setTimeout(()=>t.select(),0);}} style={inp}/>;
}

// ═══════════════════════════ LISTA DE ÓRDENES ═══════════════════════════
export function PurchasesView({purchases,isAdmin,onNew,onEdit,onReceive,onSuppliers}){
  const [flt,setFlt]   = useState("pending");
  const [open,setOpen] = useState({});
  const today = new Date().toLocaleDateString("en-CA");   // YYYY-MM-DD en hora local

  const FILTERS = [
    ["pending","Por recibir", waiting],
    ["draft",  "Borradores", p=>p.status==="borrador"],
    ["done",   "Recibidas",  p=>p.status==="recibida"],
    ["cancel", "Canceladas", p=>p.status==="cancelada"],
    ["all",    "Todas",      ()=>true],
  ];
  const list = purchases.filter(FILTERS.find(f=>f[0]===flt)[2]);

  const act = (method,url,confirmMsg) => {
    if(confirmMsg && !window.confirm(confirmMsg)) return;
    router[method](url,{},stay({onError:e=>window.alert(firstError(e))}));
  };

  return(
    <div style={{display:"flex",flexDirection:"column",gap:14}}>
      <div style={{display:"flex",gap:8,flexWrap:"wrap",alignItems:"center"}}>
        {FILTERS.map(([k,l,fn])=>{
          const n = purchases.filter(fn).length, on = flt===k;
          return <button key={k} onClick={()=>setFlt(k)} style={{background:on?C.amber:"#fff",color:on?"#fff":C.muted,
            border:`1.5px solid ${on?C.amber:C.border}`,borderRadius:99,padding:"6px 14px",fontSize:12,fontWeight:600,cursor:"pointer",fontFamily:"inherit"}}>
            {l} <span style={{opacity:.8}}>({n})</span></button>;
        })}
        {isAdmin && <div style={{marginLeft:"auto",display:"flex",gap:8,flexWrap:"wrap"}}>
          <Btn secondary label="Proveedores" Icon={Store} onClick={onSuppliers}/>
          <Btn label="Nueva orden de compra" Icon={Plus} onClick={onNew}/>
        </div>}
      </div>

      {list.length===0 && <div style={{...card,textAlign:"center",color:C.muted,fontSize:13,padding:"32px 20px"}}>
        {purchases.length===0
          ? (isAdmin ? "Aún no hay órdenes de compra. Crea la primera con \"Nueva orden de compra\"." : "Aún no hay órdenes de compra.")
          : "No hay órdenes en esta categoría."}
      </div>}

      {list.map(p=>{
        const st = PO_STATUS[p.status] ?? {label:p.status,col:C.muted,bg:"#F5F4F2"};
        const isOpen = !!open[p.id];
        const late = waiting(p) && p.expected && p.expected < today;
        const pendingLines = p.lines.filter(l=>pendingOf(l)>0).length;
        return(
          <div key={p.id} style={{...card,padding:0,overflow:"hidden"}}>
            <div onClick={()=>setOpen(o=>({...o,[p.id]:!o[p.id]}))}
              style={{display:"flex",alignItems:"center",gap:10,padding:"13px 16px",flexWrap:"wrap",cursor:"pointer"}}>
              {isOpen?<ChevronDown size={16} color={C.muted}/>:<ChevronRight size={16} color={C.muted}/>}
              <span style={{fontFamily:"monospace",fontWeight:700,color:C.amber,fontSize:13}}>{p.code}</span>
              <span style={{fontWeight:600,fontSize:14,color:C.text}}>{p.supplier}</span>
              <Tag label={st.label} col={st.col} bg={st.bg}/>
              {late && <Tag label="Atrasada" col={C.red} bg={C.redBg}/>}
              <span style={{fontSize:12,color:C.muted}}>{p.lines.length} artículo{p.lines.length===1?"":"s"}{waiting(p)&&pendingLines>0?` · ${pendingLines} por llegar`:""}</span>
              <div style={{marginLeft:"auto",display:"flex",alignItems:"center",gap:10}}>
                {isAdmin && p.total!==null && <span style={{fontWeight:700,fontSize:14,color:C.text}}>{$m(p.total)}</span>}
                {waiting(p) && <span onClick={e=>e.stopPropagation()}><Btn small label="Recibir mercancía" Icon={PackageCheck} onClick={()=>onReceive(p)}/></span>}
              </div>
            </div>

            {isOpen && <div style={{borderTop:`1px solid ${C.border}`,padding:"14px 16px",display:"flex",flexDirection:"column",gap:12}}>
              <div style={{fontSize:12,color:C.muted,display:"flex",gap:18,flexWrap:"wrap"}}>
                {p.ordered && <span>Enviada: <b style={{color:C.text}}>{p.ordered}</b></span>}
                {p.expected && <span>Esperada: <b style={{color:late?C.red:C.text}}>{p.expected}</b></span>}
                {p.received && <span>Recibida: <b style={{color:C.text}}>{p.received}</b></span>}
              </div>
              {p.notes && <div style={{fontSize:12,color:C.muted}}>Notas: <span style={{color:C.text}}>{p.notes}</span></div>}

              <div style={{overflowX:"auto"}}>
                <table style={{width:"100%",borderCollapse:"collapse",minWidth:isAdmin?520:0}}>
                  <thead><tr>{["Artículo","Recibido / pedido","Pendiente",...(isAdmin?["Costo","Subtotal"]:[])].map(h=><th key={h} style={th}>{h}</th>)}</tr></thead>
                  <tbody>{p.lines.map(l=>{
                    const pend = pendingOf(l);
                    return <tr key={l.id}>
                      <td style={td}><div style={{fontWeight:600,fontSize:13}}>{l.name}</div><div style={{fontSize:11,color:C.muted}}>{l.sku}</div></td>
                      <td style={{...td,fontSize:13}}>{num(l.received)} / {num(l.ordered)} <span style={{color:C.muted,fontSize:11}}>{l.unit}</span></td>
                      <td style={{...td,fontSize:13,fontWeight:700,color:pend>0&&waiting(p)?C.yellow:C.muted}}>{pend>0?num(pend):"—"}</td>
                      {isAdmin&&<td style={{...td,fontSize:13}}>{l.cost!==null?$m(l.cost):"—"}</td>}
                      {isAdmin&&<td style={{...td,fontSize:13}}>{l.subtotal!==null?$m(l.subtotal):"—"}</td>}
                    </tr>;
                  })}</tbody>
                </table>
              </div>

              {isAdmin && p.subtotal!==null && <div style={{alignSelf:"flex-end",fontSize:12,color:C.muted,textAlign:"right",lineHeight:1.7}}>
                Subtotal <b style={{color:C.text}}>{$m(p.subtotal)}</b> · IVA {p.tax_rate}% <b style={{color:C.text}}>{$m(p.tax)}</b> · Total <b style={{color:C.text}}>{$m(p.total)}</b>
              </div>}

              {isAdmin && <div style={{display:"flex",gap:8,flexWrap:"wrap"}}>
                {p.status==="borrador" && <>
                  <Btn small secondary label="Editar" Icon={Pencil} onClick={()=>onEdit(p)}/>
                  <Btn small label="Marcar como enviada" Icon={Send} onClick={()=>act("post",`/purchase-orders/${p.id}/send`)}/>
                  <Btn small danger label="Eliminar borrador" Icon={Trash2} onClick={()=>act("delete",`/purchase-orders/${p.id}`,`¿Eliminar el borrador ${p.code}?`)}/>
                </>}
                {p.status==="enviada" && <Btn small danger label="Cancelar orden" Icon={XCircle}
                  onClick={()=>act("post",`/purchase-orders/${p.id}/cancel`,`¿Cancelar la orden ${p.code}? Todavía no se ha recibido nada.`)}/>}
                {p.status==="parcial" && <Btn small secondary label="Dar por recibida (no llegará el resto)" Icon={PackageCheck}
                  onClick={()=>act("post",`/purchase-orders/${p.id}/close`,`¿Dar por recibida ${p.code} con lo que ya llegó? Los artículos pendientes se quitan de la orden.`)}/>}
              </div>}
            </div>}
          </div>
        );
      })}
    </div>
  );
}

// ═══════════════════════════ NUEVA / EDITAR ORDEN ═══════════════════════════
export function PurchaseFormModal({editing,suppliers,items,inv,onClose}){
  const activeSup = suppliers.filter(s=>s.is_active || s.id===editing?.supplier_id);
  const [f,setF] = useState(editing ? {
    supplier_id: editing.supplier_id, expected_date: editing.expected||"", tax_rate: editing.tax_rate ?? 16, notes: editing.notes||"",
    lines: editing.lines.map(l=>({item_id:l.item_id, quantity:l.ordered, unit_cost:l.cost ?? ""})),
  } : {
    supplier_id: activeSup[0]?.id ?? "", expected_date:"", tax_rate:16, notes:"", lines:[],
  });
  const [errs,setErrs] = useState({});
  const [busy,setBusy] = useState(false);
  const set = (k,v) => setF(p=>({...p,[k]:v}));
  const setLine = (i,patch) => setF(p=>({...p,lines:p.lines.map((l,j)=>j===i?{...l,...patch}:l)}));
  const costOf = id => items.find(x=>x.id===id)?.cost ?? "";

  const addLine = () => {
    const used = new Set(f.lines.map(l=>l.item_id));
    const it = items.find(i=>!used.has(i.id));
    if(!it) return window.alert("Ya agregaste todos los artículos.");
    set("lines",[...f.lines,{item_id:it.id,quantity:"",unit_cost:it.cost ?? ""}]);
  };

  // Artículos con existencia disponible por debajo del mínimo; propone completar hasta el doble del mínimo.
  const addLowStock = () => {
    const used = new Set(f.lines.map(l=>l.item_id));
    const add = [];
    items.forEach(it=>{
      if(used.has(it.id)) return;
      const rows = inv.filter(r=>r.item_id===it.id);
      const avail = rows.reduce((s,r)=>s+r.qty-r.reserved,0), min = rows.reduce((s,r)=>s+r.min_qty,0);
      if(min>0 && avail<min) add.push({item_id:it.id,quantity:Math.round((min*2-avail)*100)/100,unit_cost:it.cost ?? ""});
    });
    if(!add.length) return window.alert("No hay artículos con stock por debajo del mínimo (o ya están en la orden).");
    set("lines",[...f.lines,...add]);
  };

  const subtotal = f.lines.reduce((s,l)=>s+(Number(l.quantity)||0)*(Number(l.unit_cost)||0),0);
  const tax = subtotal*(Number(f.tax_rate)||0)/100;

  const save = () => {
    const payload = {
      supplier_id: f.supplier_id || null, expected_date: f.expected_date || null,
      tax_rate: f.tax_rate===""?null:f.tax_rate, notes: f.notes || null,
      items: f.lines.map(l=>({item_id:l.item_id,quantity:l.quantity,unit_cost:l.unit_cost})),
    };
    const opts = stay({onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onSuccess:onClose,onError:setErrs});
    editing ? router.put(`/purchase-orders/${editing.id}`,payload,opts) : router.post("/purchase-orders",payload,opts);
  };

  return(
    <Modal title={editing?`Editar ${editing.code}`:"Nueva orden de compra"} onClose={onClose} width={720}>
      <div style={{display:"flex",flexDirection:"column",gap:14}}>
        {activeSup.length===0 && <div style={{background:C.yellowBg,color:C.yellow,borderRadius:8,padding:"9px 12px",fontSize:12,fontWeight:600}}>
          Primero agrega un proveedor (botón "Proveedores").</div>}
        <div className="rg1" style={{display:"grid",gridTemplateColumns:"2fr 1fr 1fr",gap:12}}>
          <Field label="Proveedor *" value={f.supplier_id} onChange={v=>set("supplier_id",Number(v))} opts={activeSup.map(s=>({v:s.id,l:s.name}))}/>
          <Field label="Fecha esperada" type="date" value={f.expected_date} onChange={v=>set("expected_date",v)}/>
          <Field label="IVA (%)" type="number" value={f.tax_rate} onChange={v=>set("tax_rate",v)}/>
        </div>

        <div style={{display:"flex",flexDirection:"column",gap:8}}>
          <div style={{display:"flex",justifyContent:"space-between",alignItems:"center",flexWrap:"wrap",gap:8}}>
            <span style={{...lbl,marginBottom:0}}>Artículos a comprar *</span>
            <div style={{display:"flex",gap:8,flexWrap:"wrap"}}>
              <Btn small secondary label="Agregar los de stock bajo" Icon={AlertTriangle} onClick={addLowStock}/>
              <Btn small label="Agregar artículo" Icon={Plus} onClick={addLine}/>
            </div>
          </div>
          {f.lines.length===0 && <div style={{border:`1.5px dashed ${C.border}`,borderRadius:8,padding:16,textAlign:"center",fontSize:12,color:C.muted}}>
            Agrega los artículos que vas a pedir. "Agregar los de stock bajo" propone la cantidad para llegar al doble del mínimo.</div>}
          {f.lines.map((l,i)=>{
            const taken = new Set(f.lines.filter((_,j)=>j!==i).map(x=>x.item_id));
            const it = items.find(x=>x.id===l.item_id);
            return(
              <div key={i} className="rg1" style={{display:"grid",gridTemplateColumns:"3fr 1fr 1fr auto",gap:8,alignItems:"end",
                background:"#FAF7F4",border:`1px solid ${C.border}`,borderRadius:8,padding:"10px 12px"}}>
                <div><label style={lbl}>Artículo</label>
                  <select value={l.item_id} style={inp} onChange={e=>{
                    const id=Number(e.target.value);
                    // si el costo no se había tocado, se reemplaza por el del nuevo artículo
                    setLine(i,{item_id:id,...(String(l.unit_cost)===String(costOf(l.item_id))?{unit_cost:costOf(id)}:{})});
                  }}>
                    {items.filter(x=>!taken.has(x.id)).map(x=><option key={x.id} value={x.id}>{x.name} ({x.sku})</option>)}
                  </select></div>
                <div><label style={lbl}>Cantidad{it?` (${it.unit})`:""}</label><Num value={l.quantity} onChange={v=>setLine(i,{quantity:v})} placeholder="0"/></div>
                <div><label style={lbl}>Costo unitario ($)</label><Num value={l.unit_cost} onChange={v=>setLine(i,{unit_cost:v})} placeholder="0"/></div>
                <div style={{display:"flex",alignItems:"center",gap:8,paddingBottom:2}}>
                  <span style={{fontSize:12,fontWeight:700,color:C.text,minWidth:70,textAlign:"right"}}>{$m((Number(l.quantity)||0)*(Number(l.unit_cost)||0))}</span>
                  <Btn small danger Icon={Trash2} onClick={()=>set("lines",f.lines.filter((_,j)=>j!==i))}/>
                </div>
              </div>
            );
          })}
        </div>

        <Field label="Notas" value={f.notes} onChange={v=>set("notes",v)}/>

        <div style={{background:"#FAF7F4",borderRadius:8,padding:"10px 14px",fontSize:12,color:C.muted,textAlign:"right",lineHeight:1.8}}>
          Subtotal <b style={{color:C.text}}>{$m(subtotal)}</b> · IVA {Number(f.tax_rate)||0}% <b style={{color:C.text}}>{$m(tax)}</b> ·
          Total <b style={{color:C.text,fontSize:15}}>{$m(subtotal+tax)}</b>
        </div>

        <ErrorBox errs={errs}/>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label={busy?"Guardando…":(editing?"Guardar cambios":"Crear borrador")} onClick={save}/>
        </div>
        <p style={{fontSize:11,color:C.faint}}>Se guarda como borrador. Cuando la pidas al proveedor, márcala como enviada; el inventario solo cambia al recibir la mercancía.</p>
      </div>
    </Modal>
  );
}

// ═══════════════════════════ RECIBIR MERCANCÍA ═══════════════════════════
export function ReceiveModal({po,inv,locations,isAdmin,onClose}){
  const [rows,setRows] = useState(po.lines.filter(l=>pendingOf(l)>0).map(l=>({
    line_id:l.id, name:l.name, unit:l.unit, pending:pendingOf(l), quantity:pendingOf(l),
    location_id:bestLoc(inv,l.item_id,locations), cost:l.cost ?? "", origCost:l.cost,
  })));
  const [errs,setErrs] = useState({});
  const [busy,setBusy] = useState(false);
  const setRow = (i,patch) => setRows(rs=>rs.map((r,j)=>j===i?{...r,...patch}:r));

  const save = () => {
    const lines = rows.map(r=>({
      line_id:r.line_id, quantity:r.quantity===""?0:r.quantity, location_id:r.location_id,
      ...(isAdmin && r.cost!=="" ? {unit_cost:r.cost} : {}),   // solo el administrador puede corregir el costo
    }));
    router.post(`/purchase-orders/${po.id}/receive`,{lines},stay({onStart:()=>setBusy(true),onFinish:()=>setBusy(false),onSuccess:onClose,onError:setErrs}));
  };

  return(
    <Modal title={`Recibir mercancía · ${po.code}`} onClose={onClose} width={680}>
      <div style={{display:"flex",flexDirection:"column",gap:12}}>
        <p style={{fontSize:12,color:C.muted}}>{po.supplier}. Escribe lo que <b>realmente llegó</b> (ya viene lo pendiente; pon 0 en lo que no llegó).
          Cada artículo se registra como entrada de inventario en la ubicación que elijas.</p>

        {rows.map((r,i)=>{
          const over = Number(r.quantity) > r.pending + 0.00001;
          return(
            <div key={r.line_id} style={{background:"#FAF7F4",border:`1px solid ${over?C.red:C.border}`,borderRadius:8,padding:"10px 12px",display:"flex",flexDirection:"column",gap:8}}>
              <div style={{display:"flex",justifyContent:"space-between",gap:8,flexWrap:"wrap"}}>
                <span style={{fontWeight:600,fontSize:13}}>{r.name}</span>
                <span style={{fontSize:12,color:C.muted}}>Pendiente: <b style={{color:C.text}}>{num(r.pending)} {r.unit}</b></span>
              </div>
              <div className="rg1" style={{display:"grid",gridTemplateColumns:isAdmin?"1fr 2fr 1fr":"1fr 2fr",gap:10}}>
                <div><label style={lbl}>Llegó ({r.unit})</label><Num value={r.quantity} onChange={v=>setRow(i,{quantity:v})}/></div>
                <div><label style={lbl}>Se guarda en</label>
                  <select value={r.location_id} style={inp} onChange={e=>setRow(i,{location_id:Number(e.target.value)})}>
                    {locations.map(l=><option key={l.id} value={l.id}>{l.name}</option>)}
                  </select></div>
                {isAdmin && <div><label style={lbl}>Costo unitario ($)</label><Num value={r.cost} onChange={v=>setRow(i,{cost:v})}/></div>}
              </div>
              {over && <div style={{fontSize:11,fontWeight:600,color:C.red}}>Es más de lo que falta por recibir ({num(r.pending)}).</div>}
              {isAdmin && r.cost!=="" && Number(r.cost)!==Number(r.origCost) && <div style={{fontSize:11,color:C.yellow}}>
                Cambiaste el costo (antes {$m(r.origCost)}): se actualiza la orden y el costo promedio del artículo.</div>}
            </div>
          );
        })}

        <ErrorBox errs={errs}/>
        <div style={{display:"flex",justifyContent:"flex-end",gap:8}}>
          <Btn secondary label="Cancelar" onClick={onClose}/>
          <Btn label={busy?"Registrando…":"Registrar recepción"} Icon={PackageCheck} onClick={save}/>
        </div>
      </div>
    </Modal>
  );
}

// ═══════════════════════════ PROVEEDORES ═══════════════════════════
export function SuppliersModal({suppliers,onClose}){
  const blank = {name:"",contact:"",phone:"",email:"",notes:""};
  const [f,setF]       = useState(blank);
  const [editId,setEdit] = useState(null);
  const [errs,setErrs] = useState({});
  const set = (k,v) => setF(p=>({...p,[k]:v}));

  const edit = s => { setEdit(s.id); setF({name:s.name,contact:s.contact,phone:s.phone,email:s.email,notes:s.notes}); setErrs({}); };
  const reset = () => { setEdit(null); setF(blank); setErrs({}); };

  const save = () => {
    const opts = stay({onSuccess:reset,onError:setErrs});
    editId ? router.put(`/suppliers/${editId}`,{...f,is_active:suppliers.find(s=>s.id===editId)?.is_active ?? true},opts)
           : router.post("/suppliers",f,opts);
  };
  const toggle = s => router.put(`/suppliers/${s.id}`,{name:s.name,contact:s.contact,phone:s.phone,email:s.email,notes:s.notes,address:s.address,is_active:!s.is_active},stay());

  return(
    <Modal title="Proveedores" onClose={onClose} width={620}>
      <div style={{display:"flex",flexDirection:"column",gap:14}}>
        <div style={{display:"flex",flexDirection:"column",gap:6,maxHeight:240,overflowY:"auto"}}>
          {suppliers.length===0 && <div style={{fontSize:12,color:C.muted}}>Aún no hay proveedores.</div>}
          {suppliers.map(s=>(
            <div key={s.id} style={{display:"flex",alignItems:"center",gap:8,flexWrap:"wrap",padding:"8px 10px",border:`1px solid ${C.border}`,borderRadius:8,opacity:s.is_active?1:.6}}>
              <div style={{flex:1,minWidth:150}}>
                <div style={{fontWeight:600,fontSize:13}}>{s.name}</div>
                <div style={{fontSize:11,color:C.muted}}>{[s.contact,s.phone,s.email].filter(Boolean).join(" · ")||"Sin datos de contacto"}</div>
              </div>
              <Btn small secondary label="Editar" Icon={Pencil} onClick={()=>edit(s)}/>
              <Btn small secondary label={s.is_active?"Desactivar":"Activar"} onClick={()=>toggle(s)}/>
            </div>
          ))}
        </div>

        <div style={{borderTop:`1px solid ${C.border}`,paddingTop:12,display:"flex",flexDirection:"column",gap:10}}>
          <span style={{...lbl,marginBottom:0}}>{editId?"Editar proveedor":"Nuevo proveedor"}</span>
          <div className="rg1" style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:10}}>
            <Field label="Nombre *" value={f.name} onChange={v=>set("name",v)}/>
            <Field label="Contacto" value={f.contact} onChange={v=>set("contact",v)}/>
            <Field label="Teléfono" value={f.phone} onChange={v=>set("phone",v)}/>
            <Field label="Correo" type="email" value={f.email} onChange={v=>set("email",v)}/>
          </div>
          <Field label="Notas" value={f.notes} onChange={v=>set("notes",v)}/>
          <ErrorBox errs={errs}/>
          <div style={{display:"flex",justifyContent:"flex-end",gap:8}}>
            {editId && <Btn secondary label="Cancelar edición" onClick={reset}/>}
            <Btn label={editId?"Guardar cambios":"Agregar proveedor"} onClick={save}/>
          </div>
          <p style={{fontSize:11,color:C.faint}}>Los proveedores no se borran: se desactivan para conservar el historial de compras.</p>
        </div>
      </div>
    </Modal>
  );
}
