(function () {
    'use strict';

    window.refreshAppointmentsAjax = async function () {
        const currentList = document.querySelector('.list-view');
        if (!currentList) return;

        try {
            const response = await fetch(window.location.href, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) throw new Error('Appointment refresh failed');

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const nextList = parsed.querySelector('.list-view');
            if (!nextList) return;

            currentList.innerHTML = nextList.innerHTML;

            const currentSummary = document.querySelector('.summary-cards');
            const nextSummary = parsed.querySelector('.summary-cards');
            if (currentSummary && nextSummary) {
                currentSummary.replaceWith(nextSummary.cloneNode(true));
            }

            if (typeof filterAppointments === 'function') {
                filterAppointments();
            }
        } catch (error) {
            console.error('Appointment AJAX refresh error:', error);
        }
    };
})();
