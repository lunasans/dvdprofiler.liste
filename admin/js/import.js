// ⚠️ Datenbank-Reset mit Bestätigung
function confirmReset() {
    if (!confirm("Bist du sicher? Alle Filme und Schauspieler werden gelöscht!")) return;
    fetch('reset-db.php')
        .then(res => res.text())
        .then(txt => {
            document.getElementById('log').textContent = txt + '\n\nStarte Import.\n';
            startImport();
        });
}

// 📦 Import-Fortschritt anzeigen
function startImport() {
    setStep('s1', 'pending');
    setStep('s2', 'wait');
    setStep('s3', 'wait');
    setStep('s4', 'wait');
    updateProgress(0);
    document.getElementById('log').textContent = '';

    fetch('import-handler.php')
        .then(r => r.body.getReader())
        .then(reader => {
            const decoder = new TextDecoder();
            function read() {
                return reader.read().then(({ done, value }) => {
                    if (done) return;
                    const chunk = decoder.decode(value, { stream: true });
                    const log = document.getElementById('log');
                    log.textContent += chunk;
                    scrollLogToBottom();
                    if (chunk.includes('[STEP1]')) setStep('s1', 'done'), updateProgress(20);
                    if (chunk.includes('[STEP2]')) setStep('s2', 'pending'), updateProgress(40);
                    if (chunk.includes('[STEP2DONE]')) setStep('s2', 'done'), updateProgress(70);
                    if (chunk.includes('[STEP3]')) setStep('s3', 'pending'), updateProgress(85);
                    if (chunk.includes('[DONE]')) {
                        setStep('s3', 'done');
                        setStep('s4', 'done');
                        updateProgress(100);
                    }
                    return read();
                });
            }
            return read();
        });
}

// Fortschrittsbalken + Logging
function scrollLogToBottom() {
    const log = document.getElementById('log');
    log.scrollTop = log.scrollHeight;
}
function setStep(id, status) {
    const el = document.getElementById(id);
    el.className = `step ${status}`;
}
function updateProgress(p) {
    const el = document.getElementById('progress');
    el.style.width = p + '%';
    el.textContent = p + '%';
}