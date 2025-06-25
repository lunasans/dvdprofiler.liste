function open2FAModal() {
  fetch('actions/generate_2fa.php')
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        alert(data.error);
        return;
      }

      const codes = data.backup_codes.map(code => `<code>${code}</code>`).join('<br>');
      const html = `
        <p>Scanne den QR-Code mit deiner Authenticator App:</p>
        <img src="${data.qrcode}" alt="QR Code" class="img-fluid mb-3" />
        <p><strong>Geheimcode:</strong> <code>${data.secret}</code></p>
        <hr>
        <p>Backup-Codes (jeweils nur 1x gültig):</p>
        <div style="font-family: monospace;">${codes}</div>
      `;
      document.getElementById('twofa-content').innerHTML = html;

      const modal = new bootstrap.Modal(document.getElementById('twofaModal'));
      modal.show();
    });
}
