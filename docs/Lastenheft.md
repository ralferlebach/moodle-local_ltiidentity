# Lastenheft – `local_ltiidentity`

**Stand:** 22.04.2026  
**Komponente:** `local_ltiidentity`  
**Zielsystem:** Moodle 4.4/4.5/5.x  
**LTI-Fokus:** LTI 1.3 / Advantage  
**Scope:** Fassade 1 auf der Provider-/Tool-Seite

---

## 1. Ausgangslage

Moodle kann als LTI-Tool auftreten und eingehende LTI-Nutzer über `auth_lti` lokal provisionieren und wiedererkennen. Wenn ein aufrufendes System keine oder nur unvollständige personenbezogene Claims liefert, entstehen im Zielsystem unzureichende Standardwerte für Vorname, Nachname und E‑Mail.

Im Projekt `local_ltiidentity` soll dafür eine Identitäts-Fassade entstehen, die lokale, sprechende Personendaten für LTI-Nutzer ermöglicht, ohne den Moodle-Core zu forken.

---

## 2. Problemstellung

Der aktuelle Standardfluss reicht fachlich nicht aus, weil:

- anonyme oder pseudonyme LTI-Launches keine nutzbaren Klarnamen liefern,
- Administratoren keine globalen Regeln für solche Fälle definieren können,
- Lehrende oder Nutzer selbst Namen und optional E‑Mail nicht verlässlich lokal pflegen können,
- spätere LTI-Logins lokale Werte wieder überschreiben können,
- andere Moodle-Funktionen weiterhin direkt auf `mdl_user.firstname`, `mdl_user.lastname` und `mdl_user.email` zugreifen.

---

## 3. Zielbild

Für LTI-authentifizierte Nutzer soll das Plugin:

- lokal sinnvolle Vor- und Nachnamen bereitstellen,
- für anonyme oder unvollständige LTI-Logins globale **Namensregeln** unterstützen,
- optional eine lokal gepflegte E‑Mail erlauben,
- Pflege durch **Admin**, **Lehrende** oder **Nutzer selbst** ermöglichen,
- lokale Zielwerte trotz späterer LTI-Logins stabil halten,
- dabei Moodle-Core unverändert lassen.

Die kanonischen Werte für andere Moodle-Komponenten sollen am Ende weiterhin direkt in `mdl_user` stehen.

---

## 4. Stakeholder

- **Systemadministration**: globale Policies, Namensregeln, Audit, Betriebsfreigabe
- **Lehrende / Kursverantwortliche**: Bearbeitung und Korrektur lokaler Identitäten in Kurskontexten
- **LTI-Nutzer**: optionale Selbstpflege von Name und E‑Mail
- **Datenschutz / Compliance**: Kontrolle zusätzlicher personenbezogener Daten
- **Entwicklung / Betrieb**: upgrade-feste, wartbare Lösung ohne Core-Fork

---

## 5. Muss-Ziele

Das Zielsystem muss:

1. ausschließlich für Nutzer mit `auth = 'lti'` arbeiten,
2. die bestehende Core-Bindung `{iss, sub} -> userid` unangetastet lassen,
3. lokale Vor- und Nachnamen für LTI-Nutzer setzen können,
4. globale Namensregeln für anonyme/unvollständige Launches unterstützen,
5. folgende Quellen/Prioritäten unterstützen:
   - LTI/Core
   - regelbasiert
   - lokal fixiert
   - Fallback-only
6. optional lokale E‑Mail-Pflege unterstützen,
7. optional eine E‑Mail-Bestätigung per Bestätigungslink unterstützen,
8. ohne Moodle-Fork installierbar sein,
9. Änderungen nachvollziehbar machen,
10. am Ende wieder die echten Zielwerte in `mdl_user` bereitstellen.

---

## 6. Soll-Ziele

Das System soll:

- eine Admin-Oberfläche für globale Namensregeln bieten,
- eine Lehrendenansicht für kursbezogene Bearbeitung bieten,
- eine optionale Self-Service-Seite für LTI-Nutzer bieten,
- Konflikte bei E‑Mail-Adressen sichtbar machen,
- fehlende oder unvollständige LTI-Claims kenntlich machen,
- Bulk-Operationen für Bearbeitung und Rücksetzung ermöglichen,
- auditierbar und datenschutzfreundlich bleiben.

---

## 7. Nicht-Ziele

Nicht Bestandteil des MVP sind:

- Änderung von `username`,
- Consumer-seitige Pseudonymisierung (`sub`) – das ist Fassade 2,
- Vollersatz der LTI-Core-Authentifizierung,
- generisches Account-Merging als Standardprozess,
- heuristische oder unsichere automatische Identitätsauflösung,
- dauerhafte plugin-spezifische Parallelhaltung als alternative Primäridentität.

---

## 8. Use Cases

### UC-1: Erster Login ohne Klardaten
Ein LTI-Nutzer loggt sich ein, ohne dass `given_name`, `family_name` oder `email` vollständig geliefert werden. Das System soll danach durch Regelwerk oder lokale Pflege einen sinnvollen Personenbezug ermöglichen.

### UC-2: Admin setzt Namensregeln für anonyme Logins
Ein Administrator definiert globale Namensregeln für anonyme oder unvollständige LTI-Logins, z. B. aus Bausteinen wie:

- Freitext
- LTI-Provider-ID
- LTI-User-ID
- lokale `userid` im System

Beispiel: `LTI-User ` + `<userid im System>` oder `Anonymer Nutzer ` + `<LTI-User-ID>`.

### UC-3: Nutzer setzt Namen selbst
Ein LTI-Nutzer pflegt Vor- und Nachnamen selbst im dafür vorgesehenen Bereich.

### UC-4: Nutzer setzt E‑Mail selbst
Ein LTI-Nutzer setzt lokal eine E‑Mail-Adresse; diese wird optional erst nach Bestätigung aktiv.

### UC-5: Lehrender korrigiert einen Kursnutzer
Ein Lehrender korrigiert im Kurskontext die Anzeige eines LTI-Nutzers und markiert einen lokalen Namen als gewünscht.

### UC-6: Neuer LTI-Login folgt auf lokale Bearbeitung
Nach einer lokalen Bearbeitung meldet sich derselbe Nutzer erneut per LTI an. Das System muss die definierte Priorität korrekt anwenden und die Zielwerte stabil halten.

---

## 9. Randbedingungen

- Moodle-Core bleibt unverändert.
- `auth_lti` bleibt für Login und LTI-Bindung verantwortlich.
- Andere Moodle-Komponenten lesen weiter aus `mdl_user`.
- Duplicate-E‑Mail darf nicht stillschweigend als Normalfall angenommen werden.
- Die Lösung muss upgrade-fähig bleiben.

---

## 10. Datenschutz und Compliance

Die Lösung soll nur die minimal nötigen Zusatzdaten speichern, die für Priorisierung, Verifikation und Reconciliation erforderlich sind. Zusätzliche Daten müssen im Rahmen der Moodle Privacy API exportier- und löschbar sein.

---

## 11. Abgrenzung MVP / spätere Phasen

### MVP
- lokale Vor-/Nachnamen
- globale Namensregeln
- optionale lokale E‑Mail
- optionale Self-Service-Funktionen
- Admin-/Lehrendenbearbeitung
- E‑Mail-Bestätigung
- Reconciliation nach LTI-Login
- Audit-Grundlage

### Spätere Phasen
- Bulk-Importe
- weitergehende Reporting-Ansichten
- optionales Account-Linking/Merge unter kontrollierten Bedingungen
- zusätzliche Komfortfunktionen in der UI
