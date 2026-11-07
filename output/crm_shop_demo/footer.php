            <?php
            $connDbg = ew_Connect();
            if (EW_DEBUG_ENABLED && !empty($connDbg->sqlLog)):
            ?>
            <div class="ewSqlDebug">
                <strong><i class="fa fa-terminal"></i> SQL Debug Log (<?php echo count($connDbg->sqlLog); ?> Queries):</strong>
                <ul style="margin:6px 0 0 0; padding-left:18px;">
                    <?php foreach ($connDbg->sqlLog as $entry): ?>
                        <li>
                            <code><?php echo ew_HtmlEncode($entry['sql']); ?></code>
                            <?php if (!empty($entry['params'])): ?>
                                <span style="color:#00a65a;">Params: <?php echo ew_HtmlEncode(json_encode($entry['params'])); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </section>
        <?php if (defined('EW_UI_TEMPLATE') && EW_UI_TEMPLATE === 'topnav'): ?></div><?php endif; ?>
    </div>

    <!-- Main Footer -->
    <footer class="main-footer">
        <div class="pull-right hidden-xs">
            <b>PHPMacherNG</b> v14 Engine (PHP <?php echo ew_HtmlEncode(PHP_VERSION); ?>)
        </div>
        <?php echo EW_FOOTER_TEXT; ?>
    </footer>
</div>

<!-- Bootstrap 3.3.7 JS (Lokal) -->
<script src="assets/vendor/bootstrap3/js/bootstrap.min.js"></script>
<!-- AdminLTE App (Lokal) -->
<script src="assets/vendor/adminlte/js/adminlte.min.js"></script>
<script>
// PHPMacherNG Client-Side Helpers
function ew_ToggleAllKeys(masterCheckbox) {
    var checkboxes = document.querySelectorAll('input.ewKeyCheckbox');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = masterCheckbox.checked;
        var row = checkboxes[i].closest('tr');
        if (row) {
            if (masterCheckbox.checked) {
                row.classList.add('ewTableRowSelected');
            } else {
                row.classList.remove('ewTableRowSelected');
            }
        }
    }
}

function ew_OnRowCheckboxChange(cb) {
    var row = cb.closest('tr');
    if (row) {
        if (cb.checked) {
            row.classList.add('ewTableRowSelected');
        } else {
            row.classList.remove('ewTableRowSelected');
        }
    }
}

function ew_SubmitMultiAction(formId, targetUrl) {
    var form = document.getElementById(formId);
    if (!form) return;
    var checked = form.querySelectorAll('input.ewKeyCheckbox:checked');
    if (checked.length === 0) {
        alert('Bitte wählen Sie mindestens einen Datensatz aus.');
        return;
    }
    form.action = targetUrl;
    form.method = 'post';
    form.submit();
}

function ew_ToggleExtSearch(btn) {
    var box = document.getElementById('ewExtSearchCollapse');
    if (!box) return;
    var isOpen = box.classList.contains('in') || box.style.display === 'block';
    if (isOpen) {
        box.classList.remove('in');
        box.style.display = 'none';
        if (btn) btn.setAttribute('aria-expanded', 'false');
    } else {
        box.classList.add('in');
        box.style.display = 'block';
        if (btn) btn.setAttribute('aria-expanded', 'true');
        var firstInput = box.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
            try { firstInput.focus(); } catch (e) {}
        }
    }
}

// PHPMacherNG Master/Detail Preview Row Toggle (Aufklappbare Unterliste per AJAX)
function ew_ToggleDetailPreview(btn, rowId, firstUrl) {
    var tr = document.getElementById('ewPreviewRow_' + rowId);
    if (!tr) return;
    var icon = btn.querySelector('i.fa');
    if (tr.style.display === 'none' || tr.style.display === '') {
        tr.style.display = 'table-row';
        if (icon) {
            icon.classList.remove('fa-plus-square-o');
            icon.classList.add('fa-minus-square-o');
        }
        var contentBox = document.getElementById('ewPreviewContent_' + rowId);
        if (contentBox && !contentBox.getAttribute('data-loaded') && firstUrl) {
            ew_LoadDetailPreviewUrl(rowId, firstUrl, null);
        }
    } else {
        tr.style.display = 'none';
        if (icon) {
            icon.classList.remove('fa-minus-square-o');
            icon.classList.add('fa-plus-square-o');
        }
    }
}

