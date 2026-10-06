const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { jsPDF } = require('../assets/js/vendor/jspdf.umd.min.js');

const root = path.resolve(__dirname, '..');
let reportedError;
const status = { textContent: '' };
const context = {
    window: { jspdf: { jsPDF } },
    document: { getElementById: () => status },
    console: { error: (...args) => { reportedError = args; } }
};
vm.createContext(context);
for (const script of ['medical-certificate-template.js', 'medical-certificate-pdf.js', 'patient-medical-certificate.js']) {
    vm.runInContext(fs.readFileSync(path.join(root, 'assets/js', script), 'utf8'), context);
}

const logo = 'data:image/png;base64,' + fs.readFileSync(path.join(root, 'assets/images/Logo.png')).toString('base64');
const clinic = { name: 'RF Dental Clinic', address: 'Cagayan de Oro City', phone: '0123456789', logo };
const patient = { first_name: 'Maria', last_name: 'Santos', age: '28', gender: 'Female' };
const certificate = {
    purpose: 'Employment', findings: 'Dental examination completed.', recommendation: 'Fit to return to work.',
    issued_at: '2026-10-06', rest_from: '2026-10-06', rest_to: '2026-10-07', remarks: 'Follow up as advised.',
    dentist_name: 'Dr. Example Dentist', dentist_signature: logo
};
const build = context.window.buildMedicalCertificatePdf;
const pdf = build(certificate, patient, clinic);
const bytes = Buffer.from(pdf.output('arraybuffer'));
assert.match(bytes.toString('ascii', 0, 8), /^%PDF-/);
assert.equal(pdf.getNumberOfPages(), 1, 'A typical certificate fits on one A4 page');
assert.match(bytes.toString('latin1'), /\/Subtype \/Image/, 'Embedded images are included in the PDF');
assert.match(bytes.toString('latin1'), /\/ca 0\.07/, 'Watermark uses a faint opacity');
const text = pdf.internal.pages.slice(1).flat().join('\n');
assert.ok(text.indexOf('/I0 Do') < text.indexOf('(RF DENTAL CLINIC)'), 'Watermark is drawn behind the letterhead and text');
for (const expected of ['Maria Santos', 'Dental examination completed.', 'Fit to return to work.', 'Follow up as advised.', 'Dr. Example Dentist', 'October 6, 2026']) {
    assert.ok(text.includes(expected), 'PDF retains: ' + expected);
}

const longFindings = Array.from({ length: 250 }, (_, i) => 'Finding number ' + (i + 1) + ': clinical details recorded.').join('\n');
const longPdf = build({ ...certificate, findings: longFindings }, patient, clinic);
assert.ok(longPdf.getNumberOfPages() > 1, 'Long records continue on additional pages');
const longText = longPdf.internal.pages.slice(1).flat().join('\n');
for (let i = 1; i <= 250; i++) assert.ok(longText.includes('Finding number ' + i + ':'), 'No findings lost at page breaks');
assert.ok(longText.includes('Dr. Example Dentist'), 'Signature area remains on long documents');
for (const page of longPdf.internal.pages.slice(1)) {
    assert.ok(page.join('\n').includes('/I0 Do'), 'Watermark repeats on every PDF page');
}
assert.throws(() => build({ ...certificate, dentist_signature: 'javascript:alert(1)' }, patient, clinic), /Invalid certificate image/);

const session = { consultation_id: 42, medical_certificate: certificate };
context.window.PATIENT_RECORD = { visits: [session, { consultation_id: 43 }], patient, certificate_clinic: clinic };
const section = context.window.patientCertificateSection(session);
assert.match(section, /Download PDF/);
assert.match(section, /class="mc-watermark"/, 'Patient preview includes the logo watermark');
assert.ok(!section.includes('.html') && !section.includes('Print / Save as PDF'), 'Patient action is a direct PDF download');
assert.match(context.window.patientCertificateSection({}), /No saved medical certificate/);
let savedFilename, calls = 0;
context.window.buildMedicalCertificatePdf = (cert, p, c) => {
    calls++;
    assert.equal(cert, certificate);
    assert.equal(p, patient);
    assert.equal(c, clinic);
    return { save(filename) { savedFilename = filename; } };
};
context.window.patientCertificateAction(43);
assert.equal(calls, 0, 'Unsaved visits cannot download a certificate');
const button = { disabled: false };
context.window.patientCertificateAction(42, button);
assert.equal(savedFilename, 'medical-certificate-42.pdf');
assert.equal(button.disabled, false);
assert.equal(status.textContent, '');
context.window.buildMedicalCertificatePdf = () => { throw new Error('Download failed'); };
context.window.patientCertificateAction(42, button);
assert.match(status.textContent, /Unable to download the PDF/);
assert.equal(button.disabled, false, 'Download can be retried after an error');
assert.ok(reportedError);

console.log('PASS: real PDF, embedded images, complete text, page breaks, saved-only download, and error recovery');
console.log('Typical certificate PDF: ' + bytes.length + ' bytes');
