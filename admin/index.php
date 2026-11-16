<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Superadmin Control Dashboard
 * 
 * Secure single-page administrative dashboard with server-side auth gate.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$isAuthenticated = !empty($_SESSION['meridian_admin']) && $_SESSION['meridian_admin'] === true;
$adminUser = htmlspecialchars($_SESSION['admin_user'] ?? 'Superadmin', ENT_QUOTES, 'UTF-8');
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Superadmin Dashboard — Meridian Ice Cream Contest</title>
  
  <!-- Official Favicons -->
  <link rel="icon" type="image/png" sizes="32x32" href="../assets/official/icon_cropped-ms-icon-310x310-removebg-preview-32x32.png">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              red: '#D8232A',
              darkred: '#B3181E',
              cream: '#FFFDF9',
              warm: '#FFF6EC',
              amber: '#FFAE19',
              dark: '#1C1917'
            }
          },
          fontFamily: {
            heading: ['Outfit', 'sans-serif'],
            sans: ['Plus Jakarta Sans', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace']
          }
        }
      }
    }
  </script>
  
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <!-- Canvas Confetti -->
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #F8F9FA;
      color: #1E293B;
    }
    h1, h2, h3, h4, h5, .font-heading {
      font-family: 'Outfit', sans-serif;
    }
    .custom-scrollbar::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #F1F5F9;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #CBD5E1;
      border-radius: 9999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: #94A3B8;
    }
  </style>
</head>
<body class="antialiased min-h-screen flex flex-col bg-[#F8FAFC]">

<?php if (!$isAuthenticated): ?>
  <!-- ========================================================= -->
  <!-- 1. LOGIN SCREEN (Rendered ONLY when unauthenticated)      -->
  <!-- ========================================================= -->
  <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-[#FFF7ED] via-white to-[#FEF2F2]">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden transition-all">
      
      <!-- Card Header -->
      <div class="bg-gradient-to-r from-[#D8232A] to-[#B3181E] p-8 text-center text-white relative">
        <div class="inline-block p-3.5 bg-white rounded-2xl shadow-md mb-4">
          <img src="../assets/official/meridian-logo-red.svg" alt="Meridian Ice Cream" class="h-10 w-auto mx-auto">
        </div>
        <h1 class="text-2xl font-heading font-black tracking-tight">Superadmin Control</h1>
        <p class="text-xs text-red-100 mt-1 uppercase tracking-wider font-semibold">Brand Ambassador Campaign 2026-2027</p>
      </div>

      <!-- Login Form -->
      <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="p-8 space-y-5">
        <div id="loginErrorMsg" class="hidden p-3 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-700 flex items-center gap-2">
          <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
          <span id="loginErrorText">Invalid credentials.</span>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5" for="usernameInput">Username</label>
          <div class="relative">
            <i data-lucide="user" class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400"></i>
            <input type="text" id="usernameInput" required placeholder="admin" value="admin"
              class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5" for="passwordInput">Password / Passkey</label>
          <div class="relative">
            <i data-lucide="lock" class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400"></i>
            <input type="password" id="passwordInput" required placeholder="••••••••••••"
              class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
            <button type="button" onclick="togglePasswordVisibility()" class="absolute right-3 top-3 text-slate-400 hover:text-slate-600">
              <i data-lucide="eye" id="pwdEyeIcon" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <button type="submit" id="loginBtn"
          class="w-full py-3 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-heading font-bold text-sm shadow-md shadow-red-600/20 hover:shadow-lg transition flex items-center justify-center gap-2">
          <i data-lucide="log-in" class="w-4 h-4"></i>
          <span>Enter Dashboard</span>
        </button>

        <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 flex items-start gap-2.5">
          <i data-lucide="shield-check" class="w-4 h-4 shrink-0 text-amber-600 mt-0.5"></i>
          <div>
            <strong>Hostinger Secured:</strong> Protected with rate limiting, HttpOnly cookies, and timing-safe authentication.
          </div>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (window.lucide) lucide.createIcons();
    });

    function togglePasswordVisibility() {
      const pwd = document.getElementById('passwordInput');
      const icon = document.getElementById('pwdEyeIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
      } else {
        pwd.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
      }
      if (window.lucide) lucide.createIcons();
    }

    async function handleLoginSubmit(e) {
      e.preventDefault();
      const btn = document.getElementById('loginBtn');
      const errorBox = document.getElementById('loginErrorMsg');
      const errorText = document.getElementById('loginErrorText');

      errorBox.classList.add('hidden');
      btn.disabled = true;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Authenticating...';
      if (window.lucide) lucide.createIcons();

      const username = document.getElementById('usernameInput').value.trim();
      const password = document.getElementById('passwordInput').value.trim();

      try {
        const res = await fetch('api.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (data.success) {
          window.location.reload();
        } else {
          errorText.textContent = data.message || 'Invalid username or password.';
          errorBox.classList.remove('hidden');
        }
      } catch (err) {
        errorText.textContent = 'Connection error. Please try again.';
        errorBox.classList.remove('hidden');
      }

      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="log-in" class="w-4 h-4"></i> Enter Dashboard';
      if (window.lucide) lucide.createIcons();
    }
  </script>

