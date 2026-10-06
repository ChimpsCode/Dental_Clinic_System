(function () {
    'use strict';

    function body(session) {
        var rec = window.PATIENT_RECORD || {};
        return window.MedicalCertificateTemplate.body(session.medical_certificate, rec.patient, rec.certificate_clinic);
    }

    window.patientCertificateSection = function (session) {
        if (!session.medical_certificate) return '<span class="odx-empty">No saved medical certificate for this visit.</span>';
        var id = Number(session.consultation_id);
        return '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">' +
            '<button type="button" class="btn-full outline" onclick="patientCertificateAction(' + id + ', this)"><i class="fas fa-file-pdf" aria-hidden="true"></i> Download PDF</button></div>' +
            '<p style="font-size:0.8rem;color:#64748b;">Save a PDF copy to your phone or computer to view and print anytime.</p>' +
            '<p id="certificateDownloadStatus-' + id + '" role="status" style="font-size:0.82rem;color:#b91c1c;"></p>' +
            '<style>' + window.MedicalCertificateTemplate.css + '</style><div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">' + body(session) + '</div>';
    };

    window.patientCertificateAction = function (id, button) {
        var session = ((window.PATIENT_RECORD || {}).visits || []).find(function (visit) {
            return Number(visit.consultation_id) === Number(id) && visit.medical_certificate;
        });
        if (!session) return;
        var rec = window.PATIENT_RECORD || {};
        var status = document.getElementById('certificateDownloadStatus-' + Number(id));
        if (status) status.textContent = '';
        if (button) button.disabled = true;
        try {
            var pdf = window.buildMedicalCertificatePdf(session.medical_certificate, rec.patient, rec.certificate_clinic);
            pdf.save('medical-certificate-' + Number(id) + '.pdf');
        } catch (error) {
            if (status) status.textContent = 'Unable to download the PDF. Please try again or reload this page.';
            console.error('Medical certificate PDF download failed:', error);
        } finally {
            if (button) button.disabled = false;
        }
    };
}());
