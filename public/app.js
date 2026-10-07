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

function showSaveError(message) {
    const dialog = document.getElementById('save-error-dialog');
    if (!dialog) return;
    dialog.querySelector('#save-error-message').textContent = message;
    if (!dialog.open) dialog.showModal();
}

async function submitProtectedForm(event) {
    const form = event.currentTarget;
    const action = form.elements.namedItem('action')?.value;
    if (event.defaultPrevented || action === 'login' || action === 'logout') return;
    event.preventDefault();
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
        });
        if (response.status === 403) {
            showSaveError('Bitte zuerst einloggen. Die Änderungen wurden nicht gespeichert.');
            return;
        }
        if (!response.ok) throw new Error('Die Änderungen konnten nicht gespeichert werden.');
        window.location.assign(response.url);
    } catch (error) {
        showSaveError(error instanceof Error ? error.message : 'Die Änderungen konnten nicht gespeichert werden.');
    }
}

document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', submitProtectedForm);
});

/**
 * Saves the current set form in the background.
 *
 * @param {HTMLFormElement} form Set form to persist.
 * @returns {Promise<void>}
 */
async function saveSetForm(form) {
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const isJson = response.headers.get('Content-Type')?.includes('application/json');
            const message = isJson ? (await response.json()).message : null;
            throw new Error(message || 'Satz konnte nicht gespeichert werden.');
        }
    } catch (error) {
        console.log('Fehler beim Speichern des Satzes:', error);
        showSaveError(error instanceof Error ? error.message : 'Satz konnte nicht gespeichert werden.');
    }
}

/**
 * Changes a player's points and selects the player when points are added.
 *
 * @param {MouseEvent} event Point step button click event.
 * @returns {void}
 */
function changePlayerPoints(event) {
    const button = event.currentTarget;
    const assignment = button.closest('.assignment');
    const form = button.closest('form');
    const input = button.parentElement.querySelector('input[type="number"]');
    const next = Math.max(0, Math.min(999, Number(input.value || 0) + Number(button.dataset.step)));
    input.value = String(next);

    if (Number(button.dataset.step) > 0 && next > 0) {
        assignment.querySelector('input[type="checkbox"]').checked = true;
    }

    if (form) {
        void saveSetForm(form);
    }
}

document.querySelectorAll('[data-step]').forEach((button) => {
    button.addEventListener('click', changePlayerPoints);
});

/**
 * Saves a player's assignment immediately when the checkbox changes.
 *
 * @param {Event} event Checkbox change event.
 * @returns {void}
 */
function savePlayerAssignment(event) {
    const form = event.currentTarget.closest('form');
    if (form) {
        void saveSetForm(form);
    }
}

document.querySelectorAll('.assignment input[type="checkbox"]').forEach((checkbox) => {
    checkbox.addEventListener('change', savePlayerAssignment);
});

/**
 * Computes the sets won by each side from a game card's score inputs.
 *
 * @param {Element} gameCard Game card containing the set forms.
 * @returns {{own: number, opponent: number}} Sets won by own team and opponent.
 */
function computeGameResult(gameCard) {
    let own = 0;
    let opponent = 0;
    gameCard.querySelectorAll('.set-score').forEach((scoreLabel) => {
        const ownValue = scoreLabel.querySelector('input[name="scoreOwn"]').value.trim();
        const opponentValue = scoreLabel.querySelector('input[name="scoreOpponent"]').value.trim();
        if (ownValue === '' || opponentValue === '') return;
        const ownNumber = Number(ownValue);
        const opponentNumber = Number(opponentValue);
        if (ownNumber > opponentNumber) own++;
        else if (opponentNumber > ownNumber) opponent++;
    });
    return { own, opponent };
}

/**
 * Updates a game card's displayed result from its current set scores.
 *
 * @param {Element} gameCard Game card to update.
 * @returns {void}
 */
function updateGameResult(gameCard) {
    const resultElement = gameCard.querySelector('.game-result');
    if (!resultElement) return;
    const result = computeGameResult(gameCard);
    resultElement.textContent = `${result.own}:${result.opponent}`;
}

/**
 * Saves a set's score immediately when either score input changes and refreshes the game result.
 *
 * @param {Event} event Score input change event.
 * @returns {void}
 */
function saveSetScore(event) {
    const form = event.currentTarget.closest('form');
    const gameCard = event.currentTarget.closest('.game-card');
    if (gameCard) updateGameResult(gameCard);
    if (form) {
        void saveSetForm(form);
    }
}

document.querySelectorAll('.set-score input[type="number"]').forEach((input) => {
    input.addEventListener('change', saveSetScore);
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

document.getElementById('save-error-dialog')?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.currentTarget.close();
    }
});

const loginDialog = document.getElementById('login-dialog');
const errorNotice = document.querySelector('.notice.error');
if (loginDialog && errorNotice?.textContent.includes('Passwort')) {
    loginDialog.showModal();
}