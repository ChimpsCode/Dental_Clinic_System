<?php
/**
 * Shared 2FA setup/disable modals for admin, staff, and dentist profile pages.
 */
?>
<style>
.twofa-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100001; justify-content: center; align-items: center; padding: 16px; }
.twofa-modal-overlay.active { display: flex; }
.twofa-modal-content { background: #fff; border-radius: 12px; padding: 24px; max-width: 460px; width: 100%; max-height: calc(100vh - 32px); overflow-y: auto; position: relative; z-index: 100002; }
.twofa-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.twofa-modal-header h3 { margin: 0; font-size: 18px; color: #1a1a2e; }
.twofa-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280; line-height: 1; }
.twofa-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; margin-top: 6px; }
.twofa-status.on { background: #d1fae5; color: #065f46; }
.twofa-status.off { background: #f3f4f6; color: #6b7280; }
.twofa-steps { margin: 0 0 16px; padding-left: 18px; color: #4b5563; font-size: 14px; line-height: 1.5; }
.twofa-steps li { margin-bottom: 4px; }
.twofa-qr-wrap { display: flex; justify-content: center; margin: 8px 0 16px; }
.twofa-qr-wrap img, .twofa-qr-wrap svg { width: 220px; height: 220px; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; }
.twofa-secret-box { display: flex; gap: 8px; align-items: stretch; }
.twofa-secret-box input { flex: 1; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: 1px; text-align: center; font-weight: 600; }
.twofa-error { display: none; margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fee2e2; color: #991b1b; font-size: 14px; }
.twofa-error.visible { display: block; }
.twofa-hint { font-size: 13px; color: #6b7280; margin: 0 0 12px; }
.twofa-field-label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; }
.twofa-field-input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
.twofa-actions { display: flex; gap: 12px; }
.twofa-modal-content .btn-primary,
.twofa-modal-content .btn-secondary { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px; }
.twofa-modal-content .btn-primary { background: #005daa; color: #fff; }
.twofa-modal-content .btn-primary:hover { background: #004080; }
.twofa-modal-content .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.twofa-modal-content .btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
.twofa-modal-content .btn-secondary:hover { background: #e5e7eb; }
.twofa-success-body { text-align: center; padding: 8px 0 4px; }
.twofa-success-icon { width: 64px; height: 64px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 30px; font-weight: 700; }
.twofa-success-icon.enabled { background: #d1fae5; color: #059669; }
.twofa-success-icon.disabled { background: #fee2e2; color: #dc2626; }
.twofa-success-message { margin: 0; color: #4b5563; font-size: 15px; line-height: 1.5; }
</style>

<div class="twofa-modal-overlay" id="twoFactorSetupModal">
    <div class="twofa-modal-content">
        <div class="twofa-modal-header">
            <h3>Set up Two-Factor Authentication</h3>
            <button type="button" class="twofa-modal-close" id="twoFactorSetupCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="twofa-error" id="twoFactorSetupError"></div>
        <ol class="twofa-steps">
            <li>Install Google Authenticator (or any authenticator app) on your phone.</li>
            <li>Scan the QR code below, or enter the setup key manually.</li>
            <li>Enter the 6-digit code from the app to confirm.</li>
        </ol>
        <div class="twofa-qr-wrap" id="twoFactorQrWrap">
            <div style="padding: 40px; color: #6b7280;">Generating QR code...</div>
        </div>
        <div style="margin-bottom: 16px;">
            <label class="twofa-field-label">Setup key (manual entry)</label>
            <div class="twofa-secret-box">
                <input type="text" class="twofa-field-input" id="twoFactorSetupKey" readonly value="">
                <button type="button" class="btn-secondary" id="twoFactorCopyKeyBtn">Copy</button>
            </div>
            <p class="twofa-hint">Spaces are ignored by authenticator apps.</p>
        </div>
        <div style="margin-bottom: 16px;">
            <label class="twofa-field-label" for="twoFactorConfirmCode">Verification code</label>
            <input type="text" class="twofa-field-input" id="twoFactorConfirmCode" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="Enter 6-digit code" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
        </div>
        <div class="twofa-actions">
            <button type="button" class="btn-primary" id="twoFactorConfirmBtn" style="flex: 1;">Enable 2FA</button>
            <button type="button" class="btn-secondary" id="twoFactorSetupCancelBtn">Cancel</button>
        </div>
    </div>
</div>

<div class="twofa-modal-overlay" id="twoFactorDisableModal">
    <div class="twofa-modal-content">
        <div class="twofa-modal-header">
            <h3>Disable Two-Factor Authentication</h3>
            <button type="button" class="twofa-modal-close" id="twoFactorDisableCloseBtn" aria-label="Close">&times;</button>
        </div>
        <div class="twofa-error" id="twoFactorDisableError"></div>
        <p class="twofa-hint">Enter the current 6-digit code from your authenticator app to turn off 2FA for this account.</p>
        <div style="margin-bottom: 16px;">
            <label class="twofa-field-label" for="twoFactorDisableCode">Verification code</label>
            <input type="text" class="twofa-field-input" id="twoFactorDisableCode" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="Enter 6-digit code" style="text-align: center; letter-spacing: 6px; font-size: 18px;">
        </div>
        <div class="twofa-actions">
            <button type="button" class="btn-primary" id="twoFactorDisableConfirmBtn" style="flex: 1; background: #dc2626;">Disable 2FA</button>
            <button type="button" class="btn-secondary" id="twoFactorDisableCancelBtn">Cancel</button>
        </div>
    </div>
</div>

<div class="twofa-modal-overlay" id="twoFactorSuccessModal">
    <div class="twofa-modal-content" style="max-width: 400px;">
        <div class="twofa-success-body">
            <div class="twofa-success-icon enabled" id="twoFactorSuccessIcon">✓</div>
            <h3 id="twoFactorSuccessTitle" style="margin: 0 0 10px; font-size: 20px; color: #1a1a2e;">Success</h3>
            <p class="twofa-success-message" id="twoFactorSuccessMessage"></p>
        </div>
        <div class="twofa-actions" style="margin-top: 20px;">
            <button type="button" class="btn-primary" id="twoFactorSuccessOkBtn" style="flex: 1;">OK</button>
        </div>
    </div>
</div>
