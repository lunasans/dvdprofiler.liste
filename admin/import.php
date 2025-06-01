<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>DVD-Import</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; }
        .step { margin-bottom: 0.5rem; }
        .done::before { content: '✓ '; color: green; }
        .pending::before { content: '… '; color: orange; }
        .wait::before { content: '○ '; color: #999; }
        #bar { width: 100%; background: #eee; margin-top: 1rem; border-radius: 4px; }
        #progress { background: #007bff; height: 1.5rem; width: 0%; color: white; text-align: center; transition: width 0.3s; }
    </style>
</head>
<body>

<h1>DVD-Import</h1>
<button onclick="startImport()">Import starten</button>
<button onclick="confirmReset()">Datenbank zurücksetzen & Import starten</button>

<div id="steps">
    <div id="s1" class="step wait">Lade XML</div>
    <div id="s2" class="step wait">Verarbeite Filme</div>
    <div id="s3" class="step wait">Speichere in Datenbank</div>
    <div id="s4" class="step wait">Fertig</div>
</div>

<div id="bar"><div id="progress">0%</div></div>
<pre id="log" style="background:#111; color:#0f0; padding:1rem; height:300px; overflow-y:auto; font-family: monospace;"></pre>

<script src="js/import.js"></script>

<script>
function startImport() {
    const log = document.getElementById('log');
    log.textContent = '';
    setStep(1, 'pending');
    fetch('import-handler.php')
        .then(res => res.text())
        .then(text => {
            log.textContent = text;
            log.scrollTop = log.scrollHeight;
            setStep(1, 'done');
            setStep(2, 'done');
            setStep(3, 'done');
            setStep(4, 'done');
            updateProgress(100);
        });
}

function confirmReset() {
    if (!confirm("Bist du sicher? Alle Filme und Schauspieler werden gelöscht!")) return;
    fetch('reset-db.php')
        .then(res => res.text())
        .then(txt => {
            document.getElementById('log').textContent = txt + '\n\nStarte Import.\n';
            startImport();
        });
}

function setStep(n, status) {
    document.getElementById('s' + n).className = 'step ' + status;
}

function updateProgress(percent) {
    const bar = document.getElementById('progress');
    bar.style.width = percent + '%';
    bar.textContent = percent + '%';
}
</script>

</body>
</html>