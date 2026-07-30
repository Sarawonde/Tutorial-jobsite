document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.querySelector('[name="role"]');
    const tutorFields = document.querySelector('#tutor-registration-fields');

    if (roleSelect && tutorFields) {
        const updateTutorFields = () => {
            const tutorAccount = roleSelect.value === 'student';
            tutorFields.hidden = !tutorAccount;

            const requiredFields = ['bio', 'subjects', 'qualifications', 'availability', 'location'];
            tutorFields.querySelectorAll('input, textarea').forEach((field) => {
                field.required = tutorAccount && requiredFields.includes(field.name);
            });
        };

        roleSelect.addEventListener('change', updateTutorFields);
        updateTutorFields();
    }

    document.querySelectorAll('[data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm(button.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
