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
$adminUser = htmlspecialchars($_SESSION['admin_user'] ?? 'admin', ENT_QUOTES, 'UTF-8');
$adminName = htmlspecialchars($_SESSION['admin_name'] ?? 'Super Administrator', ENT_QUOTES, 'UTF-8');
$adminRole = htmlspecialchars($_SESSION['admin_role'] ?? 'superadmin', ENT_QUOTES, 'UTF-8');
$csrfToken = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Superadmin Dashboard — Meridian Ice Cream Contest</title>

  <!-- Official Favicons -->
  <link rel="icon" type="image/png" sizes="32x32"
    href="../assets/official/icon_cropped-ms-icon-310x310-removebg-preview-32x32.png">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap"
    rel="stylesheet">

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

    h1,
    h2,
    h3,
    h4,
    h5,
    .font-heading {
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
    <div
      class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-[#FFF7ED] via-white to-[#FEF2F2]">
      <div
        class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200/80 overflow-hidden transition-all">

        <!-- Card Header -->
        <div class="bg-gradient-to-r from-[#D8232A] to-[#B3181E] p-8 text-center text-white relative">
          <div class="inline-block p-3.5 bg-white rounded-2xl shadow-md mb-4">
            <img src="../assets/official/meridian-logo-red.svg" alt="Meridian Ice Cream" class="h-10 w-auto mx-auto">
          </div>
          <h1 class="text-2xl font-heading font-black tracking-tight">Superadmin Control</h1>
          <p class="text-xs text-red-100 mt-1 uppercase tracking-wider font-semibold">Brand Ambassador Campaign 2026-2027
          </p>
        </div>

        <!-- Login Form -->
        <form id="loginForm" onsubmit="handleLoginSubmit(event)" class="p-8 space-y-5">
          <div id="loginErrorMsg"
            class="hidden p-3 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-700 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
            <span id="loginErrorText">Invalid credentials.</span>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5"
              for="usernameInput">Username</label>
            <div class="relative">
              <i data-lucide="user" class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400"></i>
              <input type="text" id="usernameInput" required placeholder="admin" value="admin"
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5"
              for="passwordInput">Password / Passkey</label>
            <div class="relative">
              <i data-lucide="lock" class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400"></i>
              <input type="password" id="passwordInput" required placeholder="••••••••••••"
                class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
              <button type="button" onclick="togglePasswordVisibility()"
                class="absolute right-3 top-3 text-slate-400 hover:text-slate-600">
                <i data-lucide="eye" id="pwdEyeIcon" class="w-4 h-4"></i>
              </button>
            </div>
          </div>

          <button type="submit" id="loginBtn"
            class="w-full py-3 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-heading font-bold text-sm shadow-md shadow-red-600/20 hover:shadow-lg transition flex items-center justify-center gap-2">
            <i data-lucide="log-in" class="w-4 h-4"></i>
            <span>Enter Dashboard</span>
          </button>
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
      <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/90 shadow-2xs">
        <div
          class="w-full max-w-full px-4 sm:px-6 lg:px-8 min-h-16 py-2.5 sm:py-0 flex flex-wrap md:flex-nowrap items-center justify-between gap-3 sm:gap-4">

          <!-- Left: Brand & Title -->
          <div class="flex items-center gap-3 shrink-0">
            <a href="../" target="_blank" title="View Public Landing Page"
              class="flex items-center gap-2 hover:opacity-85 transition">
              <img src="../assets/official/meridian-logo-red.svg" alt="Meridian Ice Cream" class="h-8 sm:h-9 w-auto">
            </a>
            <div class="hidden sm:block h-6 w-px bg-slate-200"></div>
            <div class="hidden sm:flex items-center gap-2">
              <span class="font-heading font-black text-lg text-slate-800 leading-none">Superadmin Hub</span>
              <span class="px-2.5 py-0.5 text-[11px] font-bold bg-red-100 text-[#D8232A] rounded-full">
                2026-2027
              </span>
            </div>
          </div>

          <!-- Center: Database Engine Status Pill -->


          <!-- Right: User Actions & Utilities -->
          <div class="flex items-center gap-2 sm:gap-3 flex-wrap sm:flex-nowrap justify-end ml-auto">

            <!-- Primary Action: Create Contestant -->
            <button onclick="openCreateModal()"
              class="h-9 sm:h-10 px-3.5 sm:px-4 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl text-xs sm:text-sm font-heading font-bold shadow-xs hover:shadow transition flex items-center gap-1.5 shrink-0">
              <i data-lucide="plus-circle" class="w-4 h-4"></i>
              <span>New Contestant</span>
            </button>

            <!-- Secondary Utilities Group with generous spacing -->
            <div class="flex items-center gap-2 shrink-0">
              <button onclick="exportFilteredCSV()"
                class="h-9 sm:h-10 px-3 sm:px-3.5 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-xl text-xs sm:text-sm font-semibold border border-slate-200 transition flex items-center gap-1.5"
                title="Export Filtered CSV">
                <i data-lucide="download" class="w-4 h-4 text-slate-500"></i>
                <span class="hidden md:inline">Export</span>
              </button>

              <button onclick="refreshDashboard()"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition flex items-center justify-center shrink-0"
                title="Refresh Live Data">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
              </button>

              <a href="../" target="_blank"
                class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition flex items-center justify-center shrink-0"
                title="View Public Website">
                <i data-lucide="external-link" class="w-4 h-4"></i>
              </a>
            </div>

            <div class="hidden sm:block h-6 w-px bg-slate-200 mx-0.5"></div>

            <!-- Superadmin Account & Password Button -->
            <button onclick="openChangePasswordModal()"
              class="h-9 sm:h-10 flex items-center gap-2 px-2.5 sm:px-3 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-xl transition text-left shrink-0"
              title="Click to manage database password">
              <div
                class="w-6 h-6 rounded-lg bg-[#D8232A] text-white flex items-center justify-center font-heading font-black text-[11px] shadow-xs">
                <?= strtoupper(substr($adminUser, 0, 1)) ?>
              </div>
              <div class="hidden xl:block text-[11px] leading-tight">
                <span class="font-bold text-slate-800"><?= $adminUser ?></span>
                <span class="text-[9px] text-emerald-600 font-semibold block uppercase tracking-wider">DB
                  Superadmin</span>
              </div>
              <i data-lucide="key-round" class="w-3.5 h-3.5 text-slate-400"></i>
            </button>

            <!-- Logout Button -->
            <button onclick="handleLogout()"
              class="h-9 sm:h-10 px-3 sm:px-3.5 text-xs sm:text-sm font-bold text-red-600 bg-red-50/60 hover:bg-red-100/80 border border-red-200 rounded-xl transition flex items-center gap-1.5 shrink-0"
              title="Logout of dashboard">
              <i data-lucide="log-out" class="w-4 h-4"></i>
              <span class="hidden md:inline">Logout</span>
            </button>

          </div>

        </div>
      </header>

      <!-- Main Content Container -->
      <main class="w-full max-w-full px-4 sm:px-6 lg:px-8 py-6 flex-1 space-y-6">

        <!-- STATS OVERVIEW CARDS (4 Cards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

          <!-- Card 1: Total Contestants -->
          <div
            class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Contestants</span>
              <div class="w-9 h-9 rounded-xl bg-red-50 text-[#D8232A] flex items-center justify-center">
                <i data-lucide="users" class="w-5 h-5"></i>
              </div>
            </div>
            <div class="mt-4">
              <div class="flex items-baseline justify-between">
                <div class="text-3xl font-heading font-black text-slate-900" id="statTotalCount">0</div>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-pink-50 border border-pink-200 text-pink-700 text-xs font-bold" title="Contestants who inserted Instagram Reel URL">
                  <i data-lucide="video" class="w-3.5 h-3.5"></i> <span id="statWithReelCount">0</span> Reels
                </span>
              </div>
              <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                <span class="text-emerald-600 font-bold flex items-center"><i data-lucide="trending-up"
                    class="w-3.5 h-3.5 mr-0.5"></i> Live</span>
                <span>Across Maharashtra</span>
              </div>
            </div>
          </div>

          <!-- Card 2: 5 Categories Breakdown -->
          <div
            class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">5 Contest Categories</span>
              <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i data-lucide="pie-chart" class="w-5 h-5"></i>
              </div>
            </div>
            <div class="mt-3 space-y-1.5 text-xs">
              <div class="grid grid-cols-2 gap-1.5">
                <div class="p-1.5 px-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                  <span class="text-slate-600 flex items-center gap-1 font-medium">👨 Male:</span>
                  <strong class="font-bold text-slate-900 font-mono text-xs" id="statMaleCount">0</strong>
                </div>
                <div class="p-1.5 px-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                  <span class="text-slate-600 flex items-center gap-1 font-medium">👩 Female:</span>
                  <strong class="font-bold text-slate-900 font-mono text-xs" id="statFemaleCount">0</strong>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <div class="p-1.5 px-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                  <span class="text-slate-600 flex items-center gap-1 font-medium">🧒 Kids:</span>
                  <strong class="font-bold text-slate-900 font-mono text-xs" id="statKidsCount">0</strong>
                </div>
                <div class="p-1.5 px-2 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                  <span class="text-slate-600 flex items-center gap-1 font-medium">👥 Group/Fam:</span>
                  <strong class="font-bold text-slate-900 font-mono text-xs" id="statGroupCount">0</strong>
                </div>
              </div>
              <div class="p-1.5 px-2.5 rounded-lg bg-red-50/80 border border-red-100 flex items-center justify-between">
                <span class="text-red-700 flex items-center gap-1 font-bold">🎙️ Voice of Meridian:</span>
                <strong class="font-bold text-red-900 font-mono text-xs" id="statVoiceCount">0</strong>
              </div>
            </div>
          </div>

          <!-- Card 3: Shortlisted & Winners -->
          <div
            class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
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

          <!-- Card 4: Store Parlour Network & Today -->
          <div
            class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-300 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Parlours & Activity</span>
              <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="store" class="w-5 h-5"></i>
              </div>
            </div>
            <div class="mt-4 flex items-center justify-between">
              <div>
                <div class="text-xs text-slate-500 font-semibold">Today</div>
                <div class="text-2xl font-heading font-black text-blue-600" id="statTodayCount">0</div>
              </div>
              <div class="h-8 w-px bg-slate-200"></div>
              <div>
                <div class="text-xs text-slate-500 font-semibold">Active Parlours</div>
                <div class="text-2xl font-heading font-black text-slate-900" id="statActiveParlours">0</div>
              </div>
            </div>
          </div>

        </div>

        <!-- FILTER & CONTROL TOOLBAR -->
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">

          <!-- Row 1: Search Bar & Reset -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">

            <!-- Search Bar -->
            <div class="relative flex-1">
              <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-3.5 text-slate-400"></i>
              <input type="text" id="searchInput" oninput="handleSearchInput()"
                placeholder="Search by ticket code (#M-2026-...), name, mobile, email, parlour store code..."
                class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:border-red-600 focus:bg-white transition">
              <button id="clearSearchBtn" onclick="clearSearch()"
                class="hidden absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <i data-lucide="x" class="w-4 h-4"></i>
              </button>
            </div>

            <!-- Duplicates Filter Toggle -->
            <button onclick="toggleDuplicatesFilter()" id="btnFilterDuplicates"
              class="h-10 px-3.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200/90 rounded-xl text-xs sm:text-sm font-bold transition flex items-center justify-center gap-1.5 shrink-0 shadow-2xs"
              title="Show only duplicate contestant entries">
              <i data-lucide="copy" class="w-4 h-4 text-amber-600"></i>
              <span>Duplicates</span>
              <span id="statDupBadge" class="bg-amber-200/90 text-amber-900 text-[11px] font-black px-1.5 py-0.5 rounded-full">0</span>
            </button>

            <!-- Merge & Archive Duplicates Action -->
            <button onclick="triggerMergeAndArchiveDuplicates()" id="btnMergeArchive"
              class="h-10 px-3.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-200/90 rounded-xl text-xs sm:text-sm font-bold transition flex items-center justify-center gap-1.5 shrink-0 shadow-2xs"
              title="Treat fully completed entries as Original, merge missing fields, and archive incomplete copies">
              <i data-lucide="sparkles" class="w-4 h-4 text-emerald-600"></i>
              <span>Merge &amp; Archive</span>
            </button>

            <!-- Reset Filters Button with generous spacing -->
            <button onclick="resetAllFilters()"
              class="h-10 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs sm:text-sm font-bold border border-slate-200 transition flex items-center justify-center gap-2 shrink-0"
              title="Reset all search queries and dropdown filters">
              <i data-lucide="rotate-ccw" class="w-4 h-4 text-slate-500"></i>
              <span>Reset Filters</span>
            </button>
          </div>

          <!-- Row 2: 5 Modern Responsive Filter Dropdowns -->
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3">

            <!-- Category Filter -->
            <div class="relative">
              <select id="filterCategory" onchange="loadEntries(1)"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-red-600 focus:bg-white cursor-pointer transition">
                <option value="">All 5 Categories</option>
                <option value="male">👨 Male (Solo)</option>
                <option value="female">👩 Female (Solo)</option>
                <option value="kids">🧒 Kids</option>
                <option value="group_family">👥 Group / Family</option>
                <option value="voice_of_meridian">🎙️ Voice of Meridian</option>
                <option value="Uploaded Reels">🎬 Uploaded Reels Only</option>
              </select>
            </div>

            <!-- Instagram Reel / Video URL Filter -->
            <div class="relative">
              <select id="filterReel" onchange="loadEntries(1)"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-red-600 focus:bg-white cursor-pointer transition">
                <option value="">All Reel Submissions</option>
                <option value="has_reel">🎬 With Reel URL</option>
                <option value="no_reel">⏳ Without Reel URL</option>
              </select>
            </div>

            <!-- Status Filter -->
            <div class="relative">
              <select id="filterStatus" onchange="loadEntries(1)"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-red-600 focus:bg-white cursor-pointer transition">
                <option value="">All Statuses</option>
                <option value="registered">Registered</option>
                <option value="under_review">Under Review</option>
                <option value="shortlisted">⭐ Shortlisted</option>
                <option value="winner">🏆 Winner</option>
                <option value="rejected">Rejected</option>
                <option value="archived">📦 Archived</option>
              </select>
            </div>

            <!-- City Filter -->
            <div class="relative">
              <select id="filterCity" onchange="loadEntries(1)"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-red-600 focus:bg-white cursor-pointer transition">
                <option value="">All Cities</option>
                <option value="pune">Pune</option>
                <option value="mumbai">Mumbai</option>
                <option value="nashik">Nashik</option>
                <option value="nagpur">Nagpur</option>
                <option value="kolhapur">Kolhapur</option>
                <option value="other">Other Outlets</option>
              </select>
            </div>

            <!-- Sort By -->
            <div class="relative col-span-2 sm:col-span-1">
              <select id="filterSort" onchange="loadEntries(1)"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-semibold text-slate-700 focus:outline-none focus:border-red-600 focus:bg-white cursor-pointer transition">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="duplicates">👯 Group Duplicates</option>
                <option value="name_asc">Name (A-Z)</option>
              </select>
            </div>

          </div>

          <!-- Elevated Bulk Action Bar with clear spacing between actions -->
          <div id="bulkActionBar"
            class="hidden p-4 bg-red-50/90 border border-red-200/90 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs shadow-xs">
            <div class="flex items-center gap-2.5 text-red-900 font-bold">
              <div class="w-6 h-6 rounded-lg bg-red-600 text-white flex items-center justify-center text-xs">
                <i data-lucide="check" class="w-3.5 h-3.5"></i>
              </div>
              <div>
                <span id="selectedCount" class="font-black text-sm">0</span> contestants selected
              </div>
              <button onclick="clearAllSelections()"
                class="text-xs text-red-700 hover:text-red-900 underline ml-2 font-medium">Deselect all</button>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
              <select id="bulkStatusSelect"
                class="h-9.5 px-3 bg-white border border-red-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none">
                <option value="shortlisted">⭐ Mark Shortlisted</option>
                <option value="winner">🏆 Mark Winner</option>
                <option value="under_review">Mark Under Review</option>
                <option value="rejected">Mark Rejected</option>
                <option value="archived">📦 Move to Archive</option>
              </select>
              <button onclick="applyBulkStatus()"
                class="h-9.5 px-4 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold shadow-xs transition flex items-center gap-1.5">
                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Apply Status
              </button>

              <button onclick="applyBulkArchive()"
                class="h-9.5 px-3.5 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-xl font-bold transition flex items-center gap-1.5 border border-amber-300">
                <i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive Selected
              </button>

              <div class="h-6 w-px bg-red-200 mx-1 hidden sm:block"></div>

              <button onclick="applyBulkDelete()"
                class="h-9.5 px-4 bg-white border border-red-300 text-red-600 hover:bg-red-600 hover:text-white rounded-xl font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete Selected
              </button>
            </div>
          </div>

        </div>

        <!-- MAIN DATA CONTAINER (Desktop Table + Mobile Cards) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">

          <!-- 1. Desktop Table View (Hidden on mobile) -->
          <div class="hidden md:block overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 border-collapse">
              <thead
                class="bg-slate-50/90 border-b border-slate-200/80 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                <tr>
                  <th class="p-3.5 pl-5 w-12 text-center">
                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()"
                      class="w-4 h-4 rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer">
                  </th>
                  <th class="p-3.5">Ticket ID</th>
                  <th class="p-3.5">Contestant</th>
                  <th class="p-3.5">Category</th>
                  <th class="p-3.5">Contact</th>
                  <th class="p-3.5">Location / Store</th>
                  <th class="p-3.5">Reel / Video</th>
                  <th class="p-3.5">Review Status</th>
                  <th class="p-3.5 pr-6 text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="entriesTableBody" class="divide-y divide-slate-100 font-medium">
                <tr>
                  <td colspan="9" class="p-10 text-center text-slate-400">
                    <i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-[#D8232A]"></i>
                    Loading contest registrations...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 2. Mobile Cards Stream (Visible only on mobile < 768px) -->
          <div id="entriesMobileFeed" class="md:hidden p-3.5 sm:p-4 space-y-3.5">
            <div class="p-8 text-center text-slate-400">
              <i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-[#D8232A]"></i>
              Loading contest registrations...
            </div>
          </div>

          <!-- Table Footer / Pagination -->
          <div
            class="p-4 sm:p-5 border-t border-slate-200/80 bg-slate-50/70 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div id="paginationInfo" class="font-medium text-slate-600 text-center sm:text-left">Showing 0 to 0 of 0
              entries</div>
            <div class="flex items-center gap-2.5">
              <button id="prevPageBtn" onclick="changePage(-1)"
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-1 shadow-2xs">
                <i data-lucide="chevron-left" class="w-4 h-4"></i> Previous
              </button>
              <span id="currentPageNum"
                class="font-bold text-slate-900 font-mono px-3 py-1 bg-white border border-slate-200 rounded-xl">1</span>
              <button id="nextPageBtn" onclick="changePage(1)"
                class="px-4 py-2 bg-white border border-slate-200 rounded-xl font-bold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition flex items-center gap-1 shadow-2xs">
                Next <i data-lucide="chevron-right" class="w-4 h-4"></i>
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
    <div id="viewDossierModal"
      class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
      <div
        class="bg-white rounded-3xl max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200">
        <div class="bg-slate-900 text-white p-4 sm:p-5 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <h3 class="font-heading font-bold text-base">Contestant Dossier</h3>
          </div>
          <button onclick="closeModal('viewDossierModal')"
            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <div class="p-5 sm:p-6 space-y-4 text-xs overflow-y-auto custom-scrollbar flex-1" id="dossierModalContent">
          <!-- Injected safely via JS -->
        </div>

        <div class="p-4 sm:p-5 bg-slate-50 border-t border-slate-100 flex items-center justify-end shrink-0">
          <button onclick="closeModal('viewDossierModal')"
            class="h-10 px-5 bg-slate-800 text-white font-bold rounded-xl text-xs sm:text-sm hover:bg-slate-900 transition shadow-2xs">
            Close Dossier
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 2: Edit Contestant Details -->
    <div id="editEntryModal"
      class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
      <div
        class="bg-white rounded-3xl max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <i data-lucide="edit-3" class="w-4 h-4"></i>
            </div>
            <div>
              <h3 class="font-heading font-bold text-base text-slate-900">Edit Contestant Record</h3>
              <p class="text-[11px] text-slate-500">Update contestant details and status</p>
            </div>
          </div>
          <button onclick="closeModal('editEntryModal')"
            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <form id="editEntryForm" onsubmit="handleEditSubmit(event)" class="flex flex-col flex-1 overflow-hidden min-h-0">
          <div class="p-5 sm:p-6 space-y-4 text-xs overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" id="editEntryId" name="id">

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Full Name *</label>
              <input type="text" id="editFullName" name="full_name" required
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Mobile (10 digits) *</label>
                <input type="tel" id="editMobile" name="mobile_number" pattern="[0-9]{10}" required
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Email Address *</label>
                <input type="email" id="editEmail" name="email_address" required
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Category</label>
                <select id="editCategory" name="category"
                  class="w-full h-10 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none transition cursor-pointer">
                  <option value="male">👨 Male (Solo)</option>
                  <option value="female">👩 Female (Solo)</option>
                  <option value="kids">🧒 Kids</option>
                  <option value="group_family">👥 Group / Family</option>
                  <option value="voice_of_meridian">🎙️ Voice of Meridian</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Status</label>
                <select id="editStatus" name="status"
                  class="w-full h-10 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none transition cursor-pointer">
                  <option value="registered">Registered</option>
                  <option value="under_review">Under Review</option>
                  <option value="shortlisted">⭐ Shortlisted</option>
                  <option value="winner">🏆 Winner</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">City</label>
                <input type="text" id="editCity" name="city"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Store Code</label>
                <input type="text" id="editStoreCode" name="store_code"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Instagram Handle</label>
                <input type="text" id="editInstagram" name="instagram_handle" placeholder="@username"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Reel / Video URL</label>
                <input type="url" id="editVideoUrl" name="video_url" placeholder="https://..."
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>
          </div>

          <div
            class="p-4 sm:p-5 bg-slate-50 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4 shrink-0">
            <button type="button" onclick="closeModal('editEntryModal')"
              class="h-10 px-5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs sm:text-sm transition text-center min-w-[90px]">
              Cancel
            </button>
            <button type="submit"
              class="h-10 px-6 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition flex items-center justify-center gap-2">
              <i data-lucide="check" class="w-4 h-4"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 3: Create Manual Contestant -->
    <div id="createEntryModal"
      class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
      <div
        class="bg-white rounded-3xl max-w-lg w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200">
        <div
          class="p-4 sm:p-5 border-b border-slate-200 bg-gradient-to-r from-[#D8232A] to-[#B3181E] text-white flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-white/10 text-white flex items-center justify-center">
              <i data-lucide="user-plus" class="w-4 h-4"></i>
            </div>
            <div>
              <h3 class="font-heading font-bold text-base">New Walk-In Contestant</h3>
              <p class="text-[11px] text-red-100">Manual counter/parlour registration</p>
            </div>
          </div>
          <button onclick="closeModal('createEntryModal')"
            class="w-8 h-8 rounded-xl flex items-center justify-center text-red-200 hover:text-white hover:bg-white/10 transition">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <form id="createEntryForm" onsubmit="handleCreateSubmit(event)"
          class="flex flex-col flex-1 overflow-hidden min-h-0">
          <div class="p-5 sm:p-6 space-y-4 text-xs overflow-y-auto custom-scrollbar flex-1">
            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Full Name *</label>
              <input type="text" name="full_name" required placeholder="e.g. Aryan Deshmukh"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Mobile (10 digits) *</label>
                <input type="tel" name="mobile_number" pattern="[0-9]{10}" required placeholder="9822012345"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Email Address *</label>
                <input type="email" name="email_address" required placeholder="aryan@example.com"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Category *</label>
                <select name="category"
                  class="w-full h-10 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none transition cursor-pointer">
                  <option value="male">👨 Male (Solo)</option>
                  <option value="female">👩 Female (Solo)</option>
                  <option value="kids">🧒 Kids</option>
                  <option value="group_family">👥 Group / Family</option>
                  <option value="voice_of_meridian">🎙️ Voice of Meridian</option>
                  <option value="Uploaded Reels">Uploaded Reels</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Initial Status</label>
                <select name="status"
                  class="w-full h-10 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none transition cursor-pointer">
                  <option value="registered">Registered</option>
                  <option value="under_review">Under Review</option>
                  <option value="shortlisted">⭐ Shortlisted</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Nearest City</label>
                <select name="city"
                  class="w-full h-10 px-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:border-red-600 focus:outline-none transition cursor-pointer">
                  <option value="pune">Pune</option>
                  <option value="mumbai">Mumbai</option>
                  <option value="nashik">Nashik</option>
                  <option value="nagpur">Nagpur</option>
                  <option value="kolhapur">Kolhapur</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Parlour Store Code</label>
                <input type="text" name="store_code" value="PUN-FC-01"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm font-mono focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Instagram @Handle</label>
                <input type="text" name="instagram_handle" placeholder="@username"
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1.5">Reel / Video URL</label>
                <input type="url" name="video_url" placeholder="https://..."
                  class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
              </div>
            </div>
          </div>

          <div
            class="p-4 sm:p-5 bg-slate-50 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4 shrink-0">
            <button type="button" onclick="closeModal('createEntryModal')"
              class="h-10 px-5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs sm:text-sm transition text-center min-w-[90px]">
              Cancel
            </button>
            <button type="submit"
              class="h-10 px-6 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition flex items-center justify-center gap-2">
              <i data-lucide="plus" class="w-4 h-4"></i> Create Contestant Entry
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- 6. CHANGE SUPERADMIN DATABASE PASSWORD MODAL              -->
    <!-- ========================================================= -->
    <div id="changePasswordModal"
      class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4">
      <div
        class="bg-white rounded-3xl max-w-md w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <i data-lucide="shield-check" class="w-4 h-4"></i>
            </div>
            <div>
              <h3 class="font-heading font-black text-slate-800 text-base">Superadmin Credentials</h3>
              <p class="text-[11px] text-slate-500">Stored in database table <code
                  class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">meridian_admin_users</code></p>
            </div>
          </div>
          <button onclick="closeModal('changePasswordModal')"
            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <form id="changePasswordForm" onsubmit="handleChangePasswordSubmit(event)"
          class="flex flex-col flex-1 overflow-hidden min-h-0">
          <div class="p-5 sm:p-6 space-y-4 text-xs overflow-y-auto custom-scrollbar flex-1">
            <div id="pwdAlertBox" class="hidden p-3 rounded-xl text-xs font-semibold"></div>

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Active Username</label>
              <input type="text" value="<?= $adminUser ?>" disabled
                class="w-full h-10 px-3.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 font-mono text-xs cursor-not-allowed">
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Current Password *</label>
              <input type="password" id="curPwdInput" required placeholder="Enter current superadmin password"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">New Password (Min 8 chars) *</label>
              <input type="password" id="newPwdInput" required minlength="8" placeholder="Create strong new password"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Confirm New Password *</label>
              <input type="password" id="confPwdInput" required minlength="8" placeholder="Confirm new password"
                class="w-full h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs sm:text-sm focus:border-red-600 focus:bg-white focus:outline-none transition">
            </div>
          </div>

          <div
            class="p-4 sm:p-5 bg-slate-50 border-t border-slate-100 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 sm:gap-4 shrink-0">
            <button type="button" onclick="closeModal('changePasswordModal')"
              class="h-10 px-5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs sm:text-sm transition text-center min-w-[90px]">
              Cancel
            </button>
            <button type="submit" id="savePwdBtn"
              class="h-10 px-6 bg-[#D8232A] hover:bg-[#B3181E] text-white rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition flex items-center justify-center gap-2">
              <i data-lucide="save" class="w-4 h-4"></i> Update Password
            </button>
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
            const kidsTotal = (s.categories.kids || 0) + (s.categories.child || 0);
            const groupTotal = (s.categories.group_family || 0) + (s.categories.group || 0);
            if (document.getElementById('statKidsCount')) {
              document.getElementById('statKidsCount').textContent = kidsTotal;
            }
            if (document.getElementById('statGroupCount')) {
              document.getElementById('statGroupCount').textContent = groupTotal;
            }
            if (document.getElementById('statVoiceCount')) {
              document.getElementById('statVoiceCount').textContent = s.categories.voice_of_meridian || 0;
            }
            document.getElementById('statShortlistedCount').textContent = s.shortlisted || 0;
            document.getElementById('statWinnersCount').textContent = s.winners || 0;
            if (document.getElementById('statWithReelCount')) {
              document.getElementById('statWithReelCount').textContent = s.with_reel || 0;
            }
            if (document.getElementById('statDupBadge')) {
              document.getElementById('statDupBadge').textContent = s.duplicate_clusters || 0;
            }
            if (document.getElementById('statTodayCount')) {
              document.getElementById('statTodayCount').textContent = s.today || 0;
            }
            if (document.getElementById('statActiveParlours')) {
              document.getElementById('statActiveParlours').textContent = s.active_parlours || 0;
            }

            const ind = document.getElementById('dbStatusIndicator');
            if (ind) {
              if (s.db_driver === 'mysql') {
                ind.textContent = 'Hostinger MySQL Database Active';
              } else if (s.db_driver === 'sqlite') {
                ind.textContent = 'Local SQLite Database Active';
              } else {
                ind.textContent = 'SQL Database Connected';
              }
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

      let filterDuplicatesActive = false;

      async function loadEntries(page = 1) {
        currentPage = page;
        const search = document.getElementById('searchInput').value.trim();
        const category = document.getElementById('filterCategory').value;
        const hasReel = document.getElementById('filterReel')?.value || '';
        const status = document.getElementById('filterStatus').value;
        const city = document.getElementById('filterCity').value;
        const sort = document.getElementById('filterSort').value;
        const duplicatesOnly = filterDuplicatesActive ? '1' : '';

        const tbody = document.getElementById('entriesTableBody');
        const mobileFeed = document.getElementById('entriesMobileFeed');
        const loaderHtml = `
          <div class="p-10 text-center text-slate-400">
            <i data-lucide="loader-2" class="w-7 h-7 animate-spin mx-auto mb-2 text-[#D8232A]"></i>
            <p class="text-xs font-semibold">Fetching contestant records...</p>
          </div>
        `;
        tbody.innerHTML = `<tr><td colspan="9">${loaderHtml}</td></tr>`;
        if (mobileFeed) mobileFeed.innerHTML = loaderHtml;
        if (window.lucide) lucide.createIcons();

        try {
          const query = new URLSearchParams({
            action: 'get_entries',
            page,
            limit: 25,
            search,
            category,
            has_reel: hasReel,
            status,
            city,
            sort,
            duplicates_only: duplicatesOnly
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
          const errHtml = `
            <div class="p-10 text-center text-red-500">
              <i data-lucide="alert-triangle" class="w-7 h-7 mx-auto mb-2 text-red-400"></i>
              <p class="text-xs font-semibold">Failed to load records from server.</p>
            </div>
          `;
          tbody.innerHTML = `<tr><td colspan="9">${errHtml}</td></tr>`;
          if (mobileFeed) mobileFeed.innerHTML = errHtml;
          if (window.lucide) lucide.createIcons();
        }
      }

      function toggleDuplicatesFilter() {
        filterDuplicatesActive = !filterDuplicatesActive;
        updateDuplicatesBtnUI();
        loadEntries(1);
      }

      function updateDuplicatesBtnUI() {
        const btn = document.getElementById('btnFilterDuplicates');
        if (!btn) return;
        if (filterDuplicatesActive) {
          btn.classList.add('bg-amber-600', 'text-white', 'border-amber-700');
          btn.classList.remove('bg-amber-50', 'text-amber-900', 'border-amber-200/90');
        } else {
          btn.classList.remove('bg-amber-600', 'text-white', 'border-amber-700');
          btn.classList.add('bg-amber-50', 'text-amber-900', 'border-amber-200/90');
        }
      }

      function resetAllFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('clearSearchBtn').classList.add('hidden');
        document.getElementById('filterCategory').value = '';
        if (document.getElementById('filterReel')) {
          document.getElementById('filterReel').value = '';
        }
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterCity').value = '';
        document.getElementById('filterSort').value = 'newest';
        filterDuplicatesActive = false;
        updateDuplicatesBtnUI();
        loadEntries(1);
      }

      function clearAllSelections() {
        selectedEntryIds.clear();
        updateBulkActionUI();
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        const selectAll = document.getElementById('selectAllCheckbox');
        if (selectAll) selectAll.checked = false;
      }

      function renderTableRows(entries) {
        const tbody = document.getElementById('entriesTableBody');
        const mobileFeed = document.getElementById('entriesMobileFeed');
        tbody.innerHTML = '';
        if (mobileFeed) mobileFeed.innerHTML = '';

        if (entries.length === 0) {
          const emptyHtml = `
            <div class="p-12 text-center text-slate-400">
              <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2.5 text-slate-300"></i>
              <p class="font-heading font-bold text-slate-700 text-sm">No contestants found</p>
              <p class="text-xs text-slate-400 mt-1">Try adjusting your search criteria or resetting active filters.</p>
            </div>
          `;
          tbody.innerHTML = `<tr><td colspan="9">${emptyHtml}</td></tr>`;
          if (mobileFeed) mobileFeed.innerHTML = emptyHtml;
          if (window.lucide) lucide.createIcons();
          return;
        }

        const catIcons = {
          'male': '👨',
          'female': '👩',
          'kids': '🧒',
          'child': '🧒',
          'group': '👥',
          'group_family': '👥',
          'voice_of_meridian': '🎙️'
        };

        const catDisplayNames = {
          'male': 'Male',
          'female': 'Female',
          'kids': 'Kids',
          'child': 'Kids',
          'group': 'Group / Family',
          'group_family': 'Group / Family',
          'voice_of_meridian': 'Voice of Meridian'
        };

        const statusStyles = {
          'registered': 'bg-slate-100 text-slate-700 border-slate-200',
          'under_review': 'bg-blue-50 text-blue-700 border-blue-200',
          'shortlisted': 'bg-purple-50 text-purple-700 border-purple-200',
          'winner': 'bg-amber-100 text-amber-800 border-amber-300 font-bold',
          'rejected': 'bg-red-50 text-red-700 border-red-200',
          'archived': 'bg-slate-200 text-slate-600 border-slate-300 italic font-medium'
        };

        entries.forEach(row => {
          const isChecked = selectedEntryIds.has(row.id);
          const currStatusStyle = statusStyles[row.status] || statusStyles['registered'];
          const validatedVideoUrl = safeUrl(row.video_url);

          let dupBadge = '';
          if (row.is_duplicate) {
            if (row.is_complete) {
              dupBadge = `<div class="mt-1"><span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold"><i data-lucide="check-circle" class="w-3 h-3"></i> Original (Complete)</span></div>`;
            } else {
              const missingStr = (row.missing_fields && row.missing_fields.length > 0) ? `Missing ${row.missing_fields.join(', ')}` : 'Incomplete copy';
              dupBadge = `<div class="mt-1"><span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-300 text-[10px] font-bold" title="${escapeHtml(missingStr)}"><i data-lucide="copy" class="w-3 h-3"></i> Duplicate (${escapeHtml(missingStr)})</span></div>`;
            }
          }

          // 1. DESKTOP ROW
          const tr = document.createElement('tr');
          tr.className = 'hover:bg-slate-50/80 transition-colors group';
          tr.innerHTML = `
            <td class="p-3.5 pl-5 text-center">
              <input type="checkbox" onchange="toggleSelectRow(${row.id})" ${isChecked ? 'checked' : ''} class="w-4 h-4 row-checkbox rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer">
            </td>
            
            <td class="p-3.5 font-mono font-bold text-[#D8232A] whitespace-nowrap text-xs">
              ${escapeHtml(row.entry_code || '#M-2026')}
            </td>

            <td class="p-3.5">
              <div class="font-bold text-slate-900 text-xs sm:text-sm">${escapeHtml(row.full_name || 'Contestant')}</div>
              <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                ${row.instagram_handle ? `<span class="text-blue-600 font-semibold">${escapeHtml(row.instagram_handle)}</span>` : '<span class="text-slate-400">No handle</span>'}
              </div>
              ${dupBadge}
            </td>

            <td class="p-3.5 whitespace-nowrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${row.category === 'voice_of_meridian' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-100 text-slate-800 border border-slate-200'}">
                <span>${catIcons[row.category] || '🍦'}</span>
                <span>${escapeHtml(catDisplayNames[row.category] || row.category || 'male')}</span>
              </span>
            </td>

            <td class="p-3.5 whitespace-nowrap">
              <div class="text-slate-800 font-mono text-xs font-semibold">${escapeHtml(row.mobile_number || '-')}</div>
              <div class="text-[11px] text-slate-500">${escapeHtml(row.email_address || '-')}</div>
            </td>

            <td class="p-3.5 whitespace-nowrap">
              <div class="capitalize text-slate-800 font-semibold text-xs">${escapeHtml(row.city || 'pune')}</div>
              <span class="font-mono text-[10px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">${escapeHtml(row.store_code || 'GENERAL')}</span>
            </td>

            <td class="p-3.5 whitespace-nowrap">
              ${validatedVideoUrl !== '#' ? `
                <a href="${validatedVideoUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-pink-50 hover:bg-pink-100 text-pink-700 text-xs font-bold border border-pink-200/80 transition shadow-2xs">
                  <i data-lucide="video" class="w-3.5 h-3.5"></i> Watch Reel
                </a>
              ` : '<span class="text-slate-400 text-xs italic">Pending</span>'}
            </td>

            <td class="p-3.5 whitespace-nowrap">
              <select onchange="updateRowStatus(${row.id}, this.value)" class="text-xs font-bold px-2.5 py-1.5 rounded-xl border cursor-pointer focus:outline-none transition ${currStatusStyle}">
                <option value="registered" ${row.status === 'registered' ? 'selected' : ''}>Registered</option>
                <option value="under_review" ${row.status === 'under_review' ? 'selected' : ''}>Under Review</option>
                <option value="shortlisted" ${row.status === 'shortlisted' ? 'selected' : ''}>⭐ Shortlisted</option>
                <option value="winner" ${row.status === 'winner' ? 'selected' : ''}>🏆 Winner</option>
                <option value="rejected" ${row.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                <option value="archived" ${row.status === 'archived' ? 'selected' : ''}>📦 Archived</option>
              </select>
            </td>

            <!-- Desktop Action Buttons: Well-spaced (gap-2.5), comfortable touch targets -->
            <td class="p-3.5 pr-6 text-right whitespace-nowrap">
              <div class="inline-flex items-center gap-2">
                <button onclick="viewDossier(${row.id})" class="w-8.5 h-8.5 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition flex items-center justify-center shadow-2xs" title="View Full Dossier">
                  <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
                <button onclick="openEditModal(${row.id})" class="w-8.5 h-8.5 rounded-xl border border-blue-200 bg-blue-50/70 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center shadow-2xs" title="Edit Contestant Record">
                  <i data-lucide="edit-3" class="w-4 h-4"></i>
                </button>
                ${row.status === 'archived' ? `
                  <button onclick="restoreSingleEntry(${row.id})" class="w-8.5 h-8.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white transition flex items-center justify-center shadow-2xs" title="Restore Contestant">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                  </button>
                ` : `
                  <button onclick="archiveSingleEntry(${row.id})" class="w-8.5 h-8.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white transition flex items-center justify-center shadow-2xs" title="Archive Duplicate">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                  </button>
                `}
                <button onclick="deleteSingleEntry(${row.id})" class="w-8.5 h-8.5 rounded-xl border border-red-200 bg-red-50/70 text-red-600 hover:bg-red-600 hover:text-white transition flex items-center justify-center shadow-2xs" title="Delete Contestant Record">
                  <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
              </div>
            </td>
          `;
          tbody.appendChild(tr);

          // 2. MOBILE CARD (Smooth stream with large, comfortable touch controls)
          if (mobileFeed) {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-2xl border border-slate-200/90 p-4 space-y-3.5 shadow-2xs';
            card.innerHTML = `
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <input type="checkbox" onchange="toggleSelectRow(${row.id})" ${isChecked ? 'checked' : ''} class="w-4.5 h-4.5 row-checkbox rounded border-slate-300 text-red-600 focus:ring-red-500 cursor-pointer">
                  <span class="font-mono font-bold text-sm text-[#D8232A]">${escapeHtml(row.entry_code || '#M-2026')}</span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${row.category === 'voice_of_meridian' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-100 text-slate-800 border border-slate-200'}">
                  <span>${catIcons[row.category] || '🍦'}</span>
                  <span>${escapeHtml(catDisplayNames[row.category] || row.category || 'male')}</span>
                </span>
              </div>

              <div>
                <div class="font-heading font-black text-slate-900 text-base leading-snug">${escapeHtml(row.full_name || 'Contestant')}</div>
                <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                  ${row.instagram_handle ? `<span class="text-blue-600 font-semibold">${escapeHtml(row.instagram_handle)}</span>` : ''}
                  <a href="tel:${escapeHtml(row.mobile_number || '')}" class="font-mono text-slate-700 hover:text-red-600 font-medium">${escapeHtml(row.mobile_number || '-')}</a>
                  <span>${escapeHtml(row.email_address || '-')}</span>
                </div>
                ${dupBadge}
                <div class="text-xs text-slate-600 flex items-center gap-2 mt-2">
                  <span class="capitalize font-semibold text-slate-800 flex items-center"><i data-lucide="map-pin" class="w-3.5 h-3.5 inline mr-1 text-slate-400"></i>${escapeHtml(row.city || 'pune')}</span>
                  <span class="font-mono text-[10px] text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">${escapeHtml(row.store_code || 'GENERAL')}</span>
                </div>
              </div>

              ${validatedVideoUrl !== '#' ? `
                <div>
                  <a href="${validatedVideoUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-pink-50 hover:bg-pink-100 text-pink-700 text-xs font-bold border border-pink-200/80 transition">
                    <i data-lucide="video" class="w-3.5 h-3.5"></i> Watch Reel
                  </a>
                </div>
              ` : ''}

              <div class="pt-3 border-t border-slate-100 space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <span class="text-xs font-bold text-slate-500">Review Status:</span>
                  <select onchange="updateRowStatus(${row.id}, this.value)" class="h-9 text-xs font-bold px-3 rounded-xl border cursor-pointer focus:outline-none transition ${currStatusStyle}">
                    <option value="registered" ${row.status === 'registered' ? 'selected' : ''}>Registered</option>
                    <option value="under_review" ${row.status === 'under_review' ? 'selected' : ''}>Under Review</option>
                    <option value="shortlisted" ${row.status === 'shortlisted' ? 'selected' : ''}>⭐ Shortlisted</option>
                    <option value="winner" ${row.status === 'winner' ? 'selected' : ''}>🏆 Winner</option>
                    <option value="rejected" ${row.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                    <option value="archived" ${row.status === 'archived' ? 'selected' : ''}>📦 Archived</option>
                  </select>
                </div>

                <!-- Mobile Action Buttons: 3 spacious distinct columns -->
                <div class="grid grid-cols-3 gap-2.5 pt-1">
                  <button onclick="viewDossier(${row.id})" class="h-10 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="eye" class="w-4 h-4 text-slate-500"></i> Dossier
                  </button>
                  <button onclick="openEditModal(${row.id})" class="h-10 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="edit-3" class="w-4 h-4 text-blue-500"></i> Edit
                  </button>
                  <button onclick="deleteSingleEntry(${row.id})" class="h-10 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete
                  </button>
                </div>
              </div>
            `;
            mobileFeed.appendChild(card);
          }
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

      async function applyBulkArchive() {
        if (selectedEntryIds.size === 0) return;
        if (!confirm(`Move ${selectedEntryIds.size} selected contestant(s) to Archive?`)) return;

        try {
          const res = await fetch('api.php?action=bulk_action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({
              ids: Array.from(selectedEntryIds),
              sub_action: 'archive',
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
            alert(data.message || 'Bulk archive failed');
          }
        } catch (err) {
          alert('Failed to archive selected entries');
        }
      }

      async function archiveSingleEntry(id) {
        if (!confirm('Move this duplicate entry to Archive?')) return;
        await updateRowStatus(id, 'archived');
      }

      async function restoreSingleEntry(id) {
        if (!confirm('Restore this contestant from Archive?')) return;
        await updateRowStatus(id, 'registered');
      }

      async function triggerMergeAndArchiveDuplicates() {
        const msg = "Smart Duplicate Merge & Archive:\n\n" +
                    "1. Identifies repeated submissions by mobile number.\n" +
                    "2. Treats entries with all fields filled (Reel URL & Handle) as Original.\n" +
                    "3. Backfills any missing fields into the Original entry.\n" +
                    "4. Safely archives incomplete duplicates.\n\n" +
                    "Would you like to run this now?";
        if (!confirm(msg)) return;

        try {
          const res = await fetch('api.php?action=merge_and_archive_duplicates', {
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
            alert(data.message || 'Operation failed');
          }
        } catch (err) {
          alert('Server communication error');
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
            <div class="font-bold text-slate-800 mt-0.5">${catIcons[r.category] || '🍦'} ${escapeHtml(catDisplayNames[r.category] || r.category || '-')}</div>
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

        let normalizedCat = r.category || 'male';
        if (normalizedCat === 'child') normalizedCat = 'kids';
        if (normalizedCat === 'group' || normalizedCat === 'family') normalizedCat = 'group_family';
        if (normalizedCat === 'voice') normalizedCat = 'voice_of_meridian';

        document.getElementById('editEntryId').value = r.id;
        document.getElementById('editFullName').value = r.full_name || '';
        document.getElementById('editMobile').value = r.mobile_number || '';
        document.getElementById('editEmail').value = r.email_address || '';
        document.getElementById('editCategory').value = normalizedCat;
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

      async function refreshDashboard() {
        try {
          await Promise.all([loadStats(), loadEntries(currentPage)]);
        } catch (err) {
          console.error('Refresh error', err);
        }
      }

      // Authenticated CSV Export without secret keys in URL
      function exportFilteredCSV() {
        const search = document.getElementById('searchInput').value.trim();
        const category = document.getElementById('filterCategory').value;
        const hasReel = document.getElementById('filterReel')?.value || '';
        const status = document.getElementById('filterStatus').value;
        const city = document.getElementById('filterCity').value;

        const query = new URLSearchParams({
          action: 'export_csv',
          search,
          category,
          has_reel: hasReel,
          status,
          city
        });

        window.location.href = 'api.php?' + query.toString();
      }

      function openChangePasswordModal() {
        document.getElementById('changePasswordForm').reset();
        const alertBox = document.getElementById('pwdAlertBox');
        if (alertBox) alertBox.classList.add('hidden');
        openModal('changePasswordModal');
      }

      async function handleChangePasswordSubmit(e) {
        e.preventDefault();
        const cur = document.getElementById('curPwdInput').value.trim();
        const nxt = document.getElementById('newPwdInput').value.trim();
        const conf = document.getElementById('confPwdInput').value.trim();
        const alertBox = document.getElementById('pwdAlertBox');
        const btn = document.getElementById('savePwdBtn');

        if (nxt !== conf) {
          alertBox.className = 'p-3 rounded-xl text-xs font-semibold bg-red-50 text-red-700 border border-red-200 block';
          alertBox.textContent = 'New password and confirmation do not match.';
          return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Updating...';
        if (window.lucide) lucide.createIcons();

        try {
          const res = await fetch('api.php?action=change_password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({
              current_password: cur,
              new_password: nxt,
              confirm_password: conf,
              csrf_token: CSRF_TOKEN
            })
          });
          const data = await res.json();
          if (data.success) {
            alertBox.className = 'p-3 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 block';
            alertBox.textContent = data.message || 'Password updated in database successfully!';
            setTimeout(() => {
              closeModal('changePasswordModal');
            }, 1500);
          } else {
            alertBox.className = 'p-3 rounded-xl text-xs font-semibold bg-red-50 text-red-700 border border-red-200 block';
            alertBox.textContent = data.message || 'Failed to update password.';
          }
        } catch (err) {
          alertBox.className = 'p-3 rounded-xl text-xs font-semibold bg-red-50 text-red-700 border border-red-200 block';
          alertBox.textContent = 'Connection error communicating with database API.';
        }

        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Update Password';
        if (window.lucide) lucide.createIcons();
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