(function (global) {
  'use strict';

  var NAME_CHAR = /[\p{L}\s\-]/u;
  var NAME_PATTERN = /^[\p{L}\s\-]+$/u;
  var NAME_STRIP = /[^\p{L}\s\-]+/gu;
  var EMAIL_BLOCKED_CHAR = /[<>()\/"]/;
  var EMAIL_STRIP = /[<>()\/"]+/g;
  var PHONE_PATTERN = /^09\d{9}$/;
  var USERNAME_PATTERN = /^[A-Za-z0-9._\-]+$/;
  var ADDRESS_PATTERN = /^[\p{L}\p{N}\s.,#'\-\/]+$/u;
  var CONTROL_KEYS = {
    Backspace: true, Delete: true, Tab: true, Escape: true, Enter: true,
    ArrowLeft: true, ArrowRight: true, ArrowUp: true, ArrowDown: true,
    Home: true, End: true
  };

  function str(value) {
    return String(value == null ? '' : value);
  }

  function containsHtml(value) {
    var s = str(value);
    if (!s) return false;
    if (/<[^>]*>/.test(s)) return true;
    if (/[<>]/.test(s)) return true;
    if (/on\w+\s*=/i.test(s)) return true;
    if (/javascript\s*:/i.test(s)) return true;
    return false;
  }

  function ok(value) {
    return { ok: true, value: value, error: null };
  }

  function fail(error) {
    return { ok: false, value: '', error: error };
  }

  function lettersOnly(value) {
    return str(value).replace(NAME_STRIP, '');
  }

  function emailSafe(value) {
    return str(value).replace(EMAIL_STRIP, '');
  }

  function name(value, required, label) {
    var field = label || 'Name';
    var s = lettersOnly(value).trim().replace(/\s+/g, ' ');
    if (!s) return required ? fail(field + ' is required.') : ok('');
    if (s.length > 80) return fail(field + ' is too long.');
    if (!NAME_PATTERN.test(s)) {
      return fail(field + ' may only contain letters, spaces, and hyphens.');
    }
    return ok(s);
  }

  function email(value, required) {
    var s = emailSafe(value).trim().toLowerCase();
    if (!s) return required ? fail('Email is required.') : ok('');
    if (EMAIL_BLOCKED_CHAR.test(str(value))) {
      return fail('Please enter a valid email address.');
    }
    if (s.length > 254) return fail('Email is too long.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)) return fail('Please enter a valid email address.');
    return ok(s);
  }

  function phone(value, required) {
    var digits = str(value).replace(/\D+/g, '');
    if (!digits) return required ? fail('Phone number is required.') : ok('');
    if (!PHONE_PATTERN.test(digits)) {
      return fail('Enter a valid 11-digit mobile number starting with 09.');
    }
    return ok(digits);
  }

  function message(value, required, maxLen, label) {
    var field = label || 'Message';
    var limit = maxLen || 2000;
    var s = str(value).trim();
    if (!s) return required ? fail(field + ' is required.') : ok('');
    if (containsHtml(s)) return fail(field + ' contains invalid characters.');
    if (s.length > limit) return fail(field + ' is too long.');
    return ok(s);
  }

  function address(value, required) {
    var s = str(value).trim().replace(/[ \t]+/g, ' ');
    if (!s) return required ? fail('Address is required.') : ok('');
    if (containsHtml(s)) return fail('Address contains invalid characters.');
    if (s.length > 255) return fail('Address is too long.');
    if (!ADDRESS_PATTERN.test(s)) return fail('Address contains invalid characters.');
    return ok(s);
  }

  function loginIdentifier(value) {
    var raw = str(value);
    if (EMAIL_BLOCKED_CHAR.test(raw)) {
      return fail('Please enter a valid username or email.');
    }
    var s = emailSafe(raw).trim();
    if (!s) return fail('Username is required.');
    if (s.length > 254) return fail('Username is too long.');
    if (s.indexOf('@') !== -1) return email(s, true);
    if (!USERNAME_PATTERN.test(s)) return fail('Username contains invalid characters.');
    return ok(s);
  }

  function firstError(results) {
    for (var i = 0; i < results.length; i++) {
      if (results[i] && !results[i].ok) return results[i];
    }
    return null;
  }

  function alertIfInvalid(results) {
    var bad = firstError(results);
    if (!bad) return true;
    if (typeof global.alert === 'function') {
      global.alert(bad.error);
    }
    return false;
  }

  function isModifierKey(e) {
    return e.ctrlKey || e.metaKey || e.altKey;
  }

  function bindFilteredField(field, keepChar, stripFn) {
    if (!field || field.dataset.formSanitizeBound === '1') return;
    field.dataset.formSanitizeBound = '1';

    var apply = function() {
      var cleaned = stripFn(field.value);
      if (field.value !== cleaned) {
        field.value = cleaned;
      }
    };

    field.addEventListener('keydown', function(e) {
      if (isModifierKey(e)) return;
      if (CONTROL_KEYS[e.key]) return;
      if (e.key && e.key.indexOf('F') === 0 && e.key.length <= 3) return;
      if (e.key === 'Process' || e.key === 'Unidentified') return;
      if (e.key.length === 1 && keepChar(e.key)) return;
      if (e.key.length === 1) {
        e.preventDefault();
      }
    });

    field.addEventListener('beforeinput', function(e) {
      if (!e.data) return;
      if (!keepChar(e.data) && stripFn(e.data) === '') {
        e.preventDefault();
      }
    });

    field.addEventListener('input', apply);
    field.addEventListener('paste', function(e) {
      e.preventDefault();
      var pasted = '';
      if (e.clipboardData) {
        pasted = e.clipboardData.getData('text') || '';
      } else if (global.clipboardData) {
        pasted = global.clipboardData.getData('Text') || '';
      }
      var cleaned = stripFn(pasted);
      var start = field.selectionStart;
      var end = field.selectionEnd;
      var current = field.value;
      if (typeof start === 'number' && typeof end === 'number') {
        field.value = current.slice(0, start) + cleaned + current.slice(end);
        var pos = start + cleaned.length;
        if (field.setSelectionRange) {
          field.setSelectionRange(pos, pos);
        }
      } else {
        field.value = stripFn(current + pasted);
      }
    });
    field.addEventListener('blur', apply);
    apply();
  }

  function bindLettersOnlyField(field) {
    bindFilteredField(field, function(ch) {
      return NAME_CHAR.test(ch);
    }, lettersOnly);
  }

  function bindEmailSafeField(field) {
    bindFilteredField(field, function(ch) {
      return !EMAIL_BLOCKED_CHAR.test(ch);
    }, emailSafe);
  }

  function bindSelectorList(selectors, binder) {
    for (var i = 0; i < selectors.length; i++) {
      var nodes = document.querySelectorAll(selectors[i]);
      for (var n = 0; n < nodes.length; n++) {
        binder(nodes[n]);
      }
    }
  }

  function bindWelcomeFields() {
    bindSelectorList([
      '.appointment-form [name="first_name"]',
      '.appointment-form [name="middle_name"]',
      '.appointment-form [name="last_name"]',
      '.contact-form [name="first_name"]',
      '.contact-form [name="middle_name"]',
      '.contact-form [name="last_name"]',
      '#reg-first-name-display',
      '#reg-middle-name-display',
      '#reg-last-name-display',
      '#reg-emergency-name',
      '#reg-first-name',
      '#reg-middle-name',
      '#reg-last-name',
      '#reg_first_name',
      '#reg_middle_name',
      '#reg_last_name',
      '#reg_emergency_name'
    ], bindLettersOnlyField);

    bindSelectorList([
      '.appointment-form [name="email"]',
      '.contact-form [name="email"]',
      '#reg-email',
      '#reg-email-hidden',
      '#welcome-back-email',
      '#reg_email',
      '#fp_email',
      '#username'
    ], bindEmailSafeField);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindWelcomeFields);
  } else {
    bindWelcomeFields();
  }

  global.FormSanitize = {
    containsHtml: containsHtml,
    name: name,
    email: email,
    phone: phone,
    message: message,
    address: address,
    loginIdentifier: loginIdentifier,
    firstError: firstError,
    alertIfInvalid: alertIfInvalid,
    bindLettersOnlyField: bindLettersOnlyField,
    bindEmailSafeField: bindEmailSafeField,
    lettersOnly: lettersOnly,
    emailSafe: emailSafe
  };
})(window);
