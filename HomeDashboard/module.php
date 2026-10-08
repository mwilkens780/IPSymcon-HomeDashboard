<?php

declare(strict_types=1);

/**
 * Modernes Uebersichts-Dashboard fuer die Symcon-Hauptseite. Buendelt eine
 * frei konfigurierbare Liste von Root-Variablen/-Instanzen (die aktuell als
 * einzelne, von Symcon automatisch generierte Kacheln auf der Startseite
 * liegen -- POWER, Wetterwerte, Alarm-Status, Lampen-Schalter, Ferien,
 * Warnmeldungen ...) als einheitliche, moderne Kachel-Grafik.
 *
 * Bewusst keine eigene Alarm-/Energie-/Wetterlogik -- reine Anzeige/Schalt-
 * Huelle um bereits vorhandene Variablen, analog zum Statuspunkte-Muster im
 * Alarm Dashboard. Anzeige-Text und -Farbe bei "Wert"/"Ja-Nein" kommen
 * automatisch aus dem Variablenprofil (GetValueFormatted()/Assoziations-
 * farbe), damit derselbe Text wie in den bisherigen Symcon-Auto-Kacheln
 * erscheint, ohne pro Kachel Texte manuell zu pflegen.
 */
class HomeDashboard extends IPSModule
{
    private const CHART_SPAN_SEC = 6 * 3600; // 6h, wie Weather/Energy/Heating Dashboard

    /** Bekannte "Lampen"-Modultypen und der Ident ihrer Ein/Aus-Variable, fuer den Schalter-Typ (siehe RoomDashboard::LIGHT_MODULE_IDENTS). */
    private const LIGHT_MODULE_IDENTS = [
        '{87FA14D1-0ACA-4CBD-BE83-BA4DF8831876}' => 'on', // HUE Light
        '{6324AC4A-330C-4CB2-9281-12EECB450024}' => 'on', // HUE Grouped Light
        '{BFF4858B-78B1-B4AD-B755-24AEC44EACFF}' => 'State', // Govee Device
    ];

