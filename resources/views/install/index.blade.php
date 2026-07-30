<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WSERP Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto">
                    <span class="text-white text-3xl font-bold">W</span>
                </div>
                <h1 class="mt-4 text-3xl font-bold text-gray-900">Welcome to WSERP</h1>
                <p class="mt-2 text-gray-600">Complete Installation Wizard</p>
            </div>

            <div class="space-y-6">
                <!-- Steps -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                        <span class="ml-2 text-sm font-medium text-blue-600">Company Details</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-gray-300 mx-4"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-600 font-bold">2</div>
                        <span class="ml-2 text-sm text-gray-400">Database</span>
                    </div>
                    <div class="flex-1 h-0.5 bg-gray-300 mx-4"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-gray-600 font-bold">3</div>
                        <span class="ml-2 text-sm text-gray-400">Admin Setup</span>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        This installer will help you set up WSERP on your server.
                        Please have your database credentials ready.
                    </p>
                </div>

                <div class="flex justify-between items-center border-t border-gray-200 pt-6">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        PHP {{ phpversion() }} compatible
                    </div>
                    <a href="{{ route('install.step1') }}" 
                       class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
                        Get Started <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>