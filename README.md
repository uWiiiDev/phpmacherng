# PHPMacherNG – Web-Based PHP & MySQL/MariaDB CRUD Code Generator (PHP 5.4 – 8.4+)

**PHPMacherNG** is a standalone, 100% offline-capable low-code development environment (browser-based IDE) for automatically generating full-featured, responsive **PHP/MySQL/MariaDB CRUD web applications** inspired by PHPMaker.

Both the **Studio** and the **generated web applications** have **zero external dependencies** (no Composer, no Node.js/npm, no external CDNs) and run out of the box on any web server from **PHP 5.4 up to PHP 8.4+**.

---

## Key Features

### 1. Multi-User Studio & Visual Development Environment
- **1-Click Database Synchronization (`DB Sync`)**: Automatically inspects tables, SQL views, primary keys, column types, and foreign key relationships directly from MySQL/MariaDB.
- **Visual SQL View Builder**: Create, edit, live-test (`CREATE OR REPLACE VIEW`), and delete (`DROP VIEW`) database views visually or via raw SQL directly inside the Studio.
- **Multi-User Studio & Team Collaboration**:
  - Role-based Studio user management (`Studio Admin` & `Developer` roles; create, edit, lock/deactivate, and delete accounts with automatic project ownership preservation).
  - Projects are private by default and can be shared with team members as **Editor** (Read & Write) or **Viewer** (Read-Only), or reassigned to a new project owner at any time.
- **Built-in Git-Style Project Version Control**:
  - Revision-safe snapshots (commits), branches (`create`, `switch`, `merge`), release tags, structural PHPMacherNG diff + unified line diff (`git diff`), and 1-click rollback directly inside the Studio UI.

### 2. 5 Built-in UI Design Templates
1. **AdminLTE 2 Classic** (Left sidebar navigation + 12 color skins)
2. **Top-Navbar Layout** (Horizontal top navigation bar with dropdowns & 100% full-width content)
3. **Modern SaaS / Clean Enterprise UI** (Indigo/Slate card layout with soft shadows)
4. **Dark Mode Pro** (Consistent dark slate/anthracite dashboard theme)
5. **Classic Compact (ERP)** (High-density layout optimized for large data tables)

### 3. 9 Zero-Dependency HTML5 Canvas Chart Types
- **Vertical Bar Chart (`bar`)**, **Horizontal Bar Chart (`hbar`)**, **Line Chart (`line`)**, **Area Chart (`area`)**, **Pie Chart (`pie`)**, **Doughnut Chart (`doughnut`)**, **Funnel Chart (`funnel`)**, **Radar / Spider Chart (`radar`)**, and **KPI Gauge / Speedometer (`gauge`)**.
- Includes an interactive **live chart-type switcher** on every chart and **1-click PNG image export**.

### 4. Enterprise Features in Generated Applications
- **Master/Detail Views**: Inline detail row preview in list pages, detail record count badges, and combined Master/Detail Add, Edit, and View pages.
- **Quick Search & Extended/Advanced Search**: Field-level search operators (`LIKE`, `=`, `BETWEEN`, etc.) and modal/collapsible search panels.
- **Data Export & Print View**: Built-in CSV, Excel, HTML, XML export, and print-friendly views.
- **5-Tier Role & Permission Security System**:
  - Hardcoded Admin, database user table (`benutzer`), static & dynamic user levels (`userlevels`, `userlevelpermissions`), and row-level ownership protection (**User ID Security** including parent/supervisor hierarchy).
- **Custom Files & `DbHelper()` API**: Build custom PHP pages and dashboards using `ew_ExecuteScalar()`, `ew_ExecuteRow()`, `ew_ExecuteRows()`, `DbHelper()->ExecuteHtml()`, and `DbHelper()->ExecuteChart()`.
- **Server Events & Client Scripts**: Lifecycle hooks (`Row_Inserting`, `Row_Inserted`, `Row_Updating`, `Row_Deleting`, `Row_Rendered`, `Page_Load`, etc.) and global/table-specific JavaScript startup scripts.
- **Digital Signature Pad (`SIGNATURE`)**: Touch- and mouse-ready HTML5 canvas signature input field.

---

## Quick Start & Installation

1. Clone or copy this repository into your web server's document root (e.g. `htdocs/phpmacherng` or `/var/www/html/phpmacherng`):
   ```bash
   git clone https://github.com/uWiiiDev/phpmacherng.git
   ```
2. Ensure write permissions for the `projects/` and `output/` directories.
3. Open `http://localhost/phpmacherng/index.php` in your browser.
   - **Default Studio Login:** Username `admin` | Password `12345678`
4. **Optional – Run the included CRM & Shop Demo Project (`crm_shop_demo`)**:
   - Import `database_demo.sql` into your MySQL/MariaDB server, verify your database credentials in **Tab 1: Database**, and click **Generate (F9)**.
