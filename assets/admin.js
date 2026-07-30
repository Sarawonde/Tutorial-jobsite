document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.table-wrap tbody form[action*="admin_action"]').forEach((form) => {
        const userId = form.querySelector('input[name="id"]')?.value;
        if (!userId) return;

        const editLink = document.createElement('a');
        editLink.href = `index.php?page=admin_user&id=${encodeURIComponent(userId)}`;
        editLink.className = 'icon-action';
        editLink.title = 'Edit user';
        editLink.setAttribute('aria-label', 'Edit user');
        editLink.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Zm2-3.2 9-9 1.2 1.2-9 9H6v-1.2ZM18.5 3.5a1.4 1.4 0 0 0-2 0l-.7.7 4 4 .7-.7a1.4 1.4 0 0 0 0-2l-2-2Z"/></svg>';

        const deleteButton = document.createElement('button');
        deleteButton.type = 'submit';
        deleteButton.name = 'action';
        deleteButton.value = 'delete_user';
        deleteButton.className = 'icon-action danger';
        deleteButton.title = 'Delete user';
        deleteButton.setAttribute('aria-label', 'Delete user');
        deleteButton.dataset.confirm = 'Permanently delete this user and all related records?';
        deleteButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8l1 2h4v2H3V5h4l1-2Zm-2 6h12l-1 12H7L6 9Zm4 2v7h2v-7h-2Zm4 0v7h2v-7h-2Z"/></svg>';

        form.append(editLink, deleteButton);
    });
});
