<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Respaldo de la base de datos (SQLite o MySQL/MariaDB) con fecha en el nombre.
 *
 *   php artisan carpisys:respaldo
 *   php artisan carpisys:respaldo --destino="E:\Respaldos" --conservar=30
 */
class RespaldoBd extends Command
{
    protected $signature = 'carpisys:respaldo
                            {--destino= : Carpeta donde guardar (por defecto storage/app/respaldos)}
                            {--conservar=14 : Cuántos respaldos mantener; los más viejos se borran}
                            {--mysqldump=mysqldump : Ruta de mysqldump (solo MySQL/MariaDB)}';

    protected $description = 'Guarda una copia de la base de datos con fecha y hora';

    public function handle(): int
    {
        $conn = config('database.default');
        $cfg  = config("database.connections.{$conn}");
        $dir  = rtrim($this->option('destino') ?: storage_path('app/respaldos'), '\\/');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Y-m-d_His');

        if (($cfg['driver'] ?? null) === 'sqlite') {
            $dest = "{$dir}/carpisys_{$stamp}.sqlite";
            // VACUUM INTO genera una copia consistente aunque alguien esté usando el sistema.
            DB::connection($conn)->statement('VACUUM INTO ' . DB::connection($conn)->getPdo()->quote($dest));
        } elseif (in_array($cfg['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $dest = "{$dir}/carpisys_{$stamp}.sql";
            $proc = new Process([
                $this->option('mysqldump'),
                '--host=' . $cfg['host'],
                '--port=' . $cfg['port'],
                '--user=' . $cfg['username'],
                '--single-transaction',
                '--default-character-set=utf8mb4',
                '--result-file=' . $dest,
                $cfg['database'],
            ], null, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
            $proc->setTimeout(300);
            $proc->run();

            if (! $proc->isSuccessful()) {
                @unlink($dest);
                $this->error('mysqldump falló: ' . trim($proc->getErrorOutput()));
                $this->line('Si no lo encuentra, indica la ruta completa con --mysqldump="C:\\laragon\\bin\\mysql\\...\\bin\\mysqldump.exe"');
                return self::FAILURE;
            }
        } else {
            $this->error('Controlador de base de datos no soportado: ' . ($cfg['driver'] ?? '?'));
            return self::FAILURE;
        }

        // Conserva solo los N más recientes (el nombre lleva la fecha, así que ordena igual).
        $viejos = collect(File::glob("{$dir}/carpisys_*"))->sortDesc()->slice(max(1, (int) $this->option('conservar')));
        $viejos->each(fn ($f) => File::delete($f));

        $this->info("Respaldo guardado en: {$dest}");
        return self::SUCCESS;
    }
}
