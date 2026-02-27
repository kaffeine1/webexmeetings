<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Italian language strings for mod_webexmeetings
 *
 * @package    mod_webexmeetings
 * @copyright  2026 Michele Dipace
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Generale
$string['modulename'] = 'Riunione Webex';
$string['modulenameplural'] = 'Riunioni Webex';
$string['pluginname'] = 'Riunioni Webex';
$string['pluginadministration'] = 'Amministrazione Riunioni Webex';
$string['modulename_help'] = 'Il modulo Riunioni Webex permette di creare e gestire riunioni Cisco Webex direttamente da Moodle. Le presenze vengono tracciate automaticamente.';
$string['modulename_link'] = 'mod/webexmeetings/view';
$string['webexmeetings:addinstance'] = 'Aggiungere una nuova Riunione Webex';
$string['webexmeetings:view'] = 'Visualizzare Riunione Webex';
$string['webexmeetings:viewattendance'] = 'Visualizzare report presenze';
$string['webexmeetings:exportattendance'] = 'Esportare dati presenze';
$string['webexmeetings:managemeeting'] = 'Gestire impostazioni riunione';
$string['webexmeetings:syncattendance'] = 'Sincronizzare presenze da Webex';
$string['webexmeetings:deleteattendance'] = 'Eliminare record presenze';

// Form
$string['meetingname'] = 'Nome riunione';
$string['scheduling'] = 'Pianificazione';
$string['starttime'] = 'Ora di inizio';
$string['endtime'] = 'Ora di fine';
$string['meetingpassword'] = 'Password riunione';
$string['meetingpassword_help'] = 'Password opzionale per la riunione Webex. Se lasciata vuota, Webex potrebbe generarne una automaticamente.';
$string['recurringmeeting'] = 'Riunione ricorrente';
$string['attendancesection'] = 'Tracciamento presenze';
$string['trackattendance'] = 'Traccia presenze';
$string['trackattendance_help'] = 'Se abilitato, le presenze dei partecipanti verranno sincronizzate automaticamente da Webex.';
$string['minduration'] = 'Durata minima per la presenza';
$string['minduration_help'] = 'Tempo minimo che un partecipante deve essere presente per essere conteggiato come presente.';
$string['nominimum'] = 'Nessun minimo';
$string['endtimebeforestart'] = 'L\'ora di fine deve essere successiva all\'ora di inizio.';

// Visualizzazione
$string['meetingdetails'] = 'Dettagli riunione';
$string['joinmeeting'] = 'Partecipa alla Riunione Webex';
$string['nojoinurl'] = 'Il link di accesso non è disponibile. La riunione potrebbe non essere stata creata correttamente su Webex.';
$string['meetingnotstarted'] = 'Non iniziata';
$string['meetinginprogress'] = 'In corso';
$string['meetingended'] = 'Terminata';
$string['attendancesummary'] = 'Riepilogo presenze';
$string['noattendancedata'] = 'Nessun dato di presenza disponibile. I dati potrebbero non essere disponibili fino a circa 24 ore dopo la fine della riunione.';
$string['viewfullreport'] = 'Visualizza report completo';
$string['syncnow'] = 'Sincronizza ora';
$string['unmatchedusers'] = 'Utenti non abbinati ({$a})';

// Presenze
$string['attendancereport'] = 'Report presenze';
$string['jointime'] = 'Ora di ingresso';
$string['leavetime'] = 'Ora di uscita';
$string['duration'] = 'Durata';
$string['sessions'] = 'Sessioni';
$string['viewsessions'] = 'Visualizza sessioni';
$string['present'] = 'Presente';
$string['absent'] = 'Assente';
$string['enrolled'] = 'Iscritti';
$string['attendancerate'] = 'Tasso di presenza';
$string['attendancerecords'] = 'Record presenze';
$string['exportcsv'] = 'Esporta CSV';

// Sincronizzazione
$string['syncattendance'] = 'Sincronizza presenze';
$string['syncattendancetask'] = 'Sincronizza dati presenze Webex';
$string['syncsuccess'] = 'Presenze sincronizzate con successo per {$a} partecipanti.';
$string['syncerror'] = 'Errore nella sincronizzazione delle presenze: {$a}';
$string['nomeetingid'] = 'Questa riunione non ha un ID Webex. Potrebbe non essere stata creata correttamente.';
$string['lastsync'] = 'Ultima sincronizzazione';

// Utenti non abbinati
$string['unmatcheduserspage'] = 'Utenti Webex non abbinati';
$string['unmatchedusersdesc'] = 'Questi partecipanti Webex non sono stati automaticamente abbinati a utenti Moodle. È possibile mapparli manualmente qui sotto.';
$string['nounmatchedusers'] = 'Nessun utente non abbinato trovato.';
$string['maptouser'] = 'Mappa a utente Moodle';
$string['selectuser'] = 'Seleziona un utente...';
$string['map'] = 'Mappa';
$string['usermapped'] = 'Utente mappato con successo.';

