function parseNotifCreatedAt(createdAt) {
    if (!createdAt) return NaN;
    var created = new Date(String(createdAt) + 'Z').getTime();
    if (!isNaN(created) && created > Date.now()) {
        created = new Date(String(createdAt).replace(' ', 'T')).getTime();
    }
    return created;
}

function notifCreatedAtIsNewer(createdAt, seenAt) {
    if (!seenAt) return !!createdAt;
    if (!createdAt) return false;
    var created = parseNotifCreatedAt(createdAt);
    var seen = parseNotifCreatedAt(seenAt);
    if (!isNaN(created) && !isNaN(seen)) return created > seen;
    return String(createdAt) > String(seenAt);
}

function stopNotifBellRing(btn, seenCreatedAt) {
    if (!btn) return;
    if (btn._ringTimer) {
        clearTimeout(btn._ringTimer);
        btn._ringTimer = null;
    }
    btn.classList.remove('ringing');
    if (seenCreatedAt) {
        btn._ringSeenAt = seenCreatedAt;
    }
}

function syncNotifBellRing(btn, createdAt) {
    if (!btn) return;
    if (btn._ringTimer) {
        clearTimeout(btn._ringTimer);
        btn._ringTimer = null;
    }
    if (!createdAt || !notifCreatedAtIsNewer(createdAt, btn._ringSeenAt)) {
        btn.classList.remove('ringing');
        return;
    }
    var created = parseNotifCreatedAt(createdAt);
    var remain = 60000 - (Date.now() - created);
    if (isNaN(created) || remain <= 0) {
        btn.classList.remove('ringing');
        return;
    }
    btn.classList.add('ringing');
    btn._ringTimer = setTimeout(function() {
        btn.classList.remove('ringing');
        btn._ringTimer = null;
    }, remain);
}