    /**
     * Vorbelegung mit den auf dieser Installation tatsaechlich vorhandenen
     * Root-Objekten (per Live-Objektbaum ermittelt, nicht geraten) -- damit
     * die Kachel sofort nutzbar ist statt mit einer leeren Liste zu starten.
     * Der Nutzer kann Zeilen jederzeit entfernen/ergaenzen.
     */
    private const DEFAULT_TILES = [
        ['name' => 'Anwesenheit',              'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Zuhause (alle)',           'variable' => 15106, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Martin',         'variable' => 42942, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Lydia',          'variable' => 26353, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Lukas',          'variable' => 33952, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Elina',          'variable' => 56923, 'script' => 0,     'type' => 'toggle'],

        ['name' => 'Steuerung',                'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Erdgeschoss Licht',        'variable' => 44101, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Obergeschoss Licht',       'variable' => 34031, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Bewässerung',              'variable' => 0,     'script' => 19701, 'type' => 'action'],

        ['name' => 'Energie',                  'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Stromverbrauch',           'variable' => 14487, 'script' => 0,     'type' => 'value'],
        ['name' => 'Leistung Solar',           'variable' => 22328, 'script' => 0,     'type' => 'value'],

        ['name' => 'Wetter',                   'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Außentemperatur',          'variable' => 30589, 'script' => 0,     'type' => 'value'],
        ['name' => 'Regen heute',              'variable' => 42136, 'script' => 0,     'type' => 'value'],
        ['name' => 'Sonnenschein heute',       'variable' => 48955, 'script' => 0,     'type' => 'value'],
        ['name' => 'Aktueller Niederschlag',   'variable' => 10262, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Aktueller Sonnenschein',   'variable' => 22740, 'script' => 0,     'type' => 'bool'],

        ['name' => 'Alarme',                   'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Batterie-Alarm',           'variable' => 51665, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Feuer-Alarm',              'variable' => 51344, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Geräte-Status',            'variable' => 46955, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Alarm Feuer (CCU)',        'variable' => 26043, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Alarm Wasser (CCU)',       'variable' => 42541, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Alarm Batterie (CCU)',     'variable' => 49719, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Fensteröffnung unten',     'variable' => 45202, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Fensteröffnung oben',      'variable' => 58303, 'script' => 0,     'type' => 'bool'],
        ['name' => 'Wasser-Alarm',             'variable' => 51828, 'script' => 0,     'type' => 'bool'],

        ['name' => 'Sonstiges',                'variable' => 0,     'script' => 0,     'type' => 'group'],
        ['name' => 'Ferien',                   'variable' => 18598, 'script' => 0,     'type' => 'string'],
        ['name' => 'Warnmeldungen',            'variable' => 28250, 'script' => 0,     'type' => 'html'],
    ];

    // ─── Lifecycle ──────────────────────────────────────────────────────────────

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('tiles', json_encode(self::DEFAULT_TILES));
        // Gleiche Archiv-Instanz wie Weather/Energy/Heating Dashboard auf dieser Installation.
        $this->RegisterPropertyInteger('archive_instance', 59233);
        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterTimer('UpdateTimer', 0, 'HMD_Refresh($_IPS[\'TARGET\']);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $tiles = json_decode($this->ReadPropertyString('tiles'), true) ?: [];
        if (count($tiles) === 0) {
            $this->SetStatus(201);
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $interval = $this->ReadPropertyInteger('update_interval');
        $this->SetTimerInterval('UpdateTimer', $interval > 0 ? $interval * 1000 : 0);
        $this->SetStatus(102);
        $this->Refresh();
    }

    public function GetVisualizationTile(): string
    {
        return $this->buildDashboardHTML();
    }

    public function Refresh(): void
    {
        try {
            $this->pushValue('__all__', $this->collectData());
            $this->SetStatus(102);
        } catch (\Throwable $e) {
            $this->LogMessage('HomeDashboard Refresh: ' . $e->getMessage(), KL_ERROR);
            $this->SetStatus(200);
        }
    }

    // ─── IPS action handler ─────────────────────────────────────────────────────

    public function RequestAction($Ident, $Value): void
    {
        try {
            if (preg_match('/^tile_(\d+)_toggle$/', (string) $Ident, $m)) {
                $this->forwardToggle((int) $m[1], (bool) $Value);
                return;
            }
            if (preg_match('/^tile_(\d+)_action$/', (string) $Ident, $m)) {
                $this->forwardAction((int) $m[1]);
                return;
            }
            $this->LogMessage("HomeDashboard RequestAction: unknown ident {$Ident}", KL_WARNING);
        } catch (\Throwable $e) {
            $this->LogMessage('HomeDashboard RequestAction ' . $Ident . ': ' . $e->getMessage(), KL_ERROR);
        }
    }

    /**
     * Schaltet die Zielvariable. Lampen/Geraete haben normalerweise eine
     * registrierte Action (RequestAction greift). Reine Status-/virtuelle
     * Variablen ohne Action (z.B. Anwesenheits-Flags, die sonst nur von
     * einem Skript per SetValue gesetzt werden) haben das nicht -- dafuer
     * direkt den Wert schreiben, analog zur HM_WriteValue*-Erkenntnis im
     * Room Dashboard: SetValueBoolean ist ein reiner Kernel-Write ohne
     * Action-Pruefung und somit fuer virtuelle Variablen immer gueltig.
     */
    private function forwardToggle(int $index, bool $value): void
    {
        $tiles  = json_decode($this->ReadPropertyString('tiles'), true) ?: [];
        $nodeId = (int) ($tiles[$index]['variable'] ?? 0);
        $target = $this->resolveToggleTarget($nodeId);
        if ($target <= 0) {
            return;
        }
        $variable = @IPS_GetVariable($target);
        if ($variable !== false && (int) $variable['VariableAction'] > 0) {
            RequestAction($target, $value);
            return;
        }
        SetValueBoolean($target, $value);
    }

    private function forwardAction(int $index): void
    {
        $tiles    = json_decode($this->ReadPropertyString('tiles'), true) ?: [];
        $scriptId = $this->resolveScriptId((int) ($tiles[$index]['script'] ?? 0));
        if ($scriptId <= 0) {
            return;
        }
        @IPS_RunScriptEx($scriptId, []);
    }

    /**
     * Akzeptiert fuer den Schalter-Typ entweder direkt eine Lampeninstanz
     * (Hue/Govee -- Ein/Aus-Ident wird ueber die Modul-ID erkannt, siehe
     * RoomDashboard), eine rohe Boolean-Variable, oder ein Link-Objekt auf
     * eines der beiden (Links werden rekursiv aufgeloest, da IPS_GetVariable/
     * IPS_GetInstance anders als GetValue/RequestAction nicht transparent
     * durch Links hindurchgreifen).
     */
    private function resolveToggleTarget(int $nodeId): int
    {
        if ($nodeId <= 0) {
            return 0;
        }
        if (@IPS_InstanceExists($nodeId)) {
            $moduleId = IPS_GetInstance($nodeId)['ModuleInfo']['ModuleID'] ?? '';
            $ident    = self::LIGHT_MODULE_IDENTS[$moduleId] ?? 'STATE';
            $id       = @IPS_GetObjectIDByIdent($ident, $nodeId);
            return $id ?: 0;
        }
        if (@IPS_LinkExists($nodeId)) {
            return $this->resolveToggleTarget((int) IPS_GetLink($nodeId)['TargetID']);
        }
        return @IPS_VariableExists($nodeId) ? $nodeId : 0;
    }

    /** Loest ein Link-Objekt (z.B. der Root-Link auf einen Bewaesserungs-Flow-Script) rekursiv zur echten Skript-ID auf. */
    private function resolveScriptId(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        if (@IPS_LinkExists($id)) {
            return $this->resolveScriptId((int) IPS_GetLink($id)['TargetID']);
        }
        return @IPS_ScriptExists($id) ? $id : 0;
    }

    /** Root-Link-Objekte auf Variablen sind fuer GetValue/GetValueFormatted transparent, aber NICHT fuer IPS_GetVariable() (Profil-/Farb-Lookup) -- deshalb hier einmal zentral aufloesen. */
    private function resolveVariableId(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        if (@IPS_LinkExists($id)) {
            return $this->resolveVariableId((int) IPS_GetLink($id)['TargetID']);
        }
        return @IPS_VariableExists($id) ? $id : 0;
    }

    // ─── Data collection ──────────────────────────────────────────────────────

    private function collectData(): array
    {
        $tiles = json_decode($this->ReadPropertyString('tiles'), true) ?: [];
        $out   = [];
        foreach ($tiles as $i => $tile) {
            $out[] = $this->collectTile($i, $tile);
        }
        return ['tiles' => $out, 'updated' => date('d.m. H:i')];
    }

    private function collectTile(int $index, array $tile): array
    {
        $type  = $tile['type'] ?? 'value';
        $varId = $this->resolveVariableId((int) ($tile['variable'] ?? 0));
        $ident = 'tile_' . $index;

        $data = [
            'ident' => $ident,
            'name'  => (string) ($tile['name'] ?? ''),
            'type'  => $type,
        ];

        switch ($type) {
            case 'value':
                $data['text']  = $varId > 0 ? $this->formattedValue($varId) : '–';
                $data['chart'] = $varId > 0 ? $this->chartSvgPoints($varId) : '';
                break;
            case 'bool':
                $value         = $varId > 0 ? (bool) GetValue($varId) : null;
                $data['text']  = $varId > 0 ? $this->formattedValue($varId) : '–';
                $data['color'] = $varId > 0 ? $this->associationColor($varId) : null;
                $data['icon']  = $this->topicIcon($data['name'], $value);
                break;
            case 'toggle':
                $target          = $this->resolveToggleTarget((int) ($tile['variable'] ?? 0));
                $data['checked'] = $target > 0 ? (bool) GetValue($target) : false;
                $data['text']    = $target > 0 ? $this->formattedValue($target) : '–';
                break;
            case 'action':
                // reiner Ausloeser, nichts zu lesen
                break;
            case 'string':
                $data['text'] = $varId > 0 ? $this->formattedValue($varId) : '';
                break;
            case 'html':
                $data['html'] = $varId > 0 ? $this->darkThemeWrap((string) GetValue($varId)) : '';
                break;
        }
        return $data;
    }

    private function formattedValue(int $varId): string
    {
        $formatted = @GetValueFormatted($varId);
        if ($formatted !== false && $formatted !== null && $formatted !== '') {
            return (string) $formatted;
        }
        $raw = @GetValue($varId);
        if ($raw === false) {
            return '–';
        }
        if (is_bool($raw)) {
            return $raw ? 'Ja' : 'Nein';
        }
        return (string) $raw;
    }

    /**
     * Dezentes Themen-Piktogramm fuer Ja/Nein-Kacheln, per Namens-Stichwort
     * erkannt (kein eigenes Konfigurationsfeld noetig, funktioniert also
     * auch fuer bereits bestehende Kacheln sofort) -- zwei Symbole je Thema,
     * passend zum aktuellen Wahrheitswert. '' wenn kein Thema erkannt oder
     * der Wert unbekannt ist.
     */
    private function topicIcon(string $name, ?bool $value): string
    {
        if ($value === null) {
            return '';
        }
        $n = mb_strtolower($name);
        if (str_contains($n, 'sonnenschein')) {
            return $value ? '☀️' : '☁️';
        }
        if (str_contains($n, 'niederschlag') || (str_contains($n, 'regen') && !str_contains($n, 'wasser'))) {
            return $value ? '🌧️' : '⛅';
        }
        if (str_contains($n, 'fenster')) {
            return $value ? '🔓' : '🔒';
        }
        if (str_contains($n, 'alarm')) {
            return $value ? '🚨' : '🔕';
        }
        return '';
    }

    /** Liest die Assoziationsfarbe des aktuellen Werts aus dem Variablenprofil (z.B. rot fuer "Alarm"), null wenn kein passendes/gefaerbtes Profil existiert. */
    private function associationColor(int $varId): ?string
    {
        $var = @IPS_GetVariable($varId);
        if ($var === false) {
            return null;
        }
        $profileName = $var['VariableCustomProfile'] !== '' ? $var['VariableCustomProfile'] : $var['VariableProfile'];
        if ($profileName === '' || !@IPS_VariableProfileExists($profileName)) {
            return null;
        }
        $profile = IPS_GetVariableProfile($profileName);
        $value   = GetValue($varId);
        foreach ($profile['Associations'] as $assoc) {
            if ((string) $assoc['Value'] === (string) $value && (int) $assoc['Color'] >= 0) {
                return sprintf('#%06X', (int) $assoc['Color']);
            }
        }
        return null;
    }

    /** Fremde HTML-Inhalte (z.B. die Unwetterwarnung-Tabelle) bekommen ein Dark-Theme-Overlay, damit kein weißer/schwarzer Fremdkoerper in der Kachel erscheint. */
    private function darkThemeWrap(string $rawHtml): string
    {
        return '<style>html,body{background:#0d1b2a !important;color:#d0e8ff !important;margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:12px}'
            . 'a{color:#7ec8f0 !important} table{color:#d0e8ff !important;width:100%}</style>' . $rawHtml;
    }

    /** [[unixTimestamp, value], ...], aeltester zuerst -- leer wenn nicht archiviert. */
    private function history(int $varId): array
    {
        $archiveID = $this->ReadPropertyInteger('archive_instance');
        if ($archiveID <= 0 || $varId <= 0 || !function_exists('AC_GetLoggedValues')) {
            return [];
        }
        $now = time();
        try {
            $rows = @AC_GetLoggedValues($archiveID, $varId, $now - self::CHART_SPAN_SEC, $now, 0);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($rows) || empty($rows)) {
            return [];
        }
        $points = [];
        foreach (array_reverse($rows) as $row) { // AC_GetLoggedValues liefert neueste zuerst
            $points[] = [(int) $row['TimeStamp'], (float) $row['Value']];
        }
        return $points;
    }

    /** SVG-Polyline-Punkte, skaliert in eine 300x60-Box; '' wenn zu wenig Historie. */
    private function chartSvgPoints(int $varId): string
    {
        $history = $this->history($varId);
        if (count($history) < 2) {
            return '';
        }
        $viewW   = 300.0;
        $viewH   = 60.0;
        $startTs = time() - self::CHART_SPAN_SEC;
        $values  = array_column($history, 1);
        $min     = min($values);
        $max     = max($values);
        if ($max - $min < 0.01) {
            $mid = ($max + $min) / 2;
            $min = $mid - 0.5;
            $max = $mid + 0.5;
        }
        $pad = ($max - $min) * 0.15;
        $min -= $pad;
        $max += $pad;

        $pts = [];
        foreach ($history as [$ts, $val]) {
            $x     = max(0, min($viewW, ($ts - $startTs) / self::CHART_SPAN_SEC * $viewW));
            $y     = $viewH - (($val - $min) / ($max - $min)) * $viewH;
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        return implode(' ', $pts);
    }

    private function pushValue(string $key, $value): void
    {
        $this->UpdateVisualizationValue(json_encode(['key' => $key, 'value' => $value]));
    }

    // ─── Rendering ──────────────────────────────────────────────────────────────

    private function buildDashboardHTML(): string
    {
        $d = $this->collectData();

        $tilesHtml = '';
        foreach ($d['tiles'] as $tile) {
            $tilesHtml .= $this->renderTile($tile);
        }

        $updatedEsc = htmlspecialchars($d['updated'], ENT_QUOTES);
        $initJson   = json_encode($d);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
html{height:100%}
*{box-sizing:border-box;margin:0;padding:0}
body{overflow-y:auto;overflow-x:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:13px;background:#0d1b2a;color:#d0e8ff;display:flex;flex-direction:column;padding:8px;gap:8px}
.header{display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:13px;font-weight:600;border-bottom:1px solid #1e3a5f;padding-bottom:5px;flex:none}
.updated{font-size:10px;color:#3a5a7a;font-weight:400}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:7px;align-items:stretch}
.group-header{grid-column:1 / -1;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#7ec8f0;border-bottom:1px solid #1e3a5f;padding:4px 2px 3px;margin-top:4px}
.group-header:first-child{margin-top:0}
.tile{background:#131f33;border-radius:10px;padding:8px 10px;display:flex;flex-direction:column;justify-content:center;gap:4px;min-height:0}
.tile-name{font-size:10.5px;color:#8aa8c8;white-space:normal;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-height:1.25;overflow-wrap:anywhere}
.tile-value{font-size:19px;font-weight:600;color:#d0e8ff;line-height:1.15}
.tile-chart{margin-top:1px}
.tile-chart svg{width:100%;height:24px;display:block}
.tile-row{display:flex;align-items:center;justify-content:space-between;gap:6px}
.tile-text{font-size:14px;color:#d0e8ff}
/* Kompakte Kacheln fuer Status/Text (Bool/String) -- keine verschachtelte Box, Farbe direkt am Text.
   Name oben (bis zu 2 Zeilen), Status darunter -- nicht nebeneinander, damit lange Werte wie
   "Nicht ausgelöst" nie aus der Kachel herauslaufen. */
.tile-compact{padding:9px 12px;gap:2px}
.tile-compact .tile-name{font-size:11.5px}
.tile-status{font-size:13px;font-weight:600;color:#8aa8c8;overflow-wrap:anywhere}
/* Klickbare Kacheln (Schalter/Ausloeser): die ganze Flaeche ist der Button, kein eingebetteter Zweit-Button. */
.tile.clickable{cursor:pointer;-webkit-tap-highlight-color:transparent;user-select:none;transition:background-color .15s,transform .08s;border:1px solid #1e3a5f}
.tile.clickable:active{transform:scale(.97)}
.tile.toggle-tile{padding:10px 12px;min-height:58px}
.tile.toggle-tile .tile-row{margin-top:auto}
.tile.toggle-tile .tile-icon{font-size:15px;color:#4a6a8a;flex:none;transition:color .15s}
.tile.toggle-tile.on{background:#12405a;border-color:#2a7aa0}
.tile.toggle-tile.on .tile-icon{color:#7ec8f0}
.tile.toggle-tile.on .tile-status{color:#d0e8ff}
.tile.action-tile{align-items:center;justify-content:center;text-align:center;gap:6px;background:#12405a;border-color:#2a7aa0;min-height:72px}
.tile.action-tile .tile-icon{font-size:22px;color:#7ec8f0}
.tile.action-tile .tile-name{font-size:11px;color:#9fc8e8;-webkit-line-clamp:2}
.tile.action-tile:active{background:#17547a}
.tile-html{grid-column:1 / -1;background:#131f33;border-radius:10px;padding:8px;display:flex;flex-direction:column;gap:4px}
.tile-html iframe{width:100%;border:0;min-height:100px;border-radius:6px}
.topic-icon{font-size:12px;margin-right:4px;opacity:.85;filter:grayscale(.15)}
</style>
</head>
<body>
<div class="header">
  <span>🏠 {$this->Translate('Home Dashboard')}</span>
  <span id="updated" class="updated">{$this->Translate('Stand')} {$updatedEsc}</span>
</div>
<div class="grid">
{$tilesHtml}
</div>
<script>
var state = {$initJson};

function setText(id, text) {
  var el = document.getElementById(id);
  if (el) el.textContent = text;
}

function updateTile(t) {
  if (t.type === 'value' || t.type === 'bool' || t.type === 'string' || t.type === 'toggle') {
    setText(t.ident + '_val', t.text);
  }
  if (t.type === 'value') {
    var poly = document.getElementById(t.ident + '_poly');
    if (poly && t.chart) poly.setAttribute('points', t.chart);
  }
  if (t.type === 'bool') {
    var statusEl = document.getElementById(t.ident + '_status');
    if (statusEl) statusEl.style.color = t.color || '#8aa8c8';
    setText(t.ident + '_icon', t.icon || '');
  }
  if (t.type === 'toggle') {
    var input = document.getElementById(t.ident + '_input');
    if (input) input.checked = t.checked;
    var card = document.getElementById(t.ident + '_card');
    if (card) card.classList.toggle('on', !!t.checked);
  }
}

function toggleTile(ident) {
  var input = document.getElementById(ident + '_input');
  var card = document.getElementById(ident + '_card');
  var next = input ? !input.checked : true;
  if (input) input.checked = next;
  if (card) card.classList.toggle('on', next);
  requestAction(ident + '_toggle', next);
}

window.handleMessage = function(raw) {
  var msg = JSON.parse(raw);
  if (msg.key !== '__all__') return;
  var val = msg.value;
  state = val;
  setText('updated', val.updated);
  for (var i = 0; i < val.tiles.length; i++) {
    updateTile(val.tiles[i]);
  }
};
</script>
</body>
</html>
HTML;
    }

    private function renderTile(array $tile): string
    {
        $ident   = $tile['ident'];
        $nameEsc = htmlspecialchars($tile['name'], ENT_QUOTES);
        $type    = $tile['type'];

        switch ($type) {
            case 'group':
                return "<div class=\"group-header\">{$nameEsc}</div>";

            case 'value':
                $textEsc = htmlspecialchars($tile['text'], ENT_QUOTES);
                $chart   = $tile['chart'] !== ''
                    ? "<div class=\"tile-chart\"><svg viewBox=\"0 0 300 60\" preserveAspectRatio=\"none\"><polyline id='{$ident}_poly' points=\"{$tile['chart']}\" fill=\"none\" stroke=\"#7ec8f0\" stroke-width=\"2\"/></svg></div>"
                    : '';
                return "<div class=\"tile\"><div class=\"tile-name\">{$nameEsc}</div><div id='{$ident}_val' class=\"tile-value\">{$textEsc}</div>{$chart}</div>";

            case 'bool':
                $textEsc = htmlspecialchars($tile['text'], ENT_QUOTES);
                $color   = $tile['color'] ?? '#8aa8c8';
                $iconEsc = htmlspecialchars($tile['icon'] ?? '', ENT_QUOTES);
                return "<div class=\"tile tile-compact\"><span class=\"tile-name\">{$nameEsc}</span><span id='{$ident}_status' class=\"tile-status\" style=\"color:{$color}\"><span id='{$ident}_icon' class=\"topic-icon\">{$iconEsc}</span><span id='{$ident}_val'>{$textEsc}</span></span></div>";

            case 'toggle':
                $textEsc  = htmlspecialchars($tile['text'], ENT_QUOTES);
                $checked  = $tile['checked'] ? ' checked' : '';
                $onClass  = $tile['checked'] ? ' on' : '';
                return <<<HTML
<div class="tile clickable toggle-tile{$onClass}" id="{$ident}_card" onclick="toggleTile('{$ident}')">
  <span class="tile-name">{$nameEsc}</span>
  <span class="tile-row" style="gap:8px">
    <span id='{$ident}_val' class="tile-status">{$textEsc}</span>
    <span class="tile-icon">⏻</span>
  </span>
  <input id='{$ident}_input' type="checkbox"{$checked} style="display:none">
</div>
HTML;

            case 'action':
                return "<div class=\"tile clickable action-tile\" onclick=\"requestAction('{$ident}_action', 1)\"><span class=\"tile-icon\">▶</span><span class=\"tile-name\">{$nameEsc}</span></div>";

            case 'string':
                $textEsc = htmlspecialchars($tile['text'], ENT_QUOTES);
                return "<div class=\"tile tile-compact\"><span class=\"tile-name\">{$nameEsc}</span><span id='{$ident}_val' class=\"tile-status\">{$textEsc}</span></div>";

            case 'html':
                $srcDoc = htmlspecialchars($tile['html'], ENT_QUOTES);
                return "<div class=\"tile-html\"><div class=\"tile-name\">{$nameEsc}</div><iframe srcdoc=\"{$srcDoc}\"></iframe></div>";

            default:
                return '';
        }
    }
}
