<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function index()
    {
        $backups = $this->getBackupFiles();
        return view('admin.backups.index', compact('backups'));
    }

    public function create(Request $request)
    {
        try {
            $this->ensureBackupDirectory();
            $type = $request->type ?? 'database';
            
            if ($type == 'full') {
                $result = $this->backupFull();
            } else {
                $result = $this->backupDatabase();
            }

            return redirect()->route('admin.backups.index')
                ->with('success', 'Backup created successfully: ' . basename($result));
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function download($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (!File::exists($path)) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Backup file not found!');
        }
        return response()->download($path);
    }

    public function destroy($filename)
    {
        try {
            $path = storage_path('app/backups/' . $filename);
            
            if (!File::exists($path)) {
                return redirect()->route('admin.backups.index')
                    ->with('error', 'Backup file not found!');
            }
            
            if (File::delete($path)) {
                return redirect()->route('admin.backups.index')
                    ->with('success', 'Backup deleted successfully!');
            } else {
                return redirect()->route('admin.backups.index')
                    ->with('error', 'Failed to delete backup!');
            }
            
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete all backups
     */
    public function deleteAll()
    {
        try {
            $path = storage_path('app/backups');

            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                return redirect()->route('admin.backups.index')
                    ->with('info', 'No backups found to delete! Create a backup first.');
            }

            $files = File::files($path);

            if (count($files) === 0) {
                return redirect()->route('admin.backups.index')
                    ->with('info', 'No backup files found! Please create a backup first.');
            }

            $deletedCount = 0;
            foreach ($files as $file) {
                try {
                    if (File::delete($file)) {
                        $deletedCount++;
                    }
                } catch (\Exception $e) {
                    // Skip
                }
            }

            if ($deletedCount > 0) {
                return redirect()->route('admin.backups.index')
                    ->with('success', $deletedCount . ' backup(s) deleted successfully!');
            } else {
                return redirect()->route('admin.backups.index')
                    ->with('error', 'Failed to delete backups!');
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function restore(Request $request, $filename)
    {
        try {
            $path = storage_path('app/backups/' . $filename);
            
            if (!File::exists($path)) {
                return redirect()->route('admin.backups.index')
                    ->with('error', 'Backup file not found!');
            }

            // If zip, extract first
            $sqlFile = $path;
            if (pathinfo($path, PATHINFO_EXTENSION) == 'zip') {
                $sqlFile = $this->extractSqlFromZip($path);
            }

            if (!File::exists($sqlFile)) {
                throw new \Exception('SQL file not found in backup');
            }

            $sql = File::get($sqlFile);
            $queries = array_filter(array_map('trim', explode(';', $sql)));
            
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            foreach ($queries as $query) {
                if (!empty($query) && !str_starts_with(strtoupper($query), 'SET')) {
                    try {
                        DB::statement($query);
                    } catch (\Exception $e) {
                        // Skip errors
                    }
                }
            }
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            // Clean up extracted file
            if ($sqlFile != $path && File::exists($sqlFile)) {
                File::delete($sqlFile);
            }
            
            return redirect()->route('admin.backups.index')
                ->with('success', 'Database restored successfully from: ' . $filename);
                
        } catch (\Exception $e) {
            return redirect()->route('admin.backups.index')
                ->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }

    // =============================================
    // PRIVATE METHODS
    // =============================================

    private function backupDatabase()
    {
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '_database.sql';
        $path = storage_path('app/backups/' . $filename);

        $sql = $this->exportDatabase();
        File::put($path, $sql);

        return $path;
    }

    private function backupFull()
    {
        $timestamp = date('Y-m-d_H-i-s');
        $zipFilename = 'backup_' . $timestamp . '_full.zip';
        $zipPath = storage_path('app/backups/' . $zipFilename);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        // 1. Add SQL backup
        $sqlContent = $this->exportDatabase();
        $sqlFilename = 'backup_' . $timestamp . '.sql';
        $zip->addFromString($sqlFilename, $sqlContent);

        // 2. Add Storage files
        $storagePath = storage_path('app/public');
        if (File::exists($storagePath)) {
            $this->addFolderToZip($zip, $storagePath, 'storage');
        }

        // 3. Add Uploads folder
        $uploadsPath = public_path('uploads');
        if (File::exists($uploadsPath)) {
            $this->addFolderToZip($zip, $uploadsPath, 'uploads');
        }

        // 4. Add .env file (if exists)
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $zip->addFile($envPath, '.env');
        }

        $zip->close();

        return $zipPath;
    }

    private function exportDatabase()
    {
        $tables = $this->getTables();
        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . config('database.connections.mysql.database') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            if (in_array($table, ['migrations', 'failed_jobs', 'sessions', 'cache', 'cache_locks', 'password_reset_tokens'])) {
                continue;
            }

            $create = DB::select("SHOW CREATE TABLE `$table`");
            if (!empty($create)) {
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create[0]->{'Create Table'} . ";\n\n";
            }

            $rows = DB::table($table)->get();
            if ($rows->count() > 0) {
                foreach ($rows->chunk(50) as $chunk) {
                    foreach ($chunk as $row) {
                        $cols = [];
                        $vals = [];
                        foreach ((array)$row as $key => $value) {
                            $cols[] = "`$key`";
                            if ($value === null) {
                                $vals[] = 'NULL';
                            } else {
                                $vals[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $sql .= "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }

    private function getTables()
    {
        $tables = [];
        $results = DB::select('SHOW TABLES');
        
        $dbName = config('database.connections.mysql.database');
        $key = 'Tables_in_' . $dbName;
        
        foreach ($results as $row) {
            $tables[] = $row->$key;
        }
        
        return $tables;
    }

    private function addFolderToZip($zip, $folder, $zipFolderName)
    {
        if (!File::exists($folder)) {
            return;
        }

        $files = File::allFiles($folder);
        foreach ($files as $file) {
            $relativePath = $zipFolderName . '/' . $file->getRelativePathname();
            $zip->addFile($file->getPathname(), $relativePath);
        }
    }

    private function extractSqlFromZip($zipPath)
    {
        $zip = new \ZipArchive();
        $zip->open($zipPath);
        
        $extractPath = storage_path('app/backups/temp');
        if (!File::exists($extractPath)) {
            File::makeDirectory($extractPath, 0755, true);
        }
        
        $zip->extractTo($extractPath);
        $zip->close();

        $files = File::files($extractPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
                return $file->getPathname();
            }
        }

        throw new \Exception('SQL file not found in zip');
    }

    private function ensureBackupDirectory()
    {
        $path = storage_path('app/backups');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
        return $path;
    }

    private function getBackupFiles()
    {
        $path = storage_path('app/backups');
        $backups = [];

        if (File::exists($path)) {
            $files = File::files($path);
            foreach ($files as $file) {
                $filename = $file->getFilename();
                $backups[] = [
                    'name' => $filename,
                    'path' => $file->getPathname(),
                    'size' => $this->formatSize($file->getSize()),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    'type' => str_contains($filename, 'full') ? 'Full Backup' : 'Database',
                ];
            }
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
        }

        return $backups;
    }

    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}