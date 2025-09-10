# SkillRadar

**Kort:** SkillRadar is een lichte, herbruikbare webapp (MVC, OOP PHP) waarmee docenten groepen en vragenlijsten maken, studenten anoniem schaalvragen invullen en resultaten visueel tonen als radardiagram (exporteerbaar). Frontend: HTML/CSS (+optionele JS voor Chart.js/animaties).

---

## Belangrijkste eigenschappen
- Groepen aanmaken / hergebruiken (reuse key)  
- Vragenlijsten met skills (meerdere vragen per skill)  
- Anonieme inzendingen (studenten) en optionele docentinzendingen  
- Aggregatie per skill en per-student weergave  
- Radardiagram (Chart.js) met download/print-optie  
- Mobile-first UI, eenvoudige installatie (SQLite of MySQL)  
- MVC-structuur, OOP PHP — eenvoudig uit te breiden

---

## Quick start (ontwikkelomgeving)
1. Clone repository  
2. Kopieer `.env.example` → `.env` en pas DB-config aan  
3. Install dependencies (composer autoload gebruikt)

    composer install

4. Maak database en run migrations (zie `migrations/create_schema.sql`)  
5. Start PHP built-in server (ontwikkel)

    php -S localhost:8000 -t public

6. Open `http://localhost:8000`

---

## Vereisten
- PHP 8.0+ (PDO-extensie)  
- Composer (voor autoloading)  
- SQLite of MySQL (voor productie MySQL aanbevolen)  
- Browser met JS voor dashboards (Chart.js via CDN)

---

## Aanbevolen map-structuur
    skillradar/
    ├─ app/
    │  ├─ Controllers/
    │  │  ├─ GroupController.php
    │  │  ├─ QuestionnaireController.php
    │  │  ├─ SubmissionController.php
    │  │  └─ ResultController.php
    │  ├─ Models/
    │  │  ├─ Group.php
    │  │  ├─ Questionnaire.php
    │  │  ├─ Skill.php
    │  │  ├─ Question.php
    │  │  └─ Submission.php
    │  ├─ Views/
    │  │  ├─ admin/
    │  │  ├─ student/
    │  │  └─ dashboard/
    │  └─ Services/   (DB access, aggregation logic)
    ├─ public/
    │  ├─ index.php   (front controller / router)
    │  ├─ assets/
    │  │  ├─ css/
    │  │  └─ js/
    ├─ config/
    │  └─ config.php
    ├─ migrations/
    │  └─ create_schema.sql
    ├─ seeds/
    │  └─ seed.sql
    ├─ vendor/
    └─ composer.json

---

## Database schema (SQL)
Gebruikbaar voor SQLite en MySQL (kleine aanpassingen voor MySQL AUTO_INCREMENT vs AUTOINCREMENT).
```
    -- groups
    CREATE TABLE IF NOT EXISTS groups (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      reuse_key TEXT UNIQUE,
      members TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- questionnaires
    CREATE TABLE IF NOT EXISTS questionnaires (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- skills
    CREATE TABLE IF NOT EXISTS skills (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      questionnaire_id INTEGER NOT NULL,
      name TEXT NOT NULL,
      FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE
    );

    -- questions
    CREATE TABLE IF NOT EXISTS questions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      questionnaire_id INTEGER NOT NULL,
      skill_id INTEGER NOT NULL,
      text TEXT NOT NULL,
      max_score INTEGER NOT NULL DEFAULT 5,
      FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE,
      FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
    );

    -- submissions
    CREATE TABLE IF NOT EXISTS submissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      questionnaire_id INTEGER NOT NULL,
      group_id INTEGER NOT NULL,
      is_teacher INTEGER NOT NULL DEFAULT 0,
      answers TEXT NOT NULL, -- JSON string: {"questionId": score, ...}
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (questionnaire_id) REFERENCES questionnaires(id) ON DELETE CASCADE,
      FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
    );
```
---

