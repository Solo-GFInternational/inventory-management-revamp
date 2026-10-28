// Shared UI behaviour, ported from the tender-management / site-visit projects.

// Show/hide toggle for any password field wrapped as:
// <div data-password-field><input type="password">
//   <button data-password-toggle><svg data-icon-show><svg data-icon-hide hidden>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const field = button.closest('[data-password-field]');
        const input = field?.querySelector('input');

        if (! input) {
            return;
        }

        const revealed = input.type === 'text';

        input.type = revealed ? 'password' : 'text';
        field.querySelector('[data-icon-show]').hidden = ! revealed;
        field.querySelector('[data-icon-hide]').hidden = revealed;
        button.setAttribute('aria-label', revealed ? 'Show password' : 'Hide password');
    });
});

/*
 * SweetAlert, set up the way the site-visit project's staff panel uses it
 * (its AdminPanelProvider SCRIPTS_AFTER hook): an "Are you sure?" dialog
 * with a teal confirm button and Cancel, and success dialogs that close
 * themselves after 6 seconds. It's the app's only alert style — the layout
 * hands flashed status messages and validation errors to it.
 * SweetAlert itself is served from public/vendor/sweetalert2 (see the
 * layout); without it, confirms fall back to the browser's own OK / Cancel.
 */
window.gfSwal = {
    confirmColor: '#14b89a',

    confirm(options = {}) {
        if (! window.Swal) {
            const { title = 'Are you sure?', text } = options;

            return Promise.resolve({ isConfirmed: window.confirm(text ? `${title}\n\n${text}` : title) });
        }

        return window.Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: this.confirmColor,
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel',
            ...options,
        });
    },

    alert({ icon = 'success', title = null, text = null, html = null } = {}) {
        if (! window.Swal) {
            return Promise.resolve();
        }

        return window.Swal.fire({
            icon,
            title: title ?? (icon === 'error' ? 'Something went wrong' : 'Done'),
            text: html ? undefined : (text ?? undefined),
            html: html ?? undefined,
            confirmButtonColor: this.confirmColor,
            // Successes close themselves; errors wait to be read.
            ...(icon === 'success' ? { timer: 6000, timerProgressBar: true } : {}),
        });
    },
};

/*
 * Confirm before submitting: <form data-confirm="What will happen."
 * data-confirm-title="Deactivate user?" data-confirm-button="Yes, deactivate">.
 * The deactivate buttons and other one-way actions use this.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-confirm') || form.dataset.confirmed) {
        return;
    }

    event.preventDefault();

    // An open modal sits in the browser's top layer, above SweetAlert, so
    // it's closed while asking and reopened (input intact) on Cancel.
    const dialog = form.closest('dialog');
    const wasOpen = dialog?.open ?? false;

    if (wasOpen) {
        dialog.close();
    }

    window.gfSwal.confirm({
        title: form.dataset.confirmTitle || 'Are you sure?',
        text: form.dataset.confirm || undefined,
        confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
    }).then((result) => {
        if (! result.isConfirmed) {
            if (wasOpen) {
                dialog.showModal();
            }

            return;
        }

        // The flag lets this one resubmission through; it's cleared straight
        // after (the submit event fires synchronously inside requestSubmit), so
        // a page restored by the Back button asks again.
        form.dataset.confirmed = '1';
        form.requestSubmit(event.submitter ?? undefined);
        delete form.dataset.confirmed;
    });
});

/*
 * Modals (<x-modal>, a native <dialog>): data-modal-open="{id}" opens one,
 * data-modal-close inside closes it, a click on the backdrop closes it, and
 * data-open-on-load opens it straight away (a form inside failed validation).
 */
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');

    if (opener) {
        const dialog = document.getElementById(opener.dataset.modalOpen);

        if (dialog instanceof HTMLDialogElement) {
            event.preventDefault();
            dialog.showModal();
        }

        return;
    }

    if (event.target.closest('[data-modal-close]')) {
        event.target.closest('dialog')?.close();

        return;
    }

    // A click on a <dialog> itself (not its contents) is a click on the backdrop.
    if (event.target instanceof HTMLDialogElement && event.target.open) {
        const box = event.target.getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right
            && event.clientY >= box.top && event.clientY <= box.bottom;

        if (! inside) {
            event.target.close();
        }
    }
});

const reopened = document.querySelector('dialog[data-open-on-load]');

reopened?.showModal();

/*
 * Flashed status messages and validation errors, handed over by the layout
 * as window.gfFlash. Errors from a modal's form are already shown inside
 * the reopened modal (<x-form-errors>), so they aren't repeated here.
 */
const flash = window.gfFlash ?? {};

if (flash.status) {
    window.gfSwal.alert({ icon: 'success', title: flash.title ?? 'Done', text: flash.status });
} else if (flash.error) {
    window.gfSwal.alert({ icon: 'error', title: flash.title ?? 'Something went wrong', text: flash.error });
} else if (flash.errors?.length && ! reopened) {
    const list = document.createElement('ul');

    list.style.cssText = 'text-align:left;list-style:disc;padding-left:1.25rem;margin:0';
    flash.errors.forEach((message) => list.appendChild(Object.assign(document.createElement('li'), { textContent: message })));

    window.gfSwal.alert({ icon: 'error', title: 'Please check the details', html: list.outerHTML });
}

/*
 * "Processing…" while a form that changes data is submitted —
 * <form data-processing="Saving inventory…" data-processing-text="...">.
 * Every create / edit / approve / deactivate form carries it (not GET
 * filters, the PDF download — a file download never leaves the page, so the
 * spinner would never close — or sign in / out).
 * Same alert as site-visit's window.gfProcessing: a loading spinner that
 * can't be dismissed, replaced by the next page's success or error alert
 * (or the reopened modal when validation fails).
 * Runs after the data-confirm handler above, so a form that asks first only
 * shows it once confirmed, and the browser's own required-field checks have
 * already passed by the time a submit event fires.
 */
window.gfSwal.processing = (title, text) => {
    if (! window.Swal) {
        return;
    }

    window.Swal.fire({
        title: title || 'Processing…',
        text: text || 'Please wait, this can take a moment.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => window.Swal.showLoading(),
    });
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || ! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-processing')) {
        return;
    }

    // A modal is in the browser's top layer, above SweetAlert — close it so
    // the alert can be seen. The submission is already under way.
    form.closest('dialog')?.close();

    window.gfSwal.processing(form.dataset.processing, form.dataset.processingText);
});

// Coming back to this page with the browser's Back button can restore it
// from cache with the alert still spinning.
window.addEventListener('pageshow', (event) => {
    if (event.persisted && window.Swal?.isVisible() && window.Swal.isLoading()) {
        window.Swal.close();
    }
});
