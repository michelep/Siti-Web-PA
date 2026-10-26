<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/common.inc.php';

// Safe request parameter extraction
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parameter missing']);
    exit;
}

try {
    $db = Database::getInstance();

    switch ($action) {
        case 'get_stats':
            // 1. Core Summary Metrics
            $entiTotali   = (int)$db->query("SELECT COUNT(*) FROM Enti")->fetchColumn();
            $entiSitiWeb  = (int)$db->query("SELECT COUNT(*) FROM Enti WHERE LENGTH(Sito_istituzionale) > 1")->fetchColumn();
            $entiErrori   = (int)$db->query("SELECT COUNT(DISTINCT Codice_IPA) FROM Tags WHERE tagName IN ('validation_error_website', 'validation_error_email')")->fetchColumn();

            // 2. Top 5 Hosting Providers
            $sqlTopHosting = "SELECT tagValue AS provider, COUNT(*) as total
                              FROM Tags
                              WHERE tagName = 'hosting_provider' AND tagValue IS NOT NULL AND tagValue != ''
                              GROUP BY tagValue
                              ORDER BY total DESC
                              LIMIT 5";
            $topHosting = $db->query($sqlTopHosting)->fetchAll(PDO::FETCH_ASSOC);

            // 3. Top 5 Mail Providers (MX)
            $sqlTopMail = "SELECT tagValue AS provider, COUNT(*) as total
                           FROM Tags
                           WHERE tagName = 'mx_tag' AND tagValue IS NOT NULL AND tagValue != ''
                           GROUP BY tagValue
                           ORDER BY total DESC
                           LIMIT 5";
            $topMail = $db->query($sqlTopMail)->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'enti_totali'   => $entiTotali,
                'enti_siti_web' => $entiSitiWeb,
                'enti_errori'   => $entiErrori,
                'top_hosting'   => $topHosting,
                'top_mail'      => $topMail,
            ]);
            exit;

        case 'get_list':
            $limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
            $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

            $rawSearch  = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
            $searchTerm = mb_strlen($rawSearch) > 2 ? '%' . $rawSearch . '%' : '%';

            // 1. Fetch total count using prepared statement
            $countSql = 'SELECT COUNT(*) FROM Enti WHERE Denominazione_ente LIKE :search';
            $countSth = $db->prepare($countSql);
            $countSth->execute(['search' => $searchTerm]);
            $total = (int)$countSth->fetchColumn();

            // 2. Fetch paginated records using prepared statement
            $dataSql = 'SELECT Codice_IPA, Denominazione_ente, Tipologia, Sito_istituzionale, Data_controllo
                        FROM Enti
                        WHERE Denominazione_ente LIKE :search
                        ORDER BY Data_controllo DESC
                        LIMIT :limit OFFSET :offset';

            $sth = $db->prepare($dataSql);
            $sth->bindValue(':search', $searchTerm, PDO::PARAM_STR);
            $sth->bindValue(':limit', $limit, PDO::PARAM_INT);
            $sth->bindValue(':offset', $offset, PDO::PARAM_INT);
            $sth->execute();

            $rows = [];
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $codIpa = htmlspecialchars((string)$row['Codice_IPA'], ENT_QUOTES, 'UTF-8');

                $lastCheck = '';
                if (!empty($row['Data_controllo'])) {
                    $date = new DateTimeImmutable($row['Data_controllo']);
                    $lastCheck = $date->format('d/m/Y H:i');
                }

                $sitoRaw = (string)($row['Sito_istituzionale'] ?: '');
                $sitoHtml = '&mdash;';
                if ($sitoRaw !== '') {
                    $url = preg_match('#^https?://#i', $sitoRaw) ? $sitoRaw : 'http://' . $sitoRaw;
                    $sitoHtml = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-decoration-none text-break">' . htmlspecialchars($sitoRaw, ENT_QUOTES, 'UTF-8') . '</a>';
                }

                $rows[] = [
                    'codice_ipa'    => '<a href="/details/' . $codIpa . '" class="fw-semibold text-decoration-none">' . $codIpa . '</a>',
                    'denominazione' => htmlspecialchars((string)$row['Denominazione_ente'], ENT_QUOTES, 'UTF-8'),
                    'tipologia'     => htmlspecialchars((string)($row['Tipologia'] ?: 'N/D'), ENT_QUOTES, 'UTF-8'),
                    'sito_web'      => $sitoHtml,
                    'last_check'    => $lastCheck,
                ];
            }

            echo json_encode([
                'total' => $total,
                'rows'  => $rows,
                'debug' => $dataSql,
                'limit' => $limit,
                'offset' => $offset,
            ], JSON_UNESCAPED_SLASHES);
            exit;

        case 'get_errors':
            $limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
            $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
            $rawSearch = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

            $searchTerm = mb_strlen($rawSearch) > 2 ? '%' . $rawSearch . '%' : '%';

            // 1. COUNT: Total distinct entities with error tags
            $countSql = "SELECT COUNT(DISTINCT e.Codice_IPA)
                            FROM Enti e
                            INNER JOIN Tags t ON e.Codice_IPA = t.Codice_IPA
                            WHERE t.tagName IN ('validation_error_website', 'validation_error_email')
                            AND (e.Denominazione_ente LIKE :search1 OR e.Codice_IPA LIKE :search2)";

            $countSth = $db->prepare($countSql);
            $countSth->execute([
                'search1' => $searchTerm,
                'search2' => $searchTerm,
            ]);
            $total = (int)$countSth->fetchColumn();

            // 2. FETCH: Paginate the ENTI table FIRST, then join the tags!
            $dataSql = "SELECT e.Codice_IPA, e.Denominazione_ente, e.Sito_istituzionale,
                                MAX(CASE WHEN t.tagName = 'validation_error_website' THEN t.tagValue END) AS website_err,
                                MAX(CASE WHEN t.tagName = 'validation_error_email' THEN t.tagValue END) AS email_err
                        FROM (
                            SELECT DISTINCT e.Codice_IPA, e.Denominazione_ente, e.Sito_istituzionale
                            FROM Enti e
                            INNER JOIN Tags t ON e.Codice_IPA = t.Codice_IPA
                            WHERE t.tagName IN ('validation_error_website', 'validation_error_email')
                            AND (e.Denominazione_ente LIKE :search1 OR e.Codice_IPA LIKE :search2)
                            ORDER BY e.Denominazione_ente ASC
                            LIMIT :limit OFFSET :offset
                        ) e
                        LEFT JOIN Tags t ON e.Codice_IPA = t.Codice_IPA
                        WHERE t.tagName IN ('validation_error_website', 'validation_error_email')
                        GROUP BY e.Codice_IPA, e.Denominazione_ente, e.Sito_istituzionale
                        ORDER BY e.Denominazione_ente ASC";

            $sth = $db->prepare($dataSql);
            $sth->bindValue(':search1', $searchTerm, PDO::PARAM_STR);
            $sth->bindValue(':search2', $searchTerm, PDO::PARAM_STR);
            $sth->bindValue(':limit', $limit, PDO::PARAM_INT);
            $sth->bindValue(':offset', $offset, PDO::PARAM_INT);
            $sth->execute();

            $rows = [];
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $codIpa = htmlspecialchars((string)$row['Codice_IPA'], ENT_QUOTES, 'UTF-8');

                $badges = [];
                if (!empty($row['website_err'])) {
                    $badges[] = '<span class="badge bg-warning text-dark me-1"><i class="bi bi-globe me-1"></i>Sito: '
                        . htmlspecialchars((string)$row['website_err'], ENT_QUOTES, 'UTF-8') . '</span>';
                }

                if (!empty($row['email_err'])) {
                    $badges[] = '<span class="badge bg-danger me-1"><i class="bi bi-envelope-x me-1"></i>Email: '
                        . htmlspecialchars((string)$row['email_err'], ENT_QUOTES, 'UTF-8') . '</span>';
                }

                $anomaliesHtml = !empty($badges) ? implode(' ', $badges) : '<span class="text-muted small">&mdash;</span>';

                $rows[] = [
                    'codice_ipa'    => $codIpa,
                    'denominazione' => htmlspecialchars((string)$row['Denominazione_ente'], ENT_QUOTES, 'UTF-8'),
                    'sito_web'      => htmlspecialchars((string)($row['Sito_istituzionale'] ?: 'Non specificato'), ENT_QUOTES, 'UTF-8'),
                    'anomalies'     => $anomaliesHtml,
                    'actions'       => '<a href="/details/' . $codIpa . '" class="btn btn-sm btn-outline-primary rounded-pill px-3">Scheda</a>'
                ];
            }

            echo json_encode(['total' => $total, 'rows' => $rows]);
            exit;

        case 'get_map_data':
            header('Content-Type: application/json');

            $sql = "SELECT Codice_IPA, Denominazione_ente, Tipologia, Sito_istituzionale, latitude, longitude
                    FROM Enti
                    WHERE latitude IS NOT NULL AND longitude IS NOT NULL";

            $stmt = $db->query($sql);
            $features = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float)$row['longitude'], (float)$row['latitude']]
                    ],
                    'properties' => [
                        'codice_ipa'    => $row['Codice_IPA'],
                        'denominazione' => htmlspecialchars($row['Denominazione_ente'], ENT_QUOTES, 'UTF-8'),
                        'tipologia'     => $row['Tipologia'] ?: 'Ente Pubblico',
                        'url'           => '/details/' . urlencode($row['Codice_IPA'])
                    ]
                ];
            }

            echo json_encode([
                'type' => 'FeatureCollection',
                'features' => $features
            ], JSON_UNESCAPED_SLASHES);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e]);
    exit;
}
