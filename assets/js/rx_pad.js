(function (window) {
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function patientAddress(p) {
        return [p.street_address, p.city, p.province].filter(function (part) {
            return part && String(part).trim();
        }).join(', ');
    }

    function patientSex(p) {
        var g = String(p.gender || p.sex || '').trim();
        if (!g) return '';
        if (/^m/i.test(g)) return 'Male';
        if (/^f/i.test(g)) return 'Female';
        return g;
    }

    function fillRxPad(p) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '';
        };
        setText('rxName', p.patient_name || '');
        setText('rxAge', p.age ? String(p.age) : '');
        setText('rxSex', patientSex(p));
        setText('rxAddress', patientAddress(p));
        setText('rxDate', p.issue_date || '');
        setText('rxDoctor', p.doctor_name || '');
        setText('rxFollowup', p.follow_up_date || '');

        var meds = String(p.medications || '').split('\n').filter(function (m) { return m.trim(); });
        var container = document.getElementById('rxMedicationsContainer');
        if (container) {
            container.innerHTML = meds.length
                ? meds.map(function (m) { return '<div class="rx-med-item">' + escapeHtml(m.trim()) + '</div>'; }).join('')
                : '<div class="rx-med-item">No medications prescribed</div>';
        }
        var notes = document.getElementById('rxInstructions');
        if (notes) {
            notes.textContent = p.instructions ? ('Instructions: ' + p.instructions) : '';
        }
    }

    function printRxPad(p) {
        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.alert('Please allow popups to print the prescription.');
            return;
        }

        var paper = document.getElementById('rxPaper');
        var cssLink = Array.prototype.map.call(document.querySelectorAll('link[rel="stylesheet"]'), function (link) {
            return link.href;
        }).filter(function (href) {
            return /rx_pad\.css/.test(href);
        })[0] || '../../assets/css/rx_pad.css';

        var fa = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        fillRxPad(p);
        var body = paper ? paper.outerHTML : '';
        body = body.replace(/src="(\.\.\/)+assets\//g, function () {
            return 'src="' + window.location.origin + window.location.pathname.replace(/\/master\/.*$/, '/') + 'assets/';
        });

        printWindow.document.open();
        printWindow.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Prescription</title>' +
            '<link rel="stylesheet" href="' + fa + '">' +
            '<link rel="stylesheet" href="' + cssLink + '">' +
            '<style>@page { margin: 0.4in; } body { margin: 0; }</style>' +
            '</head><body>' + body + '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function () { printWindow.print(); }, 250);
    }

    window.escapeHtml = window.escapeHtml || escapeHtml;
    window.fillRxPad = fillRxPad;
    window.printRxPad = printRxPad;
})(window);
