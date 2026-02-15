// Admin Dashboard Modal Management and Functions

// Modal Management
function openModal(mode) {
    const modal = document.getElementById('userModal');
    const overlay = document.getElementById('modalOverlay');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!modal || !overlay) {
        console.error('Modal or overlay element not found!');
        return;
    }
    
    if (mode === 'create') {
        // Reset form for creating new user
        const form = document.querySelector('#userModal form');
        if (form) form.reset();
        document.getElementById('matricule').readOnly = false;
        document.getElementById('matricule_hidden').value = '';
        modalTitle.textContent = 'Créer Nouvel Utilisateur';
        document.getElementById('motdepasse').required = true;
        document.getElementById('motdepasse').placeholder = 'Minimum 6 caractères';
        document.getElementById('passwordHint').textContent = '*';
    }
    
    modal.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('userModal');
    const overlay = document.getElementById('modalOverlay');
    
    if (!modal || !overlay) {
        console.error('Modal or overlay element not found in closeModal!');
        return;
    }
    
    modal.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = 'auto';
}

function editUser(matricule, nom, prenom, role) {
    const modal = document.getElementById('userModal');
    const overlay = document.getElementById('modalOverlay');
    const modalTitle = document.getElementById('modalTitle');
    
    if (!modal || !overlay) {
        console.error('Modal or overlay element not found in editUser!');
        return;
    }
    
    // Pre-fill form with user data
    document.getElementById('matricule').value = matricule;
    document.getElementById('matricule').readOnly = true;
    document.getElementById('matricule_hidden').value = matricule;  // Store in hidden field for POST
    document.getElementById('nom').value = nom;
    document.getElementById('prenom').value = prenom;
    document.getElementById('role').value = role;
    document.getElementById('motdepasse').value = '';
    
    // Make password optional for editing
    document.getElementById('motdepasse').required = false;
    document.getElementById('motdepasse').placeholder = 'Laisser vide pour conserver le mot de passe actuel';
    document.getElementById('passwordHint').textContent = '(optionnel)';
    
    modalTitle.textContent = 'Modifier Utilisateur: ' + matricule;
    
    modal.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function deleteUser(matricule) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur (' + matricule + ') ?\n\nCette action est irréversible.')) {
        document.getElementById('matricule_delete').value = matricule;
        document.getElementById('deleteForm').submit();
    }
}

function validateForm() {
    const matricule = document.getElementById('matricule').value.trim();
    const nom = document.getElementById('nom').value.trim();
    const prenom = document.getElementById('prenom').value.trim();
    const role = document.getElementById('role').value.trim();
    const motdepasse = document.getElementById('motdepasse').value.trim();

    if (!matricule || !nom || !prenom || !role) {
        alert('Tous les champs sont obligatoires.');
        return false;
    }

    // Password validation if provided
    if (motdepasse && motdepasse.length < 6) {
        alert('Le mot de passe doit contenir au moins 6 caractères.');
        return false;
    }

    return true;
}

// Search/Filter Table Function
function filterTable() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase();
    const tableRows = document.querySelectorAll('.user-row');
    const emptyState = document.getElementById('emptyState');
    let visibleCount = 0;

    tableRows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        if (searchData.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    // Show/hide empty state message
    if (emptyState) {
        if (visibleCount === 0 && searchTerm === '') {
            emptyState.style.display = '';
        } else if (visibleCount === 0) {
            emptyState.style.display = '';
            emptyState.querySelector('p').textContent = 'Aucun résultat trouvé pour "' + searchInput.value + '"';
        } else {
            emptyState.style.display = 'none';
        }
    }
}

// Clear search on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const searchInput = document.getElementById('searchInput');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            filterTable();
        } else {
            closeModal();
        }
    }
});

// Close modal when clicking on overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            closeModal();
        });
    }

    // Auto-dismiss alerts after 4 seconds
    const successAlert = document.getElementById('successAlert');
    const errorAlert = document.getElementById('errorAlert');

    if (successAlert) {
        setTimeout(function() {
            successAlert.remove();
        }, 3000);
    }

    if (errorAlert) {
        setTimeout(function() {
            errorAlert.remove();
        }, 3000);
    }
});
