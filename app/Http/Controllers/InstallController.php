<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller
{
    /**
     * Check if already installed
     */
    public function index()
    {
        if (file_exists(storage_path('installed'))) {
            return redirect('/')->with('error', 'System already installed!');
        }

        // Check if .env exists, if not, create from example
        if (!file_exists(base_path('.env'))) {
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), base_path('.env'));
            }
        }

        return view('install.index');
    }

    /**
     * Step 1: Database Configuration
     */
    public function step1(Request $request)
    {
        if (file_exists(storage_path('installed'))) {
            return redirect('/')->with('error', 'System already installed!');
        }

        if ($request->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'db_host' => 'required|string',
                'db_port' => 'required|string',
                'db_database' => 'required|string',
                'db_username' => 'required|string',
                'db_password' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            // Test database connection
            try {
                $connection = new \mysqli(
                    $request->db_host,
                    $request->db_username,
                    $request->db_password,
                    $request->db_database,
                    $request->db_port
                );

                if ($connection->connect_error) {
                    return back()->with('error', 'Database connection failed: ' . $connection->connect_error);
                }
                $connection->close();

                // Save to session
                session(['db_config' => [
                    'db_host' => $request->db_host,
                    'db_port' => $request->db_port,
                    'db_database' => $request->db_database,
                    'db_username' => $request->db_username,
                    'db_password' => $request->db_password,
                ]]);

                // Create .env file with database config
                $this->generateEnvFile($request->all());

                return redirect()->route('install.step2');

            } catch (\Exception $e) {
                return back()->with('error', 'Database connection failed: ' . $e->getMessage());
            }
        }

        return view('install.step1');
    }

    /**
     * Step 2: Run Migrations & Create Admin
     */
    public function step2(Request $request)
    {
        if (file_exists(storage_path('installed'))) {
            return redirect('/')->with('error', 'System already installed!');
        }

        if ($request->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'admin_name' => 'required|string|max:255',
                'admin_email' => 'required|email|max:100',
                'admin_password' => 'required|string|min:8|confirmed',
                'company_name' => 'required|string|max:255',
                'company_phone' => 'nullable|string|max:50',
                'company_address' => 'nullable|string|max:500',
                'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            try {
                // Handle logo upload
                $logoPath = null;
                if ($request->hasFile('company_logo')) {
                    $logo = $request->file('company_logo');
                    $logoName = 'logo.' . $logo->getClientOriginalExtension();
                    $logo->move(public_path('uploads'), $logoName);
                    $logoPath = 'uploads/' . $logoName;
                }

                // Run migrations
                Artisan::call('migrate:fresh', ['--force' => true]);
                
                // Run seeders
                Artisan::call('db:seed', ['--force' => true]);

                // Create admin user
                $this->createAdminUser($request->all());

                // Update company settings
                $this->updateCompanySettings($request->all(), $logoPath);

                // Generate app key if not exists
                if (!env('APP_KEY')) {
                    Artisan::call('key:generate', ['--force' => true]);
                }

                // Clear cache
                Artisan::call('optimize:clear');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');

                // Create storage link
                try {
                    Artisan::call('storage:link');
                } catch (\Exception $e) {
                    // Storage link may already exist
                }

                // Create installed flag
                file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

                return redirect()->route('install.complete')->with('success', 'Installation completed successfully!');

            } catch (\Exception $e) {
                return back()->with('error', 'Installation failed: ' . $e->getMessage());
            }
        }

        return view('install.step2');
    }

    /**
     * Step 3: Installation Complete
     */
    public function complete()
    {
        if (!file_exists(storage_path('installed'))) {
            return redirect()->route('install.index');
        }
        return view('install.complete');
    }

    /**
     * Generate .env file
     */
    private function generateEnvFile($dbConfig)
    {
        $envContent = "APP_NAME=\"WSERP\"\n";
        $envContent .= "APP_ENV=production\n";
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

    /**
     * Create admin user
     */
    private function createAdminUser($data)
    {
        // Create admin user
        DB::table('users')->insert([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also create default admin (for backup)
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

    /**
     * Update company settings
     */
    private function updateCompanySettings($data, $logoPath)
    {
        // Create settings table if not exists
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        $settings = [
            'company_name' => $data['company_name'],
            'company_phone' => $data['company_phone'] ?? '',
            'company_address' => $data['company_address'] ?? '',
            'company_logo' => $logoPath ?? '',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }
}