function ew_LoadDetailPreviewUrl(rowId, url, tabEl) {
    var contentBox = document.getElementById('ewPreviewContent_' + rowId);
    if (!contentBox) return;
    if (tabEl) {
        var ul = tabEl.closest('ul');
        if (ul) {
            var lis = ul.querySelectorAll('li');
            for (var i = 0; i < lis.length; i++) lis[i].classList.remove('active');
        }
        var li = tabEl.closest('li');
        if (li) li.classList.add('active');
    }
    contentBox.innerHTML = '<div style="padding:12px; color:#666;"><i class="fa fa-spinner fa-spin"></i> Lade Unterliste...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                contentBox.innerHTML = xhr.responseText;
                contentBox.setAttribute('data-loaded', '1');
            } else {
                contentBox.innerHTML = '<div class="text-danger" style="padding:10px;">Fehler beim Laden der Detail-Vorschau (HTTP ' + xhr.status + ').</div>';
            }
        }
    };
    xhr.send();
}

// PHPMacherNG Touch & Mouse HTML5 Signature Pad Engine (für SIGNATURE Felder)
function ew_InitSignaturePads() {
    var widgets = document.querySelectorAll('.ewSignaturePadWidget');
    for (var i = 0; i < widgets.length; i++) {
        (function (widget) {
            if (widget.getAttribute('data-sig-initialized') === '1') return;
            widget.setAttribute('data-sig-initialized', '1');

            var targetId = widget.getAttribute('data-sig-target');
            var hiddenInput = document.getElementById(targetId);
            var canvas = document.getElementById('canvas_' + targetId);
            var statusEl = document.getElementById('sig_status_' + targetId);
            if (!canvas || !hiddenInput) return;

            var ctx = canvas.getContext('2d');
            var isDrawing = false;
            var lastX = 0;
            var lastY = 0;

            function configureBrush() {
                ctx.strokeStyle = '#112244';
                ctx.lineWidth = 2.4;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }
            configureBrush();

            // Falls bereits eine gespeicherte Base64-Unterschrift existiert (z.B. im Edit-Formular), auf Canvas zeichnen
            if (hiddenInput.value && hiddenInput.value.indexOf('data:image/') === 0) {
                var img = new Image();
                img.onload = function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                };
                img.src = hiddenInput.value;
            }

            function getCoords(e) {
                var rect = canvas.getBoundingClientRect();
                var scaleX = canvas.width / (rect.width || canvas.width);
                var scaleY = canvas.height / (rect.height || canvas.height);
                var clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else if (e.changedTouches && e.changedTouches.length > 0) {
                    clientX = e.changedTouches[0].clientX;
                    clientY = e.changedTouches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }
                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            }

            function startDraw(e) {
                if (e.cancelable) e.preventDefault();
                isDrawing = true;
                configureBrush();
                var pos = getCoords(e);
                lastX = pos.x;
                lastY = pos.y;
                ctx.beginPath();
                ctx.arc(lastX, lastY, 1.1, 0, Math.PI * 2, false);
                ctx.fillStyle = '#112244';
                ctx.fill();
            }

            function moveDraw(e) {
                if (!isDrawing) return;
                if (e.cancelable) e.preventDefault();
                var pos = getCoords(e);
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                lastX = pos.x;
                lastY = pos.y;
            }

            function endDraw(e) {
                if (!isDrawing) return;
                if (e && e.cancelable) e.preventDefault();
                isDrawing = false;
                try {
                    hiddenInput.value = canvas.toDataURL('image/png');
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="fa fa-check text-success"></i> Unterschrift erfasst';
                    }
                    // Falls Multi-Update Checkbox vorhanden ist, automatisch aktivieren
                    var fieldName = targetId.replace(/^x_/, '');
                    var multiCb = document.getElementById('u_' + fieldName);
                    if (multiCb) multiCb.checked = true;
                } catch (err) {}
            }

            // Maus-Events
            canvas.addEventListener('mousedown', startDraw, false);
            canvas.addEventListener('mousemove', moveDraw, false);
            window.addEventListener('mouseup', endDraw, false);

            // Touchscreen-Events (Smartphone / Tablet / Stylus)
            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', moveDraw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });
            canvas.addEventListener('touchcancel', endDraw, { passive: false });
        })(widgets[i]);
    }
}

