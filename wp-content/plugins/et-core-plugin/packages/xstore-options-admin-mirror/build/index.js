/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/index.js"
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./style.scss */ "./src/style.scss");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







const boot = window.XStoreOptionsAdmin || {};
const codeEditorSettings = boot.codeEditor || null;
_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().createNonceMiddleware(boot.nonce));
try {
  const root = window.wpApiSettings && window.wpApiSettings.root ? window.wpApiSettings.root : boot.root ? boot.root : null;
  if (root && (_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().createRootURLMiddleware)) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().createRootURLMiddleware(root));
  }
} catch (e) {}
function isInvalidJsonError(e) {
  return !!(e && e.code === 'invalid_json');
}
function getStatusFromError(e) {
  const s = e && e.data && typeof e.data.status !== 'undefined' ? Number(e.data.status) : null;
  return Number.isFinite(s) ? s : null;
}
function toRestRouteUrl(path) {
  const p = String(path || '');
  const route = p.startsWith('/') ? p : `/${p}`;
  const base = String(boot.root || '').trim();
  if (!base) return null;
  const u = new URL(base, window.location.href);
  if (u.searchParams.has('rest_route')) {
    u.searchParams.set('rest_route', route);
    return u.toString();
  }
  u.pathname = String(u.pathname || '').replace(/\/wp-json\/?$/, '/');
  u.search = '';
  u.hash = '';
  u.searchParams.set('rest_route', route);
  return u.toString();
}
async function fetchViaRestRoute(options) {
  const url = toRestRouteUrl(options && options.path ? options.path : '');
  if (!url) throw options;
  const method = options && options.method ? String(options.method).toUpperCase() : 'GET';
  const headers = {
    Accept: 'application/json',
    'X-WP-Nonce': String(boot.nonce || '')
  };
  let body;
  if (options && typeof options.data !== 'undefined' && method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.data);
  }
  const res = await fetch(url, {
    method,
    headers,
    body,
    credentials: 'same-origin'
  });
  const ct = String(res.headers.get('content-type') || '');
  if (!ct.includes('application/json')) {
    throw {
      code: 'invalid_json',
      message: 'Non-JSON response',
      data: {
        status: res.status
      }
    };
  }
  const json = await res.json();
  if (res.ok) return json;
  throw {
    code: json && json.code ? json.code : 'rest_error',
    message: json && json.message ? json.message : 'Request failed',
    data: json && json.data ? json.data : {
      status: res.status
    }
  };
}
_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default().use((options, next) => next(options).catch(e => {
  const p = options && options.path ? String(options.path) : '';
  if (p.startsWith('/xstore-options/v1/') && isInvalidJsonError(e)) {
    return fetchViaRestRoute(options);
  }
  throw e;
}));
function clamp(n, min, max) {
  return Math.min(Math.max(n, min), max);
}
function isThemeCustomCssSetting(setting) {
  const key = String(setting || '');
  return key === 'custom_css_global' || key === 'custom_css_desktop' || key === 'custom_css_tablet' || key === 'custom_css_wide_mobile' || key === 'custom_css_mobile';
}
function isHiddenField(field) {
  return !!(field && field.type === 'kirki-box-model');
}
function ThemeCustomCssCodeEditor({
  value,
  onChange
}) {
  const textareaRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const editorRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const ignoreChangeRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(false);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    if (!codeEditorSettings) return;
    if (!window.wp || !window.wp.codeEditor || typeof window.wp.codeEditor.initialize !== 'function') return;
    textarea.value = String(value !== null && value !== void 0 ? value : '');
    const settings = {
      ...codeEditorSettings,
      codemirror: {
        ...(codeEditorSettings.codemirror || {})
      }
    };
    const editor = window.wp.codeEditor.initialize(textarea, settings);
    editorRef.current = editor;
    const cm = editor && editor.codemirror ? editor.codemirror : null;
    if (!cm || typeof cm.on !== 'function') return;
    const handleChange = () => {
      if (ignoreChangeRef.current) return;
      const next = typeof cm.getValue === 'function' ? cm.getValue() : '';
      onChange(next);
    };
    cm.on('change', handleChange);
    return () => {
      try {
        cm.off('change', handleChange);
      } catch (e) {}
      try {
        if (typeof cm.toTextArea === 'function') cm.toTextArea();
      } catch (e) {}
    };
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const cm = editorRef.current && editorRef.current.codemirror ? editorRef.current.codemirror : null;
    const next = String(value !== null && value !== void 0 ? value : '');
    if (cm && typeof cm.getValue === 'function' && typeof cm.setValue === 'function') {
      if (cm.getValue() === next) return;
      ignoreChangeRef.current = true;
      const cursor = typeof cm.getCursor === 'function' ? cm.getCursor() : null;
      cm.setValue(next);
      if (cursor && typeof cm.setCursor === 'function') {
        cm.setCursor(cursor);
      }
      ignoreChangeRef.current = false;
      return;
    }
    const textarea = textareaRef.current;
    if (textarea && textarea.value !== next) textarea.value = next;
  }, [value]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
    className: "xstoreOptCodeEditor",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("textarea", {
      ref: textareaRef,
      className: "xstoreOptCodeEditorTextarea",
      spellCheck: "false"
    })
  });
}
function normalizeHex(input) {
  const str = String(input || '').trim();
  if (!str) return '';
  if (str[0] !== '#') return '';
  const hex = str.toLowerCase();
  if (/^#[0-9a-f]{3}$/.test(hex)) {
    return `#${hex[1]}${hex[1]}${hex[2]}${hex[2]}${hex[3]}${hex[3]}`;
  }
  if (/^#[0-9a-f]{6}$/.test(hex)) {
    return hex;
  }
  if (/^#[0-9a-f]{8}$/.test(hex)) {
    return `#${hex.slice(1, 7)}`;
  }
  return '';
}
function rgbToHex(r, g, b) {
  const to2 = x => clamp(Math.round(x), 0, 255).toString(16).padStart(2, '0');
  return `#${to2(r)}${to2(g)}${to2(b)}`;
}
function hexToRgb(hex) {
  const h = normalizeHex(hex);
  if (!h) return null;
  const r = parseInt(h.slice(1, 3), 16);
  const g = parseInt(h.slice(3, 5), 16);
  const b = parseInt(h.slice(5, 7), 16);
  return {
    r,
    g,
    b
  };
}
function parseRgbString(input) {
  const str = String(input || '').trim();
  const m = str.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(0|1|0?\.\d+)\s*)?\)$/i);
  if (!m) return null;
  const r = clamp(parseInt(m[1], 10), 0, 255);
  const g = clamp(parseInt(m[2], 10), 0, 255);
  const b = clamp(parseInt(m[3], 10), 0, 255);
  const a = typeof m[4] !== 'undefined' ? clamp(parseFloat(m[4]), 0, 1) : 1;
  return {
    r,
    g,
    b,
    a
  };
}
function rgbaFromHex(hex, alpha) {
  const rgb = hexToRgb(hex);
  if (!rgb) return '';
  const a = clamp(alpha, 0, 1);
  return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${a})`;
}
function toOptions(choices) {
  if (!choices || typeof choices !== 'object') return [];
  return Object.keys(choices).map(key => ({
    value: key,
    label: String(choices[key])
  }));
}
function toStringArray(value) {
  if (Array.isArray(value)) return value.map(v => String(v));
  if (value === null || typeof value === 'undefined') return [];
  if (typeof value === 'string') {
    return value.split(',').map(s => s.trim()).filter(Boolean);
  }
  return [];
}
function inferDashiconForChoice(value) {
  const v = String(value || '');
  if (v === 'start' || v === 'flex-start' || v === 'left') return 'dashicons-editor-alignleft';
  if (v === 'center') return 'dashicons-editor-aligncenter';
  if (v === 'end' || v === 'flex-end' || v === 'right') return 'dashicons-editor-alignright';
  if (v === 'justify') return 'dashicons-editor-justify';
  return '';
}
function getString(v) {
  if (v === null || typeof v === 'undefined') return '';
  return String(v);
}
function getCustomizerUrl() {
  try {
    const u = new URL(window.location.href);
    const p = String(u.pathname || '');
    if (p.endsWith('/admin.php')) {
      u.pathname = p.replace(/\/admin\.php$/, '/customize.php');
    } else if (/\/[^/]+\.php$/.test(p)) {
      u.pathname = p.replace(/\/[^/]+\.php$/, '/customize.php');
    } else {
      u.pathname = '/wp-admin/customize.php';
    }
    u.search = '';
    u.hash = '';
    return u.toString();
  } catch (e) {
    return 'customize.php';
  }
}
function toBoolean(v) {
  if (v === null || typeof v === 'undefined') return false;
  if (typeof v === 'string') {
    const s = v.trim().toLowerCase();
    if (s === '0' || s === 'false') return false;
    if (s === '1' || s === 'true') return true;
  }
  return !!v;
}
function parseColorValue(value) {
  const raw = getString(value).trim();
  if (!raw) {
    return {
      isEmpty: true,
      hex: '#ffffff',
      alpha: 1
    };
  }
  const hex = normalizeHex(raw);
  if (hex) {
    return {
      isEmpty: false,
      hex,
      alpha: 1
    };
  }
  const rgb = parseRgbString(raw);
  if (rgb) {
    return {
      isEmpty: false,
      hex: rgbToHex(rgb.r, rgb.g, rgb.b),
      alpha: rgb.a
    };
  }
  return {
    isEmpty: false,
    hex: '#ffffff',
    alpha: 1
  };
}
function hasTextMatch(field, q) {
  const query = String(q || '').trim();
  if (query === '' || query.length < 3) return true;
  const needle = query.toLowerCase();
  const hay = `${field.label || ''} ${field.tooltip || ''} ${field.description || ''}`;
  return hay.toLowerCase().includes(needle);
}
function isFieldActive(field, values) {
  const rules = field.active_callback || [];
  if (!Array.isArray(rules) || rules.length === 0) return true;
  for (const r of rules) {
    const s = r && r.setting ? String(r.setting) : '';
    let op = r && r.operator ? String(r.operator) : '==';
    op = op.trim();
    if (op === '=') op = '==';
    const expected = r && typeof r.value !== 'undefined' ? r.value : null;
    const actual = values[s];
    if (typeof actual === 'undefined') {
      continue;
    }
    if (op === '==') {
      if (actual != expected) return false;
    } else if (op === '!=') {
      if (actual == expected) return false;
    } else if (op === 'in') {
      const list = Array.isArray(expected) ? expected : [expected];
      if (Array.isArray(actual)) {
        let ok = false;
        for (const a of actual) {
          if (list.includes(a)) ok = true;
        }
        if (!ok) return false;
      } else if (!list.includes(actual)) {
        return false;
      }
    } else if (op === 'not_in') {
      const list = Array.isArray(expected) ? expected : [expected];
      if (Array.isArray(actual)) {
        for (const a of actual) {
          if (list.includes(a)) return false;
        }
      } else if (list.includes(actual)) {
        return false;
      }
    }
  }
  return true;
}
function ColorField({
  value,
  onChange,
  allowAlpha = false
}) {
  const parsed = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => parseColorValue(value), [value]);
  const [open, setOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [anchor, setAnchor] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [alphaPct, setAlphaPct] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(Math.round(parsed.alpha * 100));
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    setAlphaPct(Math.round(parsed.alpha * 100));
  }, [parsed.alpha]);
  const alpha = allowAlpha ? clamp(alphaPct / 100, 0, 1) : 1;
  const swatch = allowAlpha && alpha < 1 ? rgbaFromHex(parsed.hex, alpha) : parsed.hex;
  const togglePicker = event => {
    const el = event && event.currentTarget ? event.currentTarget : null;
    if (open) {
      setOpen(false);
      return;
    }
    setAnchor(el);
    setOpen(true);
  };
  const closePicker = () => {
    setOpen(false);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "xstoreOptInlineRow",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
      type: "button",
      className: "xstoreOptColorSwatch",
      style: {
        background: parsed.isEmpty ? 'transparent' : swatch
      },
      onClick: togglePicker,
      "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select color', 'xstore'),
      "aria-expanded": open,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
        className: "screen-reader-text",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select color', 'xstore')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "secondary",
      onClick: togglePicker,
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select color', 'xstore')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "tertiary",
      isDestructive: true,
      onClick: () => onChange(''),
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Clear', 'xstore')
    }), open ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Popover, {
      anchor: anchor,
      placement: "bottom-start",
      offset: 8,
      onClose: closePicker,
      shift: true,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          padding: 12,
          width: 240
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.ColorPicker, {
          color: parsed.hex,
          enableAlpha: false,
          onChange: nextHex => {
            const h = normalizeHex(nextHex) || parsed.hex;
            if (allowAlpha && alpha < 1) {
              onChange(rgbaFromHex(h, alpha));
            } else {
              onChange(h);
            }
          }
        }), allowAlpha ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            marginTop: 12
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.RangeControl, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Alpha', 'xstore'),
            value: alphaPct,
            min: 0,
            max: 100,
            step: 1,
            onChange: nextPct => {
              const pct = clamp(Number(nextPct), 0, 100);
              setAlphaPct(pct);
              const a = clamp(pct / 100, 0, 1);
              const h = parsed.hex;
              if (a < 1) {
                onChange(rgbaFromHex(h, a));
              } else {
                onChange(h);
              }
            }
          })
        }) : null]
      })
    }) : null]
  });
}
function ImageField({
  value,
  onChange
}) {
  const url = value && typeof value === 'object' ? value.url || '' : getString(value);
  const openMedia = () => {
    if (!window.wp || !window.wp.media) return;
    const frame = window.wp.media({
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select image', 'xstore'),
      button: {
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use this image', 'xstore')
      },
      library: {
        type: 'image'
      },
      multiple: false
    });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (!att) return;
      if (att.type && att.type !== 'image') return;
      if (att.mime && typeof att.mime === 'string' && !att.mime.startsWith('image/')) return;
      onChange({
        id: att.id,
        url: att.url
      });
    });
    frame.open();
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "xstoreOptInlineRow",
    children: [url ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
      src: url,
      alt: "",
      style: {
        maxWidth: 160,
        height: 'auto',
        borderRadius: 8,
        border: '1px solid #dcdcde'
      }
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "secondary",
      onClick: openMedia,
      children: url ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Change', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select', 'xstore')
    }), url ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "tertiary",
      isDestructive: true,
      onClick: () => onChange(''),
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'xstore')
    }) : null]
  });
}
function UploadField({
  value,
  onChange
}) {
  const url = value && typeof value === 'object' ? value.url || '' : getString(value);
  const openMedia = () => {
    if (!window.wp || !window.wp.media) return;
    const frame = window.wp.media({
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select file', 'xstore'),
      button: {
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use this file', 'xstore')
      },
      multiple: false
    });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (!att) return;
      onChange(att.url || '');
    });
    frame.open();
  };
  const fileName = (() => {
    if (!url) return '';
    try {
      const u = new URL(url, window.location.href);
      const path = String(u.pathname || '');
      const last = path.split('/').filter(Boolean).pop() || '';
      return decodeURIComponent(last);
    } catch (e) {
      const parts = String(url).split('/');
      return parts[parts.length - 1] || url;
    }
  })();
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "xstoreOptInlineRow",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "secondary",
      onClick: openMedia,
      children: url ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Change file', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select file', 'xstore')
    }), url ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
        href: url,
        target: "_blank",
        rel: "noopener noreferrer",
        children: fileName || url
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
        variant: "tertiary",
        isDestructive: true,
        onClick: () => onChange(''),
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'xstore')
      })]
    }) : null]
  });
}
function TinyMceField({
  field,
  value,
  onChange
}) {
  const textareaRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const ignoreChangeRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(false);
  const setting = getString(field && field.setting ? field.setting : '');
  const editorId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => `xstoreOptEditor-${setting || Math.random().toString(16).slice(2)}`, [setting]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const textarea = textareaRef.current;
    if (!textarea) return;
    if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') return;
    textarea.id = editorId;
    textarea.value = getString(value);
    try {
      window.wp.editor.initialize(editorId, {
        tinymce: true,
        quicktags: true,
        mediaButtons: false
      });
    } catch (e) {
      return;
    }
    let cancelled = false;
    let bound = false;
    const bind = () => {
      if (cancelled || bound) return;
      const ed = window.tinymce && typeof window.tinymce.get === 'function' ? window.tinymce.get(editorId) : null;
      if (!ed) {
        setTimeout(bind, 50);
        return;
      }
      bound = true;
      try {
        ignoreChangeRef.current = true;
        ed.setContent(getString(value));
        ignoreChangeRef.current = false;
      } catch (e) {}
      const emit = () => {
        if (ignoreChangeRef.current) return;
        try {
          onChange(ed.getContent());
        } catch (e) {}
      };
      ed.on('change keyup undo redo', emit);
      ed.on('blur', emit);
    };
    bind();
    return () => {
      cancelled = true;
      try {
        if (window.wp && window.wp.editor && typeof window.wp.editor.remove === 'function') {
          window.wp.editor.remove(editorId);
        }
      } catch (e) {}
    };
  }, [editorId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const next = getString(value);
    const ed = window.tinymce && typeof window.tinymce.get === 'function' ? window.tinymce.get(editorId) : null;
    if (ed && typeof ed.getContent === 'function' && typeof ed.setContent === 'function') {
      try {
        const current = ed.getContent();
        if (current === next) return;
        ignoreChangeRef.current = true;
        ed.setContent(next);
        ignoreChangeRef.current = false;
      } catch (e) {}
      return;
    }
    const textarea = textareaRef.current;
    if (textarea && textarea.value !== next) textarea.value = next;
  }, [editorId, value]);
  if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextareaControl, {
      value: getString(value),
      onChange: x => onChange(x)
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("textarea", {
    ref: textareaRef,
    className: "xstoreOptTinyMceTextarea"
  });
}
function DimensionsField({
  field,
  value,
  onChange
}) {
  const obj = value && typeof value === 'object' ? value : {};
  const labels = field.choices && field.choices.labels ? field.choices.labels : null;
  const keys = labels ? Object.keys(labels) : Object.keys(obj);
  const setKey = (k, v) => onChange({
    ...obj,
    [k]: v
  });
  const setting = getString(field.setting);
  const boxUnits = [{
    value: 'px',
    label: 'px'
  }, {
    value: 'em',
    label: 'em'
  }, {
    value: 'rem',
    label: 'rem'
  }, {
    value: '%',
    label: '%'
  }];
  const borderBoxSettings = new Set(['light_buttons_border_width', 'light_buttons_border_width_hover', 'bordered_buttons_border_width', 'bordered_buttons_border_width_hover', 'dark_buttons_border_width', 'dark_buttons_border_width_hover', 'active_buttons_border_width', 'active_buttons_border_width_hover']);
  const radiusSettings = new Set(['light_buttons_border_radius', 'bordered_buttons_border_radius', 'dark_buttons_border_radius', 'active_buttons_border_radius']);
  const paddingBoxSettings = new Set(['footer_padding', 'copyrights_padding', 'breadcrumb_padding', 'overlap_breadcrumb_padding_et-desktop', 'overlap_breadcrumb_padding_et-mobile']);
  const hasAll = arr => arr.every(k => keys.includes(k));
  const borderKeys = ['border-top', 'border-right', 'border-bottom', 'border-left'];
  const paddingKeys = ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'];
  const radiusKeys = ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius'];
  const isBorderBox = borderBoxSettings.has(setting) && hasAll(borderKeys);
  const isPaddingBox = paddingBoxSettings.has(setting) && hasAll(paddingKeys);
  const isRadius = radiusSettings.has(setting) && hasAll(radiusKeys);
  if (_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__.__experimentalBorderRadiusControl && isRadius) {
    const radiusValues = {
      topLeft: getString(obj['border-top-left-radius']),
      topRight: getString(obj['border-top-right-radius']),
      bottomRight: getString(obj['border-bottom-right-radius']),
      bottomLeft: getString(obj['border-bottom-left-radius'])
    };
    const norm = x => x === null || typeof x === 'undefined' ? '' : String(x);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_4__.__experimentalBorderRadiusControl, {
      values: radiusValues,
      onChange: next => onChange({
        ...obj,
        'border-top-left-radius': norm(next && next.topLeft),
        'border-top-right-radius': norm(next && next.topRight),
        'border-bottom-right-radius': norm(next && next.bottomRight),
        'border-bottom-left-radius': norm(next && next.bottomLeft)
      })
    });
  }
  if (_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.__experimentalBoxControl && (isBorderBox || isPaddingBox)) {
    const map = isBorderBox ? {
      top: 'border-top',
      right: 'border-right',
      bottom: 'border-bottom',
      left: 'border-left'
    } : {
      top: 'padding-top',
      right: 'padding-right',
      bottom: 'padding-bottom',
      left: 'padding-left'
    };
    const boxValues = {
      top: getString(obj[map.top]),
      right: getString(obj[map.right]),
      bottom: getString(obj[map.bottom]),
      left: getString(obj[map.left])
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.__experimentalBoxControl, {
      values: boxValues,
      units: boxUnits,
      onChange: next => onChange({
        ...obj,
        [map.top]: getString(next && typeof next.top !== 'undefined' ? next.top : ''),
        [map.right]: getString(next && typeof next.right !== 'undefined' ? next.right : ''),
        [map.bottom]: getString(next && typeof next.bottom !== 'undefined' ? next.bottom : ''),
        [map.left]: getString(next && typeof next.left !== 'undefined' ? next.left : '')
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
    className: "xstoreOptInlineRow",
    children: keys.map(k => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: labels ? String(labels[k]) : k,
      value: getString(obj[k]),
      onChange: v => setKey(k, v)
    }, k))
  });
}
function MulticolorField({
  field,
  value,
  onChange
}) {
  const obj = value && typeof value === 'object' ? value : {};
  const choices = field.choices || {};
  const keys = Object.keys(choices);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
    style: {
      display: 'grid',
      gap: 12
    },
    children: keys.map(k => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          fontWeight: 600,
          marginBottom: 6
        },
        children: String(choices[k] || k)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorField, {
        value: obj[k],
        onChange: v => onChange({
          ...obj,
          [k]: v
        })
      })]
    }, k))
  });
}
function MultiSelectField({
  field,
  value,
  onChange
}) {
  const choices = field && field.choices ? field.choices : {};
  const keys = Object.keys(choices || {});
  const max = Number(field && field.multiple ? field.multiple : 0);
  const placeholder = field && field.placeholder ? String(field.placeholder) : '';
  const anchorRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [filter, setFilter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const toArray = v => {
    if (Array.isArray(v)) return v.map(x => String(x)).filter(Boolean);
    if (typeof v === 'string') return v.split(',').map(s => s.trim()).filter(Boolean);
    return [];
  };
  const allowed = new Set(keys);
  let selected = toArray(value).filter(k => allowed.has(k));
  if (max > 0 && selected.length > max) selected = selected.slice(0, max);
  const filterLc = String(filter || '').trim().toLowerCase();
  const filteredKeys = filterLc ? keys.filter(k => {
    const label = String(choices && choices[k] || k);
    return String(k).toLowerCase().includes(filterLc) || label.toLowerCase().includes(filterLc);
  }) : keys;
  const toggleToken = k => {
    const has = selected.includes(k);
    const next = has ? selected.filter(x => x !== k) : [...selected, k];
    const uniq = Array.from(new Set(next)).filter(x => allowed.has(x));
    onChange(max > 0 ? uniq.slice(0, max) : uniq);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    ref: anchorRef,
    onMouseDown: () => {
      setIsOpen(true);
    },
    onFocus: () => setIsOpen(true),
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.FormTokenField, {
      value: selected,
      suggestions: keys,
      onChange: next => {
        const uniq = Array.from(new Set((next || []).map(String))).filter(k => allowed.has(k));
        onChange(max > 0 ? uniq.slice(0, max) : uniq);
      },
      placeholder: placeholder,
      displayTransform: token => String(choices && choices[token] || token)
    }), isOpen && anchorRef.current ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Popover, {
      anchor: anchorRef.current.querySelector('.components-form-token-field__input-container') || anchorRef.current,
      onClose: () => setIsOpen(false),
      position: "bottom-start",
      offset: 2,
      focusOnMount: false,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        style: {
          padding: 10,
          minWidth: 360,
          maxWidth: 520
        },
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
          value: filter,
          onChange: x => setFilter(x || ''),
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search…', 'xstore')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            maxHeight: 260,
            overflow: 'auto',
            marginTop: 8,
            display: 'grid',
            gap: 6
          },
          children: filteredKeys.map(k => {
            const label = String(choices && choices[k] || k);
            const isSelected = selected.includes(k);
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
              type: "button",
              className: `xstoreOptMultiSelectItem${isSelected ? ' isSelected' : ''}`,
              onClick: () => toggleToken(k),
              children: label
            }, k);
          })
        })]
      })
    }) : null]
  });
}
function BackgroundField({
  value,
  onChange
}) {
  const obj = value && typeof value === 'object' ? value : {};
  const bgColor = getString(obj['background-color'] || '');
  const bgImage = getString(obj['background-image'] || '');
  const bgRepeat = getString(obj['background-repeat'] || 'no-repeat');
  const bgPosition = getString(obj['background-position'] || 'center center');
  const bgSize = getString(obj['background-size'] || 'cover');
  const bgAttachment = getString(obj['background-attachment'] || 'scroll');
  const setKey = (k, v) => onChange({
    ...obj,
    [k]: v
  });
  const openBackgroundMedia = () => {
    if (!window.wp || !window.wp.media) return;
    const frame = window.wp.media({
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select File', 'xstore'),
      button: {
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Use this image', 'xstore')
      },
      library: {
        type: 'image'
      },
      multiple: false
    });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (!att) return;
      if (att.type && att.type !== 'image') return;
      if (att.mime && typeof att.mime === 'string' && !att.mime.startsWith('image/')) return;
      setKey('background-image', att.url || '');
    });
    frame.open();
  };
  const showImageOptions = !!bgImage;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    style: {
      display: 'grid',
      gap: 16
    },
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          fontWeight: 600,
          marginBottom: 6
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Color', 'xstore')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorField, {
        value: bgColor,
        onChange: v => setKey('background-color', v),
        allowAlpha: true
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          fontWeight: 600,
          marginBottom: 6
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Image', 'xstore')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "xstoreOptInlineRow",
        children: [bgImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
          src: bgImage,
          alt: "",
          style: {
            maxWidth: 160,
            height: 'auto',
            borderRadius: 8,
            border: '1px solid #dcdcde'
          }
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "secondary",
          onClick: openBackgroundMedia,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select File', 'xstore')
        }), bgImage ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "tertiary",
          isDestructive: true,
          onClick: () => setKey('background-image', ''),
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'xstore')
        }) : null]
      })]
    }), showImageOptions ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Repeat', 'xstore'),
        value: bgRepeat,
        options: [{
          value: 'no-repeat',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No Repeat', 'xstore')
        }, {
          value: 'repeat',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Repeat All', 'xstore')
        }, {
          value: 'repeat-x',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Repeat Horizontally', 'xstore')
        }, {
          value: 'repeat-y',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Repeat Vertically', 'xstore')
        }],
        onChange: v => setKey('background-repeat', v)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Position', 'xstore'),
        value: bgPosition,
        options: [{
          value: 'left top',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Left Top', 'xstore')
        }, {
          value: 'left center',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Left Center', 'xstore')
        }, {
          value: 'left bottom',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Left Bottom', 'xstore')
        }, {
          value: 'right top',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Right Top', 'xstore')
        }, {
          value: 'right center',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Right Center', 'xstore')
        }, {
          value: 'right bottom',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Right Bottom', 'xstore')
        }, {
          value: 'center top',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Center Top', 'xstore')
        }, {
          value: 'center center',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Center Center', 'xstore')
        }, {
          value: 'center bottom',
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Center Bottom', 'xstore')
        }],
        onChange: v => setKey('background-position', v)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            fontWeight: 600,
            marginBottom: 6
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Size', 'xstore')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: "xstoreOptInlineRow",
          children: ['cover', 'contain', 'auto'].map(opt => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
            variant: bgSize === opt ? 'primary' : 'secondary',
            onClick: () => setKey('background-size', opt),
            children: opt === 'cover' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cover', 'xstore') : opt === 'contain' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Contain', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Auto', 'xstore')
          }, opt))
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            fontWeight: 600,
            marginBottom: 6
          },
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Background Attachment', 'xstore')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: "xstoreOptInlineRow",
          children: ['scroll', 'fixed'].map(opt => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
            variant: bgAttachment === opt ? 'primary' : 'secondary',
            onClick: () => setKey('background-attachment', opt),
            children: opt === 'scroll' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Scroll', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Fixed', 'xstore')
          }, opt))
        })]
      })]
    }) : null]
  });
}
function TypographyField({
  field,
  value,
  onChange,
  fontsData
}) {
  const obj = value && typeof value === 'object' ? value : {};
  const def = field && field.default && typeof field.default === 'object' ? field.default : {};
  const setValues = patch => onChange({
    ...obj,
    ...patch
  });
  const setKey = (k, v) => setValues({
    [k]: v
  });
  const fontFamily = getString(obj['font-family']);
  const variant = getString(obj.variant);
  const fontsIndex = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const out = {
      googleByFamily: {},
      variantsLabel: {}
    };
    if (fontsData && fontsData.google && Array.isArray(fontsData.google)) {
      for (const f of fontsData.google) {
        if (f && f.family) out.googleByFamily[String(f.family)] = f;
      }
    }
    if (fontsData && fontsData.variants && typeof fontsData.variants === 'object') {
      out.variantsLabel = fontsData.variants;
    }
    return out;
  }, [fontsData]);
  const getVariantsForFamily = family => {
    let variants = ['', 'regular', 'italic', '700', '700italic'];
    if (family && fontsIndex.googleByFamily[family] && Array.isArray(fontsIndex.googleByFamily[family].variants)) {
      variants = fontsIndex.googleByFamily[family].variants;
    }
    if (field && field.choices && field.choices.fonts && field.choices.fonts.variants && field.choices.fonts.variants[family] && Array.isArray(field.choices.fonts.variants[family])) {
      variants = field.choices.fonts.variants[family];
    }
    return variants;
  };
  const fontOptions = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const opts = [{
      value: '',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Default browser font-family', 'xstore')
    }, {
      value: 'initial',
      label: 'initial'
    }, {
      value: 'inherit',
      label: 'inherit'
    }];
    if (fontsData && Array.isArray(fontsData.standard)) {
      for (const f of fontsData.standard) {
        if (!f || !f.stack) continue;
        opts.push({
          value: String(f.stack),
          label: String(f.label || f.stack)
        });
      }
    }
    if (fontsData && Array.isArray(fontsData.google)) {
      for (const f of fontsData.google) {
        if (!f || !f.family) continue;
        opts.push({
          value: String(f.family),
          label: String(f.family)
        });
      }
    }
    if (fontFamily && !opts.some(o => o.value === fontFamily)) {
      opts.push({
        value: fontFamily,
        label: fontFamily
      });
    }
    const seen = new Set();
    return opts.filter(o => {
      if (seen.has(o.value)) return false;
      seen.add(o.value);
      return true;
    });
  }, [fontsData, fontFamily]);
  const variantOptions = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const vars = getVariantsForFamily(fontFamily);
    const opts = (Array.isArray(vars) ? vars : []).map(v => ({
      value: String(v),
      label: fontsIndex.variantsLabel[v] ? String(fontsIndex.variantsLabel[v]) : String(v || '')
    }));
    if (variant && !opts.some(o => o.value === variant)) {
      opts.push({
        value: variant,
        label: variant
      });
    }
    const seen = new Set();
    return opts.filter(o => {
      if (seen.has(o.value)) return false;
      seen.add(o.value);
      return true;
    });
  }, [fontFamily, variant, fontsIndex.variantsLabel]);
  const applyVariant = nextVariant => {
    const v = getString(nextVariant);
    let fontWeight = '';
    let fontStyle = '';
    if (v) {
      const digits = v.match(/\d/g);
      fontWeight = digits && digits.length ? digits.join('') : v === 'regular' || v === 'italic' ? '400' : '400';
      fontStyle = v.includes('italic') ? 'italic' : 'normal';
    }
    setValues({
      variant: v,
      'font-weight': fontWeight,
      'font-style': fontStyle
    });
  };
  const onFamilyChange = nextFamily => {
    const fam = getString(nextFamily);
    if (fam === '' || fam === 'inherit' || fam === 'initial') {
      setValues({
        'font-family': fam,
        variant: '',
        'font-weight': '',
        'font-style': ''
      });
      return;
    }
    const vars = getVariantsForFamily(fam);
    const list = Array.isArray(vars) ? vars.map(x => String(x)) : [];
    let nextVariantVal = variant;
    if (list.length > 0 && !list.includes(nextVariantVal)) {
      nextVariantVal = list.includes('regular') ? 'regular' : list[0];
    }
    setValues({
      'font-family': fam
    });
    if (nextVariantVal !== variant) {
      applyVariant(nextVariantVal);
    }
  };
  const showVariant = typeof def.variant !== 'undefined' && def.variant !== false && fontFamily !== '' && fontFamily !== 'inherit' && fontFamily !== 'initial' && variantOptions.length > 1;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    style: {
      display: 'grid',
      gap: 12
    },
    children: [typeof def['font-family'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.ComboboxControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Font Family', 'xstore'),
      value: fontFamily,
      options: fontOptions,
      onChange: onFamilyChange
    }) : null, showVariant ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.ComboboxControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Variant', 'xstore'),
      value: variant,
      options: variantOptions,
      onChange: v => applyVariant(v)
    }) : null, typeof def['font-size'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Font Size', 'xstore'),
      value: getString(obj['font-size']),
      onChange: v => setKey('font-size', v)
    }) : null, typeof def['line-height'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Line Height', 'xstore'),
      value: getString(obj['line-height']),
      onChange: v => setKey('line-height', v)
    }) : null, typeof def['letter-spacing'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Letter Spacing', 'xstore'),
      value: getString(obj['letter-spacing']),
      onChange: v => setKey('letter-spacing', v)
    }) : null, typeof def['word-spacing'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Word Spacing', 'xstore'),
      value: getString(obj['word-spacing']),
      onChange: v => setKey('word-spacing', v)
    }) : null, typeof def['text-transform'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Text Transform', 'xstore'),
      value: getString(obj['text-transform']),
      options: [{
        value: '',
        label: ''
      }, {
        value: 'none',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('None', 'xstore')
      }, {
        value: 'capitalize',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Capitalize', 'xstore')
      }, {
        value: 'uppercase',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Uppercase', 'xstore')
      }, {
        value: 'lowercase',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Lowercase', 'xstore')
      }, {
        value: 'initial',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Initial', 'xstore')
      }, {
        value: 'inherit',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Inherit', 'xstore')
      }],
      onChange: v => setKey('text-transform', v)
    }) : null, typeof def['text-decoration'] !== 'undefined' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Text Decoration', 'xstore'),
      value: getString(obj['text-decoration']),
      options: [{
        value: '',
        label: ''
      }, {
        value: 'none',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('None', 'xstore')
      }, {
        value: 'underline',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Underline', 'xstore')
      }, {
        value: 'overline',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Overline', 'xstore')
      }, {
        value: 'line-through',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Line-Through', 'xstore')
      }, {
        value: 'initial',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Initial', 'xstore')
      }, {
        value: 'inherit',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Inherit', 'xstore')
      }],
      onChange: v => setKey('text-decoration', v)
    }) : null, typeof def.color !== 'undefined' && def.color !== false ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        style: {
          fontWeight: 600,
          marginBottom: 6
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Color', 'xstore')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorField, {
        value: obj.color,
        onChange: v => setKey('color', v),
        allowAlpha: false
      })]
    }) : null, Object.keys(obj).filter(k => !['font-family', 'variant', 'font-size', 'line-height', 'letter-spacing', 'word-spacing', 'text-transform', 'text-decoration', 'color', 'font-weight', 'font-style', 'font-backup', 'font_backup'].includes(k)).map(k => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: k.replace(/[-_]/g, ' '),
      value: getString(obj[k]),
      onChange: v => setKey(k, v)
    }, k))]
  });
}
function SortableField({
  field,
  value,
  onChange
}) {
  const choices = field.choices || {};
  const selected = Array.isArray(value) ? [...value] : [];
  const remaining = Object.keys(choices).filter(k => !selected.includes(k));
  const [toAdd, setToAdd] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(remaining[0] || '');
  const move = (i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= selected.length) return;
    const next = [...selected];
    const tmp = next[i];
    next[i] = next[j];
    next[j] = tmp;
    onChange(next);
  };
  const remove = i => {
    const next = [...selected];
    next.splice(i, 1);
    onChange(next);
  };
  const add = () => {
    if (!toAdd) return;
    const next = [...selected, toAdd];
    onChange(next);
    const rem2 = Object.keys(choices).filter(k => !next.includes(k));
    setToAdd(rem2[0] || '');
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      style: {
        display: 'grid',
        gap: 8
      },
      children: selected.map((k, i) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "xstoreOptInlineRow",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            minWidth: 240
          },
          children: String(choices[k] || k)
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "secondary",
          onClick: () => move(i, -1),
          children: "\u2191"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "secondary",
          onClick: () => move(i, 1),
          children: "\u2193"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "tertiary",
          isDestructive: true,
          onClick: () => remove(i),
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'xstore')
        })]
      }, k))
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "xstoreOptInlineRow",
      style: {
        marginTop: 10,
        alignItems: 'flex-end'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add element', 'xstore'),
        value: toAdd,
        options: remaining.map(k => ({
          value: k,
          label: String(choices[k] || k)
        })),
        onChange: v => setToAdd(v)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
        variant: "primary",
        onClick: add,
        disabled: !toAdd,
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add', 'xstore')
      })]
    })]
  });
}
function RepeaterField({
  field,
  value,
  onChange
}) {
  const list = Array.isArray(value) ? value : [];
  const subFields = field.fields || {};
  const rowLabel = field.row_label || {};
  const buttonLabel = field.button_label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add new item', 'xstore');
  const [openRows, setOpenRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)({});
  const isOpen = idx => !!openRows[idx];
  const toggleOpen = idx => setOpenRows(prev => ({
    ...prev,
    [idx]: !prev[idx]
  }));
  const setItem = (idx, nextItem) => {
    const next = [...list];
    next[idx] = nextItem;
    onChange(next);
  };
  const addItem = () => {
    const item = {};
    for (const key of Object.keys(subFields)) {
      const f = subFields[key] || {};
      item[key] = typeof f.default !== 'undefined' ? f.default : '';
    }
    setOpenRows(prev => ({
      ...prev,
      [list.length]: true
    }));
    onChange([...list, item]);
  };
  const removeItem = idx => {
    const next = [...list];
    next.splice(idx, 1);
    setOpenRows(prev => {
      const out = {};
      for (const k of Object.keys(prev)) {
        const i = Number(k);
        if (Number.isNaN(i)) continue;
        if (i === idx) continue;
        out[i > idx ? i - 1 : i] = prev[i];
      }
      return out;
    });
    onChange(next);
  };
  const moveItem = (idx, dir) => {
    const j = idx + dir;
    if (j < 0 || j >= list.length) return;
    const next = [...list];
    const tmp = next[idx];
    next[idx] = next[j];
    next[j] = tmp;
    setOpenRows(prev => {
      const out = {
        ...prev
      };
      const a = !!out[idx];
      const b = !!out[j];
      out[idx] = b;
      out[j] = a;
      return out;
    });
    onChange(next);
  };
  const getItemTitle = (item, idx) => {
    const fallbackBase = rowLabel && rowLabel.value ? String(rowLabel.value) : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Item', 'xstore');
    if (rowLabel && rowLabel.type === 'field' && rowLabel.field && item && typeof item === 'object' && typeof item[rowLabel.field] !== 'undefined') {
      const raw = getString(item[rowLabel.field]);
      if (raw) {
        const sf = subFields[rowLabel.field] || {};
        const t = sf.type || '';
        const choices = sf.choices || {};
        if ((t === 'select' || t === 'radio' || t === 'radio-image') && choices && typeof choices === 'object' && typeof choices[raw] !== 'undefined') {
          return String(choices[raw]);
        }
        return raw;
      }
    }
    return `${fallbackBase} ${idx + 1}`;
  };
  const renderSub = (subKey, subField, itemVal, setVal) => {
    const t = subField.type || 'text';
    const label = subField.label ? String(subField.label) : subKey;
    if (t === 'select') {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
        label: label,
        value: getString(itemVal),
        options: toOptions(subField.choices || {}),
        onChange: v => setVal(v)
      });
    }
    if (t === 'checkbox') {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
        label: label,
        checked: !!itemVal,
        onChange: v => setVal(!!v)
      });
    }
    if (t === 'image') {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          style: {
            fontWeight: 600,
            marginBottom: 6
          },
          children: label
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ImageField, {
          value: itemVal,
          onChange: setVal
        })]
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
      label: label,
      value: getString(itemVal),
      onChange: v => setVal(v)
    });
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    children: [list.map((item, idx) => {
      const itemObj = item && typeof item === 'object' ? item : {};
      const title = getItemTitle(itemObj, idx);
      const open = isOpen(idx);
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "xstoreOptRepeaterItem",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "xstoreOptRepeaterHeader",
          role: "button",
          tabIndex: 0,
          onClick: () => toggleOpen(idx),
          onKeyDown: e => {
            if (e.key === 'Enter' || e.key === ' ') toggleOpen(idx);
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "xstoreOptRepeaterTitle",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: `dashicons ${open ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2'}`
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              children: title
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "xstoreOptInlineRow",
            onClick: e => e.stopPropagation(),
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "secondary",
              onClick: () => moveItem(idx, -1),
              children: "\u2191"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "secondary",
              onClick: () => moveItem(idx, 1),
              children: "\u2193"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "tertiary",
              isDestructive: true,
              onClick: () => removeItem(idx),
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Remove', 'xstore')
            })]
          })]
        }), open ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: "xstoreOptRepeaterGrid",
          children: Object.keys(subFields).map(subKey => {
            const sf = subFields[subKey] || {};
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              children: renderSub(subKey, sf, itemObj[subKey], nv => setItem(idx, {
                ...itemObj,
                [subKey]: nv
              }))
            }, subKey);
          })
        }) : null]
      }, idx);
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
      variant: "primary",
      onClick: addItem,
      children: buttonLabel
    })]
  });
}
function FieldControl({
  field,
  value,
  onChange,
  fontsData
}) {
  const type = field.type;
  const v = typeof value === 'undefined' ? field.default : value;
  if (type === 'toggle') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.ToggleControl, {
      checked: toBoolean(v),
      onChange: x => onChange(x ? 1 : 0)
    });
  }
  if (type === 'checkbox') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
      checked: toBoolean(v),
      onChange: x => onChange(!!x)
    });
  }
  if (type === 'multicheck') {
    const choices = field.choices || {};
    const keys = Object.keys(choices);
    const selected = new Set(toStringArray(v));
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "xstoreOptMulticheckBox",
      children: keys.map(key => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.CheckboxControl, {
        label: String(choices[key] || key),
        checked: selected.has(key),
        onChange: isChecked => {
          const next = new Set(toStringArray(v));
          if (isChecked) next.add(key);else next.delete(key);
          onChange(keys.filter(k => next.has(k)));
        }
      }, key))
    });
  }
  if (type === 'slider' || type === 'number') {
    const min = field.choices && typeof field.choices.min !== 'undefined' ? Number(field.choices.min) : undefined;
    const max = field.choices && typeof field.choices.max !== 'undefined' ? Number(field.choices.max) : undefined;
    const step = field.choices && typeof field.choices.step !== 'undefined' ? Number(field.choices.step) : 1;
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.RangeControl, {
      value: v === '' ? undefined : Number(v),
      min: min,
      max: max,
      step: step,
      onChange: x => onChange(x)
    });
  }
  if (type === 'select') {
    const isMulti = Number(field && field.multiple ? field.multiple : 0) > 1;
    if (isMulti) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(MultiSelectField, {
        field: field,
        value: v,
        onChange: x => onChange(x)
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
      value: getString(v),
      options: toOptions(field.choices),
      onChange: x => onChange(x)
    });
  }
  if (type === 'radio-buttonset') {
    const opts = toOptions(field.choices);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "xstoreOptInlineRow",
      children: opts.map(o => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
        variant: getString(v) === o.value ? 'primary' : 'secondary',
        onClick: () => onChange(o.value),
        "aria-label": (o.label || o.value) && String(o.label || o.value),
        className: !String(o.label || '').trim() && inferDashiconForChoice(o.value) ? 'xstoreOptButtonsetBtn isIcon' : undefined,
        children: String(o.label || '').trim() ? o.label : inferDashiconForChoice(o.value) ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
          className: `dashicons ${inferDashiconForChoice(o.value)}`,
          "aria-hidden": "true"
        }) : o.value
      }, o.value))
    });
  }
  if (type === 'radio-image') {
    const choices = field.choices || {};
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "xstoreOptRadioImageGrid",
      children: Object.keys(choices).map(k => {
        const src = String(choices[k] || '');
        const active = getString(v) === k;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
          type: "button",
          className: `xstoreOptRadioImageBtn${active ? ' isActive' : ''}`,
          onClick: () => onChange(k),
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("img", {
            src: src,
            alt: k
          })
        }, k);
      })
    });
  }
  if (type === 'color') {
    const allowAlpha = !!(field.choices && field.choices.alpha);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorField, {
      value: v,
      onChange: onChange,
      allowAlpha: allowAlpha
    });
  }
  if (type === 'kirki-wcag-tc') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ColorField, {
      value: v,
      onChange: onChange,
      allowAlpha: false
    });
  }
  if (type === 'image') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ImageField, {
    value: v,
    onChange: onChange
  });
  if (type === 'upload') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(UploadField, {
    value: v,
    onChange: onChange
  });
  if (type === 'dimensions') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(DimensionsField, {
    field: field,
    value: v,
    onChange: onChange
  });
  if (type === 'multicolor') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(MulticolorField, {
    field: field,
    value: v,
    onChange: onChange
  });
  if (type === 'background') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(BackgroundField, {
    value: v,
    onChange: onChange
  });
  if (type === 'typography') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(TypographyField, {
    field: field,
    value: v,
    onChange: onChange,
    fontsData: fontsData
  });
  if (type === 'sortable') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(SortableField, {
    field: field,
    value: v,
    onChange: onChange
  });
  if (type === 'repeater') return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(RepeaterField, {
    field: field,
    value: v,
    onChange: onChange
  });
  if (type === 'code') {
    if (isThemeCustomCssSetting(field.setting) && codeEditorSettings) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(ThemeCustomCssCodeEditor, {
        value: getString(v),
        onChange: x => onChange(x)
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextareaControl, {
      value: getString(v),
      onChange: x => onChange(x)
    });
  }
  if (type === 'editor') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(TinyMceField, {
      field: field,
      value: getString(v),
      onChange: x => onChange(x)
    });
  }
  if (type === 'textarea' || type === 'etheme-textarea') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextareaControl, {
      value: getString(v),
      onChange: x => onChange(x)
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.TextControl, {
    value: getString(v),
    onChange: x => onChange(x)
  });
}
function App() {
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(true);
  const [schema, setSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [values, setValues] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)({});
  const [dirty, setDirty] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)({});
  const [activeSection, setActiveSection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(boot.tab || '');
  const [search, setSearch] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const [searchMatches, setSearchMatches] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [isSearching, setIsSearching] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [collapsedGroups, setCollapsedGroups] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)({});
  const [fontsData, setFontsData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [isExporting, setIsExporting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [isImporting, setIsImporting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [importJsonText, setImportJsonText] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [isConfirmResetAllOpen, setIsConfirmResetAllOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [headerLogoHtml, setHeaderLogoHtml] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const [switcherHtml, setSwitcherHtml] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const [footerHtml, setFooterHtml] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)('');
  const [colorMode, setColorMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const [themeLinks, setThemeLinks] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(null);
  const mainRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const actionsRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(null);
  const [actionsLayout, setActionsLayout] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)({
    mode: 'fixed',
    left: 0,
    width: 0
  });
  const searchSeqRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)(0);
  const effectiveValues = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => ({
    ...values,
    ...dirty
  }), [values, dirty]);
  const isDirty = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => Object.keys(dirty).length > 0, [dirty]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    setLoading(true);
    Promise.all([_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/schema'
    }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/values'
    }), _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/fonts'
    }).catch(() => null)]).then(([s, v, f]) => {
      setSchema(s);
      setValues(v && v.values ? v.values : {});
      setFontsData(f);
      setLoading(false);
      const sections = s && s.sections ? s.sections : [];
      if (sections.length) {
        const initial = boot.tab && sections.find(sec => sec.id === boot.tab) ? boot.tab : sections[0].id;
        setActiveSection(initial);
        const panelsById = s && s.panelsById ? s.panelsById : {};
        const collapsedInit = {};
        for (const pid of Object.keys(panelsById)) {
          collapsedInit[pid] = true;
        }
        const activeSec = sections.find(sec => sec && sec.id === initial);
        if (activeSec && activeSec.panel) {
          let pid = String(activeSec.panel);
          while (pid) {
            collapsedInit[pid] = false;
            pid = panelsById[pid] && panelsById[pid].panel ? String(panelsById[pid].panel) : '';
          }
        }
        setCollapsedGroups(collapsedInit);
      }
    }).catch(e => {
      setLoading(false);
      const status = getStatusFromError(e);
      if (isInvalidJsonError(e) || status === 401 || status === 403) {
        const target = boot && boot.legacyUrl ? boot.legacyUrl : boot && boot.customizerUrl ? boot.customizerUrl : getCustomizerUrl();
        window.location.assign(target);
        return;
      }
      setNotice({
        status: 'error',
        text: e && e.message ? e.message : 'Error loading data.'
      });
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!activeSection) return;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', activeSection);
    window.history.replaceState({}, '', url.toString());
  }, [activeSection]);
  const nav = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const ROOT = '__root__';
    if (!schema) {
      return {
        rootId: ROOT,
        childrenByPanel: {
          [ROOT]: []
        }
      };
    }
    const panelsById = schema.panelsById || {};
    const sections = schema.sections || [];
    const children = {};
    const ensure = pid => {
      if (!children[pid]) children[pid] = [];
      return children[pid];
    };
    ensure(ROOT);
    for (const pid of Object.keys(panelsById)) {
      var _p$priority;
      const p = panelsById[pid];
      if (!p || !p.id) continue;
      const parent = p.panel ? String(p.panel) : ROOT;
      ensure(parent).push({
        kind: 'panel',
        id: String(p.id),
        title: String(p.title || p.id),
        icon: String(p.icon || ''),
        priority: Number((_p$priority = p.priority) !== null && _p$priority !== void 0 ? _p$priority : 10)
      });
    }
    for (const s of sections) {
      var _s$priority;
      if (!s || !s.id) continue;
      const parent = s.panel ? String(s.panel) : ROOT;
      ensure(parent).push({
        kind: 'section',
        id: String(s.id),
        title: String(s.title || s.id),
        icon: String(s.icon || ''),
        priority: Number((_s$priority = s.priority) !== null && _s$priority !== void 0 ? _s$priority : 10),
        panel: String(s.panel || '')
      });
    }
    ensure(ROOT).push({
      kind: 'link',
      id: 'live-theme-options',
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Live Theme Options', 'xstore'),
      icon: 'dashicons-admin-customizer',
      priority: 999998,
      url: getCustomizerUrl()
    });
    for (const pid of Object.keys(children)) {
      children[pid].sort((a, b) => (a.priority || 0) - (b.priority || 0));
    }
    const pruned = {};
    const prune = pid => {
      const items = children[pid] || [];
      const out = [];
      for (const item of items) {
        if (item.kind === 'section' || item.kind === 'link') {
          out.push(item);
          continue;
        }
        const kids = prune(item.id);
        if (kids.length) {
          out.push(item);
        }
      }
      pruned[pid] = out;
      return out;
    };
    prune(ROOT);
    return {
      rootId: ROOT,
      childrenByPanel: pruned
    };
  }, [schema]);
  const setValue = (setting, next) => setDirty(prev => ({
    ...prev,
    [setting]: next
  }));
  const openSection = sectionId => {
    setActiveSection(sectionId);
    const q = String(search || '').trim();
    if (q !== '') {
      searchSeqRef.current++;
      setSearch('');
      setSearchMatches(null);
      setIsSearching(false);
    }
  };
  const getDescendantPanels = rootId => {
    const out = [];
    const stack = [rootId];
    while (stack.length) {
      const id = stack.pop();
      const kids = nav.childrenByPanel?.[id] || [];
      const childPanels = kids.filter(k => k?.kind === "panel").map(k => k.id);
      out.push(...childPanels);
      stack.push(...childPanels);
    }
    return out;
  };
  const panelParent = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const parent = {};
    Object.entries(nav.childrenByPanel || {}).forEach(([panelId, kids]) => {
      (kids || []).forEach(child => {
        if (child?.kind === "panel") parent[child.id] = panelId;
      });
    });
    return parent;
  }, [nav]);
  const panelSiblings = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useMemo)(() => {
    const siblings = {};
    Object.entries(nav.childrenByPanel || {}).forEach(([panelId, kids]) => {
      const panels = (kids || []).filter(c => c?.kind === "panel").map(c => c.id);
      panels.forEach(id => siblings[id] = panels);
    });
    return siblings;
  }, [nav]);
  const openParentChain = (map, id) => {
    let p = panelParent[id];
    while (p) {
      map[p] = false;
      p = panelParent[p];
    }
  };
  const toggleGroup = groupId => {
    setCollapsedGroups(prev => {
      const isCollapsed = prev[groupId] !== undefined ? !!prev[groupId] : true;
      const willOpen = isCollapsed;
      const next = {
        ...prev
      };
      openParentChain(next, groupId);
      const sibs = panelSiblings[groupId] || [];
      sibs.forEach(sid => {
        next[sid] = true;
      });
      if (willOpen) {
        next[groupId] = false;
      } else {
        next[groupId] = true;
        getDescendantPanels(groupId).forEach(cid => {
          next[cid] = true;
        });
      }
      return next;
    });
  };
  const handleInlineEditLink = e => {
    const el = e.target?.closest?.(".et_edit");
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
    var parentId = el.getAttribute("data-parent") || "";
    // const sectionId = el.getAttribute("data-section") || "";

    // 1) Prefer explicit section if provided
    // if (sectionId) {
    // 	openSection(sectionId);
    //
    // 	// optional: make sure parent panel is open too (if it is a panel id)
    // 	if (parentId) {
    // 		setCollapsedGroups((prev) => ({ ...prev, [parentId]: false }));
    // 	}
    // 	return;
    // }

    // 2) If only parent is provided, try:
    // - open section with that id, OR
    // - open first section that belongs to that panel, OR
    // - at least expand panel
    if (parentId) {
      if (['xstore_wishlist', 'xstore_compare', 'xstore_waitlist'].includes(parentId)) {
        parentId = parentId.replace('_', '-');
      }
      const directSection = (schema?.sections || []).find(s => String(s?.id) === String(parentId));
      if (directSection) {
        openSection(directSection.id);
        return;
      }
      const firstSectionInPanel = (schema?.sections || []).find(s => String(s?.panel) === String(parentId));
      if (firstSectionInPanel) {
        openSection(firstSectionInPanel.id);
        return;
      }

      // fallback: just expand panel
      setCollapsedGroups(prev => ({
        ...prev,
        [parentId]: false
      }));
    }
  };
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!schema || !activeSection) return;
    const sections = schema.sections || [];
    const sec = sections.find(s => s && s.id === activeSection);
    if (!sec || !sec.panel) return;
    const panelsById = schema.panelsById || {};
    setCollapsedGroups(prev => {
      const next = {
        ...prev
      };
      let pid = String(sec.panel);
      while (pid) {
        next[pid] = false;
        pid = panelsById[pid] && panelsById[pid].panel ? String(panelsById[pid].panel) : '';
      }
      return next;
    });
  }, [activeSection, schema]);
  const renderNavItems = (items, depth) => items.map(item => {
    if (item.kind === 'link') {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("li", {
        className: "xstoreOptNavItem",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("a", {
          href: item.url,
          className: "xstoreOptGroupTitle",
          target: "_blank",
          rel: "noopener noreferrer",
          children: [item.icon ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: `et-panel-nav-icon dashicons ${item.icon}`
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            children: item.title
          })]
        })
      }, `link-${item.id}`);
    }
    if (item.kind === 'panel') {
      const isCollapsed = typeof collapsedGroups[item.id] !== 'undefined' ? !!collapsedGroups[item.id] : true;
      const kids = nav.childrenByPanel[item.id] || [];
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("li", {
        className: `xstoreOptNavItem xstoreOptNavPanel${isCollapsed ? '' : ' isOpen'}`,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("a", {
          href: "#",
          className: "xstoreOptGroupTitle",
          role: "button",
          "aria-expanded": !isCollapsed,
          onClick: e => {
            e.preventDefault();
            toggleGroup(item.id);
          },
          onKeyDown: e => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              toggleGroup(item.id);
            }
          },
          children: [item.icon ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: `et-panel-nav-icon dashicons ${item.icon}`
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            children: item.title
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: `et-panel-nav-icon dashicons ${isCollapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2'} xstoreOptPanelChevron`,
            "aria-hidden": "true"
          })]
        }), !isCollapsed && kids.length && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("ul", {
          children: renderNavItems(kids, depth + 1)
        })]
      }, `panel-${item.id}`);
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("li", {
      className: `xstoreOptNavItem${item.id === activeSection ? ' isActive' : ''}`,
      onClick: e => {
        e.preventDefault();
        openSection(item.id);
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("a", {
        href: "#",
        className: `xstoreOptGroupTitle${item.id === activeSection ? ' active' : ''}`,
        children: [item.icon ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
          className: `et-panel-nav-icon dashicons ${item.icon}`
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
          children: item.title
        })]
      })
    }, `section-${item.id}`);
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/page-colormode',
      method: 'POST',
      data: {}
    }).then(res => {
      setColorMode(res);
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/theme-links',
      method: 'POST',
      data: {}
    }).then(res => {
      setThemeLinks(res);
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/page-logo',
      method: 'POST',
      data: {}
    }).then(res => {
      if (res?.success) setHeaderLogoHtml(res.data.html || "");
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/page-switcher',
      method: 'POST',
      data: {}
    }).then(res => {
      if (res?.success) setSwitcherHtml(res.data.html || "");
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/page-footer',
      method: 'POST',
      data: {}
    }).then(res => {
      if (res?.success) setFooterHtml(res.data.html || "");
    });
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!schema) return;
    const q = String(search || '').trim();
    if (q === '' || q.length < 3) {
      setIsSearching(false);
      setSearchMatches(null);
      return;
    }
    const seq = ++searchSeqRef.current;
    setIsSearching(true);
    const timer = setTimeout(() => {
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
        path: `/xstore-options/v1/search?q=${encodeURIComponent(q)}&limit=250`
      }).then(res => {
        if (seq !== searchSeqRef.current) return;
        const matches = res && Array.isArray(res.matches) ? res.matches : [];
        setSearchMatches(matches);
      }).catch(() => {
        if (seq !== searchSeqRef.current) return;
        setSearchMatches([]);
      }).finally(() => {
        if (seq !== searchSeqRef.current) return;
        setIsSearching(false);
      });
    }, 200);
    return () => clearTimeout(timer);
  }, [search, schema]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useLayoutEffect)(() => {
    if (loading) return;
    let raf = 0;
    const update = () => {
      raf = 0;
      const mainEl = mainRef.current;
      const barEl = actionsRef.current;
      if (!mainEl || !barEl) return;
      const rect = mainEl.getBoundingClientRect();
      const shouldFix = rect.bottom > window.innerHeight;
      setActionsLayout({
        mode: shouldFix ? 'fixed' : 'absolute',
        left: Math.round(rect.left),
        width: Math.round(rect.width)
      });
    };
    const onScroll = () => {
      if (raf) return;
      raf = window.requestAnimationFrame(update);
    };
    update();
    window.addEventListener('scroll', onScroll, {
      passive: true
    });
    window.addEventListener('resize', onScroll);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
      if (raf) window.cancelAnimationFrame(raf);
    };
  }, [loading]);
  const fileToText = file => new Promise((resolve, reject) => {
    if (!file) return resolve('');
    if (typeof file.text === 'function') {
      file.text().then(resolve).catch(reject);
      return;
    }
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(reader.error || new Error('Read error'));
    reader.readAsText(file);
  });
  const exportOptions = () => {
    if (isExporting) return;
    setNotice(null);
    setIsExporting(true);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/export'
    }).then(payload => {
      const data = payload && payload.values ? payload : {
        values: payload
      };
      const json = JSON.stringify(data, null, 2);
      const blob = new Blob([json], {
        type: 'application/json;charset=utf-8'
      });
      const url = URL.createObjectURL(blob);
      const stamp = String(data && data.meta && data.meta.exportedAt || new Date().toISOString()).replace(/[:.]/g, '-');
      const a = document.createElement('a');
      a.href = url;
      a.download = `xstore-options-${stamp}.json`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      setNotice({
        status: 'success',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export file downloaded.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).catch(e => {
      setNotice({
        status: 'error',
        text: e && e.message ? e.message : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export error.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).finally(() => setIsExporting(false));
  };
  const onPickImportFile = e => {
    const file = e?.target?.files?.[0];
    if (!file) {
      setImportJsonText('');
      return;
    }
    fileToText(file).then(text => setImportJsonText(String(text || ''))).catch(() => {
      setImportJsonText('');
      setNotice({
        status: 'error',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cannot read file.', 'xstore')
      });
    });
  };
  const importOptions = () => {
    if (isImporting) return;
    if (!importJsonText) {
      setNotice({
        status: 'error',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Please choose a JSON file first.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      return;
    }
    if (!window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Import will overwrite existing options. Continue?', 'xstore'))) return;
    setNotice(null);
    setIsImporting(true);
    let parsed;
    try {
      parsed = JSON.parse(importJsonText);
    } catch (err) {
      setIsImporting(false);
      setNotice({
        status: 'error',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Invalid JSON file.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      return;
    }
    const valuesToImport = parsed && typeof parsed === 'object' && !Array.isArray(parsed) && parsed.values && typeof parsed.values === 'object' ? parsed.values : parsed;
    if (!valuesToImport || typeof valuesToImport !== 'object' || Array.isArray(valuesToImport)) {
      setIsImporting(false);
      setNotice({
        status: 'error',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Invalid import payload.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      return;
    }
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/import',
      method: 'POST',
      data: {
        values: valuesToImport
      }
    }).then(r => {
      const upd = r && r.updated ? r.updated : {};
      setValues(prev => ({
        ...prev,
        ...upd
      }));
      setDirty({});
      const imported = r && typeof r.imported === 'number' ? r.imported : Object.keys(upd).length;
      const ignored = r && typeof r.ignored === 'number' ? r.ignored : 0;
      setNotice({
        status: 'success',
        text: ignored > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Imported.', 'xstore') + ` ${imported}. ` + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Ignored:', 'xstore') + ` ${ignored}.` : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Imported.', 'xstore') + ` ${imported}.`
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).catch(e => {
      setNotice({
        status: 'error',
        text: e && e.message ? e.message : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Import error.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).finally(() => setIsImporting(false));
  };
  const saveChanges = () => {
    if (!isDirty || isSaving) return;
    setNotice(null);
    setIsSaving(true);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/save',
      method: 'POST',
      data: {
        changes: dirty
      }
    }).then(r => {
      const upd = r && r.updated ? r.updated : {};
      setValues(prev => ({
        ...prev,
        ...upd
      }));
      setDirty({});
      setNotice({
        status: 'success',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Changes saved successfully.', 'xstore')
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).catch(e => {
      setNotice({
        status: 'error',
        text: e && e.message ? e.message : 'Save error.'
      });
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    }).finally(() => setIsSaving(false));
  };
  const resetSection = () => {
    if (!activeSection) return;
    if (!window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset this section to defaults?', 'xstore'))) return;
    setNotice(null);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/reset-section',
      method: 'POST',
      data: {
        section: activeSection
      }
    }).then(r => {
      const upd = r && r.updated ? r.updated : {};
      setValues(prev => ({
        ...prev,
        ...upd
      }));
      setDirty({});
      setNotice({
        status: 'success',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Section reset successfully.', 'xstore')
      });
    }).catch(e => setNotice({
      status: 'error',
      text: e && e.message ? e.message : 'Reset error.'
    }));
  };
  const resetAll = () => {
    setIsConfirmResetAllOpen(true);
  };
  const doResetAll = () => {
    setNotice(null);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/xstore-options/v1/reset-all',
      method: 'POST',
      data: {}
    }).then(r => {
      const upd = r && r.updated ? r.updated : {};
      setValues(prev => ({
        ...prev,
        ...upd
      }));
      setDirty({});
      setNotice({
        status: 'success',
        text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All options reset successfully.', 'xstore')
      });
      setIsConfirmResetAllOpen(false);
    }).catch(e => setNotice({
      status: 'error',
      text: e && e.message ? e.message : 'Reset error.'
    }));
  };
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "xstoreOptLoadingOverlay",
      "aria-busy": "true",
      "aria-live": "polite",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        className: "xstoreOptLoadingSpinner",
        "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Loading...', 'xstore')
      })
    });
  }
  if (!schema) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      style: {
        padding: 24
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No data.', 'xstore')
    });
  }
  const currentFields = schema.fieldsBySection && schema.fieldsBySection[activeSection] ? schema.fieldsBySection[activeSection] : [];
  const sectionObj = (schema.sections || []).find(s => s.id === activeSection);
  const searchQuery = String(search || '').trim();
  const isSearchMode = searchQuery.length >= 3;
  const isImportExportSection = activeSection === 'import_export';
  const activeCallbackValues = (() => {
    if (activeSection !== 'single-product-page-wishlist' && activeSection !== 'single-product-page-compare' && activeSection !== 'single-product-page-waitlist') {
      return effectiveValues;
    }
    const out = {};
    for (const f of currentFields) {
      const k = f && f.setting ? String(f.setting) : '';
      if (!k) continue;
      out[k] = effectiveValues[k];
    }
    return out;
  })();
  const visibleFields = currentFields.filter(f => !isHiddenField(f)).filter(f => isFieldActive(f, activeCallbackValues)).filter(f => hasTextMatch(f, search));
  const sectionTitleById = (() => {
    const map = {};
    for (const s of schema.sections || []) {
      if (s && s.id) map[String(s.id)] = String(s.title || s.id);
    }
    return map;
  })();
  const searchResultFields = (() => {
    if (!isSearchMode || !schema) return [];
    if (!Array.isArray(searchMatches)) return [];
    const out = [];
    const bySetting = schema.fieldsBySetting || {};
    for (const m of searchMatches) {
      const setting = m && m.setting ? String(m.setting) : '';
      if (!setting) continue;
      const field = bySetting[setting];
      if (!field) continue;
      if (isHiddenField(field)) continue;
      if (field.type === 'custom' || field.is_separator) continue;
      if (!isFieldActive(field, effectiveValues)) continue;
      out.push(field);
    }
    const sectionPriority = {};
    for (const s of schema.sections || []) {
      var _s$priority2;
      if (s && s.id) sectionPriority[String(s.id)] = Number((_s$priority2 = s.priority) !== null && _s$priority2 !== void 0 ? _s$priority2 : 10);
    }
    out.sort((a, b) => {
      var _sectionPriority$Stri, _sectionPriority$Stri2, _a$priority, _b$priority;
      const ap = (_sectionPriority$Stri = sectionPriority[String(a.section || '')]) !== null && _sectionPriority$Stri !== void 0 ? _sectionPriority$Stri : 10;
      const bp = (_sectionPriority$Stri2 = sectionPriority[String(b.section || '')]) !== null && _sectionPriority$Stri2 !== void 0 ? _sectionPriority$Stri2 : 10;
      if (ap !== bp) return ap - bp;
      return Number((_a$priority = a.priority) !== null && _a$priority !== void 0 ? _a$priority : 10) - Number((_b$priority = b.priority) !== null && _b$priority !== void 0 ? _b$priority : 10);
    });
    return out;
  })();
  const searchResultGroups = (() => {
    if (!isSearchMode || !schema) return [];
    if (!Array.isArray(searchResultFields) || searchResultFields.length === 0) return [];
    const groups = [];
    const byId = {};
    for (const f of searchResultFields) {
      const secIdRaw = f && f.section ? String(f.section) : '';
      const secId = secIdRaw || '__other__';
      if (!byId[secId]) {
        byId[secId] = {
          id: secId,
          title: secIdRaw ? sectionTitleById[secIdRaw] || secIdRaw : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Other', 'xstore'),
          fields: []
        };
        groups.push(byId[secId]);
      }
      byId[secId].fields.push(f);
    }
    return groups;
  })();
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      class: "etheme-page-wrapper",
      "data-mode": colorMode,
      children: [headerLogoHtml ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "etheme-page-header flex justify-content-between align-items-center",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: `etheme-logo`,
          dangerouslySetInnerHTML: {
            __html: headerLogoHtml
          }
        }), switcherHtml ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
          className: "et_panel-dark-light-switcher",
          dangerouslySetInnerHTML: {
            __html: switcherHtml
          }
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "etheme-search",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("i", {
            className: "etheme-search-icon",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("svg", {
              version: "1.1",
              xmlns: "http://www.w3.org/2000/svg",
              width: "1em",
              height: "1em",
              viewBox: "0 0 32 32",
              fill: "currentColor",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("path", {
                d: "M31.712 30.4l-8.224-8.192c2.112-2.464 3.264-5.568 3.264-8.768 0-7.36-5.984-13.376-13.376-13.376-7.36 0-13.344 5.984-13.344 13.344s5.984 13.376 13.376 13.376c3.232 0 6.304-1.152 8.768-3.296l8.224 8.192c0.192 0.192 0.416 0.288 0.64 0.288s0.448-0.096 0.608-0.256c0.192-0.16 0.288-0.384 0.32-0.64 0-0.256-0.096-0.512-0.256-0.672zM24.928 13.44c0 6.336-5.184 11.52-11.552 11.52-6.336 0-11.52-5.184-11.52-11.552 0-6.336 5.184-11.52 11.552-11.52s11.52 5.184 11.52 11.552z"
              })
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "spinner",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "et-loader",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("svg", {
                className: "loader-circular",
                viewBox: "25 25 50 50",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("circle", {
                  className: "loader-path",
                  cx: "50",
                  cy: "50",
                  r: "12",
                  fill: "none",
                  strokeWidth: "2",
                  strokeMiterlimit: "10"
                })
              })
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
            type: "text",
            className: "etheme-options-search form-control",
            placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search for options', 'xstore'),
            value: search,
            onChange: e => setSearch(e?.target?.value || '')
          })]
        })]
      }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "xstoreOptApp",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("aside", {
          className: "etheme-page-nav xstoreOptSidebar",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("ul", {
            children: renderNavItems(nav.childrenByPanel[nav.rootId] || [], 0)
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            className: "etheme-page-nav-collapser",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "dashicons dashicons-arrow-left"
            })
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("main", {
          className: "et-row etheme-page-content",
          ref: mainRef,
          children: [isSaving ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            className: "xstoreOptSavingOverlay",
            "aria-busy": "true",
            "aria-live": "polite",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              className: "xstoreOptSavingSpinner",
              "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Saving...', 'xstore')
            })
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "xstoreOptContent",
            children: [notice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
              status: notice.status,
              isDismissible: true,
              onRemove: () => setNotice(null),
              children: notice.text
            }) : null, isDirty ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
              status: "warning",
              isDismissible: false,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('You have unsaved changes.', 'xstore')
            }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h1", {
              class: "etheme-page-title etheme-page-title-type-2",
              children: isSearchMode ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Search Results', 'xstore') : sectionObj ? sectionObj.title : ''
            }), !isSearchMode && sectionObj && sectionObj.description ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              className: "xstoreOptSectionDescription",
              dangerouslySetInnerHTML: {
                __html: sectionObj.description
              }
            }) : null, isSearchMode ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
              children: [isSearching ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
                className: "xstoreOptLoadingOverlay",
                "aria-busy": "true",
                "aria-live": "polite",
                style: {
                  marginTop: '100px'
                },
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
                  className: "xstoreOptLoadingSpinner",
                  "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Searching...', 'xstore')
                })
              }) : null, !isSearching && Array.isArray(searchMatches) && searchMatches.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
                status: "warning",
                isDismissible: false,
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No results.', 'xstore')
              }) : null, searchResultGroups.map(g => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
                  class: "xstoreOptSearchSectionTitle etheme-page-title etheme-page-title-type-2",
                  children: g.title
                }), (g.fields || []).map(field => {
                  const setting = field.setting;
                  const val = effectiveValues[setting];
                  const hideTitle = field.type === 'code' && isThemeCustomCssSetting(setting);
                  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                    className: "xstoreOptField flex align-items-center",
                    "data-setting": setting,
                    children: [!hideTitle ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                      className: "xstoreOptFieldMeta",
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
                        className: "xstoreOptFieldLabel et-title",
                        children: field.label || setting
                      }), field.tooltip ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                        className: "xstoreOptFieldDesc",
                        onClick: handleInlineEditLink,
                        dangerouslySetInnerHTML: {
                          __html: field.tooltip
                        }
                      }) : null, field.description ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                        className: "xstoreOptFieldDesc",
                        onClick: handleInlineEditLink,
                        dangerouslySetInnerHTML: {
                          __html: field.description
                        }
                      }) : null]
                    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                      className: "xstoreOptFieldControl",
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(FieldControl, {
                        field: field,
                        value: val,
                        onChange: next => setValue(setting, next),
                        fontsData: fontsData
                      })
                    })]
                  }, setting);
                })]
              }, `search_group_${g.id}`))]
            }) : isImportExportSection ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                className: "xstoreOptField flex align-items-center",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                  className: "xstoreOptFieldMeta",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
                    className: "xstoreOptFieldLabel et-title",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export options', 'xstore')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                    className: "xstoreOptFieldDesc",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Download a JSON backup of all Theme Options.', 'xstore')
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                  className: "xstoreOptFieldControl",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
                    type: "button",
                    className: "et-button no-loader",
                    onClick: exportOptions,
                    disabled: isExporting,
                    children: isExporting ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Exporting...', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export', 'xstore')
                  })
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                className: "xstoreOptField flex align-items-center",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                  className: "xstoreOptFieldMeta",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
                    className: "xstoreOptFieldLabel et-title",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Import options', 'xstore')
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                    className: "xstoreOptFieldDesc",
                    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Upload a previously exported JSON file to overwrite current Theme Options.', 'xstore')
                  })]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
                  className: "xstoreOptFieldControl",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
                    type: "file",
                    accept: ".json,application/json",
                    onChange: onPickImportFile
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("br", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
                    type: "button",
                    className: "et-button et-button-grey no-loader",
                    onClick: importOptions,
                    disabled: isImporting,
                    children: isImporting ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Importing...', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Import', 'xstore')
                  })]
                })]
              })]
            }) : visibleFields.map(field => {
              const setting = field.setting;
              const val = effectiveValues[setting];
              const hideTitle = field.type === 'code' && isThemeCustomCssSetting(setting);
              if (field.type === 'custom' || field.is_separator) {
                const title = String(field.label || '').trim();
                if (!title) return null;
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
                  className: "xstoreOptSearchSectionTitle etheme-page-title etheme-page-title-type-2",
                  children: title
                }, `sep_${setting}`);
              }
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                className: "xstoreOptField flex align-items-center",
                "data-setting": setting,
                children: [!hideTitle ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
                  className: "xstoreOptFieldMeta",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
                    className: "xstoreOptFieldLabel et-title",
                    children: field.label || setting
                  }), field.tooltip ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                    className: "xstoreOptFieldDesc",
                    onClick: handleInlineEditLink,
                    dangerouslySetInnerHTML: {
                      __html: field.tooltip
                    }
                  }) : null, field.description ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                    className: "xstoreOptFieldDesc",
                    onClick: handleInlineEditLink,
                    dangerouslySetInnerHTML: {
                      __html: field.description
                    }
                  }) : null]
                }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
                  className: "xstoreOptFieldControl",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(FieldControl, {
                    field: field,
                    value: val,
                    onChange: next => setValue(setting, next),
                    fontsData: fontsData
                  })
                })]
              }, setting);
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            ref: actionsRef,
            className: `xstoreOptActionsBar${actionsLayout.mode === 'fixed' ? ' isFixed' : ' isAbsolute'}`,
            style: actionsLayout.mode === 'fixed' ? {
              left: actionsLayout.left,
              width: actionsLayout.width
            } : undefined,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
              type: "button",
              className: "et-button no-loader xstoreOptBtnSave",
              onClick: saveChanges,
              disabled: !isDirty || isSaving,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Save Changes', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
              type: "button",
              className: "et-button et-button-grey no-loader",
              onClick: resetSection,
              disabled: isImportExportSection || isSaving,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset Section', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
              type: "button",
              className: "et-button et-button-grey no-loader",
              onClick: resetAll,
              disabled: isSaving,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset All', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
              className: "xstoreOptThemeSupport",
              href: themeLinks?.support_forum?.link ? themeLinks.support_forum.link : null,
              target: "_blank",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("svg", {
                width: "1em",
                height: "1em",
                viewBox: "0 0 10 10",
                fill: "currentColor",
                xmlns: "http://www.w3.org/2000/svg",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("path", {
                  d: "M5 0C2.24284 0 0 2.24284 0 5C0 7.75716 2.24284 10 5 10C7.75716 10 10 7.75716 10 5C10 2.24284 7.75716 0 5 0ZM5 0.833333C7.30631 0.833333 9.16667 2.69369 9.16667 5C9.16667 7.30631 7.30631 9.16667 5 9.16667C2.69369 9.16667 0.833333 7.30631 0.833333 5C0.833333 2.69369 2.69369 0.833333 5 0.833333ZM5 2.5C4.08366 2.5 3.33333 3.25033 3.33333 4.16667H4.16667C4.16667 3.70117 4.53451 3.33333 5 3.33333C5.46549 3.33333 5.83333 3.70117 5.83333 4.16667C5.83333 4.48568 5.62826 4.76888 5.32552 4.86979L5.15625 4.92188C4.81608 5.03418 4.58333 5.3597 4.58333 5.71615V6.25H5.41667V5.71615L5.58594 5.66406C6.22721 5.45085 6.66667 4.84212 6.66667 4.16667C6.66667 3.25033 5.91634 2.5 5 2.5ZM4.58333 6.66667V7.5H5.41667V6.66667H4.58333Z"
                })
              })
            })]
          })]
        })]
      }), isConfirmResetAllOpen ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Modal, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset ALL options to defaults?', 'xstore'),
        onRequestClose: () => setIsConfirmResetAllOpen(false),
        shouldCloseOnClickOutside: true,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          style: {
            display: 'grid',
            gap: 10
          },
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              style: {
                margin: 0
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This action will permanently reset all theme options to their default values.', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              style: {
                margin: 0
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('We strongly recommend exporting your current settings before proceeding.', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              style: {
                margin: 0
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This action cannot be undone.', 'xstore')
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "xstoreOptInlineRow",
            style: {
              justifyContent: 'flex-end'
            },
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "secondary",
              onClick: exportOptions,
              disabled: isExporting,
              children: isExporting ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Exporting...', 'xstore') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Export', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "tertiary",
              onClick: () => setIsConfirmResetAllOpen(false),
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Cancel', 'xstore')
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
              variant: "primary",
              isDestructive: true,
              onClick: doResetAll,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reset all', 'xstore')
            })]
          })]
        })
      }) : null, footerHtml ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
        dangerouslySetInnerHTML: {
          __html: footerHtml
        }
      }) : null]
    })
  });
}
const root = document.getElementById('xstore-options-admin-root');
if (root) {
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.render)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(App, {}), root);
}

/***/ },

/***/ "./src/style.scss"
/*!************************!*\
  !*** ./src/style.scss ***!
  \************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"index": 0,
/******/ 			"./style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkxstore_options_admin_mirror"] = globalThis["webpackChunkxstore_options_admin_mirror"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-index"], () => (__webpack_require__("./src/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map