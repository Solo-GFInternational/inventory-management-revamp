/*
 * TallStackUI version of window.gfSwal (see app.js), loaded before app.js on
 * pages that set @section('alerts', 'tallstackui') — the Inventory pages, as
 * a trial. Every alert is a TallStackUI dialog, the centred modal SweetAlert
 * used to show:
 *
 * - confirm(): a warning dialog with Yes / Cancel, resolving { isConfirmed }.
 *   Persistent, so only a button closes it and the promise always settles.
 * - alert(): success, error, warning or info dialog with an OK button.
 * - processing(): a dialog with no buttons that can't be dismissed; the next
 *   page load replaces it.
 *
 * TallStackUI only lets a dialog's buttons run callbacks inside a Livewire
 * component, so confirm() uses plain buttons and listens for the
 * dialog:accepted / dialog:rejected events the dialog fires on window.
 * Titles and descriptions are rendered as HTML, so all text is escaped here.
 * The dialog is an Alpine component (Alpine comes with Livewire's script, at
 * the end of the page), so calls made before Alpine has started wait for it.
 */
const ready = new Promise((resolve) => {
    if (window.Alpine?.version && document.querySelector('[x-data]')?._x_dataStack) {
        resolve();
    } else {
        document.addEventListener('alpine:initialized', () => resolve(), { once: true });
    }
});

const escape = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const dialog = () => window.$tsui.interaction('dialog');

window.gfAlertProvider = {
    confirm({ title = 'Are you sure?', text = 'This action cannot be undone.', confirmButtonText = 'Yes, continue', cancelButtonText = 'Cancel' } = {}) {
        return ready.then(() => new Promise((resolve) => {
            const answer = (isConfirmed) => () => {
                window.removeEventListener('dialog:accepted', yes);
                window.removeEventListener('dialog:rejected', no);
                resolve({ isConfirmed });
            };
            const yes = answer(true);
            const no = answer(false);

            window.addEventListener('dialog:accepted', yes);
            window.addEventListener('dialog:rejected', no);

            dialog()
                .warning(escape(title), text ? escape(text) : null)
                .confirm(escape(confirmButtonText))
                .cancel(escape(cancelButtonText))
                .persistent()
                .send();
        }));
    },

    // `html` is only ever built by app.js (the validation error list), from escaped text.
    alert({ icon = 'success', title = null, text = null, html = null } = {}) {
        const type = ['success', 'error', 'warning', 'info'].includes(icon) ? icon : 'info';
        const heading = escape(title ?? (type === 'error' ? 'Something went wrong' : 'Done'));

        return ready.then(() => {
            dialog()[type](heading, html ?? (text ? escape(text) : null)).send();
        });
    },

    processing(title, text) {
        ready.then(() => {
            const waiting = dialog()
                .info(escape(title || 'Processing…'), escape(text || 'Please wait, this can take a moment.'))
                .persistent();

            // TallStackUI always adds an OK button to a dialog; with no options
            // there are no buttons. The corner × is hidden by app.css while
            // data-gf-processing is set, so it can't be closed while the form submits.
            waiting._data.options = {};
            document.documentElement.dataset.gfProcessing = '';
            waiting.send();
        });
    },
};

// Coming back with the browser's Back button can restore the page from cache
// with the processing dialog still up.
window.addEventListener('pageshow', (event) => {
    delete document.documentElement.dataset.gfProcessing;

    if (event.persisted && window.Alpine) {
        document.querySelectorAll('[role="dialog"][x-data]').forEach((element) => window.Alpine.$data(element).remove?.(false, true));
    }
});
