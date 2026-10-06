(function () {
    'use strict';

    function escape(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }
    function date(value) {
        var parts = String(value || '').slice(0, 10).split('-');
        if (parts.length !== 3) return '';
        var parsed = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(parsed.getTime()) ? '' : parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }
    function image(src, className, alt) {
        return /^data:image\/(png|jpeg|jpg|webp|gif);base64,[A-Za-z0-9+/=\s]+$/i.test(String(src || ''))
            ? '<img class="' + className + '" src="' + escape(src) + '" alt="' + alt + '">' : '';
    }
    function field(label, value) {
        return '<div class="mc-field"><h3>' + escape(label) + '</h3><p>' + escape(value || 'Not recorded').replace(/\r?\n/g, '<br>') + '</p></div>';
    }

    var css = '.mc-letter,.mc-letter *{box-sizing:border-box}' +
        '.mc-letter{position:relative;isolation:isolate;background:#fff;color:#1e293b;font-family:Georgia,"Times New Roman",serif;font-size:15px;line-height:1.8;max-width:794px;margin:0 auto;padding:52px 56px;overflow-wrap:anywhere;text-align:left}' +
        '.mc-letter .mc-watermark{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:76%;max-width:560px;height:auto;opacity:0.065;filter:blur(2px);pointer-events:none;user-select:none;z-index:0}' +
        '.mc-letter .mc-content{position:relative;z-index:1}' +
        '.mc-letter .mc-head{display:flex;align-items:center;gap:24px;padding-bottom:22px;border-bottom:3px solid #123d77}' +
        '.mc-letter .mc-logo{width:76px;height:90px;object-fit:contain;flex-shrink:0}' +
        '.mc-letter .mc-brand{flex:1}.mc-letter .mc-clinic{font-family:Arial,sans-serif;font-size:23px;font-weight:700;letter-spacing:1px;color:#123d77;line-height:1.3;margin:0 0 8px;text-transform:uppercase}' +
        '.mc-letter .mc-contact{font-family:Arial,sans-serif;font-size:11px;line-height:1.7;color:#475569;margin:0}' +
        '.mc-letter .mc-title{text-align:center;margin:30px 0 26px}.mc-letter .mc-title h2{font-family:Arial,sans-serif;font-size:21px;font-weight:700;letter-spacing:2px;color:#123d77;line-height:1.4;margin:0}' +
        '.mc-letter .mc-title-line{display:block;width:46px;border-top:2px solid #123d77;margin:12px auto 0}' +
        '.mc-letter .mc-date{text-align:right;font-family:Arial,sans-serif;font-size:12px;margin:0 0 26px}' +
        '.mc-letter .mc-salutation{font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.5px;margin:0 0 14px}' +
        '.mc-letter p{font-size:15px;line-height:1.8;color:#1e293b;margin:0 0 20px}' +
        '.mc-letter .mc-details{margin:26px 0}.mc-letter .mc-field{margin:0 0 18px;break-inside:avoid}' +
        '.mc-letter .mc-field h3{font-family:Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#123d77;line-height:1.5;margin:0 0 5px}' +
        '.mc-letter .mc-field p{margin:0;white-space:normal}' +
        '.mc-letter .mc-issued{font-size:13px;color:#475569;margin-top:28px}' +
        '.mc-letter .mc-sign{margin:40px 0 34px;display:flex;justify-content:flex-end;break-inside:avoid}.mc-letter .mc-sign-col{width:260px;max-width:100%;text-align:center}' +
        '.mc-letter .mc-signature{display:block;width:180px;height:68px;object-fit:contain;margin:0 auto 6px}' +
        '.mc-letter .mc-dentist{font-family:Arial,sans-serif;font-size:13px;font-weight:700;border-top:1px solid #64748b;padding-top:8px;color:#1e293b;line-height:1.5}' +
        '.mc-letter .mc-role{font-family:Arial,sans-serif;font-size:11px;color:#475569;line-height:1.6;margin-top:3px}' +
        '.mc-letter .mc-footer{border-top:1px solid #cbd5e1;padding-top:12px;font-family:Arial,sans-serif;font-size:10px;letter-spacing:1px;color:#64748b;text-align:center}' +
        '@media screen and (max-width:600px){.mc-letter{padding:28px 22px}.mc-letter .mc-head{gap:16px}.mc-letter .mc-logo{width:56px;height:68px}.mc-letter .mc-clinic{font-size:18px}.mc-letter .mc-title h2{font-size:18px}}' +
        '@media print{.mc-letter{max-width:none;width:100%;padding:0;color:#111;font-size:14px}.mc-letter p{font-size:14px;line-height:1.75}.mc-letter .mc-field{margin-bottom:15px}.mc-letter .mc-head{break-inside:avoid}.mc-letter .mc-title{break-after:avoid}}';

    function body(cert, patient, clinic) {
        patient = patient || {};
        clinic = clinic || {};
        var name = patient.name || [patient.first_name, patient.middle_name, patient.last_name].filter(Boolean).join(' ') || 'the patient';
        var who = [name];
        if (patient.age !== '' && patient.age != null) who.push(patient.age + ' years old');
        if (patient.gender) who.push(patient.gender);
        var address = patient.address || patient.street_address;
        var html = '<article class="mc-letter">' + image(clinic.watermark || clinic.logo, 'mc-watermark', '') + '<div class="mc-content"><header class="mc-head">' + image(clinic.logo, 'mc-logo', 'Clinic logo') +
            '<div class="mc-brand"><div class="mc-clinic">' + escape(clinic.name || 'RF Dental Clinic') + '</div>' +
            (clinic.address ? '<div class="mc-contact">' + escape(clinic.address) + '</div>' : '') +
            (clinic.phone ? '<div class="mc-contact">Tel. ' + escape(clinic.phone) + '</div>' : '') + '</div></header>' +
            '<div class="mc-title"><h2>MEDICAL CERTIFICATE</h2><span class="mc-title-line"></span></div>' +
            '<div class="mc-date"><strong>Date issued:</strong> ' + escape(date(cert.issued_at) || 'Not recorded') + '</div>' +
            '<div class="mc-salutation">TO WHOM IT MAY CONCERN:</div>' +
            '<p>This is to certify that <strong>' + escape(who.join(', ')) + '</strong>' +
            (address ? ', residing at ' + escape(address) : '') + ', was examined at this clinic.</p>' +
            '<div class="mc-details">' + field('Purpose', cert.purpose) + field('Clinical findings', cert.findings) + field('Recommendation', cert.recommendation);
        if (cert.rest_from || cert.rest_to) html += field('Advised period of rest', (date(cert.rest_from) || 'Not specified') + ' to ' + (date(cert.rest_to) || 'Not specified'));
        if (cert.remarks) html += field('Remarks', cert.remarks);
        html += '</div><p class="mc-issued">This certificate is issued upon the patient\'s request for the purpose stated above.</p>' +
            '<div class="mc-sign"><div class="mc-sign-col">' + image(cert.dentist_signature, 'mc-signature', 'Dentist signature') +
            '<div class="mc-dentist">' + escape(cert.dentist_name || 'Dentist') + '</div><div class="mc-role">Attending Dentist</div></div></div>' +
            '<footer class="mc-footer">' + escape(clinic.name || 'RF Dental Clinic') + ' &bull; MEDICAL CERTIFICATE</footer></div></article>';
        return html;
    }
    function documentHtml(cert, patient, clinic) {
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<title>Medical Certificate</title><style>@page{size:A4;margin:18mm}body{margin:0;background:#edf1f5}' + css +
            '.mc-toolbar{text-align:center;padding:16px;font-family:Arial,sans-serif}.mc-toolbar button{background:#123d77;color:white;border:0;border-radius:6px;padding:10px 18px;cursor:pointer}' +
            '@media print{body{background:white}.mc-toolbar{display:none}}</style></head><body>' +
            '<div class="mc-toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>' + body(cert, patient, clinic) + '</body></html>';
    }
    function printWhenReady(preview) {
        return Promise.all(Array.from(preview.document.images).map(function (img) {
            if (img.complete) return Promise.resolve();
            return new Promise(function (resolve) { img.onload = resolve; img.onerror = resolve; });
        })).then(function () { preview.focus(); preview.print(); });
    }
    window.MedicalCertificateTemplate = { css: css, body: body, documentHtml: documentHtml, printWhenReady: printWhenReady };
}());
