<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings for local_ltiidentity.
 *
 * @package    local_ltiidentity
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['auditretention'] = 'Aufbewahrung des Audits (Tage)';
$string['auditretention_desc'] = 'Ältere Audit-Einträge werden vom geplanten Task gelöscht. 0 behält sie unbegrenzt.';
$string['cannotmanageuser'] = 'Sie dürfen dieses Profil nicht bearbeiten.';
$string['check_syncdetection'] = 'LTI-Profilschutz: Erkennung der Launch-Synchronisation';
$string['check_syncdetection_changed'] = 'Die Aktualisierung, die auth_lti an die Benutzer-API übergibt, hat sich in dieser Moodle-Version geändert. Sperren und Claim-Priorität werden bei LTI-Starts eventuell nicht angewendet, bis das Plugin aktualisiert ist. Platzhalterwerte überschreiben echte Werte weiterhin nicht.';
$string['check_syncdetection_nohook'] = 'Der Hook-Callback before_user_updated dieses Plugins ist nicht registriert. Caches leeren; gesperrte Profilwerte sind bis dahin nicht geschützt.';
$string['check_syncdetection_ok'] = 'Die LTI-Launch-Synchronisation entspricht dem Muster, gegen das dieses Plugin schützt.';
$string['check_syncdetection_unknown'] = 'Der Quelltext von auth_plugin_lti::update_user_account() konnte nicht gelesen werden; die Erkennung kann nicht geprüft werden.';
$string['claimpriority'] = 'Priorität der LTI-Claims';
$string['claimpriority_claimswin'] = 'Claims gewinnen, außer das Feld ist gesperrt (Standard)';
$string['claimpriority_desc'] = 'Legt fest, was geschieht, wenn ein LTI-Start einen echten Namen oder eine E-Mail für ein nicht gesperrtes Feld übermittelt. Gesperrte Felder werden nie überschrieben, fehlende Claims setzen vorhandene Werte nie zurück.';
$string['claimpriority_fill'] = 'Claims ersetzen nur Platzhalter';
$string['claimpriority_localwins'] = 'Vorhandene lokale Werte gewinnen immer';
$string['confirmemail'] = 'E-Mail-Adresse bestätigen';
$string['confirmemailquestion'] = 'Möchten Sie die neue E-Mail-Adresse für dieses Konto bestätigen?';
$string['confirmresult_busy'] = 'Eine andere Anfrage für dieses Konto wird gerade verarbeitet. Bitte versuchen Sie es gleich noch einmal.';
$string['confirmresult_confirmed'] = 'Die E-Mail-Adresse wurde bestätigt.';
$string['confirmresult_duplicate'] = 'Die E-Mail-Adresse ist nicht mehr verfügbar.';
$string['confirmresult_expired'] = 'Der Link ist abgelaufen. Bitte fordern Sie einen neuen an.';
$string['confirmresult_invalid'] = 'Der Link ist ungültig oder wurde bereits verwendet.';
$string['confirmresult_wronguser'] = 'Bitte melden Sie sich mit dem Konto an, für das der Link angefordert wurde.';
$string['editprofile'] = 'Profil vervollständigen';
$string['emailduplicate'] = 'Doppelte E-Mail-Adressen';
$string['emailduplicate_allow'] = 'Erlauben';
$string['emailduplicate_block'] = 'Blockieren (Standard)';
$string['emailduplicate_desc'] = 'Was geschieht, wenn eine gewünschte Adresse bereits von einem anderen Konto verwendet wird. Die Core-Einstellung "Mehrfache Konten mit gleicher E-Mail" kann dies nur verschärfen: Ist sie aus, werden Duplikate immer blockiert.';
$string['emailduplicate_warn'] = 'Mit Warnung erlauben';
$string['emailduplicatewarning'] = 'Warnung: Ein anderes Konto verwendet dieselbe Adresse. Passwort-Zurücksetzen und Kontowiederherstellung können das falsche Konto erreichen.';
$string['emailrequested'] = 'Ein Bestätigungslink wurde an die neue E-Mail-Adresse gesendet.';
$string['emailresult_busy'] = 'Eine andere Anfrage für dieses Konto wird gerade verarbeitet. Bitte versuchen Sie es gleich noch einmal.';
$string['emailresult_duplicate'] = 'Die E-Mail-Adresse ist nicht verfügbar.';
$string['emailresult_invalid'] = 'Die E-Mail-Adresse wurde nicht akzeptiert.';
$string['emailresult_toosoon'] = 'Ein Bestätigungslink wurde soeben angefordert. Bitte warten Sie eine Minute.';
$string['emailresult_unchanged'] = 'Die E-Mail-Adresse ist unverändert.';
$string['emailstatus'] = 'E-Mail-Status';
$string['emailstatus_pending'] = 'Bestätigung ausstehend';
$string['emailstatus_unverified'] = 'Nicht bestätigt';
$string['emailstatus_verified'] = 'Bestätigt';
$string['emailtokenlifetime'] = 'Gültigkeit des Bestätigungslinks';
$string['emailtokenlifetime_desc'] = 'Wie lange der Einmal-Link für eine E-Mail-Änderung gültig bleibt.';
$string['emailverify_body'] = 'Bitte bestätigen Sie diese E-Mail-Adresse für Ihr Konto bei {$a->sitename}, indem Sie den folgenden Link öffnen, während Sie in diesem Konto angemeldet sind: {$a->link} Der Link kann einmal verwendet werden und läuft nach etwa {$a->hours} Stunden ab. Falls Sie diese Nachricht nicht erwartet haben, ignorieren Sie sie; es ändert sich nichts.';
$string['emailverify_subject'] = '{$a->sitename}: E-Mail-Adresse bestätigen';
$string['errorbusy'] = 'Eine andere Änderung an diesem Profil wird gerade verarbeitet. Bitte versuchen Sie es gleich noch einmal.';
$string['erroremailinvalid'] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
$string['erroremailnotavailable'] = 'Diese E-Mail-Adresse ist nicht verfügbar.';
$string['errornameempty'] = 'Bitte geben Sie einen Wert ein.';
$string['errornametoolong'] = 'Der Wert ist zu lang (höchstens 100 Zeichen).';
$string['errorplaceholdervalue'] = 'Dies ist der automatische Platzhalter. Bitte geben Sie einen echten Namen ein.';
$string['event_emailverified'] = 'LTI-Profil-E-Mail bestätigt';
$string['event_lockreleased'] = 'LTI-Profilsperre aufgehoben';
$string['event_profileupdated'] = 'LTI-Profilfelder aktualisiert';
$string['event_syncprotected'] = 'LTI-Synchronisation zum Schutz von Profilwerten gefiltert';
$string['lockedfields'] = 'Lokal gesperrte Felder';
$string['lockexplanation'] = 'Gespeicherte Namen werden gesperrt: Spätere LTI-Starts überschreiben sie nicht.';
$string['lockreleased'] = 'Die Sperre wurde aufgehoben.';
$string['locktimeout'] = 'Sperr-Timeout (Sekunden)';
$string['locktimeout_desc'] = 'Wie lange eine E-Mail-Anfrage, Bestätigung oder Profilspeicherung auf eine gleichzeitige Anfrage desselben Kontos wartet (1 bis 60). LTI-Starts warten nie.';
$string['logprotected'] = 'Geschützte Synchronisationen protokollieren';
$string['logprotected_desc'] = 'Schreibt ein Ereignis (nur Feldnamen, keine Werte), wenn ein LTI-Start oder ein Platzhalterwert gefiltert wurde. Standardmäßig aus, da pro geschütztem Start eine Logzeile entsteht.';
$string['ltiidentity:editown'] = 'Eigenes LTI-Profil vervollständigen';
$string['ltiidentity:manage'] = 'Profil von LTI-Nutzenden im Kurs vervollständigen';
$string['ltiidentity:releaselock'] = 'Lokale Profilsperren aufheben';
$string['ltiidentity:viewprovenance'] = 'Herkunft der LTI-Profildaten ansehen';
$string['ltiusers'] = 'LTI-Nutzende';
$string['myltiprofile'] = 'Mein LTI-Profil';
$string['nameplaceholder'] = 'Name von der Plattform nicht übermittelt ({$a})';
$string['newemail'] = 'Neue E-Mail-Adresse (optional)';
$string['newemail_help'] = 'Die Adresse wird erst verwendet, nachdem die Person das an sie gesendete Link bestätigt hat. Bis dahin bleibt die aktuelle Adresse unverändert.';
$string['nousers'] = 'In diesem Kurs gibt es keine LTI-Nutzenden, die Sie bearbeiten dürfen.';
$string['placeholderfields'] = 'Platzhalterwerte';
$string['pluginname'] = 'LTI-Identität';
$string['privacy:audit'] = 'Profiländerungen (Audit)';
$string['privacy:metadata:audit'] = 'Protokoll der über dieses Plugin vorgenommenen Profiländerungen. Enthält nur Feldnamen und Sperrzustände.';
$string['privacy:metadata:audit:action'] = 'Die Art der Änderung.';
$string['privacy:metadata:audit:actorid'] = 'Die Person, die die Änderung vorgenommen hat.';
$string['privacy:metadata:audit:contextid'] = 'Der Kontext, in dem die Änderung vorgenommen wurde.';
$string['privacy:metadata:audit:fieldnames'] = 'Die Namen der betroffenen Felder.';
$string['privacy:metadata:audit:newflag'] = 'Der Sperrzustand nach der Änderung.';
$string['privacy:metadata:audit:oldflag'] = 'Der Sperrzustand vor der Änderung.';
$string['privacy:metadata:audit:targetuserid'] = 'Die Person, deren Profil geändert wurde.';
$string['privacy:metadata:audit:timecreated'] = 'Der Zeitpunkt der Änderung.';
$string['privacy:metadata:preference:emailpending'] = 'Eine ausstehende E-Mail-Änderung: die gewünschte Adresse und der Hash des Einmal-Tokens.';
$string['privacy:metadata:preference:emailverified'] = 'Ein Hash der E-Mail-Adresse, die über den Link bestätigt wurde.';
$string['privacy:metadata:preference:lock'] = 'Herkunft eines lokal gesperrten Profilfelds (Lehrende, selbst oder bestätigt).';
$string['privacy:metadata:preference:provenance'] = 'Herkunft des aktuellen Werts eines Profilfelds: die Quelle (LTI-Plattform, Lehrende, Person selbst, per Link bestätigt, andere) und ein Hash des beschriebenen Werts.';
$string['privacy:preferences'] = 'LTI-Profil-Einstellungen';
$string['privacy:preferences:emailpending'] = 'Ausstehende E-Mail-Änderung (Adresse und Ablauf; der Token-Hash wird nicht exportiert)';
$string['profilesaved'] = 'Das Profil wurde gespeichert.';
$string['provenance'] = 'Herkunft der LTI-Profildaten';
$string['provenance_initial'] = 'Nicht erfasst: unverändert seit Anlage des Kontos (normalerweise durch den ersten LTI-Start)';
$string['provenance_lti'] = 'LTI-Plattform (bei einem Start übertragen)';
$string['provenance_other'] = 'Außerhalb dieses Plugins geändert (zum Beispiel durch die Administration)';
$string['provenance_placeholder'] = 'LTI-Plattform: nicht übertragen (automatischer Platzhalter)';
$string['provenance_self'] = 'Die Person selbst';
$string['provenance_teacher'] = 'Lehrende';
$string['provenance_unknown'] = 'Für den aktuellen Wert nicht erfasst';
$string['provenance_verified'] = 'Die Person selbst, per E-Mail-Link bestätigt';
$string['provenancefield'] = 'Herkunft: {$a}';
$string['provenancelocked'] = 'gegen LTI-Starts gesperrt';
$string['releaselock'] = 'Sperre aufheben';
$string['releaselockquestion'] = 'Lokale Sperre aufheben? Der nächste LTI-Start darf dieses Feld dann gemäß Site-Richtlinie überschreiben.';
$string['source_self'] = 'von der Person selbst eingetragen';
$string['source_teacher'] = 'von Lehrenden eingetragen';
$string['source_verified'] = 'per E-Mail-Link bestätigt';
$string['task_purge'] = 'Abgelaufene E-Mail-Anfragen und alte Audit-Einträge entfernen';
$string['teacheremail'] = 'Lehrende dürfen E-Mail-Änderungen anfordern';
$string['teacheremail_desc'] = 'Wenn aktiviert, können Lehrende eine E-Mail-Adresse für LTI-Nutzende eintragen. Sie wird erst nach Bestätigung durch die Person übernommen.';
$string['upgradefromrump'] = 'local_ltiidentity kann die installierte Version {$a} nicht aktualisieren: Sie ist der 0.1.0-Rumpf dieser Komponente mit einer anderen, inkompatiblen Datenbankstruktur. Es gibt keinen Aktualisierungspfad. Den 0.1.0-Code wiederherstellen, das Plugin deinstallieren (Website-Administration > Plugins > Plugin-Übersicht) und dieses Release als neues Plugin installieren. Siehe docs/MIGRATION.md.';
