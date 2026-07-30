<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller
{
    public function index()
    {
        // Check if already installed
        if (file_exists(storage_path('installed'))) {
            return redirect('/')->with('error', 'System already installed!');
        }

        return view('install.index');
    }

    public function step1(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'company_name' => 'required|string|max:255',
                'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
                'company_address' => 'nullable|string|max:500',
                'company_phone' => 'nullable|string|max:50',
                'company_email' => 'nullable|email|max:100',
            ]);

            session(['company_data' => $validated]);

            // Handle logo upload
            if ($request->hasFile('company_logo')) {
                $logo = $request->file('company_logo');
                $logoName = 'logo.' . $logo->getClientOriginalExtension();
                $logo->move(public_path('uploads'), $logoName);
                session(['company_logo' => 'uploads/' . $logoName]);
            }

            return redirect()->route('install.step2');
        }

        return view('install.step1');
    }

    public function step2(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'db_host' => 'required|string',
                'db_port' => 'required|string',
                'db_database' => 'required|string',
                'db_username' => 'required|string',
                'db_password' => 'nullable|string',
            ]);

            session(['db_config' => $validated]);

            // Test database connection
            try {
                $connection = new \mysqli(
                    $validated['db_host'],
                    $validated['db_username'],
                    $validated['db_password'],
                    $validated['db_database'],
                    $validated['db_port']
                );

                if ($connection->connect_error) {
                    return back()->with('error', 'Database connection failed: ' . $connection->connect_error);
                }
                $connection->close();
            } catch (\Exception $e) {
                return back()->with('error', 'Database connection failed: ' . $e->getMessage());
            }

            return redirect()->route('install.step3');
        }

        return view('install.step2');
    }

    public function step3(Request $request)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:100',
                'admin_password' => 'required|string|min:8|confirmed',
            ]);

            session(['admin_data' => $validated]);

            // Run installation
            try {
                $this->runInstallation();
                return redirect()->route('install.complete')->with('success', 'Installation completed successfully!');
            } catch (\Exception $e) {
                return back()->with('error', 'Installation failed: ' . $e->getMessage());
            }
        }

        return view('install.step3');
    }

    public function complete()
    {
        return view('install.complete');
    }

    private function runInstallation()
    {
        // 1. Generate .env file
        $this->generateEnvFile();

        // 2. Run migrations
        Artisan::call('migrate:fresh', ['--force' => true]);

        // 3. Run seeders
        Artisan::call('db:seed', ['--force' => true]);

        // 4. Create admin user
        $this->createAdminUser();

        // 5. Update company settings
        $this->updateCompanySettings();

        // 6. Clear cache
        Artisan::call('optimize:clear');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        // 7. Create installed flag
        file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

        // 8. Create storage link
        Artisan::call('storage:link');
    }

    private function generateEnvFile()
    {
        $dbConfig = session('db_config');
        $companyData = session('company_data');

        $envContent = "APP_NAME=\"" . $companyData['company_name'] . "\"\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_KEY=" . base64_encode(random_bytes(32)) . "\n";
        $envContent .= "APP_DEBUG=false\n";
        $envContent .= "APP_URL=" . url('/') . "\n\n";
        $envContent .= "DB_CONNECTION=mysql\n";
        $envContent .= "DB_HOST=" . $dbConfig['db_host'] . "\n";
        $envContent .= "DB_PORT=" . $dbConfig['db_port'] . "\n";
        $envContent .= "DB_DATABASE=" . $dbConfig['db_database'] . "\n";
        $envContent .= "DB_USERNAME=" . $dbConfig['db_username'] . "\n";
        $envContent .= "DB_PASSWORD=\"" . $dbConfig['db_password'] . "\"\n\n";
        $envContent .= "SESSION_DRIVER=database\n";
        $envContent .= "SESSION_LIFETIME=120\n\n";
        $envContent .= "CACHE_STORE=database\n";
        $envContent .= "QUEUE_CONNECTION=database\n\n";
        $envContent .= "MAIL_MAILER=log\n";
        $envContent .= "MAIL_HOST=mailpit\n";
        $envContent .= "MAIL_PORT=1025\n";
        $envContent .= "MAIL_USERNAME=null\n";
        $envContent .= "MAIL_PASSWORD=null\n";
        $envContent .= "MAIL_ENCRYPTION=null\n";
        $envContent .= "MAIL_FROM_ADDRESS=\"hello@example.com\"\n";
        $envContent .= "MAIL_FROM_NAME=\"\${APP_NAME}\"\n";

        File::put(base_path('.env'), $envContent);
    }

    private function createAdminUser()
    {
        $adminData = session('admin_data');

        DB::table('users')->insert([
            'name' => $adminData['admin_name'],
            'email' => $adminData['admin_email'],
            'password' => Hash::make($adminData['admin_password']),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also create a default admin account
        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@wserp.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateCompanySettings()
    {
        $companyData = session('company_data');

        // Update settings table if exists
        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'company_name'],
                ['value' => $companyData['company_name'], 'updated_at' => now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'company_address'],
                ['value' => $companyData['company_address'] ?? '', 'updated_at' => now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'company_phone'],
                ['value' => $companyData['company_phone'] ?? '', 'updated_at' => now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'company_email'],
                ['value' => $companyData['company_email'] ?? '', 'updated_at' => now()]
            );
            DB::table('settings')->updateOrInsert(
                ['key' => 'company_logo'],
                ['value' => session('company_logo') ?? '', 'updated_at' => now()]
            );
        }
    }
}
