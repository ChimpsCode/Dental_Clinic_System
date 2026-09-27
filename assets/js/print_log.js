/**
 * Print Activity logger - records who printed what, and when.
 * Pages live one folder below master/, so ../api/ resolves to master/api/.
 */
(function () {
    if (window.rfLogPrint) return;
    window.rfLogPrint = function (documentType, details, refTable, refId) {
        try {
            const page = (location.pathname.split('/').pop() || '').slice(0, 100);
            fetch('../api/log_print.php', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_type: documentType,
                    details: details == null ? '' : String(details).slice(0, 255),
                    reference_table: refTable || '',
                    reference_id: refId == null ? '' : refId,
                    page: page
                })
            }).catch(function () {});
        } catch (e) {}
    };
})();
