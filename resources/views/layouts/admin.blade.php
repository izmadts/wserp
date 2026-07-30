<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WSERP - Admin Panel')</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- @livewireStyles --}}

    <style>
        [x-cloak] {
            display: none !important;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .transition-fast {
            transition: all 0.15s ease-in-out;
        }

        /* ========================================== */
        /* SIDEBAR STYLES */
        /* ========================================== */
        .sidebar-link {
            @apply flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150;
        }

        .sidebar-link-active {
            @apply bg-blue-50 text-blue-700;
        }

        .sidebar-link-inactive {
            @apply text-gray-700 hover:bg-gray-100;
        }

        .sidebar-icon {
            @apply w-5 text-lg flex-shrink-0 text-center;
        }

        .sidebar-badge {
            @apply ml-auto text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0;
        }

        .sidebar-badge-red {
            @apply bg-red-500 text-white;
        }

        .sidebar-badge-yellow {
            @apply bg-yellow-500 text-white;
        }

        .sidebar-badge-gray {
            @apply bg-gray-200 text-gray-600;
        }

        .sidebar-section-title {
            @apply px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider;
        }

        /* Mobile overlay */
        .sidebar-overlay {
            @apply fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden;
        }
    </style>
</head>

<body class="h-full font-sans antialiased">
    <div x-data="{ 
        sidebarOpen: true,
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            document.body.style.overflow = this.sidebarOpen ? 'hidden' : '';
        },
        closeSidebar() {
            this.sidebarOpen = false;
            document.body.style.overflow = '';
        }
    }" class="min-h-screen bg-gray-50">

        <!-- ========================================== -->
        <!-- MOBILE OVERLAY -->
        <!-- ========================================== -->
        <div x-show="sidebarOpen" x-cloak
            @click="closeSidebar()"
            class="sidebar-overlay"
            x-transition:enter="transition-opacity ease-linear duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">
        </div>

        <!-- ========================================== -->
        <!-- SIDEBAR -->
        <!-- ========================================== -->
        <!-- Sidebar -->
        <div class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-gray-200 shadow-lg transform transition-transform duration-300 ease-in-out"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

            <!-- Brand -->
            <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">W</span>
                    </div>
                    <span class="ml-2 text-xl font-bold text-gray-800">WSERP</span>
                </div>
                <button @click="sidebarOpen = false" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="px-2 py-4 overflow-y-auto h-[calc(100vh-4rem)]">

                <!-- ========================================== -->
                <!-- DASHBOARD -->
                <!-- ========================================== -->
                <div class="space-y-1">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Main Menu</p>

                    <a href="{{ route('admin.dashboard') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-home w-5 text-lg text-green-600"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- INVENTORY -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventory</p>

                    <a href="{{ route('admin.products.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.products.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-box w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Products</span>
                    </a>

                    <a href="{{ route('admin.categories.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.categories.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-tags w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Categories</span>
                    </a>

                    <a href="{{ route('admin.inventory.dashboard') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.inventory.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-warehouse w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Inventory</span>
                    </a>

                    <a href="{{ route('admin.inventory.adjustments.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.inventory.adjustments.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-sliders-h w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Stock Adjustments</span>
                    </a>

                    <a href="{{ route('admin.inventory.history') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.inventory.history') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-history w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Stock History</span>
                    </a>

                    <a href="{{ route('admin.products.low-stock') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.products.low-stock') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-exclamation-triangle w-5 text-lg text-yellow-600"></i>
                        <span class="ml-3">Low Stock Alert</span>
                        @php $count = App\Models\Product::lowStock()->count(); @endphp
                        @if($count > 0)
                        <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $count }}
                        </span>
                        @endif
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- PURCHASES -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Purchases</p>

                    <a href="{{ route('admin.suppliers.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.suppliers.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-truck w-5 text-lg text-purple-700"></i>
                        <span class="ml-3">Suppliers</span>
                    </a>

                    <a href="{{ route('admin.purchases.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.purchases.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-shopping-cart w-5 text-lg text-purple-700"></i>
                        <span class="ml-3">Purchases</span>
                    </a>

                    <a href="{{ route('admin.purchase-returns.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.purchase-returns.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-undo-alt w-5 text-lg text-purple-700"></i>
                        <span class="ml-3">Purchase Returns</span>
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- SALES -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Sales</p>

                    <a href="{{ route('admin.customers.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.customers.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-users w-5 text-lg text-emerald-600"></i>
                        <span class="ml-3">Customers</span>
                    </a>

                    <a href="{{ route('admin.sales.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.sales.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-shopping-bag w-5 text-lg text-emerald-600"></i>
                        <span class="ml-3">Sales</span>
                    </a>

                    <a href="{{ route('admin.sales-returns.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.sales-returns.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-undo-alt w-5 text-lg text-emerald-600"></i>
                        <span class="ml-3">Sales Returns</span>
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- AGENTS -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Agents</p>

                    <a href="{{ route('admin.agents.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.agents.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-user-tie w-5 text-lg text-teal-500"></i>
                        <span class="ml-3">Agents</span>
                        @php $pending = App\Models\User::where('role', 'sales_agent')->where('is_active', false)->whereNull('approved_at')->count(); @endphp
                        @if($pending > 0)
                        <span class="ml-auto bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $pending }}
                        </span>
                        @endif
                    </a>

                    <a href="{{ route('admin.agents.pending') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.agents.pending') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-clock w-5 text-lg text-teal-500"></i>
                        <span class="ml-3">Pending Approvals</span>
                        @if($pending > 0)
                        <span class="ml-auto bg-yellow-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ $pending }}
                        </span>
                        @endif
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- FINANCE -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Finance</p>

                    <a href="{{ route('admin.incomes.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.incomes.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-arrow-up w-5 text-lg text-green-600"></i>
                        <span class="ml-3">Income</span>
                    </a>
                    <a href="{{ route('admin.incomes.create') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.incomes.create') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-plus-circle w-5 text-lg text-green-500"></i>
                        <span class="ml-3">Add Income</span>
                    </a>
                    <a href="{{ route('admin.income-categories.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.income-categories.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-tags w-5 text-lg text-green-600"></i>
                        <span class="ml-3">Income Categories</span>
                    </a>
                    <a href="{{ route('admin.expenses.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.expenses.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-arrow-down w-5 text-lg text-red-600"></i>
                        <span class="ml-3">Expenses</span>
                    </a>
                    <a href="{{ route('admin.expenses.create') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.expenses.create') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-plus-circle w-5 text-lg text-red-500"></i>
                        <span class="ml-3">Add Expense</span>
                    </a>

                    <a href="{{ route('admin.expense-categories.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.expense-categories.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-tags w-5 text-lg text-red-600"></i>
                        <span class="ml-3">Expense Categories</span>
                    </a>
                    <a href="{{ route('admin.money-transfers.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.money-transfers.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-exchange-alt w-5 text-lg text-blue-600"></i>
                        <span class="ml-3">Money Transfer</span>
                    </a>
                </div>
                <!-- ========================================== -->
                <!-- ACCOUNTING -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Accounting</p>

                    <a href="{{ route('admin.accounts.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
                      {{ request()->routeIs('admin.accounts.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-book w-5 text-lg text-cyan-500"></i>
                        <span class="ml-3">Chart of Accounts</span>
                    </a>
                </div>

                <!-- ========================================== -->
                <!-- REPORTS -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</p>

                    <!-- Financial Reports -->
                    <a href="{{ route('admin.reports.profit-loss') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.profit-loss') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-chart-bar w-5 text-lg"></i>
                        <span class="ml-3">Profit & Loss</span>
                    </a>

                    <a href="{{ route('admin.reports.trial-balance') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.trial-balance') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-balance-scale w-5 text-lg"></i>
                        <span class="ml-3">Trial Balance</span>
                    </a>

                    <!-- Customer Reports -->
                    <a href="{{ route('admin.reports.customers') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.customers') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-users w-5 text-lg"></i>
                        <span class="ml-3">Customers</span>
                    </a>

                    <a href="{{ route('admin.reports.receivable') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.receivable') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-hand-holding-usd w-5 text-lg"></i>
                        <span class="ml-3">Receivable</span>
                    </a>

                    <!-- Supplier Reports -->
                    <a href="{{ route('admin.reports.suppliers') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.suppliers') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-truck w-5 text-lg"></i>
                        <span class="ml-3">Suppliers</span>
                    </a>

                    <!-- Expense & Income -->
                    <a href="{{ route('admin.reports.expenses') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.expenses') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-arrow-down w-5 text-lg text-red-500"></i>
                        <span class="ml-3">Expenses</span>
                    </a>

                    <a href="{{ route('admin.reports.incomes') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.incomes') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-arrow-up w-5 text-lg text-green-500"></i>
                        <span class="ml-3">Income</span>
                    </a>

                    <!-- Agent Reports -->
                    <a href="{{ route('admin.reports.agents') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.agents') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-user-tie w-5 text-lg"></i>
                        <span class="ml-3">Agents</span>
                    </a>

                    <!-- Daily Summary -->
                    <a href="{{ route('admin.reports.daily-summary') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.daily-summary') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-calendar-day w-5 text-lg"></i>
                        <span class="ml-3">Daily Summary</span>
                    </a>

                    <!-- Tax Report -->
                    <a href="{{ route('admin.reports.tax') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.reports.tax') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-percentage w-5 text-lg"></i>
                        <span class="ml-3">Tax Report</span>
                    </a>
                </div>
                <!-- ========================================== -->
                <!-- SYSTEM -->
                <!-- ========================================== -->
                <div class="space-y-1 mt-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">System</p>

                    <a href="{{ route('admin.activity-logs.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.activity-logs.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-history w-5 text-lg"></i>
                        <span class="ml-3">Activity Logs</span>
                    </a>

                    <a href="{{ route('admin.backups.index') }}"
                        class="flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150
              {{ request()->routeIs('admin.backups.*') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-cloud-upload-alt w-5 text-lg"></i>
                        <span class="ml-3">Backup & Restore</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- ========================================== -->
        <!-- MAIN CONTENT -->
        <!-- ========================================== -->
        <div class="flex-1 lg:ml-64">

            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center">
                        <button @click="toggleSidebar()" class="text-gray-500 hover:text-gray-700 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="ml-2 text-xl font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h1>
                    </div>

                    <!-- Profile Dropdown -->
                    <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                        <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                                {{ auth()->user() ? strtoupper(substr(auth()->user()->name, 0, 1)) : 'A' }}
                            </div>
                            <span class="text-sm font-medium text-gray-700 hidden sm:block">{{ auth()->user() ? auth()->user()->name : 'Admin' }}</span>
                            <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open" x-transition:enter.duration.200ms x-transition:leave.duration.150ms class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                            <a href="{{ route('admin.profile.edit') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-user w-5 text-gray-400"></i><span class="ml-3">My Profile</span></a>
                            <hr class="my-1 border-gray-100">
                            <form method="POST" action="{{ route('logout') }}" class="block">@csrf<button type="submit" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt w-5 text-red-400"></i><span class="ml-3">Logout</span></button></form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 lg:p-6">
                @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 p-4 bg-green-50 border-l-4 border-green-400 rounded-r-lg">
                    <div class="flex items-center"><i class="fas fa-check-circle text-green-400 text-xl"></i>
                        <p class="ml-3 text-green-700">{{ session('success') }}</p><button @click="show = false" class="ml-auto text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                @endif
                @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 rounded-r-lg">
                    <div class="flex items-center"><i class="fas fa-exclamation-circle text-red-400 text-xl"></i>
                        <p class="ml-3 text-red-700">{{ session('error') }}</p><button @click="show = false" class="ml-auto text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Remove @livewireScripts -->
    {{-- @livewireScripts --}}
    @vite(['resources/js/app.js'])
    <script>
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof $.fn.DataTable !== 'undefined') {
                $.extend(true, $.fn.dataTable.defaults, {
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        zeroRecords: "No matching records found"
                    }
                });
            }
        });
    </script>
    @yield('scripts')
</body>

</html>