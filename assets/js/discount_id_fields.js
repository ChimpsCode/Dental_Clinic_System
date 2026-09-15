/**
 * Show senior ID fields only when age >= 60.
 * Show PWD ID fields only when the PWD checkbox is checked.
 */
(function (window) {
    function ageFromDob(value) {
        if (!value) return null;
        const birth = new Date(value);
        if (isNaN(birth.getTime())) return null;
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age >= 0 ? age : null;
    }

    function findDob(root) {
        return root.querySelector('[data-discount-dob]')
            || root.querySelector('input[name="date_of_birth"]')
            || root.querySelector('input[name="birthdate"]');
    }

    function sync(root) {
        if (!root) return;
        const dob = findDob(root);
        const seniorWrap = root.querySelector('[data-senior-id-fields]');
        const pwdCheck = root.querySelector('[data-pwd-toggle]');
        const pwdWrap = root.querySelector('[data-pwd-id-fields]');

        if (seniorWrap) {
            const age = ageFromDob(dob ? dob.value : '');
            const showSenior = (age !== null && age >= 60);
            seniorWrap.style.display = showSenior ? '' : 'none';
            setFieldsRequired(seniorWrap, showSenior);
        }
        if (pwdWrap) {
            const showPwd = Boolean(pwdCheck && pwdCheck.checked);
            pwdWrap.style.display = showPwd ? '' : 'none';
            setFieldsRequired(pwdWrap, showPwd);
        }
    }

    function setFieldsRequired(wrap, isRequired) {
        wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'checkbox' || el.type === 'hidden') return;
            if (isRequired) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
    }

    function bindIdCodeInputs(root) {
        root.querySelectorAll('input[name="senior_citizen_id"], input[name="pwd_id"]').forEach(function (el) {
            if (el.dataset.idCodeBound) return;
            el.dataset.idCodeBound = '1';
            el.setAttribute('pattern', '[A-Za-z0-9\\-/]+');
            el.setAttribute('title', 'Letters, numbers, hyphens, and slashes only');
            el.addEventListener('input', function () {
                this.value = this.value.replace(/[^A-Za-z0-9\-\/]/g, '');
            });
        });
    }

    function init(root) {
        root = root || document;
        const dob = findDob(root);
        const pwdCheck = root.querySelector('[data-pwd-toggle]');

        if (dob && !dob.dataset.discountDobBound) {
            dob.dataset.discountDobBound = '1';
            dob.addEventListener('change', function () { sync(root); });
            dob.addEventListener('input', function () { sync(root); });
        }
        if (pwdCheck && !pwdCheck.dataset.discountPwdBound) {
            pwdCheck.dataset.discountPwdBound = '1';
            pwdCheck.addEventListener('change', function () { sync(root); });
        }
        bindIdCodeInputs(root);
        sync(root);
    }

    window.DiscountIdFields = {
        ageFromDob: ageFromDob,
        sync: sync,
        init: init
    };

    document.addEventListener('DOMContentLoaded', function () {
        init(document);
    });
})(window);
