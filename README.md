# SkillRadar

Welcome to SkillRadar — an MVC, object-oriented PHP application for anonymous group assessments and visual feedback using radar charts. Teachers create and reuse groups and questionnaires; students submit anonymous, scale-based responses from any device. Results are visualized for retrospectives and group-formation decisions.

-------------------------------------------------------------------------------
TABLE OF CONTENTS
-------------------------------------------------------------------------------
• Features  
• Architecture and File Structure  
• Installation and Setup  
• Configuration  
• Usage  
• Testing  
• Contribution Guidelines  
• License  

-------------------------------------------------------------------------------
FEATURES
-------------------------------------------------------------------------------
• MVC architecture with a clear separation among models, views, and controllers.  
• Ajax-enabled interactions using vanilla JavaScript for responsive UIs and optional animations; CSS-first design.  
• Reusable group keys and optional unique access links (UUID) for controlled student access.  
• Anonymous student submissions; optional teacher submissions (toggleable in aggregation).  
• Multiple skills per questionnaire and multiple questions per skill.  
• Aggregation modes: grouped average (for retrospectives) and per-student view (for group composition).  
• Chart.js radar chart for visualization and export to PNG.  
• Environment configuration via an .env file.  
• Mobile-first UI for phone/tablet data entry.

-------------------------------------------------------------------------------
ARCHITECTURE AND FILE STRUCTURE
-------------------------------------------------------------------------------
Recommended project layout:

  - app: Controllers, Models, Views, Services (admin, student, dashboard subfolders)  
  - core: Router, BaseController, BaseModel, Renderer, Request utilities  
  - public: Web root with index.php, assets (css, js, images)  
  - routes: Application route definitions (web.php)  
  - config: Configuration and environment loaders  
  - migrations: SQL schema files  
  - seeds: Seed data for quick testing  
  - vendor: Composer-managed dependencies  
  - .env-default and .env (environment configuration)  
  - composer.json

Core responsibilities:
- core/ provides minimal framework pieces (simple Router, Controller, Model, View renderer).  
- app/Models map DB tables to objects and handle basic persistence via PDO or a small DAO layer.  
- app/Controllers handle HTTP requests, validate inputs, call Services, and render views or return JSON.  
- app/Services implement reusable logic (AggregationService, QuestionnaireService, GroupService).  
- public/ is the single entry point and hosts static assets.

-------------------------------------------------------------------------------
INSTALLATION AND SETUP
-------------------------------------------------------------------------------
Prerequisites:
  • PHP 8.0+ with PDO extension  
  • Composer  
  • MySQL or SQLite (MySQL recommended for production)  
  • Apache or Nginx with URL rewriting

Step-by-step:

1. Clone the repository:
   git clone https://your.repo.url skillradar
   cd skillradar

2. Install dependencies:
   composer install

3. Create the database
   Example (MySQL):
   CREATE DATABASE skillradar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

   Or create a SQLite file and point .env to it.

4. Import the schema
   Use migrations/create_schema.sql with your DB client:
   mysql -u your_user -p skillradar < migrations/create_schema.sql

   Or execute the SQL in your SQLite environment.

5. Seed test data (optional)
   mysql -u your_user -p skillradar < seeds/seed.sql

6. Configure environment
   cp .env-default .env
   Edit .env with DB credentials, base URL, and optional mail settings.

7. Start the dev server
   php -S localhost:8000 -t public

   Open http://localhost:8000

-------------------------------------------------------------------------------
CONFIGURATION
-------------------------------------------------------------------------------
• The .env file stores database credentials, base URL, and optional mail settings. Keep it out of version control.  
• Routing is defined in routes/web.php — add middleware or auth checks there.  
• PHPMailer (optional) can be configured in app/Services/EmailService.php.  
• Toggle anonymity behavior, teacher-inclusion in aggregation, and CSV import settings via a small settings table or .env flags.

-------------------------------------------------------------------------------
USAGE
-------------------------------------------------------------------------------
Teachers / Instructors:
  • Create and reuse groups using reuse keys or generate one-time access links.  
  • Build questionnaires: define skills, add questions and max scales.  
  • Optionally submit teacher evaluations (toggleable inclusion in results).  
  • During meetings, present radar chart and export PNG for reports.

Students:
  • Receive a reuse key or link from the teacher.  
  • Open the URL, complete mobile-friendly sliders or radio inputs, and submit anonymously.  
  • No login required to maintain anonymity.

Admins:
  • Manage system settings, seed data, and monitor logs.  
  • Optionally manage users and roles if you implement an admin user system.

-------------------------------------------------------------------------------
TESTING
-------------------------------------------------------------------------------
• Confirm the migration ran and tables exist.  
• Create a test questionnaire and group; submit multiple anonymous responses and verify aggregation matches expected averages.  
• Test per-student mode: retrieve raw submissions without user identifiers.  
• Validate input ranges: scores within 0..max_score.  
• Test edge cases: zero submissions, mixed teacher/student submissions, and questions with different max scores.  
• Inspect browser console and PHP logs for errors.

-------------------------------------------------------------------------------
CONTRIBUTION GUIDELINES
-------------------------------------------------------------------------------
• Keep MVC separation and single-responsibility principles.  
• Commit small, focused changes with clear messages.  
• Add unit tests for aggregation logic (PHPUnit recommended).  
• Document non-obvious behavior and update README when adding features.  
• Keep .env and secret files out of version control.

-------------------------------------------------------------------------------
LICENSE
-------------------------------------------------------------------------------
This project is for internal use within Gilde Opleidingen and is not intended for public distribution. 

**SCHOOL INTERNAL USE LICENSE**

This Software ("Software") is provided by Gilde Opleidingen ("Licensor") solely for internal use by authorized users within Gilde Opleidingen. By using this Software, you agree to the terms of this License.

1. **Grant of License:**  
   The Licensor grants a non-exclusive, non-transferable license to use the Software strictly for internal educational, administrative, and research purposes within Gilde Opleidingen.

2. **Restrictions:**  
   a. You shall not distribute, sublicense, or provide the Software to any third party outside of Gilde Opleidingen.  
   b. You shall not modify, reverse engineer, decompile, or create derivative works of the Software without prior written consent from the Licensor.  
   c. The Software shall not be used for commercial purposes or outside the internal, educational needs of Gilde Opleidingen.

3. **Ownership:**  
   All rights, title, and interest in the Software remain with the Licensor. No ownership rights are transferred by this License.

4. **Disclaimer:**  
   The Software is provided "AS IS," without warranty of any kind. The Licensor is not liable for any damages resulting from the use or inability to use the Software.

5. **Termination:**  
   This License is effective until terminated. The Licensor may terminate this License immediately if you breach any of its terms. Upon termination, you must discontinue use and destroy all copies of the Software.

6. **Governing Law:**  
   This License shall be governed by and construed in accordance with the laws of the Netherlands. Any disputes shall be resolved in the appropriate courts in the Netherlands.

7. **Entire Agreement:**  
   This License constitutes the entire agreement regarding the Software and supersedes all prior understandings.

By using this Software, you acknowledge that you have read, understood, and agree to the terms of this School Internal Use License.

-------------------------------------------------------------------------------

Happy coding — want me to generate the minimal working file set (controllers, models, views, migration + seed) next? Reply with generate files and I will produce the PHP files.