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
