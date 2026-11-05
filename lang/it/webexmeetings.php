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
$string['auth_oauth'] = 'OAuth 2.0';
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
$string['apihostemail_desc'] = 'Se un amministratore Webex autorizzato crea riunioni per conto di un altro host, inserire qui l\'email Webex dell\'host. Con il profilo permessi Host, lasciare vuoto: le riunioni appartengono all\'account Webex che ha autorizzato il collegamento.';
$string['testconnection'] = 'Test connessione';
$string['connectionsuccessful'] = 'Identità Webex e lettura riunioni verificate. Creazione e presenze richiedono ancora una prova reale.';
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

// Amministrazione del collegamento OAuth.
$string['oauthmanage'] = 'Collegamento Webex';
$string['oauthredirecturi'] = 'Redirect URI';
$string['oauthscopes'] = 'Permessi Webex';
$string['oauthscopeprofile'] = 'Profilo dei permessi Webex';
$string['oauthscopehost'] = 'Riunioni dell\'account autorizzante';
$string['oauthscopeorganization'] = 'Riunioni dell\'organizzazione (amministratore Webex)';
$string['oauthconnect'] = 'Collega Webex';
$string['webexnavigation'] = 'Webex';
$string['oauthdelegateuser'] = 'Utente autorizzato a collegare Webex';
$string['oauthdelegatewarning'] = 'L\'utente selezionato potra autorizzare o sostituire il collegamento Webex per tutta l\'istanza Moodle. Svuotare la selezione per revocare il permesso.';
$string['oauthdelegatesaved'] = 'L\'utente autorizzato a collegare Webex e stato aggiornato.';
$string['oauthinvaliddelegate'] = 'Selezionare un utente Moodle attivo e confermato.';
$string['oauthdisconnect'] = 'Rimuovi collegamento locale';
$string['oauthdisconnectconfirm'] = 'Rimuovere i token OAuth salvati in questa istanza Moodle? La gestione delle riunioni e la sincronizzazione delle presenze si fermeranno fino al nuovo collegamento. L\'autorizzazione in Cisco non viene revocata. Client ID e Client Secret vengono conservati.';
$string['oauthdisconnected'] = 'Il collegamento Webex locale e stato rimosso.';
$string['oauthconnected'] = 'Autorizzazione Webex salvata correttamente.';
$string['oauthconnectedat'] = 'Autorizzazione salvata il';
$string['oauthconnectionstored'] = 'Credenziali OAuth salvate. Il test di connessione e disponibile separatamente.';
$string['oauthconnectioninactive'] = 'Credenziali OAuth salvate, ma e selezionato un altro metodo di autenticazione.';
$string['oauthnotconnected'] = 'Non e presente un collegamento OAuth salvato.';
$string['oauthmissingclient'] = 'Salvare Client ID e Client Secret di Webex nelle impostazioni del plugin prima del collegamento.';
$string['oauthinvalidprofile'] = 'Il profilo dei permessi Webex non e valido.';
$string['oauthhttpsrequired'] = 'Per autorizzare Webex e necessario un indirizzo Moodle HTTPS.';
$string['oauthinvalidstate'] = 'L\'autorizzazione non corrisponde alla sessione Moodle corrente. Avviare un nuovo collegamento dalla pagina Webex.';
$string['oauthexpiredstate'] = 'La richiesta di autorizzazione e scaduta. Avviare un nuovo collegamento.';
$string['oauthdenied'] = 'Autorizzazione Webex annullata o negata. Il collegamento precedente non e stato sostituito.';
$string['oauthinvalidcode'] = 'Webex non ha restituito un codice di autorizzazione valido. Avviare un nuovo collegamento.';
$string['oauthconfigchanged'] = 'Le impostazioni del collegamento Webex sono cambiate. Salvare le impostazioni e autorizzare nuovamente Webex.';
$string['oauthmissingscopes'] = 'Webex non ha concesso tutti i permessi richiesti. Verificare i permessi dell\'integrazione e autorizzare nuovamente.';
$string['oauthbusy'] = 'Un\'altra richiesta sta aggiornando il collegamento Webex. Riprovare tra poco.';
$string['oauthforcedsettings'] = 'Il collegamento e controllato da impostazioni in config.php e non puo essere aggiornato da questa pagina.';
$string['oauthrequestfailed'] = 'Richiesta dei token Webex non riuscita. Verificare credenziali, permessi, rete e Redirect URI registrata. Nessuna risposta del servizio o token viene mostrato.';
$string['oauthinvalidresponse'] = 'Webex non ha restituito una risposta completa e valida per i token. Il collegamento salvato non e stato sostituito.';
$string['oauthsavefailed'] = 'Impossibile salvare i token Webex. Avviare una nuova autorizzazione.';
$string['oauthpostrequired'] = 'Questa azione deve essere inviata tramite il pulsante nella pagina di collegamento Webex.';
$string['oauthtestfailed'] = 'Test di connessione Webex non riuscito. Verificare credenziali salvate, permessi e collegamento di rete.';
$string['meetingcreationerror'] = 'Errore nella creazione della riunione Webex: {$a}';
$string['invalidmeetingduration'] = 'La riunione deve durare tra 10 minuti e 24 ore.';
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
$string['startmeetingashost'] = 'Avvia come organizzatore';
$string['personalizedjoin'] = 'Ingresso personale Webex';
$string['personalizedjoin_desc'] = 'Genera un link di ingresso da browser valido cinque minuti con nome ed email univoca dell\'utente collegato a Faddy, per consentire l\'abbinamento automatico delle presenze. Richiede l\'accesso alle API Webex. In caso di errore non ripiega su un ingresso anonimo. Non riassegna le presenze anonime pregresse. Disattivato per impostazione predefinita per un rilascio controllato.';
$string['participantidentityunavailable'] = 'Il link personale Webex richiede un indirizzo email valido associato a un solo account Faddy attivo. Chiedi a un amministratore di verificare il tuo account.';
$string['personaljoinunavailable'] = 'Impossibile generare il tuo link personale di ingresso Webex. Verifica il collegamento Webex e le regole di accesso, poi riprova. Non e stato utilizzato un ingresso anonimo alternativo.';
$string['disclaimer_participants'] = 'Mostra avviso registrazione ai partecipanti';
$string['disclaimer_assignees'] = 'Mostra avviso registrazione agli incaricati';
$string['disclaimer_participants_help'] = 'Chiede agli studenti di confermare l\'avviso prima di entrare nell\'attivita. La scelta della singola aula prevale sul default per le nuove attivita.';
$string['disclaimer_assignees_help'] = 'Chiede a docenti, tutor e altri incaricati di confermare l\'avviso. Chi appartiene a entrambi i gruppi vede un solo avviso se almeno uno dei due switch e attivo.';
$string['disclaimerdefaults_desc'] = 'Default per le nuove attivita: non modifica le scelte delle aule esistenti e non attiva la registrazione Webex.';
$string['recordingdisclaimertitle'] = 'Avviso di registrazione';
$string['recordingdisclaimertext'] = 'Testo dell\'avviso di registrazione';
$string['recordingdisclaimertext_desc'] = 'Testo semplice, non HTML. Se vuoto viene usato l\'avviso standard. La conferma non avvia la registrazione e non invia un consenso alle API Webex.';
$string['recordingdisclaimerdefault'] = 'Attenzione: questa sessione potrebbe essere registrata. Accedendo all\'aula virtuale accetti che la sessione possa essere registrata.';
$string['recordingdisclaimeraccept'] = 'OK, accedi all\'aula';
$string['meetingscheduleended'] = 'Orario della riunione terminato';
$string['meetingaccessclosed'] = 'Accesso alla riunione chiuso';
$string['meetingaccessended'] = 'L\'orario di fine previsto per questa attivita e trascorso. Non e piu possibile accedere o avviare la riunione da Faddy. I report delle presenze restano disponibili.';
$string['startmeetingnotallowed'] = 'Solo un amministratore Moodle o il delegato Webex autorizzato che puo gestire questa attivita puo avviarla come organizzatore.';
$string['startmeetingunavailable'] = 'Impossibile ottenere il link organizzatore. Verifica il collegamento Webex, l\'organizzatore della riunione e le regole di accesso del sito, poi riprova.';
