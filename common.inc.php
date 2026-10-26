<?php

declare(strict_types=1);

// Set application base constants
define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/config/config.inc.php';

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    throw new RuntimeException('Vendor autoloader not found. Run "composer install".');
}

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Initialize Logger globally or reuse existing handler
global $logger;
if (!isset($logger)) {
    $logger = new Logger('pa_logger');
    $logger->pushHandler(new StreamHandler(APP_ROOT . '/logs/pa_zerozone.log', Level::Debug));
}

const BOT_USER_AGENT = 'Mozilla/5.0 (compatible; SitiWebPA-Bot/2.0; +https://pa.zerozone.it/about; o-zone@zerozone.it)';

// Global Security Headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Singleton Database wrapper using PDO.
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            global $CFG, $logger;

            $host    = $CFG['dbHost'] ?? 'localhost';
            $db      = $CFG['dbDatabase'] ?? '';
            $user    = $CFG['dbUser'] ?? '';
            $pass    = $CFG['dbPass'] ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                if (isset($logger)) {
                    $logger->error('Database connection failure', ['exception' => $e]);
                }
                throw new RuntimeException('Database connection failed. Please check system logs.');
            }
        }

        return self::$instance;
    }
}

/**
 * Entity representation for Public Administration 'Ente'
 */
class Ente
{
    public function __construct(
        public ?string $codiceIpa = null,
        public ?string $denominazioneEnte = null,
        public ?string $tipologia = null,
        public ?string $codiceComuneIstat = null,
        public ?string $cap = null,
        public ?string $indirizzo = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $sitoIstituzionale = null,
        public ?DateTimeImmutable $dataAggiornamento = null,
        public ?DateTimeImmutable $dataControllo = null
    ) {
        if ($this->codiceIpa !== null) {
            $this->load($this->codiceIpa);
        }
    }

    public function load(string $codiceIpa): bool
    {
        $db = Database::getInstance();

        $sql = 'SELECT Denominazione_ente, Tipologia, Codice_comune_ISTAT, CAP, Indirizzo, latitude, longitude, Sito_istituzionale, Data_aggiornamento, Data_controllo
                FROM Enti
                WHERE Codice_IPA = :codice_ipa
                LIMIT 1';

        $sth = $db->prepare($sql);
        $sth->execute(['codice_ipa' => $codiceIpa]);

        $row = $sth->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $this->codiceIpa         = $codiceIpa;
        $this->denominazioneEnte = $row['Denominazione_ente'];
        $this->tipologia         = $row['Tipologia'];
        $this->codiceComuneIstat = $row['Codice_comune_ISTAT'];
        $this->cap               = $row['CAP'];
        $this->indirizzo         = $row['Indirizzo'];
        $this->latitude          = isset($row['latitude']) ? (float)$row['latitude'] : null;
        $this->longitude         = isset($row['longitude']) ? (float)$row['longitude'] : null;
        $this->sitoIstituzionale = $row['Sito_istituzionale'];

        $this->dataAggiornamento = !empty($row['Data_aggiornamento'])
            ? new DateTimeImmutable($row['Data_aggiornamento'])
            : null;

        $this->dataControllo = !empty($row['Data_controllo'])
            ? new DateTimeImmutable($row['Data_controllo'])
            : null;

        return true;
    }

