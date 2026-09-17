(function (global) {
    function escapeInquiryText(text) {
        if (typeof global.escapeHtml === 'function') {
            return global.escapeHtml(text);
        }
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseInquiryQuotedMessage(text) {
        const raw = String(text || '');
        const match = raw.match(/^\[\[QUOTE\]\]\s*([\s\S]*?)\s*\[\[\/QUOTE\]\]\s*([\s\S]*)$/);
        if (match) {
            return { quote: match[1].trim(), message: match[2] };
        }
        const loose = raw.match(/^([\s\S]*?)\[\[\/QUOTE\]\]\s*([\s\S]*)$/);
        if (loose) {
            return { quote: loose[1].replace(/\[\[QUOTE\]\]/g, '').trim(), message: loose[2] };
        }
        return { quote: '', message: raw };
    }

    function wrapInquiryQuotedMessage(quote, message) {
        const cleanQuote = String(quote || '').trim();
        const cleanMessage = String(message || '');
        if (!cleanQuote) {
            return cleanMessage;
        }
        return '[[QUOTE]]\n' + cleanQuote + '\n[[/QUOTE]]\n' + cleanMessage;
    }

    function inquiryQuotedMessageHtml(text) {
        const parsed = parseInquiryQuotedMessage(text);
        let html = '';
        if (parsed.quote) {
            html += '<div class="inq-quote">' + escapeInquiryText(parsed.quote).replace(/\n/g, '<br>') + '</div>';
        }
        if (parsed.message) {
            html += escapeInquiryText(parsed.message).replace(/\n/g, '<br>');
        }
        return html;
    }

    global.parseInquiryQuotedMessage = parseInquiryQuotedMessage;
    global.wrapInquiryQuotedMessage = wrapInquiryQuotedMessage;
    global.inquiryQuotedMessageHtml = inquiryQuotedMessageHtml;
})(window);
