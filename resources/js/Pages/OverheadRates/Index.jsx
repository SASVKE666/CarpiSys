import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const TYPE_INFO = {
    porcentaje_mano_obra:  { label: 'Porcentaje de la mano de obra',  unit: '%',     example: 'Ej.: 25 → por cada $100 de mano de obra se suman $25 de CIF.' },
    porcentaje_materiales: { label: 'Porcentaje de los materiales',   unit: '%',     example: 'Ej.: 10 → por cada $100 de materiales se suman $10 de CIF.' },
    por_hora_maquina:      { label: 'Tarifa por hora de máquina',     unit: '$/h',   example: 'Ej.: 20 → $20 por cada hora de máquina anotada en la orden.' },
    monto_fijo_mensual:    { label: 'Monto fijo mensual (sin reparto automático)', unit: '$', example: '' },
};
const field = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500';

function NewRateForm({ types }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', rate_type: types[0], value: '', description: '',
    });
    const info = TYPE_INFO[data.rate_type];

    const submit = (e) => {
        e.preventDefault();
        post(route('overhead-rates.store'), { preserveScroll: true, onSuccess: () => reset('name', 'value', 'description') });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <div>
                <label className="text-sm font-medium text-gray-700">Nombre</label>
                <input className={field} placeholder="Luz, renta y agua" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Cómo se calcula</label>
                <select className={field} value={data.rate_type} onChange={(e) => setData('rate_type', e.target.value)}>
                    {types.map((t) => <option key={t} value={t}>{TYPE_INFO[t].label}</option>)}
                </select>
                <InputError message={errors.rate_type} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Valor ({info.unit})</label>
                <input type="number" step="any" inputMode="decimal" className={field} value={data.value}
                       onFocus={(e) => e.target.select()} onChange={(e) => setData('value', e.target.value)} />
                <InputError message={errors.value} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Descripción (opcional)</label>
                <input className={field} value={data.description} onChange={(e) => setData('description', e.target.value)} />
            </div>
            <p className="text-xs text-gray-500 sm:col-span-2">{info.example}</p>
            <div className="sm:col-span-2">
                <button type="submit" disabled={processing}
                        className="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
                    Crear tasa
                </button>
            </div>
        </form>
    );
}

function RateRow({ rate }) {
    const info = TYPE_INFO[rate.rate_type] ?? { label: rate.rate_type, unit: '' };
    const [value, setValue] = useState(String(Number(rate.value)));
    const { errors } = usePage().props;
    const save = (payload) => router.put(route('overhead-rates.update', rate.id), payload, { preserveScroll: true });
    const supported = rate.rate_type !== 'monto_fijo_mensual';

    return (
        <li className="space-y-2 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate font-semibold text-gray-900">{rate.name}</p>
                    <p className="text-sm text-gray-500">{info.label}</p>
                </div>
                <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${rate.is_active && supported ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                    {!supported ? 'No se aplica' : rate.is_active ? 'Activa' : 'Apagada'}
                </span>
            </div>
            {!supported && (
                <p className="text-xs text-amber-700">Este tipo no se reparte automáticamente entre órdenes. Puedes dejarlo como referencia o reemplazarlo por uno de los otros tipos.</p>
            )}
            {supported && (
                <div className="flex flex-wrap items-center gap-2">
                    <input type="number" step="any" inputMode="decimal" value={value} onFocus={(e) => e.target.select()}
                           onChange={(e) => setValue(e.target.value)} className="w-28 rounded-md border-gray-300 text-sm" />
                    <span className="text-sm text-gray-500">{info.unit}</span>
                    <button type="button" disabled={value === '' || Number(value) === Number(rate.value)}
                            onClick={() => save({ value })}
                            className="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-40">
                        Guardar valor
                    </button>
                    <button type="button" onClick={() => save({ is_active: !rate.is_active })}
                            className="rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                        {rate.is_active ? 'Apagar' : 'Activar'}
                    </button>
                </div>
            )}
            {(errors.value || errors.is_active) && <p className="text-sm text-red-600">{errors.value || errors.is_active}</p>}
        </li>
    );
}

export default function OverheadRatesIndex({ rates, types }) {
    const { flash } = usePage().props;
    const activas = rates.filter((r) => r.is_active && r.rate_type !== 'monto_fijo_mensual').length;

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Tasas de costos indirectos (CIF)</h2>}>
            <Head title="Tasas CIF" />
            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success && <div className="rounded-md bg-green-50 p-3 text-sm font-medium text-green-700">{flash.success}</div>}

                    <div className={`rounded-md p-3 text-sm ${activas ? 'bg-blue-50 text-blue-800' : 'bg-red-50 text-red-700'}`}>
                        {activas
                            ? 'Las tasas activas se suman automáticamente al costo de cada orden que tenga materiales u horas registrados. Al cambiar una tasa se recalculan las órdenes abiertas; las completadas o canceladas conservan su costo.'
                            : 'No hay tasas activas: los costos indirectos de las órdenes salen en $0. Crea al menos una tasa para que el costeo incluya el CIF.'}
                    </div>

                    <section className="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Nueva tasa</h3>
                        <NewRateForm types={types} />
                    </section>

                    <section className="bg-white shadow sm:rounded-lg">
                        <h3 className="border-b border-gray-100 p-4 text-lg font-semibold text-gray-900 sm:px-6">Tasas registradas</h3>
                        {rates.length ? (
                            <ul className="divide-y divide-gray-100">{rates.map((r) => <RateRow key={r.id} rate={r} />)}</ul>
                        ) : (
                            <p className="p-6 text-sm text-gray-500">Todavía no hay tasas.</p>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
