document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.nav-link-ajax').forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      const page = this.dataset.page;

      fetch(page)
        .then(res => res.text())
        .then(html => {
          document.getElementById('admin-content').innerHTML = html;
        })
        .catch(err => {
          document.getElementById('admin-content').innerHTML = '<div class="alert alert-danger">Fehler beim Laden!</div>';
          console.error(err);
        });
    });
  });
});

// admin.js

document.addEventListener('DOMContentLoaded', () => {
  loadUsers();
  setupUserForm();
});

// Benutzer laden
function loadUsers() {
  fetch('users.php?ajax=1')
    .then(res => res.text())
    .then(html => {
      document.getElementById('userTable').innerHTML = html;
      setupEditButtons();
      setupDeleteButtons();
    });
}

// Benutzer bearbeiten vorbereiten
function setupEditButtons() {
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      const tr = btn.closest('tr');
      document.getElementById('user-id').value = tr.dataset.id;
      document.getElementById('email').value = tr.dataset.email;
      document.getElementById('password').value = '';
      document.getElementById('form-title').textContent = 'Benutzer bearbeiten';
    });
  });
}

// Benutzer löschen
function setupDeleteButtons() {
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.id;
      if (confirm('Benutzer wirklich löschen?')) {
        fetch(`delete_user.php?id=${id}`)
          .then(res => res.text())
          .then(() => loadUsers());
      }
    });
  });
}

// Benutzer speichern (Neu oder Bearbeiten)
function setupUserForm() {
  const form = document.getElementById('user-form');
  if (!form) return;

  form.addEventListener('submit', e => {
    e.preventDefault();
    const data = new FormData(form);
    fetch('update_user.php', {
      method: 'POST',
      body: data
    })
      .then(res => res.text())
      .then(() => {
        form.reset();
        document.getElementById('form-title').textContent = 'Neuen Benutzer anlegen';
        loadUsers();
      });
  });
}

