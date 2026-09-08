(function () {
    var cfg = window.PATIENT_TOUR || {};
    var storageKey = 'patient_tour_completed_' + String(cfg.userId || '0');
    var driverObj = null;
    var starting = false;
    var autoStartTimer = null;
    var sidebarSnapTimer = null;
    var sidebarStepReady = false;

    function hasCompleted() {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (e) {
            return false;
        }
    }

    function markCompleted() {
        try {
            window.localStorage.setItem(storageKey, '1');
        } catch (e) {
            /* ignore quota / private mode */
        }
    }

    function isMobileNav() {
        return window.innerWidth <= 1024;
    }

    function setSidebarOpen(open) {
        var sidebar = document.getElementById('patientSidebar') || document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggle = document.getElementById('menuToggle');
        if (!sidebar || !isMobileNav()) {
            return;
        }
        sidebar.classList.toggle('open', open);
        if (overlay) {
            overlay.classList.toggle('active', open);
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function driverFactory() {
        return window.driver && window.driver.js && window.driver.js.driver;
    }

    function stepDefs() {
        return [
            {
                element: '[data-tour="welcome"]',
                side: 'bottom',
                title: 'Your dashboard',
                description: 'This is your home overview. Track appointments, inquiries, queue status, and billing from here.'
            },
            {
                element: '[data-tour="book"]',
                side: 'bottom',
                title: 'Book a visit',
                description: 'Click here when you want to book a new appointment with the clinic.'
            },
            {
                element: '[data-tour="inquiry"]',
                side: 'right',
                title: 'Inquiry / Consultation',
                description: 'Message or consult the clinic from this menu. Use it for questions before or after a visit.'
            },
            {
                element: '[data-tour="appointments"]',
                side: 'right',
                title: 'My appointments',
                description: 'Open this page to view, manage, and book your scheduled visits.'
            },
            {
                element: '[data-tour="records"]',
                side: 'right',
                title: 'Medical records',
                description: 'Your visit history, treatments, and prescriptions live here.'
            },
            {
                element: '[data-tour="billing"]',
                side: 'right',
                title: 'Billing',
                description: 'Check balances, due amounts, and payment history on this page.'
            },
            {
                element: '[data-tour="settings"]',
                side: 'right',
                title: 'Account settings',
                description: 'Update your password, notification preferences, and other account options here.'
            },
            {
                element: '#notifBellBtn',
                side: 'bottom',
                align: 'end',
                title: 'Notifications',
                description: 'Clinic alerts such as appointment reminders and billing updates appear here.'
            },
            {
                element: '[data-tour="messages"]',
                side: 'left',
                title: 'Messages',
                description: 'Use this button to chat with the clinic anytime.'
            },
            {
                element: '[data-tour="profile"]',
                side: 'bottom',
                align: 'end',
                title: 'Your profile',
                description: 'Open this menu for your profile details or to log out.'
            },
            {
                element: '#patientTourHelpBtn',
                side: 'bottom',
                align: 'end',
                title: 'Need a recap?',
                description: 'Click this question mark anytime to play this tour again.'
            }
        ];
    }

    function buildSteps() {
        return stepDefs().filter(function (def) {
            return document.querySelector(def.element);
        }).map(function (def) {
            return {
                element: def.element,
                popover: {
                    title: def.title,
                    description: def.description,
                    side: def.side || 'bottom',
                    align: def.align || 'start'
                }
            };
        });
    }

    function addSkipButton(popover) {
        if (!popover || !popover.footer || popover.footer.querySelector('.patient-tour-skip')) {
            return;
        }
        var skip = document.createElement('button');
        skip.type = 'button';
        skip.className = 'patient-tour-skip';
        skip.textContent = 'Skip';
        skip.addEventListener('click', function () {
            markCompleted();
            if (driverObj) {
                driverObj.destroy();
            }
        });
        popover.footer.insertBefore(skip, popover.footer.firstChild);
    }

    function isSidebarTarget(element) {
        return !!(element && element.closest && element.closest('#patientSidebar'));
    }

    function getTourPopover() {
        return document.querySelector('.driver-popover.patient-tour-popover');
    }

    function setPopoverPending(hidden) {
        var popover = getTourPopover();
        if (!popover) {
            return;
        }
        popover.classList.toggle('patient-tour-popover-pending', !!hidden);
    }

    function cancelSidebarSnapTimer() {
        if (sidebarSnapTimer) {
            window.clearTimeout(sidebarSnapTimer);
            sidebarSnapTimer = null;
        }
    }

    function snapSidebarPopover(element) {
        if (!isSidebarTarget(element)) {
            setPopoverPending(false);
            return;
        }
        var popover = getTourPopover();
        if (!popover) {
            return;
        }
        var rect = element.getBoundingClientRect();
        var popHeight = popover.offsetHeight || 180;
        var top = rect.top + (rect.height / 2) - (popHeight / 2);
        var maxTop = Math.max(12, window.innerHeight - popHeight - 12);
        top = Math.max(12, Math.min(top, maxTop));

        popover.style.left = (rect.right + 14) + 'px';
        popover.style.right = 'auto';
        popover.style.top = top + 'px';
        popover.style.bottom = 'auto';

        var arrow = popover.querySelector('.driver-popover-arrow');
        if (arrow) {
            arrow.className = 'driver-popover-arrow driver-popover-arrow-side-right driver-popover-arrow-align-center';
        }
        setPopoverPending(false);
        sidebarStepReady = true;
    }

    function clearTourQuery() {
        if (!cfg.forceStart || !window.history || !window.history.replaceState) {
            return;
        }
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('tour');
            var next = url.pathname + (url.search ? url.search : '') + url.hash;
            window.history.replaceState({}, '', next);
        } catch (e) {
            /* ignore */
        }
    }

    function startTour() {
        var factory = driverFactory();
        if (typeof factory !== 'function') {
            console.warn('Patient tour could not start because Driver.js did not load.');
            return;
        }
        if (starting) {
            return;
        }

        var steps = buildSteps();
        if (!steps.length) {
            return;
        }

        if (autoStartTimer) {
            window.clearTimeout(autoStartTimer);
            autoStartTimer = null;
        }

        starting = true;
        if (driverObj) {
            try {
                driverObj.destroy();
            } catch (e) {
                /* ignore */
            }
            driverObj = null;
            starting = true;
        }

        var notifDropdown = document.getElementById('notifDropdown');
        if (notifDropdown) {
            notifDropdown.classList.remove('open');
        }

        driverObj = factory({
            showProgress: true,
            progressText: '{{current}} of {{total}}',
            nextBtnText: 'Next',
            prevBtnText: 'Back',
            doneBtnText: 'Done',
            showButtons: ['next', 'previous'],
            popoverClass: 'patient-tour-popover',
            overlayOpacity: 0.55,
            stagePadding: 8,
            stageRadius: 10,
            allowClose: false,
            disableActiveInteraction: true,
            smoothScroll: false,
            animate: true,
            steps: steps,
            onPopoverRender: function (popover, options) {
                addSkipButton(popover);
                var el = (options && options.state && (options.state.activeElement || options.state.activeElem))
                    || document.querySelector('.driver-active-element');
                if (isSidebarTarget(el) && !sidebarStepReady && popover && popover.wrapper) {
                    popover.wrapper.classList.add('patient-tour-popover-pending');
                }
            },
            onHighlightStarted: function (element) {
                cancelSidebarSnapTimer();
                sidebarStepReady = false;
                if (isSidebarTarget(element)) {
                    setPopoverPending(true);
                    if (isMobileNav()) {
                        var sidebar = document.getElementById('patientSidebar') || document.querySelector('.sidebar');
                        var alreadyOpen = !!(sidebar && sidebar.classList.contains('open'));
                        setSidebarOpen(true);
                        if (!alreadyOpen) {
                            sidebarSnapTimer = window.setTimeout(function () {
                                sidebarSnapTimer = null;
                                snapSidebarPopover(element);
                            }, 280);
                        }
                    }
                } else if (isMobileNav()) {
                    setSidebarOpen(false);
                    setPopoverPending(false);
                }
            },
            onHighlighted: function (element) {
                if (isSidebarTarget(element) && sidebarSnapTimer) {
                    return;
                }
                snapSidebarPopover(element);
            },
            onDestroyed: function () {
                cancelSidebarSnapTimer();
                markCompleted();
                setSidebarOpen(false);
                starting = false;
                driverObj = null;
            }
        });

        clearTourQuery();
        driverObj.drive();
        starting = false;
    }

    function onHelpClick(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        if (autoStartTimer) {
            window.clearTimeout(autoStartTimer);
            autoStartTimer = null;
        }
        if (!cfg.isDashboard) {
            window.location.href = cfg.dashboardUrl || 'patient-dashboard.php?tour=1';
            return;
        }
        startTour();
    }

    function bindHelp() {
        var helpBtn = document.getElementById('patientTourHelpBtn');
        if (!helpBtn) {
            return;
        }
        helpBtn.addEventListener('click', onHelpClick);
    }

    function maybeAutoStart() {
        if (!cfg.isDashboard || driverObj) {
            return;
        }
        if (cfg.forceStart) {
            startTour();
            return;
        }
        if (cfg.autoStart && !hasCompleted()) {
            startTour();
        }
    }

    function init() {
        bindHelp();
        autoStartTimer = window.setTimeout(maybeAutoStart, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.startPatientTour = startTour;
})();
