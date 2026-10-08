<dialog id="paymentVoidDialog" aria-labelledby="paymentVoidTitle" aria-describedby="paymentVoidSummary paymentVoidDescription">
    <form id="paymentVoidForm">
        <div class="payment-void-header">
            <h2 id="paymentVoidTitle">Void Payment</h2>
            <p id="paymentVoidDescription">Correct a payment recorded by mistake. For returned money, use a refund record.</p>
            <p id="paymentVoidSummary"></p>
        </div>
        <div class="payment-void-body">
            <div class="payment-void-field">
                <label for="paymentVoidReason">Reason for voiding</label>
                <textarea id="paymentVoidReason" name="reason" maxlength="500" required rows="3" placeholder="Briefly explain why this payment needs to be voided"></textarea>
            </div>
            <div class="payment-void-field">
                <label for="paymentVoidAdmin">Approving admin</label>
                <select id="paymentVoidAdmin" name="admin_id" aria-describedby="paymentVoidError" required disabled><option value="">Loading admins…</option></select>
            </div>
            <div class="payment-void-field">
                <label for="paymentVoidPin">Admin approval PIN</label>
                <input id="paymentVoidPin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" placeholder="Enter six-digit PIN" required>
            </div>
            <p id="paymentVoidError" role="alert"></p>
        </div>
        <div class="payment-void-footer">
            <button type="button" id="paymentVoidCancel">Cancel</button>
            <button type="submit" id="paymentVoidConfirm">Confirm Void</button>
        </div>
    </form>
</dialog>
<style>
#paymentVoidDialog::backdrop { background: #0007; }
#paymentVoidDialog { position:fixed;inset:0;margin:auto;transform:none;height:fit-content;width:min(480px,calc(100vw - 32px));box-sizing:border-box;max-height:calc(100dvh - 32px);overflow:auto;border:1px solid #e2e8f0;border-radius:16px;padding:0;background:#fff;color:#334155;font:400 14px/1.5 "Segoe UI",Arial,sans-serif;box-shadow:0 20px 60px #0f172a30; }
#paymentVoidDialog form { margin:0; }
#paymentVoidDialog .payment-void-header { padding:24px 24px 0; }
#paymentVoidDialog h2 { margin:0 0 8px;color:#1e293b;font-size:20px;line-height:1.3;font-weight:600; }
#paymentVoidDescription { margin:0;color:#64748b;font-size:13px;line-height:1.6; }
#paymentVoidSummary { margin:16px 0 0;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:400;line-height:1.5;overflow-wrap:anywhere; }
#paymentVoidDialog .payment-void-body { padding:20px 24px 24px; }
#paymentVoidDialog .payment-void-field + .payment-void-field { margin-top:18px; }
#paymentVoidDialog label { display:block;font-size:13px;font-weight:500;margin:0 0 7px;color:#475569; }
#paymentVoidDialog input,#paymentVoidDialog select,#paymentVoidDialog textarea { box-sizing:border-box;width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:10px 12px;margin:0;background:#fff;color:#334155;font:inherit; }
#paymentVoidDialog textarea { resize:vertical;min-height:86px;display:block; }
#paymentVoidDialog input::placeholder,#paymentVoidDialog textarea::placeholder { color:#94a3b8;font-size:13px; }
#paymentVoidDialog input:focus,#paymentVoidDialog select:focus,#paymentVoidDialog textarea:focus { outline:none;border-color:#005daa;box-shadow:0 0 0 3px #005daa14; }
#paymentVoidError { margin:14px 0 0;color:#b91c1c;font-size:13px;line-height:1.5; }
#paymentVoidError:empty { display:none; }
#paymentVoidDialog .payment-void-footer { display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #e2e8f0;background:#f8fafc; }
#paymentVoidDialog button { border:1px solid #cbd5e1;border-radius:8px;padding:9px 16px;min-height:40px;background:white;color:#475569;font:500 13px/1.5 "Segoe UI",Arial,sans-serif;cursor:pointer; }
#paymentVoidDialog #paymentVoidCancel:hover { background:#f1f5f9; }
#paymentVoidDialog #paymentVoidConfirm { background:#b91c1c;border-color:#b91c1c;color:white; }
#paymentVoidDialog #paymentVoidConfirm:hover:not(:disabled) { background:#991b1b;border-color:#991b1b; }
#paymentVoidDialog button:focus-visible { outline:2px solid #005daa;outline-offset:3px; }
.payment-void-trigger { border:1px solid #d1d5db;border-radius:7px;padding:8px 12px;cursor:pointer; }
.payment-void-trigger { color:#b91c1c;background:white;margin:0;display:inline-block;white-space:nowrap; }
.pay-history-table .payment-history-action { text-align:center;vertical-align:middle;width:150px; }
#paymentVoidDialog button:disabled { opacity:.6;cursor:wait; }
#paymentVoidDialog select:disabled { background:#f3f4f6;color:#6b7280;cursor:not-allowed; }
.payment-void-details { display:block;font-size:12px;color:#6b7280;margin-top:4px;white-space:pre-line; }
</style>
<script src="../../assets/js/payment-approval.js"></script>
