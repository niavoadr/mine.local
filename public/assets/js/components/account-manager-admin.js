(function () {
    const root = document.querySelector('#manager-content .manager-view');

    if (!root || root.dataset.ready) {
        return;
    }

    root.dataset.ready = '1';

    const showMessage = (type, text) => {
        const element = root.querySelector('[data-manager-' + type + ']');
        element.textContent = text;
        element.classList.remove('d-none');
        setTimeout(() => element.classList.add('d-none'), 5000);
    };

    root.querySelector('[data-manager-reload]').onclick = () => window.location.reload();

    root.querySelector('[data-user-search]').oninput = (event) => {
        const query = event.target.value.toLowerCase();
        root.querySelectorAll('tbody tr').forEach((row) => {
            row.hidden = !row.textContent.toLowerCase().includes(query);
        });
    };

    root.querySelector('[data-create-user]').onsubmit = async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        formData.append('action', 'create_user');

        try {
            const response = await fetch('api/manager.php', {
                method: 'POST',
                body: formData,
            }).then((result) => result.json());

            if (!response.success) {
                showMessage('error', response.message);
                return;
            }

            showMessage('success', response.message);
            event.target.reset();
            setTimeout(() => window.location.reload(), 700);
        } catch (error) {
            showMessage('error', 'Erreur lors de la création de l’utilisateur');
        }
    };

    root.querySelectorAll('[data-user-status]').forEach((button) => {
        button.onclick = async () => {
            button.disabled = true;
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('user_id', button.dataset.userId);
            formData.append('new_status', button.dataset.userStatus);

            try {
                const response = await fetch('api/manager.php', {
                    method: 'POST',
                    body: formData,
                }).then((result) => result.json());

                if (!response.success) {
                    showMessage('error', response.message);
                    button.disabled = false;
                    return;
                }

                window.location.reload();
            } catch (error) {
                showMessage('error', 'Erreur lors de la mise à jour du statut');
                button.disabled = false;
            }
        };
    });
})();

setInterval(() => {
    fetch('api/app-session.php', { credentials: 'same-origin' }).catch(() => {});
}, 30000);