<?php else: ?>
  <!-- ========================================================= -->
  <!-- 2. MAIN DASHBOARD SCREEN (Rendered ONLY when authenticated)-->
  <!-- ========================================================= -->
  <div class="flex-1 flex flex-col">
    
    <!-- Top Sticky Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200/80 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        <!-- Brand & Title -->
        <div class="flex items-center gap-3">
          <a href="../index.html" target="_blank" title="View Landing Page" class="flex items-center gap-2 hover:opacity-85 transition">
            <img src="../assets/official/meridian-logo-red.svg" alt="Meridian Ice Cream" class="h-9 w-auto">
          </a>
          <div class="hidden sm:block h-6 w-px bg-slate-200"></div>
          <div class="hidden sm:flex items-center gap-2">
            <span class="font-heading font-black text-lg text-slate-800 leading-none">Superadmin Hub</span>
            <span class="px-2.5 py-0.5 text-xs font-bold bg-red-100 text-[#D8232A] rounded-full">
              Season 2026-2027
            </span>
          </div>
        </div>

        <!-- Center Status Pill -->
        <div class="hidden md:flex items-center gap-2 px-3 py-1 bg-slate-50 border border-slate-200/80 rounded-full text-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-slate-600 font-medium" id="dbStatusIndicator">Hostinger MySQL Connected</span>
        </div>

        <!-- Right User Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          <button onclick="openCreateModal()" class="px-3.5 py-1.5 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl text-xs font-heading font-bold shadow-xs hover:shadow transition flex items-center gap-1.5">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span class="hidden sm:inline">New Contestant</span>
          </button>
          
          <button onclick="exportFilteredCSV()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-200 transition flex items-center gap-1.5" title="Export Filtered CSV">
            <i data-lucide="download" class="w-4 h-4"></i>
            <span class="hidden md:inline">Export CSV</span>
          </button>

          <a href="../index.html" target="_blank" class="p-2 text-slate-500 hover:text-slate-800 rounded-xl hover:bg-slate-100 transition" title="View Public Website">
            <i data-lucide="external-link" class="w-4 h-4"></i>
          </a>

          <button onclick="handleLogout()" class="px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-xl border border-red-200 transition flex items-center gap-1">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Logout</span>
          </button>
        </div>

      </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 w-full space-y-6">
      
      <!-- STATS OVERVIEW CARDS (4 Cards) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Card 1: Total Contestants -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Contestants</span>
            <div class="w-9 h-9 rounded-xl bg-red-50 text-[#D8232A] flex items-center justify-center">
              <i data-lucide="users" class="w-5 h-5"></i>
            </div>
          </div>
          <div class="mt-4">
            <div class="text-3xl font-heading font-black text-slate-900" id="statTotalCount">0</div>
            <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
              <span class="text-emerald-600 font-bold flex items-center"><i data-lucide="trending-up" class="w-3.5 h-3.5 mr-0.5"></i> Live</span>
              <span>Across Maharashtra</span>
            </div>
          </div>
        </div>

        <!-- Card 2: 4 Categories Breakdown -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Category Distribution</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <i data-lucide="pie-chart" class="w-5 h-5"></i>
            </div>
          </div>
          <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
            <div class="p-1.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
              <span class="text-slate-600">👨 Male:</span>
              <strong class="font-bold text-slate-900" id="statMaleCount">0</strong>
            </div>
            <div class="p-1.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
              <span class="text-slate-600">👩 Female:</span>
              <strong class="font-bold text-slate-900" id="statFemaleCount">0</strong>
            </div>
            <div class="p-1.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
              <span class="text-slate-600">🧒 Family:</span>
              <strong class="font-bold text-slate-900" id="statChildCount">0</strong>
            </div>
            <div class="p-1.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
              <span class="text-slate-600">👥 Group:</span>
              <strong class="font-bold text-slate-900" id="statGroupCount">0</strong>
            </div>
          </div>
        </div>

        <!-- Card 3: Shortlisted & Winners -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Jury Decisions</span>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
              <i data-lucide="award" class="w-5 h-5"></i>
            </div>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <div>
              <div class="text-xs text-slate-500 font-semibold">Shortlisted</div>
              <div class="text-2xl font-heading font-black text-purple-600" id="statShortlistedCount">0</div>
            </div>
            <div class="h-8 w-px bg-slate-200"></div>
            <div>
              <div class="text-xs text-slate-500 font-semibold">Brand Face Winners</div>
              <div class="text-2xl font-heading font-black text-amber-500" id="statWinnersCount">0</div>
            </div>
          </div>
        </div>

        <!-- Card 4: Offline Backup & Sync -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Offline Fail-Safe</span>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between">
            <div>
              <div class="text-xs text-slate-500 font-medium">Backup Store</div>
              <div class="text-xl font-heading font-bold text-slate-800" id="statBackupCount">0 entries</div>
            </div>
            <button onclick="triggerSyncBackup()" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition flex items-center gap-1.5">
              <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Sync DB
            </button>
          </div>
        </div>

      </div>

      <!-- FILTER & CONTROL TOOLBAR -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
          
          <!-- Search Bar -->
          <div class="relative flex-1">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-3 text-slate-400"></i>
            <input type="text" id="searchInput" oninput="handleSearchInput()" placeholder="Search by ticket code, name, phone, email, or parlour store code..."
              class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
            <button id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
              <i data-lucide="x" class="w-4 h-4"></i>
            </button>
          </div>

          <!-- Dropdown Filters -->
          <div class="flex flex-wrap items-center gap-2">
            
            <!-- Category Filter -->
            <select id="filterCategory" onchange="loadEntries(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-red-600 cursor-pointer">
              <option value="">All Categories</option>
              <option value="male">Male (Solo)</option>
              <option value="female">Female (Solo)</option>
              <option value="child">Child & Family</option>
              <option value="group">Group Squad</option>
            </select>

            <!-- Status Filter -->
            <select id="filterStatus" onchange="loadEntries(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-red-600 cursor-pointer">
              <option value="">All Statuses</option>
              <option value="registered">Registered</option>
              <option value="under_review">Under Review</option>
              <option value="shortlisted">⭐ Shortlisted</option>
              <option value="winner">🏆 Winner</option>
              <option value="rejected">Rejected</option>
            </select>

            <!-- City Filter -->
            <select id="filterCity" onchange="loadEntries(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-red-600 cursor-pointer">
              <option value="">All Cities</option>
              <option value="pune">Pune</option>
              <option value="mumbai">Mumbai</option>
              <option value="nashik">Nashik</option>
              <option value="nagpur">Nagpur</option>
              <option value="kolhapur">Kolhapur</option>
              <option value="other">Other Outlets</option>
            </select>

            <!-- Sort By -->
            <select id="filterSort" onchange="loadEntries(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-red-600 cursor-pointer">
              <option value="newest">Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="name_asc">Name (A-Z)</option>
            </select>

            <button onclick="loadEntries(1)" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl border border-slate-200 transition" title="Refresh records">
              <i data-lucide="rotate-cw" class="w-4 h-4"></i>
            </button>
          </div>

        </div>

        <!-- Bulk Action Bar -->
        <div id="bulkActionBar" class="hidden p-2.5 bg-red-50 border border-red-200 rounded-xl flex items-center justify-between text-xs">
          <div class="flex items-center gap-2 text-red-800 font-semibold">
            <span id="selectedCount">0</span> selected:
          </div>
          <div class="flex items-center gap-2">
            <select id="bulkStatusSelect" class="px-2.5 py-1 bg-white border border-red-200 rounded-lg text-xs font-medium text-slate-700">
              <option value="shortlisted">Mark Shortlisted</option>
              <option value="winner">Mark Winner</option>
              <option value="under_review">Mark Under Review</option>
              <option value="rejected">Mark Rejected</option>
            </select>
            <button onclick="applyBulkStatus()" class="px-3 py-1 bg-[#D8232A] text-white rounded-lg font-bold hover:bg-[#B3181E] transition">Apply</button>
            <button onclick="applyBulkDelete()" class="px-3 py-1 bg-slate-200 text-slate-700 hover:bg-red-600 hover:text-white rounded-lg font-bold transition">Delete</button>
          </div>
        </div>

      </div>

      <!-- MAIN DATA TABLE -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        
        <div class="overflow-x-auto custom-scrollbar">
          <table class="w-full text-left text-xs text-slate-600 border-collapse">
            
            <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider">
              <tr>
                <th class="p-3 pl-4 w-10">
                  <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()" class="rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer">
                </th>
                <th class="p-3">Ticket ID</th>
                <th class="p-3">Contestant</th>
                <th class="p-3">Category</th>
                <th class="p-3">Contact</th>
                <th class="p-3">Location / Store</th>
                <th class="p-3">Reel / Video</th>
                <th class="p-3">Status</th>
                <th class="p-3 pr-4 text-right">Actions</th>
              </tr>
            </thead>

            <tbody id="entriesTableBody" class="divide-y divide-slate-100 font-medium">
              <tr>
                <td colspan="9" class="p-8 text-center text-slate-400">
                  <i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-[#D8232A]"></i>
                  Loading contest registrations...
                </td>
              </tr>
            </tbody>

          </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="p-4 border-t border-slate-200/80 bg-slate-50 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
          <div id="paginationInfo">Showing 0 to 0 of 0 entries</div>
          <div class="flex items-center gap-2">
            <button id="prevPageBtn" onclick="changePage(-1)" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition">
              &larr; Previous
            </button>
            <span id="currentPageNum" class="font-bold text-slate-800 font-mono px-2">1</span>
            <button id="nextPageBtn" onclick="changePage(1)" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold hover:bg-slate-100 disabled:opacity-50 disabled:cursor-not-allowed transition">
              Next &rarr;
            </button>
          </div>
        </div>

      </div>

    </main>
  </div>

  <!-- ========================================================= -->
  <!-- 3. MODALS (View Dossier, Edit, Create Manual Contestant)  -->
  <!-- ========================================================= -->

  <!-- Modal 1: View Full Contestant Dossier -->
  <div id="viewDossierModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200">
      <div class="bg-slate-900 text-white p-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          <h3 class="font-heading font-bold text-base">Contestant Dossier</h3>
        </div>
        <button onclick="closeModal('viewDossierModal')" class="text-slate-400 hover:text-white p-1 rounded-lg">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <div class="p-6 space-y-4 text-xs" id="dossierModalContent">
        <!-- Injected safely via JS -->
      </div>

      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
        <button onclick="closeModal('viewDossierModal')" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-xl text-xs hover:bg-slate-900 transition">
          Close Dossier
        </button>
      </div>
    </div>
  </div>

  <!-- Modal 2: Edit Contestant Details -->
  <div id="editEntryModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200">
      <div class="p-5 border-b border-slate-200 flex items-center justify-between">
        <h3 class="font-heading font-bold text-base text-slate-800">Edit Contestant Record</h3>
        <button onclick="closeModal('editEntryModal')" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="editEntryForm" onsubmit="handleEditSubmit(event)" class="p-6 space-y-3.5 text-xs">
        <input type="hidden" id="editEntryId" name="id">

        <div>
          <label class="block font-bold text-slate-600 mb-1">Full Name *</label>
          <input type="text" id="editFullName" name="full_name" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Mobile (10 digits) *</label>
            <input type="tel" id="editMobile" name="mobile_number" pattern="[0-9]{10}" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Email Address *</label>
            <input type="email" id="editEmail" name="email_address" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Category</label>
            <select id="editCategory" name="category" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none">
              <option value="male">Male (Solo)</option>
              <option value="female">Female (Solo)</option>
              <option value="child">Child / Family</option>
              <option value="group">Group Squad</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Status</label>
            <select id="editStatus" name="status" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none">
              <option value="registered">Registered</option>
              <option value="under_review">Under Review</option>
              <option value="shortlisted">⭐ Shortlisted</option>
              <option value="winner">🏆 Winner</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">City</label>
            <input type="text" id="editCity" name="city" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Store Code</label>
            <input type="text" id="editStoreCode" name="store_code" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Instagram Handle</label>
            <input type="text" id="editInstagram" name="instagram_handle" placeholder="@username" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Reel / Video URL</label>
            <input type="url" id="editVideoUrl" name="video_url" placeholder="https://..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" onclick="closeModal('editEntryModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold transition">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold shadow-xs transition">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal 3: Create Manual Contestant -->
  <div id="createEntryModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-200">
      <div class="p-5 border-b border-slate-200 bg-gradient-to-r from-[#D8232A] to-[#B3181E] text-white flex items-center justify-between">
        <h3 class="font-heading font-bold text-base">New Walk-In Contestant Registration</h3>
        <button onclick="closeModal('createEntryModal')" class="text-red-200 hover:text-white p-1 rounded-lg">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="createEntryForm" onsubmit="handleCreateSubmit(event)" class="p-6 space-y-3.5 text-xs">
        <div>
          <label class="block font-bold text-slate-600 mb-1">Full Name *</label>
          <input type="text" name="full_name" required placeholder="e.g. Aryan Deshmukh" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Mobile (10 digits) *</label>
            <input type="tel" name="mobile_number" pattern="[0-9]{10}" required placeholder="9822012345" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Email Address *</label>
            <input type="email" name="email_address" required placeholder="aryan@example.com" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Category *</label>
            <select name="category" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none">
              <option value="male">Male (Solo)</option>
              <option value="female">Female (Solo)</option>
              <option value="child">Child / Family</option>
              <option value="group">Group Squad</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Initial Status</label>
            <select name="status" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none">
              <option value="registered">Registered</option>
              <option value="under_review">Under Review</option>
              <option value="shortlisted">⭐ Shortlisted</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Nearest City</label>
            <select name="city" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none">
              <option value="pune">Pune</option>
              <option value="mumbai">Mumbai</option>
              <option value="nashik">Nashik</option>
              <option value="nagpur">Nagpur</option>
              <option value="kolhapur">Kolhapur</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Parlour Store Code</label>
            <input type="text" name="store_code" value="PUN-FC-01" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-slate-600 mb-1">Instagram @Handle</label>
            <input type="text" name="instagram_handle" placeholder="@username" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block font-bold text-slate-600 mb-1">Reel / Video URL</label>
            <input type="url" name="video_url" placeholder="https://..." class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:border-red-600 focus:bg-white focus:outline-none">
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" onclick="closeModal('createEntryModal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold transition">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold shadow-xs transition">Create Contestant Entry</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- JAVASCRIPT DASHBOARD LOGIC                                -->
  <!-- ========================================================= -->
  <script>
    const CSRF_TOKEN = '<?= $csrfToken ?>';
    let currentPage = 1;
    let totalPages = 1;
    let selectedEntryIds = new Set();
    let currentEntriesCache = [];

    document.addEventListener('DOMContentLoaded', async () => {
      if (window.lucide) lucide.createIcons();
      await refreshDashboardData();
    });

    async function handleLogout() {
      await fetch('api.php?action=logout', { method: 'POST' });
      window.location.reload();
    }

    async function refreshDashboardData() {
      await Promise.all([loadStats(), loadEntries(currentPage)]);
    }

    async function loadStats() {
      try {
        const res = await fetch('api.php?action=get_stats');
        const data = await res.json();
        if (data.success) {
          const s = data.stats;
          document.getElementById('statTotalCount').textContent = s.total;
          document.getElementById('statMaleCount').textContent = s.categories.male || 0;
          document.getElementById('statFemaleCount').textContent = s.categories.female || 0;
          document.getElementById('statChildCount').textContent = s.categories.child || 0;
          document.getElementById('statGroupCount').textContent = s.categories.group || 0;
          document.getElementById('statShortlistedCount').textContent = s.shortlisted || 0;
          document.getElementById('statWinnersCount').textContent = s.winners || 0;
          document.getElementById('statBackupCount').textContent = (s.backup_count || 0) + ' entries';

          const ind = document.getElementById('dbStatusIndicator');
          if (s.db_connected) {
            ind.textContent = 'Hostinger MySQL Connected';
          } else {
            ind.textContent = 'Offline File Storage Active';
          }
        }
      } catch (err) {
        console.error('Stats load error', err);
      }
    }

    let searchDebounceTimer = null;
    function handleSearchInput() {
      clearTimeout(searchDebounceTimer);
      const val = document.getElementById('searchInput').value.trim();
      document.getElementById('clearSearchBtn').classList.toggle('hidden', val.length === 0);
      searchDebounceTimer = setTimeout(() => {
        loadEntries(1);
      }, 300);
    }

    function clearSearch() {
      document.getElementById('searchInput').value = '';
      document.getElementById('clearSearchBtn').classList.add('hidden');
      loadEntries(1);
    }

    async function loadEntries(page = 1) {
      currentPage = page;
      const search   = document.getElementById('searchInput').value.trim();
      const category = document.getElementById('filterCategory').value;
      const status   = document.getElementById('filterStatus').value;
      const city     = document.getElementById('filterCity').value;
      const sort     = document.getElementById('filterSort').value;

      const tbody = document.getElementById('entriesTableBody');
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="p-8 text-center text-slate-400">
            <i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-[#D8232A]"></i>
            Fetching contestant records...
          </td>
        </tr>
      `;
      if (window.lucide) lucide.createIcons();

      try {
        const query = new URLSearchParams({
          action: 'get_entries',
          page,
          limit: 25,
          search,
          category,
          status,
          city,
          sort
        });

        const res = await fetch('api.php?' + query.toString());
        const data = await res.json();

        if (data.success) {
          currentEntriesCache = data.entries || [];
          totalPages = data.total_pages || 1;

          renderTableRows(currentEntriesCache);
          updatePaginationUI(data.total, data.page, data.limit);
        }
      } catch (err) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="p-8 text-center text-red-500">
              Failed to load records from server.
            </td>
          </tr>
        `;
      }
    }

    function renderTableRows(entries) {
      const tbody = document.getElementById('entriesTableBody');
      tbody.innerHTML = '';

      if (entries.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" class="p-10 text-center text-slate-400">
              <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
              No contestant records match your filters.
            </td>
          </tr>
        `;
        if (window.lucide) lucide.createIcons();
        return;
      }

      entries.forEach(row => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50/80 transition-colors';
        const isChecked = selectedEntryIds.has(row.id);

        const catIcons = {
          'male': '👨',
          'female': '👩',
          'child': '🧒',
          'group': '👥'
        };

        const statusStyles = {
          'registered': 'bg-slate-100 text-slate-700 border-slate-200',
          'under_review': 'bg-blue-50 text-blue-700 border-blue-200',
          'shortlisted': 'bg-purple-50 text-purple-700 border-purple-200',
          'winner': 'bg-amber-100 text-amber-800 border-amber-300 font-bold',
          'rejected': 'bg-red-50 text-red-700 border-red-200'
        };

        const currStatusStyle = statusStyles[row.status] || statusStyles['registered'];
        const validatedVideoUrl = safeUrl(row.video_url);

        tr.innerHTML = `
          <td class="p-3 pl-4">
            <input type="checkbox" onchange="toggleSelectRow(${row.id})" ${isChecked ? 'checked' : ''} class="row-checkbox rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer">
          </td>
          
          <td class="p-3 font-mono font-bold text-[#D8232A] whitespace-nowrap">
            ${escapeHtml(row.entry_code || '#M-2026')}
          </td>

          <td class="p-3">
            <div class="font-bold text-slate-900">${escapeHtml(row.full_name || 'Contestant')}</div>
            <div class="text-[11px] text-slate-500 flex items-center gap-1">
              ${row.instagram_handle ? `<span class="text-blue-600 font-semibold">${escapeHtml(row.instagram_handle)}</span>` : '<span class="text-slate-400">No handle</span>'}
            </div>
          </td>

          <td class="p-3 whitespace-nowrap">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-800 border border-slate-200">
              <span>${catIcons[row.category] || '🍦'}</span>
              <span class="capitalize">${escapeHtml(row.category || 'male')}</span>
            </span>
          </td>

          <td class="p-3 whitespace-nowrap">
            <div class="text-slate-800 font-mono text-xs">${escapeHtml(row.mobile_number || '-')}</div>
            <div class="text-[11px] text-slate-500">${escapeHtml(row.email_address || '-')}</div>
          </td>

          <td class="p-3 whitespace-nowrap">
            <div class="capitalize text-slate-800 font-semibold">${escapeHtml(row.city || 'pune')}</div>
            <span class="font-mono text-[11px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">${escapeHtml(row.store_code || 'GENERAL')}</span>
          </td>

          <td class="p-3 whitespace-nowrap">
            ${validatedVideoUrl !== '#' ? `
              <a href="${validatedVideoUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-pink-50 hover:bg-pink-100 text-pink-700 text-xs font-bold transition">
                <i data-lucide="video" class="w-3.5 h-3.5"></i> Watch Reel
              </a>
            ` : '<span class="text-slate-400 text-xs italic">Pending</span>'}
          </td>

          <td class="p-3 whitespace-nowrap">
            <select onchange="updateRowStatus(${row.id}, this.value)" class="text-xs font-bold px-2 py-1 rounded-lg border cursor-pointer focus:outline-none transition ${currStatusStyle}">
              <option value="registered" ${row.status === 'registered' ? 'selected' : ''}>Registered</option>
              <option value="under_review" ${row.status === 'under_review' ? 'selected' : ''}>Under Review</option>
              <option value="shortlisted" ${row.status === 'shortlisted' ? 'selected' : ''}>⭐ Shortlisted</option>
              <option value="winner" ${row.status === 'winner' ? 'selected' : ''}>🏆 Winner</option>
              <option value="rejected" ${row.status === 'rejected' ? 'selected' : ''}>Rejected</option>
            </select>
          </td>

          <td class="p-3 pr-4 text-right whitespace-nowrap">
            <div class="inline-flex items-center gap-1">
              <button onclick="viewDossier(${row.id})" class="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition" title="View Full Dossier">
                <i data-lucide="eye" class="w-4 h-4"></i>
              </button>
              <button onclick="openEditModal(${row.id})" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit Record">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
              </button>
              <button onclick="deleteSingleEntry(${row.id})" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </div>
          </td>
        `;

        tbody.appendChild(tr);
      });

      if (window.lucide) lucide.createIcons();
    }

    function updatePaginationUI(total, page, limit) {
      const from = total === 0 ? 0 : (page - 1) * limit + 1;
      const to = Math.min(page * limit, total);
      document.getElementById('paginationInfo').textContent = `Showing ${from} to ${to} of ${total} entries`;
      document.getElementById('currentPageNum').textContent = page;
      document.getElementById('prevPageBtn').disabled = (page <= 1);
      document.getElementById('nextPageBtn').disabled = (page >= totalPages);
    }

    function changePage(delta) {
      const next = currentPage + delta;
      if (next >= 1 && next <= totalPages) {
        loadEntries(next);
      }
    }

    async function updateRowStatus(id, newStatus) {
      try {
        const res = await fetch('api.php?action=update_status', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({ id, status: newStatus, csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.success) {
          if (newStatus === 'winner') {
            confetti({ particleCount: 75, spread: 70, origin: { y: 0.6 } });
          }
          await loadStats();
          loadEntries(currentPage);
        } else {
          alert(data.message || 'Failed to update status');
        }
      } catch (err) {
        alert('Server communication error');
      }
    }

    function toggleSelectAll() {
      const selectAll = document.getElementById('selectAllCheckbox').checked;
      selectedEntryIds.clear();
      if (selectAll) {
        currentEntriesCache.forEach(r => selectedEntryIds.add(r.id));
      }
      document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = selectAll);
      updateBulkActionBar();
    }

    function toggleSelectRow(id) {
      if (selectedEntryIds.has(id)) {
        selectedEntryIds.delete(id);
      } else {
        selectedEntryIds.add(id);
      }
      updateBulkActionBar();
    }

    function updateBulkActionBar() {
      const bar = document.getElementById('bulkActionBar');
      const countEl = document.getElementById('selectedCount');
      if (selectedEntryIds.size > 0) {
        countEl.textContent = selectedEntryIds.size;
        bar.classList.remove('hidden');
      } else {
        bar.classList.add('hidden');
      }
    }

    async function applyBulkStatus() {
      const status = document.getElementById('bulkStatusSelect').value;
      if (selectedEntryIds.size === 0) return;

      const res = await fetch('api.php?action=bulk_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({
          ids: Array.from(selectedEntryIds),
          sub_action: 'status_update',
          status: status,
          csrf_token: CSRF_TOKEN
        })
      });
      const data = await res.json();
      if (data.success) {
        selectedEntryIds.clear();
        updateBulkActionBar();
        await loadStats();
        loadEntries(currentPage);
      } else {
        alert(data.message);
      }
    }

    async function applyBulkDelete() {
      if (!confirm(`Are you sure you want to delete ${selectedEntryIds.size} records? This cannot be undone.`)) return;

      const res = await fetch('api.php?action=bulk_action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({
          ids: Array.from(selectedEntryIds),
          sub_action: 'delete',
          csrf_token: CSRF_TOKEN
        })
      });
      const data = await res.json();
      if (data.success) {
        selectedEntryIds.clear();
        updateBulkActionBar();
        await loadStats();
        loadEntries(currentPage);
      } else {
        alert(data.message);
      }
    }

    function viewDossier(id) {
      const r = currentEntriesCache.find(x => x.id === id);
      if (!r) return;

      const validatedVideoUrl = safeUrl(r.video_url);
      const content = document.getElementById('dossierModalContent');
      content.innerHTML = `
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <div class="text-base font-heading font-black text-slate-900">${escapeHtml(r.full_name || 'Contestant')}</div>
            <div class="text-slate-500 font-mono">${escapeHtml(r.entry_code || '')}</div>
          </div>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-100 border">${escapeHtml(r.status || 'registered')}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-1">
          <div class="p-2.5 bg-slate-50 rounded-xl">
            <div class="text-[11px] text-slate-500 uppercase font-semibold">Mobile Number</div>
            <div class="font-mono font-bold text-slate-800 mt-0.5">${escapeHtml(r.mobile_number || '-')}</div>
          </div>
          <div class="p-2.5 bg-slate-50 rounded-xl">
            <div class="text-[11px] text-slate-500 uppercase font-semibold">Email Address</div>
            <div class="font-bold text-slate-800 mt-0.5 truncate">${escapeHtml(r.email_address || '-')}</div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="p-2.5 bg-slate-50 rounded-xl">
            <div class="text-[11px] text-slate-500 uppercase font-semibold">Category</div>
            <div class="font-bold text-slate-800 mt-0.5 capitalize">${escapeHtml(r.category || '-')}</div>
          </div>
          <div class="p-2.5 bg-slate-50 rounded-xl">
            <div class="text-[11px] text-slate-500 uppercase font-semibold">Location & Store Code</div>
            <div class="font-bold text-slate-800 mt-0.5 capitalize">${escapeHtml(r.city || '-')} (${escapeHtml(r.store_code || 'GENERAL')})</div>
          </div>
        </div>

        <div class="p-3 bg-pink-50/70 border border-pink-100 rounded-xl">
          <div class="text-[11px] text-pink-700 uppercase font-bold">Instagram Reel / Video Submission</div>
          ${validatedVideoUrl !== '#' ? `
            <a href="${validatedVideoUrl}" target="_blank" rel="noopener noreferrer" class="text-xs text-pink-600 font-bold hover:underline break-all mt-1 inline-block">
              ${escapeHtml(r.video_url)} ↗
            </a>
          ` : '<div class="text-xs text-slate-400 mt-1 italic">No URL provided yet</div>'}
        </div>

        <div class="p-2.5 bg-slate-50 rounded-xl text-[11px] text-slate-500 space-y-1">
          <div>Client IP: <span class="font-mono text-slate-700">${escapeHtml(r.ip_address || 'UNKNOWN')}</span></div>
          <div>Submitted: <span class="text-slate-700">${escapeHtml(r.created_at || '-')}</span></div>
        </div>
      `;

      openModal('viewDossierModal');
    }

    function openEditModal(id) {
      const r = currentEntriesCache.find(x => x.id === id);
      if (!r) return;

      document.getElementById('editEntryId').value = r.id;
      document.getElementById('editFullName').value = r.full_name || '';
      document.getElementById('editMobile').value = r.mobile_number || '';
      document.getElementById('editEmail').value = r.email_address || '';
      document.getElementById('editCategory').value = r.category || 'male';
      document.getElementById('editStatus').value = r.status || 'registered';
      document.getElementById('editCity').value = r.city || 'pune';
      document.getElementById('editStoreCode').value = r.store_code || 'GENERAL';
      document.getElementById('editInstagram').value = r.instagram_handle || '';
      document.getElementById('editVideoUrl').value = r.video_url || '';

      openModal('editEntryModal');
    }

    async function handleEditSubmit(e) {
      e.preventDefault();
      const form = document.getElementById('editEntryForm');
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      payload.csrf_token = CSRF_TOKEN;

      try {
        const res = await fetch('api.php?action=update_entry', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          closeModal('editEntryModal');
          await loadStats();
          loadEntries(currentPage);
        } else {
          alert(data.message);
        }
      } catch (err) {
        alert('Server update error');
      }
    }

    function openCreateModal() {
      document.getElementById('createEntryForm').reset();
      openModal('createEntryModal');
    }

    async function handleCreateSubmit(e) {
      e.preventDefault();
      const form = document.getElementById('createEntryForm');
      const formData = new FormData(form);
      const payload = Object.fromEntries(formData.entries());
      payload.csrf_token = CSRF_TOKEN;

      try {
        const res = await fetch('api.php?action=create_entry', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          closeModal('createEntryModal');
          confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
          await loadStats();
          loadEntries(1);
        } else {
          alert(data.message);
        }
      } catch (err) {
        alert('Failed to create contestant record');
      }
    }

    // Immune to attribute injection XSS: reads name safely from in-memory cache
    async function deleteSingleEntry(id) {
      const r = currentEntriesCache.find(x => x.id === id);
      const name = r ? r.full_name : 'this contestant';

      if (!confirm(`Are you sure you want to delete contestant record "${name}" (ID #${id})?`)) return;

      try {
        const res = await fetch('api.php?action=delete_entry', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify({ id, csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.success) {
          await loadStats();
          loadEntries(currentPage);
        } else {
          alert(data.message);
        }
      } catch (err) {
        alert('Failed to delete entry');
      }
    }

    async function triggerSyncBackup() {
      try {
        const res = await fetch('api.php?action=sync_backup', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.success) {
          alert(data.message);
          await loadStats();
          loadEntries(1);
        } else {
          alert(data.message || 'Sync failed');
        }
      } catch (err) {
        alert('Error communicating with sync endpoint');
      }
    }

    // Authenticated CSV Export without secret keys in URL
    function exportFilteredCSV() {
      const search   = document.getElementById('searchInput').value.trim();
      const category = document.getElementById('filterCategory').value;
      const status   = document.getElementById('filterStatus').value;
      const city     = document.getElementById('filterCity').value;

      const query = new URLSearchParams({
        action: 'export_csv',
        search,
        category,
        status,
        city
      });

      window.location.href = 'api.php?' + query.toString();
    }

    function openModal(id) {
      document.getElementById(id).classList.remove('hidden');
      if (window.lucide) lucide.createIcons();
    }

    function closeModal(id) {
      document.getElementById(id).classList.add('hidden');
    }

    function escapeHtml(str) {
      if (typeof str !== 'string') return str;
      return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // URL Scheme validation prevents pseudo-protocol XSS (javascript:, data:)
    function safeUrl(url) {
      if (!url) return '#';
      const u = url.trim();
      return /^https?:\/\//i.test(u) ? escapeHtml(u) : '#';
    }
  </script>
<?php endif; ?>

</body>
</html>
