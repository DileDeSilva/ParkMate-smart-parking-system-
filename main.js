/* =====================================================================
   ParkMate - client-side behaviour
   The server re-checks everything this file does. This layer only makes
   the page answer faster than a round trip.
   ===================================================================== */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        setupBayPicker();
        setupWindowFields();
        setupConfirmPrompts();
        setupPasswordMatch();
        setupCardFormatting();
    });

    /* ---------------------------------------------------------------
       Bay picker: choosing a bay updates the summary rail and enables
       the confirm button.
       --------------------------------------------------------------- */
    function setupBayPicker() {
        const deck = document.querySelector('[data-deck]');
        if (!deck) return;

        const radios     = deck.querySelectorAll('input[name="slot_id"]');
        const codeOut    = document.querySelector('[data-out="bay"]');
        const rateOut    = document.querySelector('[data-out="rate"]');
        const totalOut   = document.querySelector('[data-out="total"]');
        const submitBtn  = document.querySelector('[data-book-submit]');
        const hoursField = document.querySelector('[data-hours]');

        function refresh() {
            const picked = deck.querySelector('input[name="slot_id"]:checked');

            // Keep the fallback class in sync for browsers without :has().
            deck.querySelectorAll('.bay').forEach(function (bay) {
                bay.classList.remove('bay--selected');
            });

            if (!picked) {
                if (submitBtn) submitBtn.disabled = true;
                if (codeOut)  codeOut.textContent  = 'Not chosen';
                if (rateOut)  rateOut.textContent  = '—';
                if (totalOut) totalOut.textContent = '—';
                return;
            }

            const label = picked.closest('.bay');
            if (label) label.classList.add('bay--selected');

            const rate  = parseFloat(picked.dataset.rate || '0');
            const hours = parseInt(hoursField ? hoursField.value : '1', 10) || 1;

            if (codeOut)  codeOut.textContent  = picked.dataset.code || '';
            if (rateOut)  rateOut.textContent  = money(rate) + ' / hour';
            if (totalOut) totalOut.textContent = money(rate * hours);
            if (submitBtn) submitBtn.disabled = false;
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', refresh);
        });
        refresh();
    }

    /* ---------------------------------------------------------------
       Start and end time fields: keep the end after the start, and never
       let someone book a window that has already passed.
       --------------------------------------------------------------- */
    function setupWindowFields() {
        const start = document.querySelector('[data-window-start]');
        const end   = document.querySelector('[data-window-end]');
        if (!start || !end) return;

        // Earliest selectable moment is the next minute.
        const now = new Date(Date.now() + 60000);
        start.min = toLocalValue(now);

        function syncEnd() {
            if (!start.value) return;
            const startAt = new Date(start.value);
            end.min = toLocalValue(new Date(startAt.getTime() + 30 * 60000));

            if (end.value && new Date(end.value) <= startAt) {
                end.value = toLocalValue(new Date(startAt.getTime() + 2 * 3600000));
            }
        }

        start.addEventListener('change', syncEnd);
        syncEnd();
    }

    /* ---------------------------------------------------------------
       Anything destructive asks first.
       --------------------------------------------------------------- */
    function setupConfirmPrompts() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                if (!window.confirm(el.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    /* ---------------------------------------------------------------
       Registration: flag a password mismatch before the form is sent.
       --------------------------------------------------------------- */
    function setupPasswordMatch() {
        const pass    = document.querySelector('[data-password]');
        const confirm = document.querySelector('[data-password-confirm]');
        if (!pass || !confirm) return;

        function check() {
            confirm.setCustomValidity(
                confirm.value && confirm.value !== pass.value
                    ? 'The two passwords do not match.'
                    : ''
            );
        }

        pass.addEventListener('input', check);
        confirm.addEventListener('input', check);
    }

    /* ---------------------------------------------------------------
       Payment screen: group the demo card number into fours.
       --------------------------------------------------------------- */
    function setupCardFormatting() {
        const card = document.querySelector('[data-card-number]');
        if (!card) return;

        card.addEventListener('input', function () {
            const digits = card.value.replace(/\D/g, '').slice(0, 16);
            card.value = digits.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    /* --------------------------------------------------------------- */
    function money(value) {
        return 'LKR ' + value.toLocaleString('en-LK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /** Date -> "YYYY-MM-DDTHH:MM" in local time, for datetime-local inputs. */
    function toLocalValue(date) {
        const pad = function (n) { return String(n).padStart(2, '0'); };
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' +
               pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' +
               pad(date.getMinutes());
    }
})();
