import { Layers, Wrench, ShoppingBag, Zap, X } from "lucide-react";

// Piezas de interfaz compartidas por el Dashboard y las pantallas de costeo.

// ── COLOR SYSTEM ──────────────────────────────────────────────────────────────
export const C = {
  bg:"#FAF7F4", card:"#FFFFFF", sidebar:"#1C1917", border:"#E7E5E4",
  amber:"#D97706", amberBg:"#FEF3C7", text:"#1C1917", muted:"#78716C", faint:"#A8A29E",
  green:"#059669", greenBg:"#D1FAE5", red:"#DC2626", redBg:"#FEE2E2",
  yellow:"#B45309", yellowBg:"#FEF3C7", blue:"#1D4ED8", blueBg:"#DBEAFE",
  purple:"#6D28D9", purpleBg:"#EDE9FE", teal:"#0E7490", tealBg:"#CFFAFE",
};
export const ITEM_TYPES = {
  materia_prima:{ label:"Materia Prima", Icon:Layers,      col:C.amber,  bg:C.amberBg  },
  herramienta:  { label:"Herramienta",   Icon:Wrench,      col:C.purple, bg:C.purpleBg },
  producto:     { label:"Producto",      Icon:ShoppingBag, col:C.green,  bg:C.greenBg  },
  consumible:   { label:"Consumible",    Icon:Zap,         col:C.teal,   bg:C.tealBg   },
};
export const ORDER_STATUS = {
  pendiente: { label:"Pendiente",  col:C.yellow, bg:C.yellowBg },
  en_proceso:{ label:"En proceso", col:C.blue,   bg:C.blueBg   },
  pausada:   { label:"Pausada",    col:C.muted,  bg:"#F5F4F2"  },
  completada:{ label:"Completada", col:C.green,  bg:C.greenBg  },
  cancelada: { label:"Cancelada",  col:C.red,    bg:C.redBg    },
};
// Si llega un estado desconocido, se muestra tal cual en vez de romper la pantalla.
export const statusOf = st => ORDER_STATUS[st] ?? { label:String(st), col:C.muted, bg:"#F5F4F2" };
export const UNITS = ["pieza","metro","m²","kg","litro","lata","caja","par","rollo","galón","paquete","metro lineal"];

// ── UTILS ─────────────────────────────────────────────────────────────────────
export const $m = n=>`$${Number(n).toLocaleString("es-MX",{minimumFractionDigits:0})}`;

// ── STYLE SHORTCUTS ───────────────────────────────────────────────────────────
export const card = {background:C.card,borderRadius:12,border:`1px solid ${C.border}`,padding:"18px 20px"};
export const inp = {padding:"8px 12px",borderRadius:8,border:`1.5px solid ${C.border}`,fontSize:13,color:C.text,width:"100%",background:"#fff",fontFamily:"inherit",boxSizing:"border-box"};
export const lbl = {fontSize:12,fontWeight:600,color:C.muted,display:"block",marginBottom:4};
export const th = {textAlign:"left",fontSize:11,fontWeight:700,color:C.muted,padding:"10px 14px",textTransform:"uppercase",letterSpacing:.5,borderBottom:`1px solid ${C.border}`,background:"#FAFAF9"};
export const td = {padding:"11px 14px",borderBottom:`1px solid #F8F7F6`};

// ── ATOMS ─────────────────────────────────────────────────────────────────────
export const Tag = ({label,col,bg})=><span style={{background:bg,color:col,fontSize:11,fontWeight:700,padding:"2px 9px",borderRadius:99,whiteSpace:"nowrap"}}>{label}</span>;

export function Btn({label,onClick,secondary,danger,small,Icon}){
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

export function Field({label,type="text",value,onChange,opts}){
  return(
    <div>
      <label style={lbl}>{label}</label>
      {opts
        ? <select value={value} onChange={e=>onChange(e.target.value)} style={inp}>
            {opts.map(o=>{const v=o.v??o, l=o.l??o; return <option key={v} value={v}>{l}</option>;})}
          </select>
        : <input type={type} value={value} onChange={e=>onChange(e.target.value)} style={inp}
            // En campos numéricos, al hacer clic se selecciona todo el contenido (así el "0" se reemplaza al escribir).
            // El setTimeout evita que el navegador deshaga la selección al soltar el clic.
            onFocus={type==="number" ? e=>{const t=e.target; setTimeout(()=>t.select(),0);} : undefined}
            inputMode={type==="number" ? "decimal" : undefined}/>}
    </div>
  );
}

export function Modal({title,onClose,children,width=500}){
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