function ew_ClearSignaturePad(targetId) {
    var hiddenInput = document.getElementById(targetId);
    var canvas = document.getElementById('canvas_' + targetId);
    var statusEl = document.getElementById('sig_status_' + targetId);
    if (canvas) {
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
    if (hiddenInput) {
        hiddenInput.value = '';
    }
    if (statusEl) {
        statusEl.innerHTML = 'Unterschrift gelöscht';
    }
}

// ============================================================================
// PHPMacherNG HTML5 Bar & Pie Chart Engine (100% lokal & ohne externe Abhängigkeiten)
// ============================================================================
var EW_CHART_PALETTE = [
    '#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8',
    '#00c0ef', '#d81b60', '#39cccc', '#ff851b', '#001f3f',
    '#2ecc71', '#e74c3c', '#9b59b6', '#34495e', '#16a085'
];

function ew_FormatChartNum(n) {
    var num = Number(n) || 0;
    var isInt = (Math.abs(num - Math.round(num)) < 0.0001);
    var fixed = isInt ? String(Math.round(num)) : num.toFixed(2).replace('.', ',');
    return fixed.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function ew_DrawChartCanvas(canvas) {
    if (!canvas) return;
    var raw = canvas.getAttribute('data-ew-chart');
    if (!raw) return;
    var cfg;
    try {
        cfg = JSON.parse(raw);
    } catch (e) {
        return;
    }
    var labels = cfg.labels || [];
    var values = cfg.values || [];
    var validTypes = ['bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge'];
    var chartType = (cfg.type && validTypes.indexOf(String(cfg.type).toLowerCase()) !== -1)
        ? String(cfg.type).toLowerCase()
        : 'bar';

    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var cssW = Math.max(260, Math.floor(rect.width || (canvas.parentElement ? canvas.parentElement.clientWidth : 500) || 500));
    var cssH = parseInt(canvas.getAttribute('height') || '280', 10) || 280;

    canvas.width = cssW * dpr;
    canvas.height = cssH * dpr;
    canvas.style.height = cssH + 'px';

    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssW, cssH);

    if (labels.length === 0) {
        ctx.fillStyle = '#888';
        ctx.font = '13px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Keine Daten für dieses Diagramm vorhanden.', cssW / 2, cssH / 2);
        return;
    }

    var total = 0;
    var maxVal = 0;
    for (var i = 0; i < values.length; i++) {
        var v = Math.max(0, Number(values[i]) || 0);
        total += v;
        if (v > maxVal) maxVal = v;
    }

    if (chartType === 'pie' || chartType === 'doughnut') {
        // 1. TORTENDIAGRAMM (PIE) & 2. RING-/DONUT-DIAGRAMM (DOUGHNUT) MIT LEGENDE & PROZENTWERTEN
        if (total <= 0) {
            ctx.fillStyle = '#888';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Alle Werte sind 0 – Diagramm kann nicht berechnet werden.', cssW / 2, cssH / 2);
            return;
        }

        var isWide = (cssW >= 460);
        var centerX = isWide ? Math.floor(cssW * 0.34) : Math.floor(cssW * 0.5);
        var centerY = isWide ? Math.floor(cssH * 0.5) : Math.floor(cssH * 0.38);
        var radius = Math.max(45, Math.min(centerX - 24, centerY - 22, 115));

        var startAngle = -Math.PI / 2;
        for (var s = 0; s < values.length; s++) {
            var val = Math.max(0, Number(values[s]) || 0);
            if (val <= 0) continue;
            var sliceAngle = (val / total) * (Math.PI * 2);
            var endAngle = startAngle + sliceAngle;
            var color = EW_CHART_PALETTE[s % EW_CHART_PALETTE.length];

            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = color;
            ctx.fill();
            ctx.lineWidth = 2;
            ctx.strokeStyle = '#ffffff';
            ctx.stroke();

            var pct = (val / total) * 100;
            if (pct >= 6) {
                var midAngle = startAngle + sliceAngle / 2;
                var labelRad = (chartType === 'doughnut') ? (radius * 0.76) : (radius * 0.65);
                var tx = centerX + Math.cos(midAngle) * labelRad;
                var ty = centerY + Math.sin(midAngle) * labelRad;
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 11px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(Math.round(pct) + '%', tx, ty);
            }

            startAngle = endAngle;
        }

        if (chartType === 'doughnut') {
            var innerRadius = radius * 0.54;
            ctx.beginPath();
            ctx.arc(centerX, centerY, innerRadius, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();

            ctx.fillStyle = '#64748b';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText('GESAMT', centerX, centerY - 2);

            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 13px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(ew_FormatChartNum(total), centerX, centerY + 1);
        }

        // Legende rechts daneben (oder darunter bei schmalen Bildschirmen)
        var legX = isWide ? Math.floor(cssW * 0.64) : 16;
        var legY = isWide ? 24 : (centerY + radius + 16);
        var maxLegendItems = Math.min(labels.length, isWide ? 10 : 5);

        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        for (var l = 0; l < maxLegendItems; l++) {
            var lVal = Math.max(0, Number(values[l]) || 0);
            var lPct = total > 0 ? ((lVal / total) * 100).toFixed(1).replace('.', ',') : '0,0';
            var lColor = EW_CHART_PALETTE[l % EW_CHART_PALETTE.length];
            var rowY = legY + l * 22;
            if (rowY > cssH - 10) break;

            ctx.fillStyle = lColor;
            ctx.fillRect(legX, rowY - 6, 12, 12);

            ctx.fillStyle = '#1e293b';
            ctx.font = '12px sans-serif';
            var lblText = String(labels[l]);
            if (lblText.length > 22) lblText = lblText.substring(0, 20) + '…';
            ctx.fillText(lblText + ': ' + ew_FormatChartNum(lVal) + ' (' + lPct + '%)', legX + 18, rowY);
        }
    } else if (chartType === 'hbar') {
        // 3. HORIZONTALES BALKENDIAGRAMM (HBAR)
        var hPadLeft = Math.min(150, Math.max(95, Math.floor(cssW * 0.26)));
        var hPadRight = 64;
        var hPadTop = 18;
        var hPadBottom = 28;
        var hPlotW = Math.max(80, cssW - hPadLeft - hPadRight);
        var hPlotH = Math.max(60, cssH - hPadTop - hPadBottom);
        var hNiceMax = (maxVal > 0 ? maxVal : 10) * 1.12;

        var hSteps = 4;
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        for (var hg = 0; hg <= hSteps; hg++) {
            var hRatio = hg / hSteps;
            var gx = hPadLeft + (hRatio * hPlotW);
            ctx.beginPath();
            ctx.moveTo(gx, hPadTop);
            ctx.lineTo(gx, hPadTop + hPlotH);
            ctx.strokeStyle = (hg === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (hg === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(hNiceMax * hRatio), gx, hPadTop + hPlotH + 6);
        }

        var hCount = labels.length;
        var rowH = hPlotH / hCount;
        var hBarThick = Math.max(8, Math.min(28, rowH * 0.65));

        for (var hi = 0; hi < hCount; hi++) {
            var hVal = Math.max(0, Number(values[hi]) || 0);
            var bw = (hVal / hNiceMax) * hPlotW;
            var byRow = hPadTop + (hi * rowH) + (rowH - hBarThick) / 2;
            var hColor = EW_CHART_PALETTE[hi % EW_CHART_PALETTE.length];

            ctx.fillStyle = hColor;
            ctx.fillRect(hPadLeft, byRow, Math.max(2, bw), hBarThick);

            var yLbl = String(labels[hi]);
            if (yLbl.length > 18) yLbl = yLbl.substring(0, 17) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.fillText(yLbl, hPadLeft - 8, byRow + hBarThick / 2);

            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(ew_FormatChartNum(hVal), hPadLeft + bw + 6, byRow + hBarThick / 2);
        }
    } else if (chartType === 'line' || chartType === 'area') {
        // 4. LINIENDIAGRAMM (LINE) & 5. FLÄCHENDIAGRAMM (AREA)
        var lPadLeft = 58;
        var lPadRight = 24;
        var lPadTop = 26;
        var lPadBottom = 52;
        var lPlotW = Math.max(100, cssW - lPadLeft - lPadRight);
        var lPlotH = Math.max(80, cssH - lPadTop - lPadBottom);
        var lNiceMax = (maxVal > 0 ? maxVal : 10) * 1.15;

        var lSteps = 4;
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (var lg = 0; lg <= lSteps; lg++) {
            var lRatio = lg / lSteps;
            var lgy = lPadTop + lPlotH - (lRatio * lPlotH);
            ctx.beginPath();
            ctx.moveTo(lPadLeft, lgy);
            ctx.lineTo(lPadLeft + lPlotW, lgy);
            ctx.strokeStyle = (lg === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (lg === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(lNiceMax * lRatio), lPadLeft - 8, lgy);
        }

        var lCount = labels.length;
        var pts = [];
        for (var li = 0; li < lCount; li++) {
            var lv = Math.max(0, Number(values[li]) || 0);
            var px = (lCount === 1) ? (lPadLeft + lPlotW / 2) : (lPadLeft + (li / (lCount - 1)) * lPlotW);
            var py = lPadTop + lPlotH - ((lv / lNiceMax) * lPlotH);
            pts.push({ x: px, y: py, val: lv, label: String(labels[li]) });
        }

        if (chartType === 'area' && pts.length > 0) {
            ctx.beginPath();
            ctx.moveTo(pts[0].x, lPadTop + lPlotH);
            for (var ai = 0; ai < pts.length; ai++) {
                ctx.lineTo(pts[ai].x, pts[ai].y);
            }
            ctx.lineTo(pts[pts.length - 1].x, lPadTop + lPlotH);
            ctx.closePath();
            var grad = ctx.createLinearGradient(0, lPadTop, 0, lPadTop + lPlotH);
            grad.addColorStop(0, 'rgba(60, 141, 188, 0.42)');
            grad.addColorStop(1, 'rgba(60, 141, 188, 0.04)');
            ctx.fillStyle = grad;
            ctx.fill();
        }

        if (pts.length > 1) {
            ctx.beginPath();
            ctx.moveTo(pts[0].x, pts[0].y);
            for (var pi = 1; pi < pts.length; pi++) {
                ctx.lineTo(pts[pi].x, pts[pi].y);
            }
            ctx.strokeStyle = '#3c8dbc';
            ctx.lineWidth = 2.5;
            ctx.stroke();
        }

        for (var di = 0; di < pts.length; di++) {
            var pt = pts[di];
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, 4.5, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.lineWidth = 2.5;
            ctx.strokeStyle = '#3c8dbc';
            ctx.stroke();

            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(ew_FormatChartNum(pt.val), pt.x, Math.max(12, pt.y - 7));

            var lxLbl = pt.label;
            var lMaxChars = lCount > 8 ? 9 : 14;
            if (lxLbl.length > lMaxChars) lxLbl = lxLbl.substring(0, lMaxChars - 1) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(lxLbl, pt.x, lPadTop + lPlotH + 8);
        }
    } else if (chartType === 'funnel') {
        // 6. TRICHTERDIAGRAMM (FUNNEL CHART)
        var fPadTop = 16;
        var fPadBottom = 16;
        var fCount = Math.min(labels.length, 10);
        var fPlotH = Math.max(80, cssH - fPadTop - fPadBottom);
        var stageH = fPlotH / fCount;
        var maxStageW = Math.min(cssW * 0.62, cssW - 140);
        var minStageW = Math.max(48, maxStageW * 0.22);
        var centerXFunnel = Math.floor(cssW * 0.42);
        var fMax = maxVal > 0 ? maxVal : 1;

        for (var fi = 0; fi < fCount; fi++) {
            var fVal = Math.max(0, Number(values[fi]) || 0);
            var nextVal = (fi + 1 < fCount) ? Math.max(0, Number(values[fi + 1]) || 0) : (fVal * 0.65);
            var wTop = minStageW + (fVal / fMax) * (maxStageW - minStageW);
            var wBot = minStageW + (nextVal / fMax) * (maxStageW - minStageW);
            var yTop = fPadTop + fi * stageH;
            var yBot = yTop + stageH - 2;

            ctx.beginPath();
            ctx.moveTo(centerXFunnel - wTop / 2, yTop);
            ctx.lineTo(centerXFunnel + wTop / 2, yTop);
            ctx.lineTo(centerXFunnel + wBot / 2, yBot);
            ctx.lineTo(centerXFunnel - wBot / 2, yBot);
            ctx.closePath();
            ctx.fillStyle = EW_CHART_PALETTE[fi % EW_CHART_PALETTE.length];
            ctx.fill();

            var fPct = total > 0 ? Math.round((fVal / total) * 100) : 0;
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(ew_FormatChartNum(fVal) + ' (' + fPct + '%)', centerXFunnel, (yTop + yBot) / 2);

            var fLbl = String(labels[fi]);
            if (fLbl.length > 20) fLbl = fLbl.substring(0, 18) + '…';
            ctx.fillStyle = '#1e293b';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(fLbl, centerXFunnel + maxStageW / 2 + 12, (yTop + yBot) / 2);
        }
    } else if (chartType === 'radar') {
        // 7. NETZ- / RADARDIAGRAMM (SPIDER / RADAR CHART)
        var rCount = Math.min(labels.length, 12);
        var rCx = Math.floor(cssW * 0.5);
        var rCy = Math.floor(cssH * 0.52);
        var rRadius = Math.max(45, Math.min(rCx - 65, rCy - 32, 110));
        var rMax = (maxVal > 0 ? maxVal : 10) * 1.1;

        var rings = 4;
        for (var rg = 1; rg <= rings; rg++) {
            var rr = (rg / rings) * rRadius;
            ctx.beginPath();
            for (var ra = 0; ra < rCount; ra++) {
                var ang = -Math.PI / 2 + (ra / rCount) * (Math.PI * 2);
                var rx = rCx + Math.cos(ang) * rr;
                var ry = rCy + Math.sin(ang) * rr;
                if (ra === 0) ctx.moveTo(rx, ry);
                else ctx.lineTo(rx, ry);
            }
            ctx.closePath();
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1;
            ctx.stroke();
        }

        for (var ax = 0; ax < rCount; ax++) {
            var axAng = -Math.PI / 2 + (ax / rCount) * (Math.PI * 2);
            var axX = rCx + Math.cos(axAng) * rRadius;
            var axY = rCy + Math.sin(axAng) * rRadius;
            ctx.beginPath();
            ctx.moveTo(rCx, rCy);
            ctx.lineTo(axX, axY);
            ctx.strokeStyle = '#cbd5e1';
            ctx.stroke();

            var lblX = rCx + Math.cos(axAng) * (rRadius + 16);
            var lblY = rCy + Math.sin(axAng) * (rRadius + 14);
            var rLbl = String(labels[ax]);
            if (rLbl.length > 14) rLbl = rLbl.substring(0, 13) + '…';
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textAlign = Math.abs(Math.cos(axAng)) < 0.2 ? 'center' : (Math.cos(axAng) > 0 ? 'left' : 'right');
            ctx.textBaseline = 'middle';
            ctx.fillText(rLbl + ' (' + ew_FormatChartNum(values[ax]) + ')', lblX, lblY);
        }

        ctx.beginPath();
        for (var dp = 0; dp < rCount; dp++) {
            var dVal = Math.max(0, Number(values[dp]) || 0);
            var dRad = (dVal / rMax) * rRadius;
            var dAng = -Math.PI / 2 + (dp / rCount) * (Math.PI * 2);
            var dx = rCx + Math.cos(dAng) * dRad;
            var dy = rCy + Math.sin(dAng) * dRad;
            if (dp === 0) ctx.moveTo(dx, dy);
            else ctx.lineTo(dx, dy);
        }
        ctx.closePath();
        ctx.fillStyle = 'rgba(60, 141, 188, 0.30)';
        ctx.fill();
        ctx.strokeStyle = '#3c8dbc';
        ctx.lineWidth = 2;
        ctx.stroke();

        for (var dp2 = 0; dp2 < rCount; dp2++) {
            var dVal2 = Math.max(0, Number(values[dp2]) || 0);
            var dRad2 = (dVal2 / rMax) * rRadius;
            var dAng2 = -Math.PI / 2 + (dp2 / rCount) * (Math.PI * 2);
            ctx.beginPath();
            ctx.arc(rCx + Math.cos(dAng2) * dRad2, rCy + Math.sin(dAng2) * dRad2, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#3c8dbc';
            ctx.fill();
        }
    } else if (chartType === 'gauge') {
        // 8. KPI-TACHO / GAUGE-DIAGRAMM (HALBKREIS-TACHO MIT DURCHSCHNITT, SUMME & TOP-WERT)
        var gCx = Math.floor(cssW * 0.5);
        var gCy = Math.floor(cssH * 0.68);
        var gRadius = Math.max(60, Math.min(gCx - 40, gCy - 28, 125));
        var avgVal = values.length > 0 ? (total / values.length) : 0;
        var gaugeMax = maxVal > 0 ? maxVal : 100;
        var fillRatio = Math.min(1, Math.max(0, avgVal / gaugeMax));

        // Hintergrundbogen
        ctx.beginPath();
        ctx.arc(gCx, gCy, gRadius, Math.PI, Math.PI * 2);
        ctx.lineWidth = 24;
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Farbiger Wertbogen (Durchschnitt im Verhältnis zum Maximalwert)
        var endAng = Math.PI + fillRatio * Math.PI;
        ctx.beginPath();
        ctx.arc(gCx, gCy, gRadius, Math.PI, endAng);
        ctx.lineWidth = 24;
        ctx.strokeStyle = fillRatio > 0.66 ? '#00a65a' : (fillRatio > 0.33 ? '#f39c12' : '#3c8dbc');
        ctx.lineCap = 'round';
        ctx.stroke();
        ctx.lineCap = 'butt';

        // Tachonadel
        var needleAng = Math.PI + fillRatio * Math.PI;
        var nx = gCx + Math.cos(needleAng) * (gRadius - 18);
        var ny = gCy + Math.sin(needleAng) * (gRadius - 18);
        ctx.beginPath();
        ctx.moveTo(gCx, gCy);
        ctx.lineTo(nx, ny);
        ctx.strokeStyle = '#1e293b';
        ctx.lineWidth = 3;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(gCx, gCy, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#1e293b';
        ctx.fill();

        // KPI-Zahlen unterhalb/im Zentrum
        ctx.textAlign = 'center';
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 18px sans-serif';
        ctx.fillText('Ø ' + ew_FormatChartNum(avgVal), gCx, gCy - 20);

        ctx.fillStyle = '#64748b';
        ctx.font = '12px sans-serif';
        ctx.fillText('Gesamtsumme: ' + ew_FormatChartNum(total) + '  |  Max: ' + ew_FormatChartNum(maxVal) + ' (' + String(labels[0] || '') + ')', gCx, gCy + 24);

        ctx.font = '11px sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText('0', gCx - gRadius - 6, gCy + 16);
        ctx.textAlign = 'right';
        ctx.fillText(ew_FormatChartNum(gaugeMax), gCx + gRadius + 10, gCy + 16);
    } else {
        // 9. SÄULENDIAGRAMM (BAR CHART) MIT GITTERLINIEN, ACHSEN & WERTANZEIGE
        var padLeft = 58;
        var padRight = 18;
        var padTop = 26;
        var padBottom = 56;
        var plotW = Math.max(100, cssW - padLeft - padRight);
        var plotH = Math.max(80, cssH - padTop - padBottom);

        var niceMax = (maxVal > 0 ? maxVal : 10) * 1.12;

        // Horizontale Gitterlinien (4 Stufen)
        var steps = 4;
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (var g = 0; g <= steps; g++) {
            var ratio = g / steps;
            var gy = padTop + plotH - (ratio * plotH);
            var gVal = niceMax * ratio;

            ctx.beginPath();
            ctx.moveTo(padLeft, gy);
            ctx.lineTo(padLeft + plotW, gy);
            ctx.strokeStyle = (g === 0) ? '#94a3b8' : '#e2e8f0';
            ctx.lineWidth = (g === 0) ? 1.5 : 1;
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(ew_FormatChartNum(gVal), padLeft - 8, gy);
        }

        // Säulen zeichnen
        var count = labels.length;
        var slotW = plotW / count;
        var barW = Math.max(10, Math.min(58, slotW * 0.62));

        for (var idx = 0; idx < count; idx++) {
            var valBar = Math.max(0, Number(values[idx]) || 0);
            var barH = (valBar / niceMax) * plotH;
            var bx = padLeft + (idx * slotW) + (slotW - barW) / 2;
            var by = padTop + plotH - barH;
            var barColor = EW_CHART_PALETTE[idx % EW_CHART_PALETTE.length];

            ctx.fillStyle = barColor;
            ctx.fillRect(bx, by, barW, Math.max(1, barH));

            // Zahlenwert über der Säule
            ctx.fillStyle = '#1e293b';
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(ew_FormatChartNum(valBar), bx + barW / 2, Math.max(12, by - 4));

            // X-Achsen-Beschriftung
            var xLabel = String(labels[idx]);
            var maxChars = count > 8 ? 10 : 16;
            if (xLabel.length > maxChars) {
                xLabel = xLabel.substring(0, maxChars - 1) + '…';
            }
            ctx.fillStyle = '#334155';
            ctx.font = '11px sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(xLabel, bx + barW / 2, padTop + plotH + 8);
        }
    }
}

function ew_SwitchChartType(canvasId, newType, btnEl) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var raw = canvas.getAttribute('data-ew-chart');
    if (!raw) return;
    try {
        var cfg = JSON.parse(raw);
        var validTypes = ['bar', 'hbar', 'line', 'area', 'pie', 'doughnut', 'funnel', 'radar', 'gauge'];
        var t = String(newType || 'bar').toLowerCase();
        cfg.type = (validTypes.indexOf(t) !== -1) ? t : 'bar';
        canvas.setAttribute('data-ew-chart', JSON.stringify(cfg));
        ew_DrawChartCanvas(canvas);
        if (btnEl && btnEl.parentElement) {
            var btns = btnEl.parentElement.querySelectorAll('button');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.remove('active');
            }
            btnEl.classList.add('active');
        }
    } catch (e) {}
}

function ew_DownloadChartPng(canvasId) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    try {
        var tmp = document.createElement('canvas');
        tmp.width = canvas.width;
        tmp.height = canvas.height;
        var tctx = tmp.getContext('2d');
        tctx.fillStyle = '#ffffff';
        tctx.fillRect(0, 0, tmp.width, tmp.height);
        tctx.drawImage(canvas, 0, 0);
        var link = document.createElement('a');
        link.download = (canvasId || 'chart') + '.png';
        link.href = tmp.toDataURL('image/png');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } catch (e) {}
}

function ew_InitCharts() {
    var canvases = document.querySelectorAll('canvas.ewChartCanvas');
    for (var i = 0; i < canvases.length; i++) {
        ew_DrawChartCanvas(canvases[i]);
    }
}

window.addEventListener('resize', function () {
    ew_InitCharts();
});

document.addEventListener('DOMContentLoaded', function () {
    ew_InitSignaturePads();
    ew_InitCharts();});
</script>
<?php
if (function_exists('Page_Unloaded')) {
    Page_Unloaded();
}
?>
</body>
</html>