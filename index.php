<?php
// Fetch 30 random questions each page load
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'patente';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASS') ?: 'admin';
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Optional varying seed to ensure different order
$seed = random_int(1, PHP_INT_MAX);
$stmt = $pdo->query("SELECT id, text, image, answer FROM question ORDER BY RAND($seed) LIMIT 30");
$questions = $stmt->fetchAll();
if (!$questions) {
    echo 'No questions available in the database.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patente Quiz</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        .container { max-width: 880px; margin: 0 auto; }
        .question { font-size: 20px; margin: 12px 0 16px; }
        .image { margin: 12px 0; }
        .nav { display: flex; justify-content: space-between; margin: 16px 0; }
        .choices { display: flex; gap: 12px; margin: 16px 0; }
        button { padding: 10px 16px; font-size: 16px; cursor: pointer; }
        .selected { outline: 3px solid #0078D7; }
        .status { color: #666; font-size: 14px; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; border: 1px solid #ddd; margin-left: 8px; font-size: 12px; color: #333; }
        .grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 6px; margin-top: 16px; }
        .grid button { text-align: center; padding: 6px 0; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .grid button.current { background: #eef6ff; border-color: #b6dbff; }
        .grid button.answered { background: #f7f7f7; }
        .grid button.ok { background: #16fa16; border-color: #b9e3b9; }
        .grid button.bad { background: #ff1919; border-color: #f3c2c2; }
        .correct-label { font-size: 14px; color: #333; margin-left: 8px; }
        .hidden { display: none; }
    </style>
</head>
<body>
<div class="container">
    <h2>Patente Quiz</h2>

    <div id="status" class="status"></div>

    <div id="question" class="question"></div>
    <div id="image" class="image"></div>

    <div class="choices">
        <button id="btnTrue" type="button">True (V)</button>
        <button id="btnFalse" type="button">False (F)</button>
        <span id="correctLabel" class="correct-label"></span>
    </div>

    <div class="nav">
        <div>
            <button id="btnPrev" type="button">« Previous</button>
        </div>
        <div>
            <button id="btnNext" type="button">Next »</button>
        </div>
    </div>

    <div id="grid" class="grid"></div>

    <hr>
    <button id="btnSubmit" type="button">Submit Answers</button>
    <button id="btnRestart" type="button" style="margin-left:8px;">New Quiz</button>

    <div id="results" style="margin-top:16px; display:none;">
        <div id="resultsSummary" style="margin-bottom:8px; font-weight:bold;"></div>
        <div id="resultsList"></div>
    </div>
</div>

<script>
const QUESTIONS = <?php echo json_encode($questions, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const NUM = QUESTIONS.length;
let index = 0;
let answers = new Array(NUM).fill(null); // 'V' or 'F'
let submitted = false;

const elStatus = document.getElementById('status');
const elQuestion = document.getElementById('question');
const elImage = document.getElementById('image');
const elBtnTrue = document.getElementById('btnTrue');
const elBtnFalse = document.getElementById('btnFalse');
const elBtnPrev = document.getElementById('btnPrev');
const elBtnNext = document.getElementById('btnNext');
const elGrid = document.getElementById('grid');
const elSubmit = document.getElementById('btnSubmit');
const elRestart = document.getElementById('btnRestart');
const elResults = document.getElementById('results');
const elResultsSummary = document.getElementById('resultsSummary');
const elResultsList = document.getElementById('resultsList');

function render() {
    elStatus.textContent = `Question ${index + 1} / ${NUM}`;
    elQuestion.textContent = (QUESTIONS[index]?.text ?? '');
    const img = QUESTIONS[index]?.image;
    elImage.innerHTML = img ? `<img src="./image/${img}" alt="question image" style="max-width:200px;height:auto;">` : '<img src="./image/no-image.svg" alt="question image" style="max-width:200px;height:auto;">';

    // selection state
    elBtnTrue.classList.toggle('selected', answers[index] === 'V');
    elBtnFalse.classList.toggle('selected', answers[index] === 'F');

    // show correct answer after submit
    const correctAns = String(QUESTIONS[index]?.answer || '').toUpperCase();
    const label = correctAns === 'V' ? '[True]' : correctAns === 'F' ? '[False]' : '';
    const lblEl = document.getElementById('correctLabel');
    if (lblEl) lblEl.textContent = submitted ? `Correct: ${label}` : '';

    elBtnPrev.disabled = index === 0;
    elBtnNext.disabled = index === NUM - 1;

    // grid
    elGrid.innerHTML = '';
    for (let i = 0; i < NUM; i++) {
        const b = document.createElement('button');
        b.textContent = String(i + 1);
        let classes = [];
        if (i === index) classes.push('current');
        if (answers[i]) classes.push('answered');
        if (submitted && answers[i]) {
            const truth = String(QUESTIONS[i].answer || '').toUpperCase();
            classes.push(answers[i].toUpperCase() === truth ? 'ok' : 'bad');
        }
        b.className = classes.join(' ').trim();
        b.onclick = () => { index = i; render(); };
        elGrid.appendChild(b);
    }
}

elBtnTrue.onclick = () => { if (!submitted) { answers[index] = 'V'; if (index < NUM - 1) { index++; } render(); } };
elBtnFalse.onclick = () => { if (!submitted) { answers[index] = 'F'; if (index < NUM - 1) { index++; } render(); } };
elBtnPrev.onclick = () => { if (index > 0) { index--; render(); } };
elBtnNext.onclick = () => { if (index < NUM - 1) { index++; render(); } };

function updateResults() {
    let correct = 0, wrong = 0, unanswered = 0;
    const rows = [];
    for (let i = 0; i < NUM; i++) {
        const a = answers[i];
        const truth = String(QUESTIONS[i].answer || '').toUpperCase();
        const truthLabel = truth === 'V' ? 'True' : truth === 'F' ? 'False' : '-';
        let yourLabel = '- (unanswered)';
        if (a) yourLabel = a.toUpperCase() === 'V' ? 'True' : 'False';
        if (!a) { unanswered++; wrong++; }
        else if (a.toUpperCase() === truth) { correct++; }
        else { wrong++; }
        const icon = !a ? '' : (a.toUpperCase() === truth ? '✅' : '❌');
        rows.push(`<div style="margin:4px 0;">Q${i+1}: Your: <strong>${yourLabel}</strong> | Correct: <strong>${truthLabel}</strong> ${icon}</div>`);
    }
    elResultsSummary.textContent = `Result: Correct ${correct} | Wrong ${wrong} | Unanswered ${unanswered}`;
    elResultsList.innerHTML = rows.join('');
    elResults.style.display = 'block';
}

elSubmit.onclick = () => {
    if (submitted) return;
    submitted = true;
    updateResults();
    render();
};

elRestart.onclick = () => { window.location.href = 'index.php'; };

render();
</script>
</body>
</html>


