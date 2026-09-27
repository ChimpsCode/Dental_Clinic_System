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

    function setRxSignature(dataUrl) {
        var img = document.getElementById('rxSignImage');
        if (!img) return;
        if (dataUrl && String(dataUrl).indexOf('data:image/') === 0) {
            img.src = dataUrl;
            img.style.display = 'block';
        } else {
            img.removeAttribute('src');
            img.style.display = 'none';
        }
    }

    function setField(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        if ('value' in el) el.value = value || '';
        else el.textContent = value || '';
    }

    function fillRxPad(p) {
        p = p || {};
        setField('rxName', p.patient_name || '');
        setField('rxAge', p.age ? String(p.age) : '');
        setField('rxSex', patientSex(p));
        setField('rxAddress', patientAddress(p));
        setField('rxDate', p.issue_date || '');
        setField('rxDoctor', p.doctor_name || '');
        setRxSignature(p.dentist_signature || p.signature_data || '');
        setField('rxFollowup', p.follow_up_date || '');
        setField('rxLicNo', p.license_no || '');
        setField('rxPtrNo', p.ptr_no || '');
        setField('rxMedicationsContainer', p.medications || '');
        setField('rxInstructions', p.instructions || '');
    }

    function snapshotPadHtml() {
        var paper = document.getElementById('rxPaper');
        if (!paper) return '';
        var clone = paper.cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(function (el) {
            var live = document.getElementById(el.id);
            var value = live ? live.value : el.value;
            if (el.tagName === 'TEXTAREA') {
                el.textContent = value;
            } else {
                el.setAttribute('value', value);
            }
        });
        return clone.outerHTML.replace(/src="(\.\.\/)+assets\//g, function () {
            return 'src="' + window.location.origin + window.location.pathname.replace(/\/master\/.*$/, '/') + 'assets/';
        });
    }

    function printRxPad(p) {
        if (p && document.getElementById('rxName') && !document.getElementById('rxName').value) {
            fillRxPad(p);
        }
        var printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.alert('Please allow popups to print the prescription.');
            return;
        }

        var cssLink = Array.prototype.map.call(document.querySelectorAll('link[rel="stylesheet"]'), function (link) {
            return link.href;
        }).filter(function (href) {
            return /rx_pad\.css/.test(href);
        })[0] || '../../assets/css/rx_pad.css';

        var fa = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        printWindow.document.open();
        printWindow.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Prescription</title>' +
            '<link rel="stylesheet" href="' + fa + '">' +
            '<link rel="stylesheet" href="' + cssLink + '">' +
            '<style>@page { margin: 0.4in; } body { margin: 0; }</style>' +
            '</head><body>' + snapshotPadHtml() + '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        if (window.rfLogPrint) {
            var rxNameEl = document.getElementById('rxName');
            var rxPatient = (rxNameEl && rxNameEl.value) || (p && p.patient_name) || '';
            window.rfLogPrint('Prescription', 'Prescription - ' + rxPatient, 'prescriptions', p && (p.id || p.prescription_id) || null);
        }
        setTimeout(function () { printWindow.print(); }, 250);
    }

    window.escapeHtml = window.escapeHtml || escapeHtml;
    window.fillRxPad = fillRxPad;
    window.printRxPad = printRxPad;
})(window);
