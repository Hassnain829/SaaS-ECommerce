const READY = 'data-country-combobox-ready';

let active = null;
const boundForms = new WeakSet();

const optionLabel = (option) => (option?.textContent || '').trim();

const selectedLabel = (select) => {
    const option = select.options[select.selectedIndex];

    return option && option.value ? optionLabel(option) : '';
};

const hideNativeSelect = (select) => {
    select.style.position = 'absolute';
    select.style.width = '1px';
    select.style.height = '1px';
    select.style.padding = '0';
    select.style.margin = '-1px';
    select.style.overflow = 'hidden';
    select.style.clip = 'rect(0, 0, 0, 0)';
    select.style.whiteSpace = 'nowrap';
    select.style.border = '0';
    select.style.opacity = '0';
    select.style.pointerEvents = 'none';
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
};

const showNativeSelect = (select) => {
    select.style.cssText = '';
    select.removeAttribute('tabindex');
    select.removeAttribute('aria-hidden');
};

const matchesQuery = (button, query) => {
    if (! query) {
        return true;
    }

    const haystack = `${button.dataset.value || ''} ${button.dataset.label || ''}`.toLowerCase();

    return haystack.includes(query);
};

const placePanel = (search, panel) => {
    const rect = search.getBoundingClientRect();
    const maxHeight = Math.min(256, Math.max(160, window.innerHeight - 24));
    const spaceBelow = window.innerHeight - rect.bottom - 8;
    const openUp = spaceBelow < 160 && rect.top > spaceBelow;

    panel.style.position = 'fixed';
    panel.style.left = `${Math.max(8, rect.left)}px`;
    panel.style.width = `${Math.max(rect.width, 220)}px`;
    panel.style.maxHeight = `${Math.min(maxHeight, openUp ? rect.top - 8 : spaceBelow)}px`;
    panel.style.zIndex = '90';
    panel.style.right = 'auto';

    if (openUp) {
        panel.style.top = 'auto';
        panel.style.bottom = `${window.innerHeight - rect.top + 4}px`;
    } else {
        panel.style.top = `${rect.bottom + 4}px`;
        panel.style.bottom = 'auto';
    }
};

const closeActive = () => {
    if (! active) {
        return;
    }

    const { wrap, search, panel, list } = active;
    panel.classList.add('hidden');
    panel.setAttribute('hidden', 'hidden');
    panel.style.pointerEvents = 'none';
    if (panel.parentElement === document.body && wrap) {
        wrap.appendChild(panel);
    }
    search?.setAttribute('aria-expanded', 'false');
    list?.querySelectorAll('[aria-selected="true"]').forEach((item) => item.setAttribute('aria-selected', 'false'));
    active = null;
};

