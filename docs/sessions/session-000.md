# LTI Identity Kontext-Dokument
Erstellt: 2026-04-22
Zuletzt aktualisiert: 2026-04-22
Sitzung Nr.: 000

---

## 1. Aktueller Projektstand

**Aktuelle Phase:** Phase 0/1 – Fachkonzept, Blueprint und Repo-Dokumentation konsolidiert

**Abgeschlossene Phasen:**
- Fassade 1 fachlich geklärt: Fokus auf Vorname, Nachname und optionale E‑Mail
- Umsetzung ohne Core-Fork als Zielarchitektur festgelegt
- Lastenheft, Pflichtenheft und Blueprint als getrennte Leitdokumente definiert
- Session- und Prompt-Templates auf `local_ltiidentity` ausgerichtet

**Was in dieser Sitzung konkret erledigt wurde:**
- bisherige Mischdokumente aus dem Vorgängerprojekt bereinigt
- Doku-Struktur auf die neue Start-Prompt-Systematik umgebaut
- `docs/Lastenheft.md`, `docs/Pflichtenheft.md` und `docs/Blueprint.md` angelegt
- Session-Start- und Session-Ende-Vorlagen für `local_ltiidentity` erstellt
- initiales Kontext-Dokument `session-000.md` erzeugt

---

## 2. Finalisierte Artefakte

| Pfad | Zweck | Einschränkungen / TODOs |
|---|---|---|
| `docs/Lastenheft.md` | Fachliche Ziele, Scope, Use Cases und Randbedingungen | bei späteren Architekturentscheidungen synchron halten |
| `docs/Pflichtenheft.md` | Umsetzungsanforderungen, Rollen, Datenmodell und Testkonzept | mit realer Plugin-Struktur abgleichen, sobald Codebasis wächst |
| `docs/Blueprint.md` | Technisches Zielbild und Architekturentscheidungen | später mit realen Klassen- und Dateipfaden konkretisieren |
| `docs/prompt-templates/sessionstart.txt` | Startvorlage für neue Arbeitssitzungen | Delivery-Regeln bei Bedarf schärfen |
| `docs/prompt-templates/sessionende.txt` | Abschlussvorlage für neue Arbeitssitzungen | bei Bedarf um Release-/QA-Details ergänzen |
| `docs/sessions/session-000.md` | Initiales Kontext-Dokument für Folgesitzungen | bei jeder Sitzung fortschreiben |

---

## 3. Offene Arbeitspakete (priorisiert)

1. Echte Plugin-Codebasis mit Skeleton-Dateien an den Docs ausrichten.
2. Datenmodell in `db/install.xml` gegen Lastenheft/Pflichtenheft finalisieren.
3. Service-Layer für Regeln, Overrides und Reconciliation anlegen.
4. Login-nahe Plugin-Punkte konkret implementieren.
5. Tests und CI auf den realen Plugin-Stand ausrichten.

---

## 4. Architekturentscheidungen (verbindlich)

- Plugin-Komponente ist `local_ltiidentity`.
- Zielscope ist ausschließlich **Fassade 1** auf der Provider-/Tool-Seite.
- Es gibt keinen Moodle-Core-Fork.
- Kanonische Zielwerte bleiben in `mdl_user`.
- Zusätzliche Persistenz ist nur als minimale Steuerungs-/Audit-Persistenz erlaubt.
- Globale Namensregeln für anonyme Launches sind Teil des MVP.
- Startdokumentation ist in `Lastenheft.md`, `Pflichtenheft.md` und `Blueprint.md` getrennt organisiert.

---

## 5. Kritische Abhängigkeiten

- Lastenheft → Pflichtenheft → Blueprint → Code-Skeleton
- Datenmodell → Service-Layer → Reconciliation → UI / Self-Service
- Policy Resolver und Name Rule Service müssen vor der finalen Login-Reconciliation geklärt sein.

---

## 6. Bekannte Probleme und Risiken

- Die konkrete Codebasis ist noch nicht im Umfang eines vollständigen Plugins ausgebaut.
- Ohne reale Implementierung bleiben Hook-/Callback-Annahmen nur architektonisch vorgeplant.
- Duplicate-E‑Mail und Self-Service-Policies müssen vor Produktivfreigabe ausdrücklich entschieden werden.

---

## 7. Versionen / Repository-Stand

- Plugin-Komponente: `local_ltiidentity`
- Aktuelle Version / Release / Maturity: unbekannt bzw. noch nicht final besprochen
- Relevantes Repository: `https://github.com/ralferlebach/moodle-local_ltiidentity`
- Relevanter Branch: unbekannt / nicht besprochen
- Letzte bekannte Commit-Message dieser Sitzung: unbekannt

---

## 8. Für die nächste Sitzung mitzugebende Dateien

- `docs/Lastenheft.md` – fachliche Grundlage
- `docs/Pflichtenheft.md` – Umsetzungsrahmen
- `docs/Blueprint.md` – technisches Zielbild
- `docs/Moodle-Plugin Coding-Styles.md` – Stilvorgaben
- `docs/coding-standards-prompt.md` – zusätzliche Coding-Regeln
- `docs/sessions/session-000.md` – aktueller Kontext

---

## 9. Startpunkt für die nächste Sitzung

1. Plugin-Skeleton gegen die Docs abgleichen.
2. Datenmodell und Versionierungsdateien anlegen oder prüfen.
3. erste Service-Klassen definieren.
4. Login-nahe Integrationspunkte konkret festlegen.
5. UI- und Teststruktur ableiten.

---

## 10. Kurzfazit

Die Dokumentation ist jetzt auf ein konsistentes Sitzungsmodell umgestellt. Neue Arbeitsläufe können anhand klarer Pflichtlektüre und eines sauberen Kontextdokuments starten.

Die nächste sinnvolle Stufe ist der Übergang von der Doku- in eine belastbare Plugin-Skeleton- und Implementierungsphase.
