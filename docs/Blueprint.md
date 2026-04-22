# Blueprint – `local_ltiidentity`

**Stand:** 22.04.2026  
**Ziel:** technisches Zielbild für eine forkfreie Umsetzung von Fassade 1

---

## 1. Architekturprinzip

`local_ltiidentity` ist ein **lokales Moodle-Plugin**, das den bestehenden LTI-Core-Fluss ergänzt. Es verändert keine Core-Dateien und ersetzt weder `auth_lti` noch die bestehende LTI-Bindung.

Die Architektur folgt einem festen Prinzip:

1. Core authentifiziert und provisioniert LTI-Nutzer.
2. Core schreibt seine Werte nach `mdl_user`.
3. `local_ltiidentity` ermittelt die gewünschte Zielidentität.
4. Falls erforderlich, schreibt das Plugin die Zielwerte kontrolliert nach `mdl_user` zurück.

---

## 2. Verbindliche Architekturentscheidungen

### ADR-1: Kein Fork
Es gibt keinen Core-Fork und keine direkten Patches an `auth_lti`.

### ADR-2: `mdl_user` bleibt kanonisch
Andere Plugins und Moodle-Funktionen sollen weiterhin direkt auf `mdl_user` arbeiten können.

### ADR-3: Zusätzliche Persistenz ist minimal und technisch begründet
Override- und Regel-Tabellen dienen ausschließlich der Steuerung, nicht als alternative Primärdatenhaltung.

### ADR-4: Post-Sync statt Pre-Sync
Die Lösung korrigiert den Core-Zustand nachgelagert, statt den Core-Write an der Quelle zu unterbinden.

### ADR-5: Regelwerk ist Teil des MVP
Globale Namensregeln für anonyme Launches sind kein Nice-to-have, sondern Bestandteil der Kernarchitektur.

---

## 3. Zielstruktur des Plugins

```text
local/ltiidentity/
├── classes/
│   ├── event/
│   │   └── observer.php
│   ├── form/
│   │   ├── selfservice_form.php
│   │   ├── admin_user_form.php
│   │   ├── admin_namerule_form.php
│   │   └── course_bulk_form.php
│   ├── local/
│   │   └── service/
│   │       ├── override_repository.php
│   │       ├── name_rule_service.php
│   │       ├── policy_resolver.php
│   │       ├── reconciliation_service.php
│   │       ├── email_verification_service.php
│   │       └── audit_service.php
│   ├── output/
│   │   ├── renderer.php
│   │   └── course_report_table.php
│   └── privacy/
│       └── provider.php
├── db/
│   ├── access.php
│   ├── events.php
│   ├── hooks.php
│   ├── install.xml
│   └── upgrade.php
├── lang/en/local_ltiidentity.php
├── confirmemail.php
├── course.php
├── lib.php
├── manage.php
├── preferences.php
├── settings.php
└── version.php
```

---

## 4. Template-Bausteine für Namensregeln

Das Regelwerk soll mindestens diese Bausteine unterstützen:

- Freitext
- LTI-Provider-ID
- LTI-User-ID
- lokale `userid`

Beispiele:

- `LTI-User ` + `<userid>`
- `Anonymer Nutzer ` + `<LTI-User-ID>`
- `<Freitext>` + ` (` + `<LTI-Provider-ID>` + `)`

Die Regel-Engine muss Vorschau, Validierung und reproduzierbare Erzeugung unterstützen.

---

## 5. Plugin-Punkte ohne Fork

### 5.1 Primäre Erweiterungspunkte

- `db/events.php` für Event Observer
- `lib.php` für plugin-spezifische Callbacks
- `db/hooks.php` für unterstützte Hooks-API-Erweiterungen
- lokale Admin- und Nutzerseiten (`manage.php`, `preferences.php`, `course.php`, `confirmemail.php`)

### 5.2 Architekturhinweis

Es wird **kein** dedizierter Pre-Write-Hook für den Kern-Write in `auth_lti` vorausgesetzt. Die Lösung ist deshalb so zu entwerfen, dass sie auch dann korrekt arbeitet, wenn der Core die Personendaten bereits geschrieben hat.

---

## 6. Ziel-Services

### `override_repository`
Kapselt Lese-/Schreibzugriffe für benutzerspezifische Overrides.

### `name_rule_service`
Ermittelt, validiert und rendert globale Namensregeln.

### `policy_resolver`
Bewertet Prioritäten aus LTI, Regelwerk, lokalem Override und Fallback.

### `reconciliation_service`
Berechnet die Zielidentität und schreibt bei Bedarf Änderungen auf `mdl_user`.

### `email_verification_service`
Verwaltet Token, Ablauf und Bestätigung lokaler E‑Mail-Adressen.

### `audit_service`
Schreibt relevante Änderungen nachvollziehbar weg.

---

## 7. Request- und Login-Flow

```text
LTI Launch
   │
   ├── Moodle Core / auth_lti
   │      ├── Nutzer finden / anlegen
   │      └── Core-Felder in mdl_user schreiben
   │
   └── local_ltiidentity
          ├── Policy bestimmen
          ├── globale Namensregel prüfen
          ├── lokale Overrides prüfen
          ├── E‑Mail-Status prüfen
          └── Zielwerte nach mdl_user zurückschreiben (falls nötig)
```

---

## 8. UI-Blueprint

### Admin-Bereich
- globale Einstellungen
- Regelverwaltung
- Vorschau und Test von Namensregeln
- Audit-Einsicht

### Self-Service
- einfache Identitätsseite für LTI-Nutzer
- Eingabe und Speicherung
- Status für E‑Mail-Verifikation

### Kursansicht
- Übersicht der LTI-Nutzer im Kurs
- Bearbeitung und Rücksetzung
- Bulk-Aktionen

---

## 9. Sicherheits- und Qualitätsprinzipien

- keine unnötige PII-Schattenhaltung
- idempotente Reconciliation
- vorsichtiger Umgang mit Duplicate-E‑Mail
- alle kritischen Änderungen auditieren
- Privacy API früh mitdenken
- Coding-Standards und klare Service-Schnittstellen einhalten

---

## 10. Priorisierte Implementierungsreihenfolge

1. Datenmodell finalisieren
2. Repository- und Service-Layer bauen
3. Reconciliation-End-to-End herstellen
4. Regel-UI und Self-Service anbinden
5. E‑Mail-Verifikation ergänzen
6. Tests, Privacy und CI härten
