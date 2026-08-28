(function () {
  const boot = window.XStoreOptionsAdmin || {};

  const apiFetch = wp.apiFetch;
  apiFetch.use(apiFetch.createNonceMiddleware(boot.nonce));

  try {
    const root = (window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : (boot.root ? boot.root : null);
    if (root && apiFetch.createRootURLMiddleware) {
      apiFetch.use(apiFetch.createRootURLMiddleware(root));
    }
  } catch (e) {}

  function isInvalidJsonError(e) {
    return !!(e && e.code === 'invalid_json');
  }

  function getStatusFromError(e) {
    const s = (e && e.data && typeof e.data.status !== 'undefined') ? Number(e.data.status) : null;
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

    const method = (options && options.method) ? String(options.method).toUpperCase() : 'GET';
    const headers = { Accept: 'application/json', 'X-WP-Nonce': String(boot.nonce || '') };
    let body;
    if (options && typeof options.data !== 'undefined' && method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(options.data);
    }

    const res = await fetch(url, { method, headers, body, credentials: 'same-origin' });
    const ct = String(res.headers.get('content-type') || '');
    if (!ct.includes('application/json')) {
      throw { code: 'invalid_json', message: 'Non-JSON response', data: { status: res.status } };
    }
    const json = await res.json();
    if (res.ok) return json;
    throw {
      code: (json && json.code) ? json.code : 'rest_error',
      message: (json && json.message) ? json.message : 'Request failed',
      data: (json && json.data) ? json.data : { status: res.status },
    };
  }

  apiFetch.use((options, next) => next(options).catch((e) => {
    const p = options && options.path ? String(options.path) : '';
    if (p.startsWith('/xstore-options/v1/') && isInvalidJsonError(e)) {
      return fetchViaRestRoute(options);
    }
    throw e;
  }));

  const el = wp.element.createElement;
  const useState = wp.element.useState;
  const useEffect = wp.element.useEffect;
  const useMemo = wp.element.useMemo;

  const __ = wp.i18n.__;

  const {
    Button,
    Notice,
    Spinner,
    SearchControl,
    TextControl,
    TextareaControl,
    ToggleControl,
    CheckboxControl,
    RangeControl,
    SelectControl,
    Popover,
    ColorPicker,
  } = wp.components;

  function api(path, options) {
    return apiFetch(Object.assign({ path }, options || {}));
  }

  function toOptions(choices) {
    if (!choices || typeof choices !== 'object') return [];
    return Object.keys(choices).map((key) => ({ value: key, label: String(choices[key]) }));
  }

  function getString(v) {
    if (v === null || typeof v === 'undefined') return '';
    return String(v);
  }

  function hasTextMatch(field, q) {
    if (!q) return true;
    const needle = q.toLowerCase();
    const hay = (field.label || '') + ' ' + (field.tooltip || '') + ' ' + (field.description || '');
    return hay.toLowerCase().includes(needle);
  }

  function isFieldActive(field, values) {
    const rules = field.active_callback || [];
    if (!Array.isArray(rules) || rules.length === 0) return true;

    for (const r of rules) {
      const s = r && r.setting ? String(r.setting) : '';
      const op = r && r.operator ? String(r.operator) : '==';
      const expected = (r && typeof r.value !== 'undefined') ? r.value : null;

      const actual = values[s];

      if (op === '==') {
        if (actual != expected) return false;
      } else if (op === '!=') {
        if (actual == expected) return false;
      } else if (op === 'in') {
        const list = Array.isArray(expected) ? expected : [expected];
        if (Array.isArray(actual)) {
          let ok = false;
          for (const a of actual) if (list.includes(a)) ok = true;
          if (!ok) return false;
        } else {
          if (!list.includes(actual)) return false;
        }
      } else if (op === 'not_in') {
        const list = Array.isArray(expected) ? expected : [expected];
        if (Array.isArray(actual)) {
          for (const a of actual) if (list.includes(a)) return false;
        } else {
          if (list.includes(actual)) return false;
        }
      }
    }

    return true;
  }

  function ColorField(props) {
    const color = getString(props.value);
    const [open, setOpen] = useState(false);

    const swatchStyle = { background: color || 'transparent' };

    return el('div', { className: 'xstoreOptInlineRow' },
      el('div', { className: 'xstoreOptColorSwatch', style: swatchStyle }),
      el(Button, { variant: 'secondary', onClick: () => setOpen(!open) }, __('Select color', 'xstore')),
      el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => props.onChange('') }, __('Clear', 'xstore')),
      open ? el(Popover, { position: 'bottom left', onClose: () => setOpen(false) },
        el('div', { style: { padding: 12 } },
          el(ColorPicker, {
            color: color || undefined,
            enableAlpha: true,
            onChange: (c) => {
              if (!c) return;
              if (c.rgb && typeof c.rgb.a !== 'undefined' && c.rgb.a !== 1) {
                props.onChange(`rgba(${c.rgb.r}, ${c.rgb.g}, ${c.rgb.b}, ${c.rgb.a})`);
              } else if (c.hex) {
                props.onChange(c.hex);
              }
            },
          })
        )
      ) : null
    );
  }

  function ImageField(props) {
    const val = props.value || '';
    const url = (val && typeof val === 'object') ? (val.url || '') : getString(val);

    function openMedia() {
      if (!window.wp || !wp.media) return;

      const frame = wp.media({
        title: __('Select image', 'xstore'),
        button: { text: __('Use this image', 'xstore') },
        multiple: false,
      });

      frame.on('select', () => {
        const att = frame.state().get('selection').first().toJSON();
        props.onChange({ id: att.id, url: att.url });
      });

      frame.open();
    }

    return el('div', { className: 'xstoreOptInlineRow' },
      url ? el('img', {
        src: url,
        style: { maxWidth: 160, height: 'auto', borderRadius: 8, border: '1px solid #dcdcde' },
        alt: '',
      }) : null,
      el(Button, { variant: 'secondary', onClick: openMedia }, url ? __('Change', 'xstore') : __('Select', 'xstore')),
      url ? el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => props.onChange('') }, __('Remove', 'xstore')) : null
    );
  }

  function DimensionsField(props) {
    const value = (props.value && typeof props.value === 'object') ? props.value : {};
    const labels = (props.field.choices && props.field.choices.labels) ? props.field.choices.labels : null;
    const keys = labels ? Object.keys(labels) : Object.keys(value);

    function setKey(k, v) {
      const next = Object.assign({}, value, { [k]: v });
      props.onChange(next);
    }

    return el('div', { className: 'xstoreOptInlineRow' },
      keys.map((k) => el(TextControl, {
        key: k,
        label: labels ? String(labels[k]) : k,
        value: getString(value[k]),
        onChange: (v) => setKey(k, v),
      }))
    );
  }

  function MulticolorField(props) {
    const value = (props.value && typeof props.value === 'object') ? props.value : {};
    const choices = props.field.choices || {};
    const keys = Object.keys(choices);

    function setKey(k, v) {
      const next = Object.assign({}, value, { [k]: v });
      props.onChange(next);
    }

    return el('div', { style: { display: 'grid', gap: 12 } },
      keys.map((k) => el('div', { key: k },
        el('div', { style: { fontWeight: 600, marginBottom: 6 } }, String(choices[k] || k)),
        el(ColorField, { value: value[k], onChange: (v) => setKey(k, v) })
      ))
    );
  }

  function BackgroundField(props) {
    const value = (props.value && typeof props.value === 'object') ? props.value : {};
    const keys = Object.keys(value);

    function setKey(k, v) {
      const next = Object.assign({}, value, { [k]: v });
      props.onChange(next);
    }

    return el('div', { style: { display: 'grid', gap: 12 } },
      keys.map((k) => {
        const label = k.replace(/[-_]/g, ' ');
        const v = value[k];

        if (k.includes('color')) {
          return el('div', { key: k },
            el('div', { style: { fontWeight: 600, marginBottom: 6 } }, label),
            el(ColorField, { value: v, onChange: (nv) => setKey(k, nv) })
          );
        }

        if (k.includes('image')) {
          return el('div', { key: k },
            el('div', { style: { fontWeight: 600, marginBottom: 6 } }, label),
            el(ImageField, { value: v, onChange: (nv) => setKey(k, nv) })
          );
        }

        return el(TextControl, {
          key: k,
          label,
          value: getString(v),
          onChange: (nv) => setKey(k, nv),
        });
      })
    );
  }

  function TypographyField(props) {
    const value = (props.value && typeof props.value === 'object') ? props.value : {};
    const keys = Object.keys(value);

    function setKey(k, v) {
      const next = Object.assign({}, value, { [k]: v });
      props.onChange(next);
    }

    return el('div', { style: { display: 'grid', gap: 12 } },
      keys.map((k) => {
        const label = k.replace(/[-_]/g, ' ');
        if (k.includes('color')) {
          return el('div', { key: k },
            el('div', { style: { fontWeight: 600, marginBottom: 6 } }, label),
            el(ColorField, { value: value[k], onChange: (nv) => setKey(k, nv) })
          );
        }
        return el(TextControl, {
          key: k,
          label,
          value: getString(value[k]),
          onChange: (nv) => setKey(k, nv),
        });
      })
    );
  }

  function SortableField(props) {
    const choices = props.field.choices || {};
    const selected = Array.isArray(props.value) ? props.value.slice() : [];
    const remaining = Object.keys(choices).filter((k) => !selected.includes(k));
    const [toAdd, setToAdd] = useState(remaining[0] || '');

    function move(i, dir) {
      const j = i + dir;
      if (j < 0 || j >= selected.length) return;
      const next = selected.slice();
      const tmp = next[i]; next[i] = next[j]; next[j] = tmp;
      props.onChange(next);
    }

    function remove(i) {
      const next = selected.slice();
      next.splice(i, 1);
      props.onChange(next);
    }

    function add() {
      if (!toAdd) return;
      const next = selected.concat([toAdd]);
      props.onChange(next);
      const rem2 = Object.keys(choices).filter((k) => !next.includes(k));
      setToAdd(rem2[0] || '');
    }

    return el('div', null,
      el('div', { style: { display: 'grid', gap: 8 } },
        selected.map((k, i) => el('div', { key: k, className: 'xstoreOptInlineRow' },
          el('div', { style: { minWidth: 240 } }, String(choices[k] || k)),
          el(Button, { variant: 'secondary', onClick: () => move(i, -1) }, '↑'),
          el(Button, { variant: 'secondary', onClick: () => move(i, 1) }, '↓'),
          el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => remove(i) }, __('Remove', 'xstore'))
        ))
      ),
      el('div', { className: 'xstoreOptInlineRow', style: { marginTop: 10, alignItems: 'flex-end' } },
        el(SelectControl, {
          label: __('Add element', 'xstore'),
          value: toAdd,
          options: remaining.map((k) => ({ value: k, label: String(choices[k] || k) })),
          onChange: (v) => setToAdd(v),
        }),
        el(Button, { variant: 'primary', onClick: add, disabled: !toAdd }, __('Add', 'xstore'))
      )
    );
  }

  function RepeaterField(props) {
    const value = Array.isArray(props.value) ? props.value : [];
    const subFields = props.field.fields || {};
    const rowLabel = props.field.row_label || {};
    const buttonLabel = props.field.button_label || __('Add new item', 'xstore');

    function setItem(idx, nextItem) {
      const next = value.slice();
      next[idx] = nextItem;
      props.onChange(next);
    }

    function addItem() {
      const item = {};
      for (const key of Object.keys(subFields)) {
        const f = subFields[key] || {};
        item[key] = (typeof f.default !== 'undefined') ? f.default : '';
      }
      props.onChange(value.concat([item]));
    }

    function removeItem(idx) {
      const next = value.slice();
      next.splice(idx, 1);
      props.onChange(next);
    }

    function moveItem(idx, dir) {
      const j = idx + dir;
      if (j < 0 || j >= value.length) return;
      const next = value.slice();
      const tmp = next[idx];
      next[idx] = next[j];
      next[j] = tmp;
      props.onChange(next);
    }

    function getItemTitle(item, idx) {
      if (rowLabel && rowLabel.field && item && typeof item === 'object' && typeof item[rowLabel.field] !== 'undefined') {
        return String(item[rowLabel.field] || `${__('Item', 'xstore')} ${idx + 1}`);
      }
      return `${__('Item', 'xstore')} ${idx + 1}`;
    }

    function renderSubFieldControl(subKey, subField, itemVal, onChange) {
      const t = subField.type || 'text';
      const label = subField.label ? String(subField.label) : subKey;

      if (t === 'select') {
        return el(SelectControl, {
          label,
          value: getString(itemVal),
          options: toOptions(subField.choices || {}),
          onChange: (v) => onChange(v),
        });
      }

      if (t === 'checkbox') {
        return el(CheckboxControl, {
          label,
          checked: !!itemVal,
          onChange: (v) => onChange(!!v),
        });
      }

      if (t === 'image') {
        return el('div', null,
          el('div', { style: { fontWeight: 600, marginBottom: 6 } }, label),
          el(ImageField, { value: itemVal, onChange })
        );
      }

      return el(TextControl, {
        label,
        value: getString(itemVal),
        onChange: (v) => onChange(v),
      });
    }

    return el('div', null,
      value.map((item, idx) => {
        const itemObj = (item && typeof item === 'object') ? item : {};
        const title = getItemTitle(itemObj, idx);

        return el('div', { key: idx, className: 'xstoreOptRepeaterItem' },
          el('div', { className: 'xstoreOptRepeaterHeader' },
            el('div', { className: 'xstoreOptRepeaterTitle' }, title),
            el('div', { className: 'xstoreOptInlineRow' },
              el(Button, { variant: 'secondary', onClick: () => moveItem(idx, -1) }, '↑'),
              el(Button, { variant: 'secondary', onClick: () => moveItem(idx, 1) }, '↓'),
              el(Button, { variant: 'tertiary', isDestructive: true, onClick: () => removeItem(idx) }, __('Remove', 'xstore'))
            )
          ),
          el('div', { className: 'xstoreOptRepeaterGrid' },
            Object.keys(subFields).map((subKey) => {
              const sf = subFields[subKey] || {};
              return el('div', { key: subKey },
                renderSubFieldControl(
                  subKey,
                  sf,
                  itemObj[subKey],
                  (nv) => setItem(idx, Object.assign({}, itemObj, { [subKey]: nv }))
                )
              );
            })
          )
        );
      }),
      el(Button, { variant: 'primary', onClick: addItem }, buttonLabel)
    );
  }

  function FieldControl(props) {
    const field = props.field;
    const type = field.type;
    const value = (typeof props.value === 'undefined') ? field.default : props.value;

    if (type === 'toggle') {
      return el(ToggleControl, {
        checked: !!value,
        onChange: (v) => props.onChange(v ? 1 : 0),
      });
    }

    if (type === 'checkbox') {
      return el(CheckboxControl, {
        checked: !!value,
        label: '',
        onChange: (v) => props.onChange(!!v),
      });
    }

    if (type === 'slider' || type === 'number') {
      const min = field.choices && typeof field.choices.min !== 'undefined' ? Number(field.choices.min) : undefined;
      const max = field.choices && typeof field.choices.max !== 'undefined' ? Number(field.choices.max) : undefined;
      const step = field.choices && typeof field.choices.step !== 'undefined' ? Number(field.choices.step) : 1;

      return el(RangeControl, {
        value: value === '' ? undefined : Number(value),
        min, max, step,
        onChange: (v) => props.onChange(v),
      });
    }

    if (type === 'select') {
      return el(SelectControl, {
        value: getString(value),
        options: toOptions(field.choices),
        onChange: (v) => props.onChange(v),
      });
    }

    if (type === 'radio-buttonset') {
      const opts = toOptions(field.choices);
      return el('div', { className: 'xstoreOptInlineRow' },
        opts.map((o) => el(Button, {
          key: o.value,
          variant: (getString(value) === o.value) ? 'primary' : 'secondary',
          onClick: () => props.onChange(o.value),
        }, o.label))
      );
    }

    if (type === 'radio-image') {
      const choices = field.choices || {};
      return el('div', { className: 'xstoreOptRadioImageGrid' },
        Object.keys(choices).map((k) => {
          const src = String(choices[k] || '');
          const active = getString(value) === k;
          return el('button', {
            key: k,
            type: 'button',
            className: 'xstoreOptRadioImageBtn' + (active ? ' isActive' : ''),
            onClick: () => props.onChange(k),
          }, el('img', { src, alt: k }));
        })
      );
    }

    if (type === 'color') {
      return el(ColorField, { value, onChange: props.onChange });
    }

    if (type === 'image') {
      return el(ImageField, { value, onChange: props.onChange });
    }

    if (type === 'dimensions') {
      return el(DimensionsField, { field, value, onChange: props.onChange });
    }

    if (type === 'multicolor') {
      return el(MulticolorField, { field, value, onChange: props.onChange });
    }

    if (type === 'background') {
      return el(BackgroundField, { field, value, onChange: props.onChange });
    }

    if (type === 'typography') {
      return el(TypographyField, { field, value, onChange: props.onChange });
    }

    if (type === 'sortable') {
      return el(SortableField, { field, value, onChange: props.onChange });
    }

    if (type === 'repeater') {
      return el(RepeaterField, { field, value, onChange: props.onChange });
    }

    if (type === 'textarea' || type === 'etheme-textarea' || type === 'code' || type === 'editor') {
      return el(TextareaControl, {
        value: getString(value),
        onChange: (v) => props.onChange(v),
      });
    }

    return el(TextControl, {
      value: getString(value),
      onChange: (v) => props.onChange(v),
    });
  }

  function App() {
    const [loading, setLoading] = useState(true);
    const [schema, setSchema] = useState(null);
    const [values, setValues] = useState({});
    const [dirty, setDirty] = useState({});
    const [activeSection, setActiveSection] = useState(boot.tab || '');
    const [search, setSearch] = useState('');
    const [notice, setNotice] = useState(null);
    const [collapsedGroups, setCollapsedGroups] = useState({});

    const effectiveValues = useMemo(() => Object.assign({}, values, dirty), [values, dirty]);
    const isDirty = useMemo(() => Object.keys(dirty).length > 0, [dirty]);

    useEffect(() => {
      setLoading(true);
      Promise.all([
        api('/xstore-options/v1/schema'),
        api('/xstore-options/v1/values'),
      ]).then(([s, v]) => {
        setSchema(s);
        setValues((v && v.values) ? v.values : {});
        setLoading(false);

        const sections = (s && s.sections) ? s.sections : [];
        if (sections.length) {
          const initial = boot.tab && sections.find(sec => sec.id === boot.tab) ? boot.tab : sections[0].id;
          setActiveSection(initial);
        }
      }).catch((e) => {
        setLoading(false);
        setNotice({ status: 'error', text: (e && e.message) ? e.message : 'Error loading data.' });
      });
    }, []);

    useEffect(() => {
      if (!activeSection) return;
      const url = new URL(window.location.href);
      url.searchParams.set('tab', activeSection);
      window.history.replaceState({}, '', url.toString());
    }, [activeSection]);

    const groups = useMemo(() => {
      if (!schema) return [];
      const panelsById = schema.panelsById || {};
      const sections = schema.sections || [];

      const map = {};
      for (const sec of sections) {
        const pid = sec.panel || '_root';
        if (!map[pid]) {
          map[pid] = {
            id: pid,
            title: pid === '_root' ? __('General Settings', 'xstore') : (panelsById[pid] ? panelsById[pid].title : pid),
            icon: pid === '_root' ? 'dashicons-admin-settings' : (panelsById[pid] ? panelsById[pid].icon : 'dashicons-admin-generic'),
            sections: [],
          };
        }
        map[pid].sections.push(sec);
      }

      const out = Object.values(map);
      out.forEach(g => g.sections.sort((a, b) => (a.priority || 0) - (b.priority || 0)));
      out.sort((a, b) => (a.id === '_root' ? -1 : 0) - (b.id === '_root' ? -1 : 0));
      return out;
    }, [schema]);

    const filteredGroups = useMemo(() => {
      if (!search) return groups;
      const q = search.toLowerCase();
      return groups.map((g) => {
        const sections = g.sections.filter((s) => (s.title || '').toLowerCase().includes(q));
        return Object.assign({}, g, { sections });
      }).filter(g => g.sections.length);
    }, [groups, search]);

    function setValue(setting, next) {
      setDirty((prev) => Object.assign({}, prev, { [setting]: next }));
    }

    function toggleGroup(groupId) {
      setCollapsedGroups((prev) => Object.assign({}, prev, { [groupId]: !prev[groupId] }));
    }

    function saveChanges() {
      if (!isDirty) return;
      setNotice(null);

      api('/xstore-options/v1/save', { method: 'POST', data: { changes: dirty } })
        .then((r) => {
          const upd = (r && r.updated) ? r.updated : {};
          setValues((prev) => Object.assign({}, prev, upd));
          setDirty({});
          setNotice({ status: 'success', text: __('Saved.', 'xstore') });
        })
        .catch((e) => {
          setNotice({ status: 'error', text: (e && e.message) ? e.message : 'Save error.' });
        });
    }

    function resetSection() {
      if (!activeSection) return;
      if (!window.confirm(__('Reset this section to defaults?', 'xstore'))) return;

      setNotice(null);
      api('/xstore-options/v1/reset-section', { method: 'POST', data: { section: activeSection } })
        .then((r) => {
          const upd = (r && r.updated) ? r.updated : {};
          setValues((prev) => Object.assign({}, prev, upd));
          setDirty({});
          setNotice({ status: 'success', text: __('Section reset.', 'xstore') });
        })
        .catch((e) => {
          setNotice({ status: 'error', text: (e && e.message) ? e.message : 'Reset error.' });
        });
    }

    function resetAll() {
      if (!window.confirm(__('Reset ALL options to defaults?', 'xstore'))) return;

      setNotice(null);
      api('/xstore-options/v1/reset-all', { method: 'POST', data: {} })
        .then((r) => {
          const upd = (r && r.updated) ? r.updated : {};
          setValues((prev) => Object.assign({}, prev, upd));
          setDirty({});
          setNotice({ status: 'success', text: __('All options reset.', 'xstore') });
        })
        .catch((e) => {
          setNotice({ status: 'error', text: (e && e.message) ? e.message : 'Reset error.' });
        });
    }

    if (loading) {
      return el('div', { style: { padding: 24 } }, el(Spinner, null));
    }

    if (!schema) {
      return el('div', { style: { padding: 24 } }, __('No data.', 'xstore'));
    }

    const currentFields = (schema.fieldsBySection && schema.fieldsBySection[activeSection]) ? schema.fieldsBySection[activeSection] : [];
    const sectionObj = (schema.sections || []).find(s => s.id === activeSection);

    const visibleFields = currentFields
      .filter((f) => isFieldActive(f, effectiveValues))
      .filter((f) => hasTextMatch(f, search));

    return el('div', { className: 'xstoreOptApp' },
      el('aside', { className: 'xstoreOptSidebar' },
        el('div', { className: 'xstoreOptBrand' },
          el('span', { className: 'dashicons dashicons-store' }),
          el('span', null, 'XStore')
        ),
        el('div', { className: 'xstoreOptNav' },
          filteredGroups.map((g) => {
            const isCollapsed = !!collapsedGroups[g.id];
            return el('div', { key: g.id },
              el('div', {
                className: 'xstoreOptGroupHeader',
                role: 'button',
                tabIndex: 0,
                onClick: () => toggleGroup(g.id),
                onKeyDown: (e) => {
                  if (e.key === 'Enter' || e.key === ' ') toggleGroup(g.id);
                },
              },
                el('div', { className: 'xstoreOptGroupTitle' },
                  g.icon ? el('span', { className: 'dashicons ' + g.icon }) : null,
                  el('span', null, g.title)
                ),
                el('span', { className: 'dashicons ' + (isCollapsed ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2') })
              ),
              !isCollapsed ? g.sections.map((s) => el('div', {
                key: s.id,
                className: 'xstoreOptNavItem' + (s.id === activeSection ? ' isActive' : ''),
                onClick: () => setActiveSection(s.id),
              },
                s.icon ? el('span', { className: 'dashicons ' + s.icon }) : null,
                el('span', null, s.title)
              )) : null
            );
          })
        )
      ),
      el('main', { className: 'xstoreOptMain' },
        el('div', { className: 'xstoreOptTopbar' },
          el(SearchControl, {
            value: search,
            onChange: setSearch,
            placeholder: __('Search', 'xstore'),
          }),
          el(Button, {
            className: 'xstoreOptBtnSave',
            variant: 'primary',
            onClick: saveChanges,
            disabled: !isDirty,
          }, __('Save Changes', 'xstore')),
          el(Button, { variant: 'secondary', onClick: resetSection }, __('Reset Section', 'xstore')),
          el(Button, { variant: 'secondary', isDestructive: true, onClick: resetAll }, __('Reset All', 'xstore'))
        ),
        el('div', { className: 'xstoreOptContent' },
          notice ? el(Notice, { status: notice.status, isDismissible: true, onRemove: () => setNotice(null) }, notice.text) : null,
          isDirty ? el(Notice, { status: 'warning', isDismissible: false }, __('Settings have changed, you should save them!', 'xstore')) : null,
          el('div', { className: 'xstoreOptSectionTitle' }, sectionObj ? sectionObj.title : ''),
          visibleFields.map((field) => {
            const setting = field.setting;
            const val = effectiveValues[setting];

            return el('div', { key: setting, className: 'xstoreOptField' },
              el('div', { className: 'xstoreOptFieldMeta' },
                el('div', { className: 'xstoreOptFieldLabel' }, field.label || setting),
                (field.tooltip || field.description) ? el('div', { className: 'xstoreOptFieldDesc' }, field.tooltip || field.description) : null
              ),
              el('div', { className: 'xstoreOptFieldControl' },
                el(FieldControl, {
                  field,
                  value: val,
                  onChange: (next) => setValue(setting, next),
                })
              )
            );
          })
        )
      )
    );
  }

  const root = document.getElementById('xstore-options-admin-root');
  if (root) {
    wp.element.render(el(App, null), root);
  }
})();