const ensureItems = (select, list) => {
    if (list.childElementCount > 0) {
        return;
    }

    [...select.options].forEach((option) => {
        if (! option.value) {
            return;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');
        button.dataset.value = option.value;
        button.dataset.label = optionLabel(option);
        button.textContent = optionLabel(option);
        button.className = 'flex w-full px-3 py-2 text-left text-sm font-medium text-[#334155] hover:bg-[#F8FAFC]';
        list.appendChild(button);
    });
};

const visibleButtons = (list) => [...list.querySelectorAll('button')].filter((button) => ! button.classList.contains('hidden'));

const applyFilter = (list, query, selectedValue) => {
    const normalized = query.trim().toLowerCase();
    let visibleCount = 0;
    let firstMatch = null;

    list.querySelectorAll('button').forEach((button) => {
        const show = matchesQuery(button, normalized);
        button.classList.toggle('hidden', ! show);
        const isSelected = button.dataset.value === selectedValue;
        button.classList.toggle('bg-[#EEF4FF]', isSelected);
        button.classList.toggle('text-[#0052CC]', isSelected);
        button.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        if (show) {
            visibleCount += 1;
            if (! firstMatch) {
                firstMatch = button;
            }
        }
    });

    let empty = list.querySelector('[data-country-combobox-empty]');
    if (! empty) {
        empty = document.createElement('p');
        empty.dataset.countryComboboxEmpty = '1';
        empty.className = 'hidden px-3 py-2 text-sm text-[#64748B]';
        empty.textContent = 'No matching country';
        list.appendChild(empty);
    }
    empty.classList.toggle('hidden', visibleCount > 0);

    return firstMatch;
};

const choose = (select, search, code, label) => {
    if (select.value !== code) {
        select.value = code;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    search.value = label || selectedLabel(select);
    search.setCustomValidity('');
    closeActive();
};

const commitTyped = (select, search, list) => {
    const typed = search.value.trim();
    if (typed === '') {
        if (select.value) {
            search.value = selectedLabel(select);
        }
        closeActive();

        return;
    }

    const exactCode = typed.toUpperCase();
    const codeOption = [...select.options].find((option) => option.value === exactCode);
    if (codeOption) {
        choose(select, search, codeOption.value, optionLabel(codeOption));

        return;
    }

    const visible = visibleButtons(list);
    const exactLabel = visible.find((button) => (button.dataset.label || '').toLowerCase() === typed.toLowerCase());
    if (exactLabel) {
        choose(select, search, exactLabel.dataset.value, exactLabel.dataset.label);

        return;
    }

    if (visible.length === 1) {
        choose(select, search, visible[0].dataset.value, visible[0].dataset.label);

        return;
    }

    search.value = selectedLabel(select);
    closeActive();
};

const bindValueSync = (select, search) => {
    const descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    if (! descriptor?.get || ! descriptor?.set) {
        return;
    }

    Object.defineProperty(select, 'value', {
        configurable: true,
        enumerable: true,
        get() {
            return descriptor.get.call(this);
        },
        set(next) {
            descriptor.set.call(this, next);
            if (document.activeElement !== search) {
                search.value = selectedLabel(select);
            }
        },
    });
};

const openPanel = (wrap, select, search, panel, list) => {
    if (search.disabled) {
        return;
    }

    if (active && active.search !== search) {
        closeActive();
    }

    ensureItems(select, list);
    const query = search.value.trim().toLowerCase() === selectedLabel(select).toLowerCase()
        ? ''
        : search.value;
    applyFilter(list, query, select.value);
    document.body.appendChild(panel);
    panel.classList.remove('hidden');
    panel.removeAttribute('hidden');
    placePanel(search, panel);
    panel.style.pointerEvents = 'auto';
    search.setAttribute('aria-expanded', 'true');
    active = { wrap, select, search, panel, list };

    const selectedButton = [...list.querySelectorAll('button')].find((button) => button.dataset.value === select.value && ! button.classList.contains('hidden'));
    selectedButton?.scrollIntoView({ block: 'nearest' });
};

const bindCombobox = (wrap) => {
    const select = wrap.querySelector('[data-country-combobox-native], [data-role="geo-country-select"]');
    const search = wrap.querySelector('[data-country-combobox-search]');
    const panel = wrap.querySelector('[data-country-combobox-panel]');
    if (! select || ! search || ! panel) {
        return;
    }

    const list = panel.querySelector('[data-country-combobox-list]') || panel;

    wrap.setAttribute(READY, '1');
    wrap.classList.add('is-enhanced');
    hideNativeSelect(select);
    search.classList.remove('hidden');
    search.value = selectedLabel(select);
    const label = wrap.querySelector('[data-country-combobox-label]');
    if (label && search.id) {
        label.setAttribute('for', search.id);
    }
    bindValueSync(select, search);

    if (select.required) {
        select.required = false;
        search.dataset.countryRequired = '1';
    }

    search.addEventListener('focus', () => {
        if (! search.disabled) {
            search.removeAttribute('readonly');
        }
        search.select();
        openPanel(wrap, select, search, panel, list);
    });

    search.addEventListener('input', () => {
        search.setCustomValidity('');
        openPanel(wrap, select, search, panel, list);
        applyFilter(list, search.value, select.value);
        placePanel(search, panel);
    });

    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            search.value = selectedLabel(select);
            closeActive();

            return;
        }

        if (event.key === 'Enter') {
            if (active?.search === search) {
                event.preventDefault();
                const highlighted = visibleButtons(list).find((button) => button.getAttribute('aria-selected') === 'true');
                if (highlighted) {
                    choose(select, search, highlighted.dataset.value, highlighted.dataset.label);
                } else {
                    commitTyped(select, search, list);
                }
            }

            return;
        }

        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        event.preventDefault();
        if (active?.search !== search) {
            openPanel(wrap, select, search, panel, list);
        }

        const buttons = visibleButtons(list);
        if (buttons.length === 0) {
            return;
        }

        const current = buttons.findIndex((button) => button.getAttribute('aria-selected') === 'true');
        const delta = event.key === 'ArrowDown' ? 1 : -1;
        const nextIndex = current < 0
            ? (event.key === 'ArrowDown' ? 0 : buttons.length - 1)
            : (current + delta + buttons.length) % buttons.length;

        buttons.forEach((button, index) => {
            const on = index === nextIndex;
            button.setAttribute('aria-selected', on ? 'true' : 'false');
            button.classList.toggle('bg-[#EEF4FF]', on);
            button.classList.toggle('text-[#0052CC]', on);
            if (on) {
                button.scrollIntoView({ block: 'nearest' });
            }
        });
    });

    search.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (active?.search === search && ! panel.contains(document.activeElement)) {
                commitTyped(select, search, list);
            }
            if (document.activeElement !== search) {
                search.setAttribute('readonly', 'readonly');
            }
        }, 120);
    });

    panel.addEventListener('mousedown', (event) => {
        const button = event.target.closest('button[data-value]');
        if (! button) {
            return;
        }
        event.preventDefault();
        choose(select, search, button.dataset.value, button.dataset.label);
    });

    const form = select.form;
    if (form && ! boundForms.has(form)) {
        boundForms.add(form);
        form.addEventListener('submit', (event) => {
            form.querySelectorAll('[data-country-combobox]').forEach((box) => {
                const boxSelect = box.querySelector('[data-country-combobox-native], [data-role="geo-country-select"]');
                const boxSearch = box.querySelector('[data-country-combobox-search]');
                const boxList = box.querySelector('[data-country-combobox-panel]');
                if (boxSelect && boxSearch && boxList) {
                    commitTyped(boxSelect, boxSearch, boxList);
                }
            });

            const missing = [...form.querySelectorAll('[data-country-combobox-search][data-country-required="1"]')]
                .find((input) => {
                    const box = input.closest('[data-country-combobox]');
                    const boxSelect = box?.querySelector('[data-country-combobox-native], [data-role="geo-country-select"]');

                    return boxSelect && ! boxSelect.value;
                });

            if (missing) {
                event.preventDefault();
                missing.setCustomValidity('Select a country from the list.');
                missing.reportValidity();
            }
        });
    }
};

