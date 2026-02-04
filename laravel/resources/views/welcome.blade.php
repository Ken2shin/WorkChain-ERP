<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkChain ERP - Enterprise Resource Planning System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            }
            50% {
                box-shadow: 0 0 30px rgba(59, 130, 246, 0.8);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .animate-pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        .glass-effect {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- Background gradient elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-900 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-900 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
        <div class="absolute top-1/2 left-1/2 w-80 h-80 bg-indigo-900 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
    </div>

    <!-- Main content -->
    <div class="relative z-10 w-full max-w-4xl px-6 py-12">
        <div class="glass-effect rounded-2xl p-12 md:p-16 text-center animate-fade-in-up">
            <!-- Logo/Icon -->
            <div class="mb-8 flex justify-center">
                <div class="relative">
                    <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center animate-pulse-glow">
                        <span class="text-3xl font-bold text-white">⚙️</span>
                    </div>
                </div>
            </div>

            <!-- Main heading -->
            <h1 class="text-4xl md:text-6xl font-bold mb-6 bg-gradient-to-r from-blue-400 via-blue-300 to-indigo-400 bg-clip-text text-transparent">
                WorkChain ERP
            </h1>

            <!-- Tagline -->
            <p class="text-xl md:text-2xl text-slate-300 mb-4 font-light">
                Enterprise Resource Planning System
            </p>

            <!-- Status indicator -->
            <div class="inline-flex items-center gap-3 mb-12 bg-green-900/20 px-6 py-3 rounded-full border border-green-500/30">
                <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-green-300 font-medium">System Online</span>
            </div>

            <!-- Status message -->
            <div class="bg-blue-900/10 border border-blue-500/20 rounded-lg p-6 mb-12">
                <p class="text-lg text-slate-200">
                    ✓ WorkChain ERP is Online and Ready
                </p>
                <p class="text-sm text-slate-400 mt-2">
                    v{{ config('app.version', '1.0.0') }} • PHP 8.3 • Laravel 11
                </p>
            </div>

            <!-- Information grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                <div class="glass-effect rounded-lg p-6 hover:border-blue-400/50 transition-colors">
                    <div class="text-2xl mb-3">🔐</div>
                    <h3 class="text-lg font-semibold text-blue-300 mb-2">Secure</h3>
                    <p class="text-slate-400 text-sm">Multi-tenant architecture with encryption</p>
                </div>

                <div class="glass-effect rounded-lg p-6 hover:border-blue-400/50 transition-colors">
                    <div class="text-2xl mb-3">⚡</div>
                    <h3 class="text-lg font-semibold text-blue-300 mb-2">Fast</h3>
                    <p class="text-slate-400 text-sm">Optimized performance with caching</p>
                </div>

                <div class="glass-effect rounded-lg p-6 hover:border-blue-400/50 transition-colors">
                    <div class="text-2xl mb-3">📊</div>
                    <h3 class="text-lg font-semibold text-blue-300 mb-2">Modular</h3>
                    <p class="text-slate-400 text-sm">8 integrated business modules</p>
                </div>
            </div>

            <!-- System modules -->
            <div class="mb-12">
                <h2 class="text-xl font-semibold text-slate-200 mb-6">Available Modules</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Warehouse Inventory</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Sales Management</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Purchasing</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Human Resources</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Project Management</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Logistics</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Financial Management</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-300">
                        <span class="text-blue-400">✓</span>
                        <span>Document Control</span>
                    </div>
                </div>
            </div>

            <!-- API endpoints info -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-lg p-6 mb-12 text-left">
                <h3 class="text-lg font-semibold text-blue-300 mb-4">API Endpoints</h3>
                <div class="space-y-3 text-sm font-mono">
                    <div>
                        <span class="text-green-400">GET</span> 
                        <span class="text-slate-300">/api/v1/health</span>
                        <span class="text-slate-500">- Health check</span>
                    </div>
                    <div>
                        <span class="text-blue-400">POST</span> 
                        <span class="text-slate-300">/api/v1/auth/login</span>
                        <span class="text-slate-500">- User authentication</span>
                    </div>
                    <div>
                        <span class="text-purple-400">GET</span> 
                        <span class="text-slate-300">/health</span>
                        <span class="text-slate-500">- Web health endpoint</span>
                    </div>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/login" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition-colors flex items-center justify-center gap-2 transform hover:scale-105">
                    <span>Acceder al Sistema</span>
                    <span>→</span>
                </a>
                <a href="/api/v1/health" class="border border-blue-400 text-blue-300 hover:bg-blue-900/20 font-semibold py-3 px-8 rounded-lg transition-colors">
                    Ver Estado API
                </a>
            </div>

            <!-- Footer -->
            <div class="mt-12 pt-8 border-t border-slate-700/50">
                <p class="text-slate-400 text-sm">
                    WorkChain ERP v1.0.0 • Built with Laravel 11 • Multi-language Security Architecture
                </p>
                <p class="text-slate-500 text-xs mt-2">
                    © 2024 WorkChain. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <!-- Alpine.js for interactivity (lightweight) -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    </script>
</body>
</html>
