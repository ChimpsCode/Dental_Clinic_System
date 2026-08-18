(function(window) {
    'use strict';

    function pad2(n) {
        return String(n).length < 2 ? '0' + n : String(n);
    }

    function localTodayStr() {
        var now = new Date();
        return now.getFullYear() + '-' + pad2(now.getMonth() + 1) + '-' + pad2(now.getDate());
    }

    function MiniCalendar(cfg) {
        var currentMonth = cfg.month;
        var busy = false;
        var container = document.getElementById(cfg.containerId);
        var labelEl = document.getElementById(cfg.labelId);

        function requestMonth(targetMonth) {
            if (busy) return;
            busy = true;
            fetch(cfg.endpoint + '?cal=' + targetMonth)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    busy = false;
                    if (!data.success) return;
                    currentMonth = data.month;
                    if (labelEl) labelEl.textContent = data.month_label;
                    window[cfg.appointmentVar] = data.appointments || {};
                    renderGrid(data.appointments || {});
                })
                .catch(function() { busy = false; });
        }

        function renderGrid(appointments) {
            if (!container) return;
            var parts = currentMonth.split('-');
            var year = parseInt(parts[0], 10);
            var monthIdx = parseInt(parts[1], 10) - 1;
            var todayStr = localTodayStr();

            var html = [
                '<div class="mc-day-head">Sun</div>',
                '<div class="mc-day-head">Mon</div>',
                '<div class="mc-day-head">Tue</div>',
                '<div class="mc-day-head">Wed</div>',
                '<div class="mc-day-head">Thu</div>',
                '<div class="mc-day-head">Fri</div>',
                '<div class="mc-day-head">Sat</div>'
            ];

            var firstDow = new Date(year, monthIdx, 1).getDay();
            var totalDays = new Date(year, monthIdx + 1, 0).getDate();

            for (var i = 0; i < firstDow; i++) {
                html.push('<div class="mc-day mc-empty"></div>');
            }

            for (var d = 1; d <= totalDays; d++) {
                var dateStr = year + '-' + pad2(monthIdx + 1) + '-' + pad2(d);
                var dayAppts = (appointments && appointments[dateStr]) || [];
                var cls = 'mc-day';
                if (dayAppts.length) cls += ' has-appt';
                if (dateStr === todayStr) cls += ' today';

                var cell = '<div class="' + cls + '"';
                if (dayAppts.length) {
                    cell += ' onclick="' + cfg.openDayFn + '(\'' + dateStr + '\')"';
                    cell += ' title="' + dayAppts.length + ' scheduled"';
                }
                cell += '><span class="mc-day-num">' + d + '</span>';
                if (dayAppts.length) cell += '<span class="mc-dot"></span>';
                cell += '</div>';
                html.push(cell);
            }

            container.innerHTML = html.join('');
        }

        function load(dir) {
            var m = new Date(parseInt(currentMonth.split('-')[0], 10), parseInt(currentMonth.split('-')[1], 10) - 1, 1);
            m.setMonth(m.getMonth() + (dir === 'next' ? 1 : -1));
            var target = m.getFullYear() + '-' + pad2(m.getMonth() + 1);
            requestMonth(target);
        }

        var instance = {
            refresh: function() {
                requestMonth(currentMonth);
            }
        };

        var prevBtn = document.getElementById(cfg.prevBtnId);
        var nextBtn = document.getElementById(cfg.nextBtnId);
        if (prevBtn) prevBtn.addEventListener('click', function(e) { e.preventDefault(); load('prev'); });
        if (nextBtn) nextBtn.addEventListener('click', function(e) { e.preventDefault(); load('next'); });

        return instance;
    }

    window.MiniCalendar = MiniCalendar;
})(window);