## Seed (snelle testdata)
Plaats in `seeds/seed.sql` en run tegen je DB:
```
    INSERT INTO groups (name, reuse_key) VALUES ('Groep A', 'groep-a-2025');

    INSERT INTO questionnaires (title) VALUES ('Kickoff skills');

    INSERT INTO skills (questionnaire_id, name) VALUES (1, 'Communicatie'), (1, 'Techniek'), (1, 'Planning');

    INSERT INTO questions (questionnaire_id, skill_id, text, max_score) VALUES
    (1,1,'De groep communiceert duidelijk',5),
    (1,1,'Feedback wordt gegeven',5),
    (1,2,'Technische kennis is toereikend',5),
    (1,2,'Taken worden technisch begrepen',5),
    (1,3,'Planning is realistisch',5),
    (1,3,'Deadlines worden gehaald',5);
```
---

## Routes / Endpoints (essentie)
```
- `GET  /groups` — lijst groepen  
- `POST /groups` — body: `{ name, reuse_key?, members? }`  
- `GET  /groups/reuse/{key}` — vind groep op reuse_key  
- `POST /questionnaires` — payload: `{ title, skills: [{name}], questions: [{text, skillIndex, max}] }`  
- `GET  /questionnaires/{id}` — haal questionnaire + skills + questions  
- `POST /submissions` — body: `{ questionnaireId, groupId, isTeacher(false/true), answers: { questionId: score, ... } }`  
- `GET  /results/{questionnaireId}?groupId=&mode=aggregate|perStudent&includeTeachers=true|false`
```
---

## Aggregatie & dataformat
- Submissions slaan `answers` op als JSON: `{ "12": 4, "13": 5 }` (questionId => score).  
- Aggregatie per skill: voor elke vraag onder een skill: tel scores op en deel door aantal antwoorden → gemiddelde per skill. Gebruik hoogste `max_score` binnen een skill om radaschalen in te stellen.  
- Per-student mode: return raw submissions (anoniem: geen student-id).

---

## Frontend / Chart.js voorbeeld (dashboard view)
Voeg Chart.js via CDN toe in je view:
```
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <canvas id="radar"></canvas>
    <script>
      // fetch /results/{id} -> data.aggregated = [{name, average, max}, ...]
      // labels = aggregated.map(s => s.name)
      // values = aggregated.map(s => s.average)
      const ctx = document.getElementById('radar').getContext('2d')
      const chart = new Chart(ctx, {
        type: 'radar',
        data: {
          labels: labels,
          datasets: [{ label: 'Groepsscore', data: values, fill: true }]
        },
        options: { scales: { r: { suggestedMin: 0 } } }
      });

      // download PNG
      function downloadPNG() {
        const url = chart.toBase64Image()
        const a = document.createElement('a')
        a.href = url
        a.download = 'radar.png'
        a.click()
      }
    </script>
```
---

## OOP / MVC - implementatietips
- **Models:** properties + eenvoudige CRUD methods (gebruik PDO prepared statements). Of maak een kleine DAO-laag.  
- **Controllers:** dunne controllers: valideren inputs, aanroepen services, render views of return JSON.  
- **Services:** `AggregationService`, `QuestionnaireService` (maak, map skillIndex → skillId), `GroupService`.  
- **Views:** pure PHP-templates + minimale JS. Houd admin-forms beschermd (CSRF).

---

## Anonimiteit & security (cruciaal)
- Studenten sturen **geen** naam of gebruikers-ID bij submissions  
- Houd geen link tussen submission en gebruiker in de DB  
- Valideer alle input op server: bestaand questionnaireId, groupId, en scores binnen 0..max  
- Gebruik prepared statements (PDO) of ORM en ontsmet output in views (XSS)  
- Bescherm admin routes met simpele auth (session + password) of externe login

---

## Deployment (kort)
- Gebruik MySQL in productie, configureer `.env` met credentials  
- Gebruik Apache/Nginx + PHP-FPM voor productie  
- Zet file permissions correct en bescherm `config/` en `migrations/`  
- Maak backups van DB en assets

---

## Contributing
1. Fork repository, maak feature branch, commit, PR  
2. Houd controllers klein en services testbaar  
3. Schrijf unit-tests voor aggregation logic (PHPUnit)

---

## License
MIT

---