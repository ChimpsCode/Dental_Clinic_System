(function () {
    'use strict';

    function date(value) {
        var parts = String(value || '').slice(0, 10).split('-');
        if (parts.length !== 3) return 'Not recorded';
        var parsed = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return isNaN(parsed.getTime()) ? 'Not recorded' : parsed.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    // Generate a real PDF with selectable text, without a print dialog or HTML capture.
    window.buildMedicalCertificatePdf = function (cert, patient, clinic) {
        if (!window.jspdf || !window.jspdf.jsPDF) throw new Error('PDF library is unavailable');
        patient = patient || {};
        clinic = clinic || {};
        var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
        var margin = 18, width = 174, bottom = 270, y = margin;
        var blue = [18, 61, 119], ink = [30, 41, 59];
        pdf.setProperties({ title: 'Medical Certificate', author: clinic.name || 'RF Dental Clinic', subject: cert.purpose || 'Medical Certificate' });

        function font(family, style, size, color) {
            pdf.setFont(family, style);
            pdf.setFontSize(size);
            pdf.setTextColor.apply(pdf, color || ink);
        }
        function newPage() {
            pdf.addPage();
            watermark();
            y = margin;
            font('helvetica', 'bold', 9, blue);
            pdf.text('MEDICAL CERTIFICATE - CONTINUED', margin, y);
            y += 9;
        }
        function room(height) {
            if (y + height > bottom) newPage();
        }
        function paragraph(text, size, family, style, lineHeight) {
            size = size || 11;
            lineHeight = lineHeight || 6;
            font(family || 'times', style || 'normal', size);
            var lines = pdf.splitTextToSize(String(text || ''), width);
            lines.forEach(function (line) {
                room(lineHeight);
                // A new page's continuation heading changes the font.
                font(family || 'times', style || 'normal', size);
                pdf.text(line, margin, y);
                y += lineHeight;
            });
        }
        function field(label, text) {
            room(14);
            font('helvetica', 'bold', 8, blue);
            pdf.text(label.toUpperCase(), margin, y);
            y += 6;
            paragraph(text || 'Not recorded');
            y += 5;
        }
        function image(src, x, top, boxWidth, boxHeight) {
            if (!src) return false;
            if (!/^data:image\/(png|jpeg|jpg|webp|gif);base64,[A-Za-z0-9+/=\s]+$/i.test(String(src))) {
                throw new Error('Invalid certificate image');
            }
            var info = pdf.getImageProperties(src);
            var scale = Math.min(boxWidth / info.width, boxHeight / info.height);
            var w = info.width * scale, h = info.height * scale;
            pdf.addImage(src, info.fileType, x + (boxWidth - w) / 2, top + (boxHeight - h) / 2, w, h);
            return true;
        }

        function watermark() {
            if (!clinic.watermark && !clinic.logo) return;
            pdf.saveGraphicsState();
            pdf.setGState(new pdf.GState({ opacity: 0.07 }));
            image(clinic.watermark || clinic.logo, 30, 73.5, 150, 150);
            pdf.restoreGraphicsState();
        }

        // Draw before all text, and repeat on continuation pages.
        watermark();
        var hasLogo = image(clinic.logo, margin, y, 20, 24);
        var brandX = margin + (hasLogo ? 27 : 0);
        var brandWidth = width - (hasLogo ? 27 : 0);
        font('helvetica', 'bold', 18, blue);
        var brandLines = pdf.splitTextToSize(String(clinic.name || 'RF Dental Clinic').toUpperCase(), brandWidth);
        pdf.text(brandLines, brandX, y + 7);
        var contactY = y + 7 + brandLines.length * 8;
        font('helvetica', 'normal', 8);
        [clinic.address, clinic.phone ? 'Tel. ' + clinic.phone : ''].filter(Boolean).forEach(function (contact) {
            var lines = pdf.splitTextToSize(String(contact), brandWidth);
            pdf.text(lines, brandX, contactY);
            contactY += lines.length * 4.5;
        });
        y = Math.max(y + 29, contactY + 5);
        pdf.setDrawColor.apply(pdf, blue);
        pdf.setLineWidth(0.7);
        pdf.line(margin, y, margin + width, y);
        y += 14;
        font('helvetica', 'bold', 17, blue);
        pdf.text('MEDICAL CERTIFICATE', 105, y, { align: 'center' });
        y += 5;
        pdf.setLineWidth(0.4);
        pdf.line(99, y, 111, y);
        y += 12;
        font('helvetica', 'normal', 9);
        pdf.text('Date issued: ' + date(cert.issued_at), margin + width, y, { align: 'right' });
        y += 14;
        paragraph('TO WHOM IT MAY CONCERN:', 9, 'helvetica', 'bold');
        y += 3;
        var name = patient.name || [patient.first_name, patient.middle_name, patient.last_name].filter(Boolean).join(' ') || 'the patient';
        var who = [name];
        if (patient.age !== '' && patient.age != null) who.push(patient.age + ' years old');
        if (patient.gender) who.push(patient.gender);
        var address = patient.address || patient.street_address;
        paragraph('This is to certify that ' + who.join(', ') + (address ? ', residing at ' + address : '') + ', was examined at this clinic.');
        y += 7;
        field('Purpose', cert.purpose);
        field('Clinical findings', cert.findings);
        field('Recommendation', cert.recommendation);
        if (cert.rest_from || cert.rest_to) field('Advised period of rest', (cert.rest_from ? date(cert.rest_from) : 'Not specified') + ' to ' + (cert.rest_to ? date(cert.rest_to) : 'Not specified'));
        if (cert.remarks) field('Remarks', cert.remarks);
        room(18);
        y += 3;
        paragraph("This certificate is issued upon the patient's request for the purpose stated above.", 10);
        // Keep the saved signature and dentist's name together on the same page.
        font('helvetica', 'bold', 10);
        var dentistLines = pdf.splitTextToSize(String(cert.dentist_name || 'Dentist'), 70);
        room(38 + dentistLines.length * 5);
        y += 6;
        var signX = margin + width - 70;
        image(cert.dentist_signature, signX + 10, y, 50, 18);
        y += 21;
        pdf.setDrawColor(100, 116, 139);
        pdf.setLineWidth(0.25);
        pdf.line(signX, y, signX + 70, y);
        y += 6;
        font('helvetica', 'bold', 10);
        pdf.text(dentistLines, signX + 35, y, { align: 'center' });
        y += dentistLines.length * 5;
        font('helvetica', 'normal', 8);
        pdf.text('Attending Dentist', signX + 35, y, { align: 'center' });

        var pages = pdf.getNumberOfPages();
        for (var page = 1; page <= pages; page++) {
            pdf.setPage(page);
            pdf.setDrawColor(203, 213, 225);
            pdf.line(margin, 278, margin + width, 278);
            font('helvetica', 'normal', 7, [100, 116, 139]);
            pdf.text('MEDICAL CERTIFICATE', margin, 283);
            pdf.text(page + ' / ' + pages, margin + width, 283, { align: 'right' });
        }
        return pdf;
    };
}());