    public function update(): void
    {
        if (!$this->codiceIpa) {
            throw new RuntimeException('Cannot update entity without a valid codiceIpa.');
        }

        $db = Database::getInstance();

        $sql = 'UPDATE Enti SET
                    Denominazione_ente = :denominazione_ente,
                    Tipologia = :tipologia,
                    Codice_comune_ISTAT = :codice_comune_istat,
                    CAP = :cap,
                    Indirizzo = :indirizzo,
                    latitude = :latitude,
                    longitude = :longitude,
                    Sito_istituzionale = :sito_istituzionale,
                    Data_aggiornamento = :data_aggiornamento,
                    Data_controllo = :data_controllo
                WHERE Codice_IPA = :codice_ipa';

        $sth = $db->prepare($sql);
        $sth->execute([
            'codice_ipa'          => $this->codiceIpa,
            'denominazione_ente'  => $this->denominazioneEnte,
            'tipologia'           => $this->tipologia,
            'codice_comune_istat' => $this->codiceComuneIstat,
            'cap'                 => $this->cap,
            'indirizzo'           => $this->indirizzo,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'sito_istituzionale'  => $this->sitoIstituzionale,
            'data_aggiornamento'  => $this->dataAggiornamento?->format('Y-m-d H:i:s'),
            'data_controllo'      => $this->dataControllo?->format('Y-m-d H:i:s'),
        ]);
    }

    public function getScreenshot(): string
    {
        $url = $this->getTag('screenshot');

        if ($url) {
            $safeFileName = basename($url);
            $filePath     = __DIR__ . '/screenshots/' . $safeFileName;

            if (file_exists($filePath)) {
                return '/screenshots/' . $safeFileName;
            }
        }

        return '/screenshots/none.jpg';
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        if (!$this->codiceIpa) {
            return [];
        }

        $db = Database::getInstance();

        $sql = 'SELECT tagName FROM Tags WHERE Codice_IPA = :codice_ipa';
        $sth = $db->prepare($sql);
        $sth->execute(['codice_ipa' => $this->codiceIpa]);

        return $sth->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    public function getTag(string $tagName): ?string
    {
        if (!$this->codiceIpa) {
            return null;
        }

        $db = Database::getInstance();

        $sql = 'SELECT tagValue FROM Tags WHERE Codice_IPA = :codice_ipa AND tagName = :tag_name LIMIT 1';
        $sth = $db->prepare($sql);
        $sth->execute([
            'codice_ipa' => $this->codiceIpa,
            'tag_name'   => $tagName,
        ]);

        $row = $sth->fetch(PDO::FETCH_ASSOC);

        return is_array($row) && isset($row['tagValue']) ? (string)$row['tagValue'] : null;
    }

    public function setTag(string $tagName, ?string $tagValue): void
    {
        if (!$this->codiceIpa) {
            throw new RuntimeException('Cannot set tag without a valid codiceIpa.');
        }

        $db = Database::getInstance();

        if ($tagValue !== null && $tagValue !== '') {
            $sql = 'INSERT INTO Tags (tagName, Codice_IPA, tagValue, addDate)
                    VALUES (:tag_name, :codice_ipa, :tag_value, NOW())
                    ON DUPLICATE KEY UPDATE tagValue = :tag_value_update';

            $sth = $db->prepare($sql);
            $sth->execute([
                'codice_ipa'       => $this->codiceIpa,
                'tag_name'         => $tagName,
                'tag_value'        => $tagValue,
                'tag_value_update' => $tagValue,
            ]);
        } else {
            $this->removeTag($tagName);
        }
    }

    public function removeTag(string $tagName): void
    {
        if (!$this->codiceIpa) {
            return;
        }

        $db = Database::getInstance();

        $sql = 'DELETE FROM Tags WHERE Codice_IPA = :codice_ipa AND tagName = :tag_name';
        $sth = $db->prepare($sql);
        $sth->execute([
            'codice_ipa' => $this->codiceIpa,
            'tag_name'   => $tagName,
        ]);
    }
}
/**
 * Registra un evento di modifica nella tabella Enti_History
 */
function logStateChange(PDO $db, ?string $codiceIpa, string $eventType, ?string $oldValue, ?string $newValue): void
{
    $oldClean = trim((string)$oldValue);
    $newClean = trim((string)$newValue);

    if ($oldClean === $newClean) {
        return;
    }

    $sql = 'INSERT INTO Enti_History (Codice_IPA, event_type, old_value, new_value, created_at)
            VALUES (:codice_ipa, :event_type, :old_value, :new_value, NOW())';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'codice_ipa' => $codiceIpa,
        'event_type' => $eventType,
        'old_value'  => $oldClean !== '' ? $oldClean : null,
        'new_value'  => $newClean !== '' ? $newClean : null,
    ]);
}