export const teardownCountryComboboxes = (root = document) => {
    closeActive();
    root.querySelectorAll(`[data-country-combobox][${READY}="1"]`).forEach((wrap) => {
        const select = wrap.querySelector('[data-country-combobox-native], [data-role="geo-country-select"]');
        const search = wrap.querySelector('[data-country-combobox-search]');
        const panel = wrap.querySelector('[data-country-combobox-panel]');
        if (select) {
            showNativeSelect(select);
            if (search?.dataset.countryRequired === '1') {
                select.required = true;
            }
        }
        if (search) {
            search.classList.add('hidden');
            search.removeAttribute('data-country-required');
            const label = wrap.querySelector('[data-country-combobox-label]');
            if (label && select?.id) {
                label.setAttribute('for', select.id);
            }
        }
        if (panel && panel.parentElement === document.body) {
            wrap.appendChild(panel);
        }
        wrap.classList.remove('is-enhanced');
        wrap.removeAttribute(READY);
    });
};

export const initCountryComboboxes = (root = document) => {
    document.querySelectorAll('body > [data-country-combobox-panel]').forEach((panel) => {
        panel.classList.add('hidden');
        panel.setAttribute('hidden', 'hidden');
        panel.style.pointerEvents = 'none';
    });
    root.querySelectorAll('[data-country-combobox]').forEach((wrap) => {
        if (wrap.getAttribute(READY) === '1') {
            return;
        }
        bindCombobox(wrap);
    });
};

document.addEventListener('click', (event) => {
    if (! active) {
        return;
    }
    if (active.wrap.contains(event.target) || active.panel.contains(event.target)) {
        return;
    }
    closeActive();
});

document.addEventListener('scroll', () => {
    if (active) {
        placePanel(active.search, active.panel);
    }
}, true);

window.addEventListener('resize', () => {
    if (active) {
        placePanel(active.search, active.panel);
    }
});
