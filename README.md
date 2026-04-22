# Moodle local_ltiidentity

Forkfreie Identitäts-Fassade für eingehende LTI-Logins in Moodle.

## Ziel

Das Plugin `local_ltiidentity` ergänzt Moodle als LTI-Provider um eine lokale Identitätssteuerung für LTI-Nutzer mit `auth = 'lti'`.

Im Fokus stehen:

- regelbasierte Namen für anonyme oder unvollständige LTI-Launches,
- lokale Overrides für Vorname und Nachname,
- optionale lokale E-Mail mit Bestätigungs-Workflow,
- Post-Login-Reconciliation ohne Core-Fork,
- Admin-, Kurs- und Self-Service-Einstiegspunkte.

## Aktueller Stand

Dieses Repository enthält die erste **Rumpfausstattung** des Plugins:

- `version.php` mit korrekter Plugin-Metadaten-Boilerplate,
- Grundstruktur für `db/`, `lang/`, `classes/` und Einstiegsseiten,
- erste Service-, Form-, Output- und Event-Klassen als technische Platzhalter,
- eine vereinfachte GitHub-CI für `local_ltiidentity`,
- Dokumentation in `docs/` konsistent zum aktuellen Blueprint.

## Geplante Architektur

Die Zielarchitektur ist im Dokument `docs/Pflichten- und Lastenheft, Blueprint.md` beschrieben.

Kernprinzipien:

1. `auth_lti` bleibt für Provisionierung und `{iss, sub}`-Bindung verantwortlich.
2. Die kanonischen Endwerte für Name und E-Mail bleiben in `mdl_user`.
3. Lokale Regeln und Overrides werden in einer eigenen Plugin-Schicht verwaltet.
4. Reconciliation erfolgt nach dem LTI-Login, nicht per Core-Fork.

## Installation

Das Plugin ist als **local plugin** gedacht und muss im Moodle-Dateisystem unter `local/ltiidentity` liegen.

## Lizenz

GNU GPL v3 or later.
