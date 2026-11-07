<?php
/**
 * PHPMacherNG Login Page (login.php)
 */
require_once dirname(__FILE__) . '/phpfn14.php';

$Security = ew_Security();
if ($Security->IsLoggedIn()) {
    ew_Redirect('index.php');
}

$username = '';
$returnUrl = ew_Get('return', 'index.php');
if (strpos($returnUrl, 'login.php') !== false || strpos($returnUrl, 'logout.php') !== false || $returnUrl === '') {
    $returnUrl = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(ew_Post('username', ''));
    $password = ew_Post('password', '');
    $returnUrl = ew_Post('return_url', 'index.php');

    if (!ew_CheckCsrfToken(ew_Post('csrf_token', ''))) {
        ew_SetFailureMessage('Ungültiges Sicherheits-Token (CSRF). Bitte versuchen Sie es erneut.');
    } elseif ($Security->GetLockoutRemaining() > 0) {
        ew_SetFailureMessage('Zu viele Fehlversuche! Bitte warten Sie noch <strong>' . (int)$Security->GetLockoutRemaining() . ' Sekunden</strong>.');
    } elseif ($username === '' || $password === '') {
        ew_SetFailureMessage('Bitte geben Sie Benutzername und Passwort ein.');
    } else {
        $canLogin = true;
        if (function_exists('User_LoggingIn')) {
            $canLogin = (bool)User_LoggingIn($username, $password);
        }
        if ($canLogin && $Security->ValidateUser($username, $password)) {
            if (function_exists('User_LoggedIn')) {
                User_LoggedIn($Security->CurrentUserName);
            }
            ew_SetSuccessMessage('Willkommen zurück, <strong>' . ew_HtmlEncode($Security->CurrentUserName) . '</strong> (Rolle: ' . ew_HtmlEncode($Security->CurrentUserLevelName) . ')!');
            ew_Redirect($returnUrl);
        } elseif ($canLogin) {
            if (function_exists('User_LoginError')) {
                User_LoginError($username, $password);
            }
            $lockRem = $Security->GetLockoutRemaining();
            if ($lockRem > 0) {
                ew_SetFailureMessage('Zu viele Fehlversuche! Die Anmeldung ist für <strong>' . (int)$lockRem . ' Sekunden</strong> gesperrt.');
            } else {
                ew_SetFailureMessage('Ungültiger Benutzername oder falsches Passwort.');
            }
        }
    }
}

$PageTitle = 'Anmelden';
$PageSubTitle = 'Benutzer-Authentifizierung';
require_once dirname(__FILE__) . '/header.php';
?>

<div class="row" style="margin-top: 25px;">
    <div class="col-md-4 col-md-offset-4 col-sm-6 col-sm-offset-3">
        <div class="box box-primary" style="box-shadow: 0 2px 8px rgba(0,0,0,0.12);">
            <div class="box-header with-border text-center" style="padding:18px 15px;">
                <h3 class="box-title" style="font-size:20px; font-weight:600;">
                    <i class="fa fa-lock text-primary"></i> <?php echo ew_HtmlEncode(EW_PROJECT_NAME); ?> Login
                </h3>
                <p class="text-muted" style="margin:6px 0 0 0; font-size:13px;">Melden Sie sich an, um Ihre Sitzung zu starten</p>
            </div>
            <form id="flogin" method="post" action="login.php" onsubmit="return ew_LoginFormValidate(this);">
                <input type="hidden" name="csrf_token" value="<?php echo ew_HtmlEncode(ew_GetCsrfToken()); ?>">
                <input type="hidden" name="return_url" value="<?php echo ew_HtmlEncode($returnUrl); ?>">
                <div class="box-body" style="padding:20px;">
                    <div class="form-group has-feedback">
                        <label for="username">Benutzername</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?php echo ew_HtmlEncode($username); ?>" placeholder="Benutzername eingeben" required autofocus>
                        <span class="glyphicon glyphicon-user form-control-feedback" style="top:25px;"></span>
                    </div>
                    <div class="form-group has-feedback">
                        <label for="password">Passwort</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Passwort eingeben" required>
                        <span class="glyphicon glyphicon-lock form-control-feedback" style="top:25px;"></span>
                    </div>
                </div>
                <div class="box-footer" style="padding:12px 20px; background:#f9fafc;">
                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-sign-in"></i> Anmelden</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>function ew_LoginFormValidate(fobj) {
    return true;
}
</script>

<?php require_once dirname(__FILE__) . '/footer.php'; ?>