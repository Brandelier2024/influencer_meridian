# Meridian Ice Cream — Brand Ambassador Contest Website

A responsive, high-energy event website for the **Meridian Ice Cream Brand Ambassador Contest**, integrating the **official brand assets and vector logos downloaded directly from [meridianicecreams.com](https://meridianicecreams.com)** alongside the campaign assets from the **Meridian Vendor Guide PDF**.

---

## 🎨 Official Assets from `meridianicecreams.com`

All official assets were fetched from `https://meridianicecreams.com` and integrated into the design:

| Asset | Path | Description & Usage |
| :--- | :--- | :--- |
| **Official Vector Logo (Red)** | `assets/official/meridian-logo-red.svg` | Official Meridian Ice Cream vector mark for the navbar & VIP badge |
| **Official Vector Logo (White)** | `assets/official/meridian-logo-white.svg` | High-contrast vector mark for the dark footer |
| **Official Vector Logo (Dynamic)** | `assets/official/meridian-logo.svg` | Scalable SVG with `currentColor` fill |
| **Official Favicons** | `assets/official/icon_cropped-ms-icon-310x310-removebg-preview-*.png` | Official website favicon (32x32, 180x180, 192x192) |
| **Official Store Map** | `assets/official/Meredian-map-01-1.svg` | Official parlour network distribution map |
| **Official Mastani Sundae** | `assets/official/Products-1080-x-1080-Mastani-768x768-1.png` | Royal Mastani transparent special |
| **Official Brownie Sundae** | `assets/official/Products-1080-x-1080-Brownie-768x768-1.png` | Sizzling Brownie transparent special |
| **Real Alphonso Mango** | `assets/official/real-Alphonso.jpg` | Authentic product card (100% Ratnagiri mango) |
| **Strawberry with Cream** | `assets/official/STRAWBERRY-WITH-CREAM.jpg` | Authentic product card (Fresh Mahabaleshwar berries) |
| **Belgian Dark Chocolate** | `assets/official/BELGIAN-CHOCOLATE.jpg` | Authentic product card (Imported Belgian cocoa truffle) |
| **Sitaphal Custard Apple** | `assets/official/SITAPHAL.jpg` | Authentic product card (Seasonal fruit special) |

---

## 📸 Campaign Cutouts from the Vendor Guide PDF (`assets/clean/`)

| Asset | Usage |
| :--- | :--- |
| `hero-cup-and-cone.png` | Hero centerpiece stage showing the iconic red tub, strawberry scoop & waffle cone |
| `hero-scoop-strawberries.png` | Fresh strawberry scoop floating interactive badge |
| `category-male.png` | Male Category interactive card (PDF Page 3) |
| `category-female.png` | Female Category interactive card (PDF Page 3) |
| `category-child.png` | Child & Family Category interactive card (PDF Page 3) |
| `category-group.png` | Group Squads Category interactive card (PDF Page 3) |
| `parlour-standee-poster.png` | In-store counter table tent standee with QR code (PDF Page 4) |
| `mobile-app-mockup.png` | User journey milestone 2 (PDF Page 4) |
| `step-registration-form.png` | User journey milestone 3 (PDF Page 4) |
| `girl-eating-icecream.png` | User journey milestone 4 & creator showcase (PDF Page 4) |
| `parlour-staff-and-customer.png` | Vendor guide store staff and customer interaction (PDF Page 6) |
| `meridian-tub-berries.png` | High-res red tub with raspberries and strawberries (PDF Page 7) |

---

## 🚀 Key Sections

1. **Header & Navigation:** Sticky glassmorphic navbar with the official **Meridian vector logo**, links to all sections, and the "Enter Contest" action.
2. **Hero Stage & Live Countdown:** Real-time countdown timer to registration closing, floating interactive perks, and animated marquee ticker.
3. **Four Ways to Participate:** Male, Female, Child & Family, and Group categories with authentic scoops and 1-click registration auto-fill.
4. **Contest User Journey:** 5 visual milestones (*Discover $\rightarrow$ Visit $\rightarrow$ Register $\rightarrow$ Create $\rightarrow$ Win*).
5. **3 Simple Steps:** Register, Create, and Follow Contest Rules.
6. **Official Flavours & Specialities (New):** Showcasing real products from `meridianicecreams.com` (*Strawberry with Cream, Real Alphonso, Belgian Chocolate, Sitaphal, Royal Mastani, Sizzling Brownie*).
7. **Vendor & Store Staff Guide:** Staff guidance, official parlour network map (`Meredian-map-01-1.svg`), and **"Zero Paperwork — 100% Digital"** guarantee.
8. **Ambassador Perks:** 1 Year Free Ice Cream, nationwide billboard feature, pro studio photoshoot, and varsity merchandise kit.
9. **Interactive VIP Pass & Registration:** Live updating contestant badge that renders the participant's name and category live with confetti celebration on submit.
10. **Community Wall & FAQ Accordion:** Common questions and rules.

---

## 💻 How to View

Open **[index.html](file:///c:/Users/sudha/Desktop/Meridian%20contest/index.html)** in any modern web browser (Google Chrome, Microsoft Edge, Safari, Firefox).


---

## 🗄️ Hostinger Database Integration Guide

This project includes a production-ready PHP/MySQL backend specifically architected for Hostinger web hosting environments.

### 1. Hostinger Files Created

- **`api/config.php`**: Database credentials and PDO connection helper with support for environment variables or direct editing.
- **`api/register.php`**: Secure POST endpoint that:
  - Validates and sanitizes contestant inputs (Full Name, Phone, Email, Category, City, Store Code, Instagram Handle, Video URL).
  - Automatically executes `CREATE TABLE IF NOT EXISTS meridian_contest_entries` with performance indexes on the very first run.
  - Generates unique contest ticket codes (e.g., `#M-2026-4821`).
  - Utilizes PDO prepared statements to guarantee SQL-injection protection.
  - Includes an automatic offline fail-safe (`data/entries_backup.json`) so zero registrations are lost during maintenance or setup.
- **`api/export.php`**: Real-time contestant export endpoint supporting both CSV (`?format=csv`) and JSON (`?format=json`) with admin key authentication (`?key=MeridianAdmin2026`).
- **`database_setup.sql`**: Full standalone SQL schema ready for phpMyAdmin import with sample data.

### 2. How to Deploy to Hostinger

1. **Upload Files**: Log in to your Hostinger hPanel &rarr; **File Manager** &rarr; navigate to `public_html/` and upload all files and folders (`assets/`, `api/`, `index.html`, etc.).
2. **Create MySQL Database**:
   - In Hostinger hPanel, click **Databases** &rarr; **MySQL Databases**.
   - Create a new database (e.g. `u123456789_meridian`) and a user with a secure password.
3. **Configure Database Credentials**:
   - Open `api/config.php` and set:
     ```php
     define('DB_HOST', 'localhost');          // Hostinger default is localhost:3306
     define('DB_NAME', 'u123456789_meridian'); // Your Hostinger DB Name
     define('DB_USER', 'u123456789_user');     // Your Hostinger DB User
     define('DB_PASS', 'YourStrongPassword');  // Your Hostinger DB Password
     ```
4. **Done!**
   - The table `meridian_contest_entries` will be automatically generated on the first contestant submission, or you can import `database_setup.sql` into phpMyAdmin.
   - To export registrations to an Excel CSV sheet anytime, visit:
     `https://yourdomain.com/api/export.php?key=MeridianAdmin2026`


---

## 🛡️ Superadmin Control Dashboard (`admin/`)

A dedicated administrative command center is available at `admin/` (or `https://yourdomain.com/admin/`).

### Features:
1. **Authentication**:
   - Secure login with session regeneration and CSRF protection.
   - Default Username: `admin`
   - Default Password: `Meridian@Super2026` (or can be customized in `api/config.php` via `SUPERADMIN_PASS`).
2. **Metrics Overview**:
   - Real-time total contestant counter.
   - Category distribution (Male, Female, Child/Family, Group).
   - Shortlisted and winner tracker.
   - Offline fail-safe queue status.
3. **Data Management & CRUD**:
   - Instant search by ticket code (`#M-2026-XXXX`), name, mobile, email, or parlour store code.
   - Category and review status filters (`Registered`, `Under Review`, `Shortlisted`, `Winner`, `Rejected`).
   - One-click status changer directly from the table.
   - Full contestant dossier viewer (shows submitted Instagram reel, IP address, registration timestamp).
   - Edit contestant records.
   - Delete single or bulk entries.
   - Add new walk-in / parlour contestants manually.
4. **Data Synchronization & Export**:
   - **Sync DB**: Imports any entries stored in `data/entries_backup.json` into MySQL with 1 click.
   - **Export CSV**: Instant, formula-sanitized CSV export.
