<?php
// determine parent_number from URL pattern esame-<num>.html
$parentNumber = 1;
// DB env vars
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'patente';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASS') ?: 'admin';

// fetch max parent_number from DB and increment
try {
    $dsnInit = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdoInit = new PDO($dsnInit, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $row = $pdoInit->query('SELECT MAX(parent_number) AS max_parent FROM question')->fetch();
    if ($row && $row['max_parent'] !== null) {
        $parentNumber = ((int)$row['max_parent']) + 1;
    }
} catch (Throwable $e) {
    // keep default parentNumber = 1 if DB not reachable
}

$url = "https://www.patentati.it/quiz-patente-b/esame-".$parentNumber.".html";

// POST data
$postFields = "correggi=true&timestamp=&timer=1183&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F&v%5B%5D=F";

// cURL init
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

// headers
$headers = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:142.0) Gecko/20100101 Firefox/142.0",
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language: en-US,en;q=0.5",
    "Content-Type: application/x-www-form-urlencoded",
    "Origin: https://www.patentati.it",
    "Referer: https://www.patentati.it/quiz-patente-b/esame-2.html",
    "Connection: keep-alive",
    "Cookie: __qca=I0-693986242-1756105258717; PHPSESSID=2157e9d17c23d8de570d63e09556f5d2;" // shorten cookie string
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// execute
$response = curl_exec($ch);
curl_close($ch);

// extract the form with DOMDocument + DOMXPath
$dom = new DOMDocument();
libxml_use_internal_errors(true); // ignore malformed HTML
$dom->loadHTML($response);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$form = $xpath->query("//form[@id='schedaData']")->item(0);

if ($form) {
    // collect answers from hidden inputs name="v[]"
    $results = [];
    $inputs = $xpath->query(".//input[@name='v[]']", $form);
    foreach ($inputs as $input) {
        $idAttr = $input->getAttribute('id');
        $valueAttr = $input->getAttribute('value');

        // extract numeric index from id like v1..v30
        $index = null;
        if (preg_match('/^v(\d+)$/', $idAttr, $m)) {
            $index = (int)$m[1];
        }

        // extract the second part after underscore, fallback to last char
        $answer = $valueAttr;
        if (strpos($valueAttr, '_') !== false) {
            $parts = explode('_', $valueAttr);
            $answer = end($parts);
        } else {
            $answer = substr($valueAttr, -1);
        }

        if ($index !== null) {
            $results[$index] = $answer;
        }
    }

    ksort($results);

    // collect question texts by id qc1..qc30
    $questions = [];
    $questionNodes = $xpath->query("//span[@class='quest' and starts-with(@id,'qc')]");
    foreach ($questionNodes as $qnode) {
        $qid = $qnode->getAttribute('id');
        if (preg_match('/^qc(\d+)$/', $qid, $mq)) {
            $qnum = (int)$mq[1];
            $qtext = trim($qnode->textContent);
            $questions[$qnum] = $qtext;
        }
    }

    // collect images by id m1..m30 from .imgArea
    $images = [];
    $imgNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' imgArea ')]//img[starts-with(@id,'m')]");
    foreach ($imgNodes as $img) {
        $mid = $img->getAttribute('id');
        if (preg_match('/^m(\d+)$/', $mid, $mi)) {
            $mnum = (int)$mi[1];
            $src = trim($img->getAttribute('src'));
            if ($src !== '') {
                // normalize to absolute URL
                if (strpos($src, 'http://') !== 0 && strpos($src, 'https://') !== 0) {
                    $src = 'https://www.patentati.it' . ($src[0] === '/' ? '' : '/') . $src;
                }
                $images[$mnum] = $src;
            }
        }
    }



    // save into MySQL database
    $dbOutcome = [
        'saved' => false,
        'inserted' => 0,
        'parent' => $parentNumber,
        'error' => null
    ];

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->beginTransaction();

        if ($parentNumber !== null) {
            $del = $pdo->prepare('DELETE FROM question WHERE parent_number = :parent');
            $del->execute([':parent' => $parentNumber]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO question (text, image, parent_number, question_number, answer) VALUES (:text, :image, :parent, :qnum, :answer)'
        );

        $inserted = 0;
        foreach ($results as $index => $answer) {
            $stmt->execute([
                ':text' => $questions[$index] ?? null,
                ':image' => $images[$index] ?? null,
                ':parent' => $parentNumber,
                ':qnum' => (int)$index,
                ':answer' => $answer,
            ]);
            $inserted++;
        }

        $pdo->commit();
        $dbOutcome['saved'] = true;
        $dbOutcome['inserted'] = $inserted;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $dbOutcome['error'] = $e->getMessage();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'parent' => $parentNumber,
        'answers' => $results,
        'questions' => $questions,
        'images' => $images,
        'db' => $dbOutcome,
    ]);
} else {
    echo "Form not found.";
}
