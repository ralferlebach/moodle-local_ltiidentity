# Pflichtenheft – `local_ltiidentity`

**Stand:** 22.04.2026  
**Komponente:** `local_ltiidentity`

---

## 1. Technischer Lösungsansatz

Die Umsetzung erfolgt als **lokales Plugin** ohne Moodle-Fork. Das Plugin ergänzt den bestehenden LTI-Core-Fluss, ersetzt ihn aber nicht.

Wesentliche Grundentscheidung:

- Moodle-Core schreibt Personendaten von LTI-Nutzern im Authentifizierungsfluss selbst in `mdl_user`.
- Ohne Core-Fork kann dieser Write nicht an der Quelle abgeschaltet werden.
- Deshalb arbeitet `local_ltiidentity` mit einer **Post-Sync-Reconciliation**: gewünschte lokale Zielwerte werden nach dem Core-Sync kontrolliert wieder nach `mdl_user` zurückgeschrieben.

Die zusätzlichen Plugin-Daten dienen nur der Steuerung und Wiederherstellung. Die kanonischen Zielwerte für andere Moodle-Komponenten bleiben `mdl_user.firstname`, `mdl_user.lastname` und `mdl_user.email`.

---

## 2. Systemgrenzen

### In Scope
- Vorname
- Nachname
- optionale E‑Mail
- globale Namensregeln für anonyme Logins
- Admin-, Lehrenden- und optionale Self-Service-Workflows
- Audit, Privacy und Verifikation

### Out of Scope
- `username`
- Consumer-seitige `sub`-Pseudonymisierung
- Core-Fork von `auth_lti`
- vollständiger Ersatz der LTI-Provisionierung

---

## 3. Fachlogische Prioritäten

### Namenspriorität
Für Namen müssen mindestens diese Modi abbildbar sein:

- `lti`: LTI/Core gewinnt
- `rule`: Admin-Namensregel erzeugt Zielwerte
- `local`: lokale Werte gewinnen
- `fallback`: lokale oder regelbasierte Werte greifen nur bei fehlenden oder unbrauchbaren Claims

### E‑Mail-Priorität
Für E‑Mail müssen mindestens diese Modi abbildbar sein:

- `lti`
- `fallback`
- `local_pending`
- `local_verified`

---

## 4. Datenmodell

### 4.1 Tabelle `local_ltiidentity_override`
Speichert benutzerspezifische Override-Werte und Policies.

Empfohlene Felder:

- `id`
- `userid`
- `firstname_override`
- `lastname_override`
- `email_override`
- `name_policy`
- `email_policy`
- `managedby`
- `source`
- `timecreated`
- `timemodified`

### 4.2 Tabelle `local_ltiidentity_namerule`
Speichert globale Namensregeln.

Empfohlene Felder:

- `id`
- `name`
- `enabled`
- `sortorder`
- `matchmode`
- `firstname_template`
- `lastname_template`
- `appliesto`
- `timecreated`
- `timemodified`

### 4.3 Tabelle `local_ltiidentity_mailtoken`
Speichert Bestätigungs-Token für E‑Mail-Adressen.

Empfohlene Felder:

- `id`
- `userid`
- `email_candidate`
- `tokenhash`
- `timecreated`
- `expiresat`
- `consumedat`

### 4.4 Tabelle `local_ltiidentity_audit`
Speichert relevante Änderungsereignisse.

Empfohlene Felder:

- `id`
- `userid`
- `actorid`
- `action`
- `oldvalue`
- `newvalue`
- `source`
- `timecreated`

---

## 5. Rechte- und Rollenmodell

Zu definierende Capabilities:

- `local/ltiidentity:managesiteidentity`
- `local/ltiidentity:managecourseidentity`
- `local/ltiidentity:selfeditidentity`
- `local/ltiidentity:viewaudit`

Empfohlene Verteilung:

- **Administrator**: alle Capabilities
- **Lehrender / editingteacher**: kursbezogene Bearbeitung
- **LTI-Nutzer**: Self-Service nur wenn global freigegeben

---

## 6. Oberflächen

### 6.1 Admin-Oberfläche
- globale Plugin-Einstellungen
- Pflege globaler Namensregeln
- Vorschau der Template-Bausteine
- Freigabeoptionen für Self-Service, E‑Mail und Duplicate-E‑Mail

### 6.2 Self-Service
- Formular für Vorname, Nachname und optionale E‑Mail
- Anzeige der aktuell geltenden Policy
- Hinweis auf Verifikationsstatus bei E‑Mail

### 6.3 Kurskontext / Lehrendenansicht
- Liste der LTI-Nutzer im Kurs
- aktueller Anzeigename und E‑Mail-Status
- Bearbeitungsaktionen
- Rücksetzung auf LTI/Regel/Local

---

## 7. Reconciliation-Logik

### Trigger
- primär über Login-nahe Plugin-Punkte
- sekundär über nachgelagerte Validierung im Request

### Verhaltensregel
Nach einem erfolgreichen LTI-Login prüft das Plugin:

1. Ist der Nutzer ein LTI-Nutzer?
2. Gibt es benutzerspezifische lokale Overrides?
3. Gibt es anwendbare globale Namensregeln?
4. Welche Policy gilt für Namen und E‑Mail?
5. Müssen `mdl_user.firstname`, `mdl_user.lastname`, `mdl_user.email` korrigiert werden?

Wenn ja, wird ein gezieltes Update auf `mdl_user` ausgeführt und auditiert.

---

## 8. E‑Mail-Workflow

1. Nutzer/Admin/Lehrender trägt E‑Mail ein.
2. System validiert Konfiguration und Kollisionen.
3. Bei aktivierter Verifikation wird ein Token erzeugt.
4. Nach Bestätigung wird die E‑Mail aktiviert.
5. Bei passender Policy wird sie nach LTI-Logins wiederhergestellt.

---

## 9. Duplicate-E‑Mail

Default-Verhalten:

- konservativ blockieren
- nur zulassen, wenn sowohl die globale Moodle-Einstellung als auch die Plugin-Einstellung dies explizit erlauben

---

## 10. Privacy

Das Plugin muss einen Privacy Provider implementieren und seine Zusatzdaten vollständig deklarieren. Export und Löschung müssen für Overrides, Namensregeln, Verifikationstoken und Auditdaten definiert sein.

---

## 11. Testkonzept

### PHPUnit
- Repository- und Service-Klassen
- Policy Resolver
- Namensregel-Engine
- Reconciliation
- E‑Mail-Verifikation

### Behat
- Self-Service-Flow
- Admin-Regelpflege
- Lehrendenbearbeitung
- Login-nahe Reconciliation

### Manuelle Tests
- anonymer Launch ohne Name
- regelbasierte Namensbildung
- lokaler Name übersteht erneuten Login
- E‑Mail-Verifikation
- Duplicate-E‑Mail blockiert / erlaubt je nach Policy

---

## 12. Lieferform und Arbeitsmodus

- Änderungen werden als ZIP mit korrekter Plugin-/Repo-Struktur geliefert.
- Versionen werden nur erhöht, wenn Plugin-Dateien geändert wurden.
- Zu jeder Lieferung gehören eine englische Commit-Zeile und bis zu fünf englische Bullet Points.
- Diagnose-Skripte, Shell-Kommandos, SQL oder JS gehören nur in den Chat, nicht in Downloads.
