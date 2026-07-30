<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Complete</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check-circle text-green-600 text-4xl"></i>
            </div>

            <h1 class="text-3xl font-bold text-gray-900">Installation Complete!</h1>
            <p class="mt-2 text-gray-600">WSERP has been successfully installed.</p>

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-6 text-left">
                <h4 class="font-semibold text-gray-700 mb-2">Next Steps:</h4>
                <ul class="text-sm text-gray-600 space-y-2">
                    <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Remove the installation folder for security</li>
                    <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Login to your admin panel</li>
                    <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Start adding products and customers</li>
                </ul>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-6">
                <a href="{{ url('/admin/login') }}" 
                   class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login
                </a>
                <a href="{{ url('/') }}" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors duration-200">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
            </div>

            <p class="text-xs text-gray-400 mt-6">
                <i class="fas fa-shield-alt mr-1"></i>
                Please delete the storage/installed file to reinstall
            </p>
        </div>
    </div>
</body>
</html>