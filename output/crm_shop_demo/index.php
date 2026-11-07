<?php
require_once dirname(__FILE__) . '/phpfn14.php';
$Security = ew_Security();
$candidates = array (
  'produkte' => 'produktelist.php',
  'benutzer' => 'benutzerlist.php',
  'bestellungen' => 'bestellungenlist.php',
  'kategorien' => 'kategorienlist.php',
  'kunden' => 'kundenlist.php',
  'userlevelpermissions' => 'userlevelpermissionslist.php',
  'userlevels' => 'userlevelslist.php',
  'v_bestellungen_uebersicht' => 'v_bestellungen_uebersichtlist.php',
);
foreach ($candidates as $tName => $url) {
    if (!$Security->IsEnabled() || $Security->CanList($tName)) {
        ew_Redirect($url);
    }
}
if ($Security->IsEnabled() && !$Security->IsLoggedIn()) {
    ew_Redirect('login.php');
}
$PageTitle = 'Dashboard';
require_once dirname(__FILE__) . '/header.php';
echo '<div class="callout callout-info"><h4>Willkommen!</h4>Für Ihre aktuelle Benutzerrolle sind keine Tabellen zur Listenansicht freigegeben oder es sind keine Tabellen aktiviert.</div>';
require_once dirname(__FILE__) . '/footer.php';
