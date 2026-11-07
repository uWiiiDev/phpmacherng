<?php
/**
 * PHPMacherNG Custom File (dashboard.php)
 * Include common files: ENABLED
 */
require_once dirname(__FILE__) . '/phpfn14.php';

$Security = ew_Security();
$Security->CheckPagePermission('dashboard.php', EW_ALLOW_LIST);
$db = &DbHelper();
$conn = &Conn();

$PageTitle = 'Management Dashboard';
$PageSubTitle = 'Custom File';
require_once dirname(__FILE__) . '/header.php';
?>
<div class="ewCustomFileContent">
<?php
// ============================================================================
// PHPMacherNG Custom File: Management Dashboard (dashboard.php)
// Verfügbare PHPMacherNG Datenzugriffs-Funktionen:
//   $db = &DbHelper();                   // Datenbank-Helper-Objekt abrufen
//   ew_ExecuteScalar($sql, $params)      // Einzelwert abrufen (z.B. COUNT, SUM)
//   ew_ExecuteRow($sql, $params)         // Einzelne Zeile als Array abrufen
//   ew_ExecuteRows($sql, $params)        // Alle Zeilen als Array-Liste abrufen
//   ew_ExecuteHtml($sql, $options)       // SQL-Ergebnis direkt als Bootstrap-Tabelle ausgeben!
//   ew_Execute($sql, $params)            // INSERT / UPDATE / DELETE ausführen
//   CurrentUserName(), CurrentUserID(), CurrentUserLevel(), IsLoggedIn(), IsSysAdmin()
// ============================================================================

$anzahlKunden       = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `kunden`");
$anzahlProdukte     = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `produkte`");
$anzahlBestellungen = (int)ew_ExecuteScalar("SELECT COUNT(*) FROM `bestellungen`");
$gesamtUmsatz       = (float)ew_ExecuteScalar("SELECT COALESCE(SUM(`gesamtbetrag`), 0) FROM `bestellungen` WHERE `status` <> 'Storniert'");
?>

<div class="callout callout-info" style="margin-bottom:20px;">
    <h4><i class="fa fa-file-code-o"></i> Eigene PHPMacherNG-Datei (Custom File: <code>dashboard.php</code>)</h4>
    <p style="margin:0;">
        Angemeldet als: <strong><?php echo ew_HtmlEncode(CurrentUserName() ? CurrentUserName() : 'Gast'); ?></strong>
        (User-ID: <code><?php echo ew_HtmlEncode(CurrentUserID()); ?></code> |
        User-Level: <code><?php echo (int)CurrentUserLevel(); ?></code> |
        Datum: <code><?php echo CurrentDate(); ?></code>).
        Diese Seite wurde über die <strong>Custom Files</strong> Funktion mit <code>ew_ExecuteScalar()</code>, <code>DbHelper()-&gt;ExecuteHtml()</code> und <code>ew_ExecuteRows()</code> erstellt.
    </p>
</div>

<!-- KPI Info-Boxen mit ew_ExecuteScalar() -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Kunden gesamt</span>
                <span class="info-box-number"><?php echo $anzahlKunden; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Produkte im Katalog</span>
                <span class="info-box-number"><?php echo $anzahlProdukte; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Bestellungen</span>
                <span class="info-box-number"><?php echo $anzahlBestellungen; ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-eur"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Gesamtumsatz</span>
                <span class="info-box-number"><?php echo number_format($gesamtUmsatz, 2, ',', '.'); ?> &euro;</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Linke Spalte: Automatische HTML-Tabelle per DbHelper()->ExecuteHtml() -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-table"></i> Umsatz pro Kunde (direkt per <code>DbHelper()-&gt;ExecuteHtml()</code>)</h3>
            </div>
            <div class="box-body table-responsive">
                <?php
                $sqlUmsatzKunden = "
                    SELECT
                        k.firma AS Firma,
                        k.stadt AS Stadt,
                        k.kundengruppe AS Gruppe,
                        COUNT(b.bestellung_id) AS Bestellungen,
                        CONCAT(FORMAT(COALESCE(SUM(b.gesamtbetrag), 0), 2, 'de_DE'), ' €') AS Umsatz
                    FROM kunden k
                    LEFT JOIN bestellungen b ON b.kunde_id = k.kunde_id
                    GROUP BY k.kunde_id, k.firma, k.stadt, k.kundengruppe
                    ORDER BY COALESCE(SUM(b.gesamtbetrag), 0) DESC
                ";
                echo DbHelper()->ExecuteHtml($sqlUmsatzKunden, array(
                    'fieldcaption' => array(
                        'Firma'        => 'Firma / Kunde',
                        'Stadt'        => 'Standort',
                        'Gruppe'       => 'Kundengruppe',
                        'Bestellungen' => 'Anz. Aufträge',
                        'Umsatz'       => 'Gesamtumsatz'
                    )
                ));
                ?>
            </div>
        </div>
    </div>

    <!-- Rechte Spalte: Eigene Schleife mit ew_ExecuteRows() -->
    <div class="col-md-5">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cubes"></i> Lagerbestand (per <code>ew_ExecuteRows()</code>)</h3>
            </div>
            <div class="box-body">
                <ul class="products-list product-list-in-box">
                    <?php
                    $produkte = ew_ExecuteRows("SELECT produktname, artikelnummer, preis, lagerbestand FROM produkte ORDER BY lagerbestand ASC");
                    if (is_array($produkte)):
                        foreach ($produkte as $p):
                            $badgeClass = ((int)$p['lagerbestand'] > 5) ? 'label-success' : (((int)$p['lagerbestand'] > 0) ? 'label-warning' : 'label-danger');
                    ?>
                        <li class="item" style="padding:8px 0; border-bottom:1px solid #f4f4f4;">
                            <div class="product-info" style="margin-left:5px;">
                                <strong><?php echo ew_HtmlEncode($p['produktname']); ?></strong>
                                <span class="label <?php echo $badgeClass; ?> pull-right">Bestand: <?php echo (int)$p['lagerbestand']; ?></span>
                                <span class="product-description" style="display:block; color:#777; font-size:12px;">
                                    Art.-Nr.: <code><?php echo ew_HtmlEncode($p['artikelnummer']); ?></code> |
                                    Einzelpreis: <?php echo number_format((float)$p['preis'], 2, ',', '.'); ?> &euro;
                                </span>
                            </div>
                        </li>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once dirname(__FILE__) . '/footer.php'; ?>
