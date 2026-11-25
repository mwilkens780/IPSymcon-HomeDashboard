<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/TileKit.php';

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
    use \Fuchsbau\TileKit\HomeDashboard\TileKit;

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
        ['name' => 'Anwesend: Person 1',         'variable' => 42942, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Person 2',          'variable' => 26353, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Person 3',          'variable' => 33952, 'script' => 0,     'type' => 'toggle'],
        ['name' => 'Anwesend: Person 4',          'variable' => 56923, 'script' => 0,     'type' => 'toggle'],

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
            $this->tkPush('__all__', $this->collectData());
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
                $data['text']  = self::tkFormatted($varId);
                $data['chart'] = $varId > 0 ? self::tkSparkline(self::tkHistory($this->ReadPropertyInteger('archive_instance'), $varId, self::CHART_SPAN_SEC), self::CHART_SPAN_SEC) : '';
                break;
            case 'bool':
                $value         = $varId > 0 ? (bool) GetValue($varId) : null;
                $data['text']  = self::tkFormatted($varId);
                $data['color'] = $varId > 0 ? self::tkAssocColor($varId) : null;
                $data['icon']  = self::tkTopicIcon($data['name'], $value);
                break;
            case 'toggle':
                $target          = $this->resolveToggleTarget((int) ($tile['variable'] ?? 0));
                $data['checked'] = $target > 0 ? (bool) GetValue($target) : false;
                $data['text']    = self::tkFormatted($target);
                break;
            case 'action':
                // reiner Ausloeser, nichts zu lesen
                break;
            case 'string':
                $data['text'] = self::tkFormatted($varId, '');
                break;
            case 'html':
                $data['html'] = $varId > 0 ? (string) GetValue($varId) : '';
                break;
        }
        return $data;
    }

    // ─── Rendering (TileKit, siehe docs/ui-kachel.md im Workspace) ─────────────

    private function buildDashboardHTML(): string
    {
        $d    = $this->collectData();
        $body = '';
        foreach ($d['tiles'] as $tile) {
            $body .= $this->renderTile($tile);
        }
        $js = <<<'JS'
var lastHtml = {};
(state.tiles || []).forEach(function (t) { if (t.type === 'html') lastHtml[t.ident] = t.html; });
function updateTile(t) {
  if (t.type === 'value') {
    tk.setText(t.ident + '_val', t.text);
    var poly = tk.el(t.ident + '_poly');
    if (poly && t.chart) poly.setAttribute('points', t.chart);
  } else if (t.type === 'bool') {
    tk.setText(t.ident + '_val', t.text);
    tk.setColor(t.ident + '_status', t.color);
    tk.setText(t.ident + '_icon', t.icon || '');
  } else if (t.type === 'toggle') {
    tk.setText(t.ident + '_val', t.text);
    tk.setOn(t.ident + '_card', t.checked);
  } else if (t.type === 'string') {
    tk.setText(t.ident + '_val', t.text);
  } else if (t.type === 'html' && lastHtml[t.ident] !== t.html) {
    // Nur bei geaendertem Inhalt neu laden (sonst flackert das iframe bei jedem Push)
    lastHtml[t.ident] = t.html;
    var f = tk.el(t.ident + '_frame');
    if (f) f.srcdoc = f.srcdoc.replace(/<\/style>[\s\S]*$/, '</style>') + t.html;
  }
}
tk.on('__all__', function (v) { state = v; v.tiles.forEach(updateTile); });
JS;
        return $this->tkPage($this->Translate('Home Dashboard'), '🏠', $d['updated'], "<div class=\"tk-grid\">{$body}</div>", '', $js, json_encode($d));
    }

    private function renderTile(array $tile): string
    {
        $ident = $tile['ident'];
        $name  = $tile['name'];
        switch ($tile['type']) {
            case 'group':
                return self::tkGroup($name);
            case 'value':
                return self::tkValueTile($ident, $name, $tile['text'], $tile['chart']);
            case 'bool':
                return self::tkStatusTile($ident, $name, $tile['text'], $tile['icon'] ?? '', '', $tile['color'] ?? null);
            case 'toggle':
                return self::tkToggleTile($ident, $name, (bool) $tile['checked'], $tile['text']);
            case 'action':
                return self::tkActionTile($ident, $ident . '_action', $name);
            case 'string':
                return self::tkStatusTile($ident, $name, $tile['text']);
            case 'html':
                return self::tkHtmlTile($ident, $name, $tile['html']);
            default:
                return '';
        }
    }
}