/**
 * File Locker utilizing flock
 */
class FileLocker
{
    /** @var array<string, resource> */
    protected static array $locFiles = [];

    /**
     * @return resource|false
     */
    public static function lockFile(string $fileName, bool $wait = false)
    {
        $handle = fopen($fileName, 'c');
        if (!$handle) {
            throw new RuntimeException("Can't open/create lock file: {$fileName}");
        }

        $flags = $wait ? LOCK_EX : (LOCK_EX | LOCK_NB);
        $lock  = flock($handle, $flags);

        if ($lock) {
            self::$locFiles[$fileName] = $handle;
            fprintf($handle, "%s\n", (string)getmypid());
            return $handle;
        }

        fclose($handle);

        if ($wait) {
            throw new RuntimeException("Can't acquire lock on file: {$fileName}");
        }

        return false;
    }

    public static function unlockFile(string $fileName): bool
    {
        if (isset(self::$locFiles[$fileName])) {
            $handle = self::$locFiles[$fileName];
            flock($handle, LOCK_UN);
            fclose($handle);
            @unlink($fileName);
            unset(self::$locFiles[$fileName]);
            return true;
        }

        return false;
    }
}

/***
 *
 *  FUNZIONI DI HELPER
 *
 */

/**
 * Valuta dinamicamente se una versione rilevata è supportata o deprecata/EOL
 *
 * @param PDO $db Istanza del database
 * @param string $tech 'php', 'wordpress', 'drupal', 'joomla'
 * @param string|null $version Versione rilevata (es. '8.1.2', '6.4.3')
 * @return array{status: string, label: string, badge_class: string, icon: string, latest_in_cycle: ?string}
 */