// Impostazioni
$string['apisettings'] = 'Impostazioni API Webex';
$string['apisettings_desc'] = 'Configura le credenziali per la connessione all\'API Cisco Webex. Inserisci il Personal Access Token oppure, in alternativa, le credenziali OAuth2.';
$string['auth_method'] = 'Metodo di autenticazione';
$string['auth_method_desc'] = 'Scegli come autenticarsi con l\'API Webex.';
$string['auth_bot'] = 'Personal / Bot Token';
$string['auth_oauth'] = 'OAuth2 (Credenziali client + Refresh token)';
$string['personaltoken'] = 'Personal Access Token / Integration Token';
$string['personaltoken_desc'] = 'Inserisci il token Webex che autorizza la creazione delle Riunioni. Puoi generarlo dal portale sviluppatori o dall\'Integrazione Webex.';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Client ID OAuth2 dall\'integrazione Webex.';
$string['clientsecret'] = 'Client secret';
$string['clientsecret_desc'] = 'Client Secret OAuth2 dall\'integrazione Webex.';
$string['refreshtoken'] = 'Refresh token';
$string['refreshtoken_desc'] = 'Refresh token OAuth2. Viene utilizzato per ottenere automaticamente nuovi access token.';
$string['siteurl'] = 'URL sito Webex';
$string['siteurl_desc'] = 'URL del sito Webex (es. nomesito.webex.com). Lasciare vuoto per usare il predefinito.';
$string['apihostemail'] = 'Email Host Predefinita (Host Email)';
$string['apihostemail_desc'] = 'Se il token API appartiene a un amministratore o a un servizio e vuoi creare le riunioni per conto di un utente specifico, inserisci qui la sua email Webex. Altrimenti lascia vuoto per usare l\'email dell\'utente Moodle corrente.';
$string['testconnection'] = 'Test connessione';
$string['connectionsuccessful'] = 'Connessione all\'API Webex riuscita!';
$string['connectionfailed'] = 'Connessione all\'API Webex fallita: {$a}';
$string['defaultsettings'] = 'Impostazioni predefinite riunione';
$string['defaultsettings_desc'] = 'Valori predefiniti per le nuove attività Riunione Webex.';
$string['defaulttrackattendance'] = 'Traccia presenze per impostazione predefinita';
$string['defaulttrackattendance_desc'] = 'Se abilitato, il tracciamento presenze sarà attivo per impostazione predefinita per le nuove riunioni.';
$string['defaultminduration'] = 'Durata minima predefinita';
$string['defaultminduration_desc'] = 'Durata minima predefinita per le presenze.';
$string['syncsettings'] = 'Impostazioni sincronizzazione';
$string['syncsettings_desc'] = 'Impostazioni per il task automatico di sincronizzazione presenze.';
$string['synclookbackdays'] = 'Giorni di ricerca sincronizzazione';
$string['synclookbackdays_desc'] = 'Quanti giorni indietro cercare le riunioni da sincronizzare.';
$string['debugmode'] = 'Modalità debug';
$string['debugmode_desc'] = 'Abilita log di debug aggiuntivi per le chiamate API Webex.';

// Errori
$string['missingcredentials'] = 'Le credenziali API Webex non sono configurate. Verificare le impostazioni del plugin.';
$string['failedtogetaccesstoken'] = 'Impossibile ottenere il token di accesso da Webex.';
$string['meetingcreationerror'] = 'Errore nella creazione della riunione Webex: {$a}';
$string['meetingupdateerror'] = 'Errore nell\'aggiornamento della riunione Webex: {$a}';
$string['meetingdeleteerror'] = 'Errore nell\'eliminazione della riunione Webex: {$a}';

// Eventi
$string['eventmeetingcreated'] = 'Riunione Webex creata';
$string['eventmeetingjoined'] = 'Riunione Webex partecipata';
$string['eventattendanceviewed'] = 'Presenze Webex visualizzate';
$string['eventattendancesynced'] = 'Presenze Webex sincronizzate';

// Indice
$string['nomeetings'] = 'Non ci sono riunioni Webex in questo corso.';

// Privacy
$string['privacy:metadata:webexmeetings_attendance'] = 'Dati di riepilogo presenze per le riunioni Webex.';
$string['privacy:metadata:webexmeetings_attendance:userid'] = 'L\'ID dell\'utente che ha partecipato.';
$string['privacy:metadata:webexmeetings_attendance:join_time'] = 'L\'ora in cui l\'utente si è collegato per la prima volta.';
$string['privacy:metadata:webexmeetings_attendance:leave_time'] = 'L\'ora in cui l\'utente si è scollegato per l\'ultima volta.';
$string['privacy:metadata:webexmeetings_attendance:duration'] = 'Durata totale della partecipazione.';
$string['privacy:metadata:webexmeetings_sessions'] = 'Dati delle singole sessioni per le riunioni Webex.';
$string['privacy:metadata:webexmeetings_sessions:userid'] = 'L\'ID dell\'utente.';
$string['privacy:metadata:webexmeetings_sessions:join_time'] = 'L\'ora di ingresso dell\'utente.';
$string['privacy:metadata:webexmeetings_sessions:leave_time'] = 'L\'ora di uscita dell\'utente.';
$string['privacy:metadata:webexmeetings_sessions:duration'] = 'Durata della sessione.';
$string['privacy:metadata:webexmeetings_sessions:ip_address'] = 'Indirizzo IP dell\'utente.';
$string['privacy:metadata:webex'] = 'Dati condivisi con Cisco Webex per la gestione delle riunioni.';
$string['privacy:metadata:webex:email'] = 'Indirizzo email dell\'utente.';
$string['privacy:metadata:webex:fullname'] = 'Nome completo dell\'utente.';
