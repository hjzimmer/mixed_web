/**
 * Confirms a destructive form submission.
 *
 * @param {SubmitEvent} event Form submit event.
 * @returns {void}
 */
function confirmFormSubmission(event) {
    const form = event.currentTarget;
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}

document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', confirmFormSubmission);
});

/**
 * Changes a player's points and selects the player when points are added.
 *
 * @param {MouseEvent} event Point step button click event.
 * @returns {void}
 */
function changePlayerPoints(event) {
    const button = event.currentTarget;
    const assignment = button.closest('.assignment');
    const input = button.parentElement.querySelector('input[type="number"]');
    const next = Math.max(0, Math.min(999, Number(input.value || 0) + Number(button.dataset.step)));
    input.value = String(next);

    if (Number(button.dataset.step) > 0 && next > 0) {
        assignment.querySelector('input[type="checkbox"]').checked = true;
    }
}

document.querySelectorAll('[data-step]').forEach((button) => {
    button.addEventListener('click', changePlayerPoints);
});

/**
 * Opens the dialog referenced by a trigger button's data-open-dialog attribute and clears its form.
 *
 * @param {MouseEvent} event Trigger button click event.
 * @returns {void}
 */
function openDialog(event) {
    const dialog = document.getElementById(event.currentTarget.dataset.openDialog);
    dialog?.querySelector('form')?.reset();
    dialog?.showModal();
}

document.querySelectorAll('[data-open-dialog]').forEach((button) => {
    button.addEventListener('click', openDialog);
});

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

const loginDialog = document.getElementById('login-dialog');
const errorNotice = document.querySelector('.notice.error');
if (loginDialog && errorNotice?.textContent.includes('Passwort')) {
    loginDialog.showModal();
}