function evaluateSoftwareVersionDynamic(PDO $db, string $tech, ?string $version): array
{
    if (!$version || $version === 'Detected') {
        return [
            'status'          => 'unknown',
            'label'           => 'Non determinata',
            'badge_class'     => 'bg-secondary',
            'icon'            => 'bi-question-circle',
            'latest_in_cycle' => null
        ];
    }

    $tech = strtolower(trim($tech));

    // Carica tutti i cicli noti per questo prodotto
    static $lifecycleCache = [];
    if (!isset($lifecycleCache[$tech])) {
        $stmt = $db->prepare('SELECT cycle, latest_version, eol_date, is_supported FROM Software_Lifecycle WHERE product = :product');
        $stmt->execute(['product' => $tech]);
        $lifecycleCache[$tech] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $productCycles = $lifecycleCache[$tech];
    if (empty($productCycles)) {
        // Fallback generico se la tabella non è ancora popolata
        return [
            'status'          => 'info',
            'label'           => 'Rilevato',
            'badge_class'     => 'bg-info text-dark',
            'icon'            => 'bi-info-circle',
            'latest_in_cycle' => null
        ];
    }

    // Trova il ciclo a cui appartiene la versione (es: 8.1.12 appartiene al ciclo '8.1')
    $matchedCycle = null;
    foreach ($productCycles as $c) {
        $cyclePrefix = $c['cycle'];
        if (str_starts_with($version, $cyclePrefix . '.') || $version === $cyclePrefix) {
            $matchedCycle = $c;
            break;
        }
    }

    // Se il ramo è più vecchio di qualsiasi ciclo attivo censito
    if (!$matchedCycle) {
        return [
            'status'          => 'eol',
            'label'           => 'Obsoleta / EOL',
            'badge_class'     => 'bg-danger',
            'icon'            => 'bi-exclamation-octagon-fill',
            'latest_in_cycle' => null
        ];
    }

    // Se il ramo è in End-of-Life
    if ((int)$matchedCycle['is_supported'] === 0) {
        $eolLabel = $matchedCycle['eol_date'] ? 'EOL dal ' . date('m/Y', strtotime($matchedCycle['eol_date'])) : 'Non supportata';
        return [
            'status'          => 'eol',
            'label'           => 'Obsoleta (' . $eolLabel . ')',
            'badge_class'     => 'bg-danger',
            'icon'            => 'bi-exclamation-triangle-fill',
            'latest_in_cycle' => $matchedCycle['latest_version']
        ];
    }

    // Se il ramo è supportato, controlla se è disponibile una minor release più recente
    $isLatestInBranch = version_compare($version, $matchedCycle['latest_version'], '>=');

    if ($isLatestInBranch) {
        return [
            'status'          => 'supported',
            'label'           => 'Supportata e aggiornata',
            'badge_class'     => 'bg-success',
            'icon'            => 'bi-check-circle-fill',
            'latest_in_cycle' => $matchedCycle['latest_version']
        ];
    }

    return [
        'status'          => 'update_available',
        'label'           => 'Supportata (Aggiornamento disp. ' . $matchedCycle['latest_version'] . ')',
        'badge_class'     => 'bg-warning text-dark',
        'icon'            => 'bi-arrow-up-circle-fill',
        'latest_in_cycle' => $matchedCycle['latest_version']
    ];
}

/**
 * Valuta se la configurazione SPF è adeguata contro lo spoofing
 */
function evaluateSpfAdequacy(bool $hasSpf, string $policy): array
{
    if (!$hasSpf) {
        return [
            'adequate'    => false,
            'label'       => 'Non configurato (Vulnerabile)',
            'badge_class' => 'bg-danger',
            'icon'        => 'bi-x-octagon-fill',
        ];
    }

    // Hardfail (-all) o Softfail (~all) con record presente
    if (str_contains($policy, '-all') || str_contains($policy, '~all')) {
        $isHard = str_contains($policy, '-all');
        return [
            'adequate'    => true,
            'label'       => $isHard ? 'Ottimale (-all)' : 'Accettabile (~all)',
            'badge_class' => $isHard ? 'bg-success' : 'bg-success-subtle text-success-emphasis border border-success',
            'icon'        => 'bi-shield-check',
        ];
    }

    // Se usa +all o ?all non offre alcuna protezione effettiva
    return [
        'adequate'    => false,
        'label'       => 'Inadeguato (' . $policy . ')',
        'badge_class' => 'bg-warning text-dark',
        'icon'        => 'bi-shield-exclamation',
    ];
}

/**
 * Valuta se la policy DMARC protegge attivamente il dominio
 */
function evaluateDmarcAdequacy(bool $hasDmarc, string $policy): array
{
    if (!$hasDmarc) {
        return [
            'adequate'    => false,
            'label'       => 'Assente (Nessuna protezione)',
            'badge_class' => 'bg-danger',
            'icon'        => 'bi-x-octagon-fill',
        ];
    }

    $policy = strtolower(trim($policy));

    return match ($policy) {
        'reject' => [
            'adequate'    => true,
            'label'       => 'Ottimale (p=reject)',
            'badge_class' => 'bg-success',
            'icon'        => 'bi-shield-lock-fill',
        ],
        'quarantine' => [
            'adequate'    => true,
            'label'       => 'Valido (p=quarantine)',
            'badge_class' => 'bg-info text-dark',
            'icon'        => 'bi-shield-shaded',
        ],
        'none' => [
            'adequate'    => false,
            'label'       => 'Solo Monitoraggio (p=none)',
            'badge_class' => 'bg-warning text-dark',
            'icon'        => 'bi-shield-slash',
        ],
        default => [
            'adequate'    => false,
            'label'       => 'Non valida / Incompleta',
            'badge_class' => 'bg-secondary',
            'icon'        => 'bi-question-circle',
        ]
    };
}
