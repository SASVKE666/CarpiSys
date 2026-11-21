import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const ROLE_LABEL = { admin: 'Administrador', operario: 'Operario' };

function NewUserForm({ roles }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '', email: '', password: '', role: 'operario', hourly_rate: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'), { preserveScroll: true, onSuccess: () => reset() });
    };

    const field = 'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500';

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <div>
                <label className="text-sm font-medium text-gray-700">Nombre</label>
                <input className={field} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Correo (para iniciar sesión)</label>
                <input type="email" className={field} value={data.email} onChange={(e) => setData('email', e.target.value)} />
                <InputError message={errors.email} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Contraseña inicial (mín. 8)</label>
                <input type="text" autoComplete="off" className={field} value={data.password} onChange={(e) => setData('password', e.target.value)} />
                <InputError message={errors.password} className="mt-1" />
            </div>
            <div>
                <label className="text-sm font-medium text-gray-700">Rol</label>
                <select className={field} value={data.role} onChange={(e) => setData('role', e.target.value)}>
                    {roles.map((r) => <option key={r} value={r}>{ROLE_LABEL[r] ?? r}</option>)}
                </select>
                <InputError message={errors.role} className="mt-1" />
            </div>
            <div className="sm:col-span-2">
                <label className="text-sm font-medium text-gray-700">Tarifa por hora ($) — para costear su mano de obra</label>
                <input type="number" step="any" inputMode="decimal" className={field} value={data.hourly_rate}
                       onFocus={(e) => e.target.select()} onChange={(e) => setData('hourly_rate', e.target.value)} />
                <InputError message={errors.hourly_rate} className="mt-1" />
                <p className="mt-1 text-xs text-gray-500">Solo la ve el administrador. Sin tarifa, esa persona no puede registrar horas.</p>
            </div>
            <div className="sm:col-span-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
                >
                    Crear usuario
                </button>
            </div>
        </form>
    );
}

function UserRow({ user, roles, isSelf }) {
    const [pwd, setPwd] = useState('');
    const [rate, setRate] = useState(user.hourly_rate ?? '');
    const { errors } = usePage().props;
    const save = (payload) => router.put(route('users.update', user.id), payload, { preserveScroll: true });

    return (
        <li className="space-y-3 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate font-semibold text-gray-900">
                        {user.name} {isSelf && <span className="text-xs font-normal text-gray-500">(tú)</span>}
                    </p>
                    <p className="truncate text-sm text-gray-500">{user.email}</p>
                </div>
                <span className={`rounded-full px-2 py-0.5 text-xs font-bold ${user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {user.is_active ? 'Activo' : 'Desactivado'}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <select
                    value={user.role}
                    onChange={(e) => save({ role: e.target.value })}
                    className="rounded-md border-gray-300 text-sm"
                >
                    {roles.map((r) => <option key={r} value={r}>{ROLE_LABEL[r] ?? r}</option>)}
                </select>

                <button
                    type="button"
                    onClick={() => save({ is_active: !user.is_active })}
                    className="rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200"
                >
                    {user.is_active ? 'Desactivar' : 'Activar'}
                </button>

                <input
                    type="text"
                    autoComplete="off"
                    placeholder="Nueva contraseña"
                    value={pwd}
                    onChange={(e) => setPwd(e.target.value)}
                    className="min-w-0 flex-1 rounded-md border-gray-300 text-sm"
                />
                <button
                    type="button"
                    disabled={pwd.length < 8}
                    onClick={() => save({ password: pwd })}
                    className="rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-40"
                >
                    Restablecer
                </button>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm text-gray-600">Tarifa por hora $</span>
                <input type="number" step="any" inputMode="decimal" value={rate} onFocus={(e) => e.target.select()}
                       onChange={(e) => setRate(e.target.value)} className="w-28 rounded-md border-gray-300 text-sm" />
                <button type="button" disabled={String(rate) === String(user.hourly_rate ?? '')}
                        onClick={() => save({ hourly_rate: rate })}
                        className="rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 disabled:opacity-40">
                    Guardar tarifa
                </button>
                {user.hourly_rate == null && <span className="text-xs text-amber-700">Sin tarifa: no puede registrar horas.</span>}
            </div>
            {(errors.role || errors.is_active || errors.password || errors.hourly_rate) && (
                <p className="text-sm text-red-600">{errors.role || errors.is_active || errors.password || errors.hourly_rate}</p>
            )}
        </li>
    );
}

export default function UsersIndex({ users, roles }) {
    const { auth, flash } = usePage().props;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Usuarios</h2>}
        >
            <Head title="Usuarios" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div className="rounded-md bg-green-50 p-3 text-sm font-medium text-green-700">{flash.success}</div>
                    )}

                    <section className="bg-white p-4 shadow sm:rounded-lg sm:p-6">
                        <h3 className="mb-4 text-lg font-semibold text-gray-900">Nuevo usuario</h3>
                        <NewUserForm roles={roles} />
                    </section>

                    <section className="bg-white shadow sm:rounded-lg">
                        <h3 className="border-b border-gray-100 p-4 text-lg font-semibold text-gray-900 sm:px-6">
                            Usuarios del sistema
                        </h3>
                        <ul className="divide-y divide-gray-100">
                            {users.map((u) => (
                                <UserRow key={u.id} user={u} roles={roles} isSelf={u.id === auth.user.id} />
                            ))}
                        </ul>
                    </section>

                    <p className="px-1 text-xs text-gray-500">
                        Operario: consulta, registra movimientos de inventario, cambia el estado de las órdenes y registra
                        tiempos muertos; no ve costos, precios ni márgenes. Administrador: además edita artículos, órdenes (precios y costos), existencias, tasas CIF y usuarios.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
