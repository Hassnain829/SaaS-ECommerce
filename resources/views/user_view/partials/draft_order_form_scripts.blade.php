<script>
    (() => {
        const parseMoney = (value) => {
            const parsed = Number.parseFloat(value);
            return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
        };

        const formatWithCode = (amount, currency) => {
            const value = Math.abs(parseMoney(amount));
            return `${currency} ${value.toFixed(2)}`;
        };

        const formatDiscountWithCode = (amount, currency) => `-${formatWithCode(amount, currency)}`;

        document.querySelectorAll('[data-draft-order-form]').forEach((form) => {
            const currency = form.dataset.currency || 'USD';
            const calculatedMode = '{{ \App\Models\DraftOrder::TAX_SOURCE_CALCULATED }}';
            const manualMode = '{{ \App\Models\DraftOrder::TAX_SOURCE_MANUAL }}';
            const persistedSourceIsCalculated = form.dataset.calculatedTax === '1';
            const pricesIncludeTax = form.dataset.pricesIncludeTax === '1';
            const isSavedDraft = form.dataset.isSavedDraft === '1';
            const isEstimate = form.dataset.isEstimate === '1';
            const persistedSubtotal = form.dataset.persistedSubtotal || '';
            const persistedDiscount = form.dataset.persistedDiscount || '';
            const persistedShipping = form.dataset.persistedShipping || '';
            const persistedTax = form.dataset.persistedTax || '';
            const persistedTotal = form.dataset.persistedTotal || '';

            const linesContainer = form.querySelector('[data-draft-lines-container]');
            const lineTemplate = document.getElementById('draft-line-row-template');
            const manualTaxFields = form.querySelector('[data-manual-tax-fields]');
            const calculatedTaxFields = form.querySelector('[data-calculated-tax-fields]');
            const manualTaxInput = form.querySelector('[data-manual-tax-input]');
            const manualTaxLockedByForm = manualTaxInput?.hasAttribute('readonly') ?? false;
            const calculatedTaxDisplay = form.querySelector('[data-calculated-tax-display]');
            const staleNotice = form.querySelector('[data-tax-stale-notice]');
            const previewGuidance = form.querySelector('[data-tax-preview-guidance]');
            const confirmManualSwitch = form.querySelector('[data-confirm-manual-tax-switch]');
            const modeRadios = form.querySelectorAll('[data-tax-mode-radio]');
            const billingSameCheckbox = form.querySelector('[data-billing-same-checkbox]');
            const billingFields = form.querySelector('[data-draft-billing-fields]');
            const convertButtons = form.querySelectorAll('[data-convert-draft-button]');
            const saveButtons = form.querySelectorAll('[data-primary-save-button]');
            let lineIndex = form.querySelectorAll('[data-draft-line]').length;
            let taxDrivingDirty = form.dataset.taxDrivingDirty === '1';
            let previewRequestId = 0;

            const selectedTaxMode = () => {
                const checked = form.querySelector('[data-tax-mode-radio]:checked');
                return checked ? checked.value : manualMode;
            };

            const isAutomaticMode = () => selectedTaxMode() === calculatedMode;

            const syncBillingFieldsVisibility = () => {
                if (! billingSameCheckbox || ! billingFields) {
                    return;
                }

                billingFields.classList.toggle('hidden', billingSameCheckbox.checked);
            };

            const syncTaxModeUi = () => {
                const isCalculated = isAutomaticMode();

                if (manualTaxFields) {
                    manualTaxFields.classList.toggle('opacity-60', isCalculated);
                }

                if (calculatedTaxFields) {
                    calculatedTaxFields.classList.toggle('opacity-60', ! isCalculated);
                }

                if (manualTaxInput) {
                    manualTaxInput.readOnly = manualTaxLockedByForm || isCalculated;
                }
            };

            const automaticTaxPending = () => {
                if (! isAutomaticMode() || isEstimate) {
                    return false;
                }

                return taxDrivingDirty || ! persistedSourceIsCalculated;
            };

            const syncPreviewGuidance = (message) => {
                if (! previewGuidance) {
                    return;
                }

                if (message) {
                    previewGuidance.textContent = message;
                    previewGuidance.classList.remove('hidden');
                } else {
                    previewGuidance.textContent = '';
                    previewGuidance.classList.add('hidden');
                }
            };

            const scheduleAutomaticTaxPreview = () => {
                if (! form.dataset.taxPreviewUrl || ! isAutomaticMode()) {
                    syncPreviewGuidance(null);

                    return;
                }

                const requestId = ++previewRequestId;
                window.clearTimeout(form._taxPreviewTimer);
                form._taxPreviewTimer = window.setTimeout(async () => {
                    try {
                        const response = await fetch(form.dataset.taxPreviewUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new FormData(form),
                        });

                        if (! response.ok || requestId !== previewRequestId) {
                            return;
                        }

                        const preview = await response.json();
                        if (requestId !== previewRequestId) {
                            return;
                        }

                        if (! preview.ready) {
                            if (calculatedTaxDisplay) {
                                calculatedTaxDisplay.textContent = '0.00';
                            }
                            syncPreviewGuidance(preview.guidance ?? null);

                            return;
                        }

                        if (calculatedTaxDisplay) {
                            calculatedTaxDisplay.textContent = parseMoney(preview.total_tax).toFixed(2);
                        }

                        syncPreviewGuidance(preview.guidance ?? null);

                        if (isEstimate && ! automaticTaxPending()) {
                            setSummaryText('[data-tax-summary-display]', `Est. ${formatWithCode(preview.total_tax, currency)}`);
                            setSummaryText('[data-total-display]', `Est. ${formatWithCode(preview.estimated_total, currency)}`);
                        }
                    } catch (error) {
                        if (requestId === previewRequestId) {
                            syncPreviewGuidance(null);
                        }
                    }
                }, 350);
            };

            const setSummaryText = (selector, text) => {
                form.querySelectorAll(selector).forEach((node) => {
                    node.textContent = text;
                });
            };

            const markLineTaxStale = (row) => {
                const lineTaxDisplay = row?.querySelector('[data-line-tax-display]');
                if (! lineTaxDisplay || ! lineTaxDisplay.dataset.persistedLineTax) {
                    return;
                }

                lineTaxDisplay.textContent = 'Recalculates on save';
                lineTaxDisplay.classList.add('italic', 'text-[#92400E]');
                lineTaxDisplay.dataset.lineTaxStale = '1';
            };

            const markAllPersistedLineTaxStale = () => {
                if (! isAutomaticMode()) {
                    return;
                }

                form.querySelectorAll('[data-draft-line]').forEach((row) => markLineTaxStale(row));
            };

            const markTaxDrivingChange = () => {
                taxDrivingDirty = true;

                if (isAutomaticMode()) {
                    staleNotice?.classList.remove('hidden');
                    markAllPersistedLineTaxStale();
                }

                updateTotals();
                scheduleAutomaticTaxPreview();
            };

            const syncAutomaticStaleUi = () => {
                if (! isAutomaticMode()) {
                    staleNotice?.classList.add('hidden');

                    return;
                }

                if (automaticTaxPending()) {
                    staleNotice?.classList.remove('hidden');
                    markAllPersistedLineTaxStale();
                } else {
                    staleNotice?.classList.add('hidden');
                }
            };

            const updateVariantMeta = (row) => {
                const select = row.querySelector('[data-variant-select]');
                const meta = row.querySelector('[data-variant-meta]');
                const badge = row.querySelector('[data-taxability-badge]');
                const selected = select?.selectedOptions?.[0];

                if (! selected || ! select.value) {
                    meta?.classList.add('hidden');
                    if (badge) {
                        badge.innerHTML = '<span class="text-[#94A3B8]">—</span>';
                    }
                    return;
                }

                const sku = selected.dataset.sku || '';
                const stock = selected.dataset.stock || '0';
                const taxable = selected.dataset.taxable === '1';

                if (meta) {
                    meta.textContent = `${sku ? `SKU ${sku} · ` : ''}${stock} available`;
                    meta.classList.remove('hidden');
                }

                if (badge) {
                    badge.innerHTML = taxable
                        ? '<span class="text-[#166534]">Taxable</span>'
                        : '<span class="text-[#64748B]">Non-taxable</span>';
                }
            };

            const updateRemoveLabels = (row) => {
                const select = row.querySelector('[data-variant-select]');
                const removeButton = row.querySelector('[data-remove-line]');
                const label = select?.selectedOptions?.[0]?.dataset.label || 'line item';
                if (removeButton) {
                    removeButton.setAttribute('aria-label', `Remove ${label}`);
                }
            };

            const reindexLines = () => {
                form.querySelectorAll('[data-draft-line]').forEach((row, index) => {
                    row.querySelectorAll('[name^="items["]').forEach((input) => {
                        input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
                    });
                });
                lineIndex = form.querySelectorAll('[data-draft-line]').length;
            };

            const updateTotals = () => {
                let subtotal = 0;

                form.querySelectorAll('[data-draft-line]').forEach((row) => {
                    const select = row.querySelector('[data-variant-select]');
                    const quantityInput = row.querySelector('[data-quantity-input]');
                    const priceInput = row.querySelector('[data-unit-price-input]');
                    const lineTotal = row.querySelector('[data-line-total]');
                    const selected = select?.selectedOptions?.[0];
                    const hasProduct = Boolean(select?.value);
                    const quantity = Math.max(1, Number.parseInt(quantityInput?.value || '1', 10) || 1);
                    const price = hasProduct ? parseMoney(priceInput?.value || selected?.dataset.price || '0') : 0;
                    const total = hasProduct ? quantity * price : 0;

                    if (lineTotal) {
                        lineTotal.textContent = formatWithCode(total, currency);
                    }

                    subtotal += total;
                    updateVariantMeta(row);
                    updateRemoveLabels(row);
                });

                const discount = parseMoney(form.querySelector('[data-discount-input]')?.value || '0');
                const shipping = parseMoney(form.querySelector('[data-shipping-input]')?.value || '0');

                setSummaryText('[data-subtotal-display]', formatWithCode(subtotal, currency));
                setSummaryText('[data-discount-display]', formatDiscountWithCode(discount, currency));
                setSummaryText('[data-shipping-display]', formatWithCode(shipping, currency));

                if (! isAutomaticMode()) {
                    const manualTax = parseMoney(manualTaxInput?.value || '0');
                    const total = Math.max(0, subtotal + shipping + manualTax - discount);
                    setSummaryText('[data-tax-summary-display]', formatWithCode(manualTax, currency));
                    setSummaryText('[data-total-display]', formatWithCode(total, currency));
                    return;
                }

                if (isEstimate && isAutomaticMode()) {
                    setSummaryText('[data-tax-summary-display]', 'Calculated when saved');
                    setSummaryText('[data-total-display]', 'Confirmed after tax calculation');
                    return;
                }

                if (automaticTaxPending()) {
                    setSummaryText('[data-tax-summary-display]', 'Recalculates on save');
                    setSummaryText('[data-total-display]', 'Pending tax recalculation');
                    return;
                }

                if (isSavedDraft && persistedSourceIsCalculated) {
                    setSummaryText('[data-tax-summary-display]', formatWithCode(persistedTax || calculatedTaxDisplay?.textContent || '0', currency));
                    setSummaryText('[data-total-display]', formatWithCode(persistedTotal, currency));
                    return;
                }

                setSummaryText('[data-tax-summary-display]', formatWithCode(persistedTax || '0', currency));
                setSummaryText('[data-total-display]', formatWithCode(persistedTotal || '0', currency));
            };

            const bindLineRow = (row) => {
                const select = row.querySelector('[data-variant-select]');
                const quantityInput = row.querySelector('[data-quantity-input]');
                const priceInput = row.querySelector('[data-unit-price-input]');
                const removeButton = row.querySelector('[data-remove-line]');
                const selected = select?.selectedOptions?.[0];

                if (select?.value && quantityInput && ! quantityInput.value) {
                    quantityInput.value = '1';
                }

                if (select?.value && priceInput && ! priceInput.value && selected?.dataset.price) {
                    priceInput.value = selected.dataset.price;
                }

                select?.addEventListener('change', () => {
                    const option = select.selectedOptions[0];
                    if (select.value && quantityInput && ! quantityInput.value) {
                        quantityInput.value = '1';
                    }
                    if (select.value && priceInput && option?.dataset.price) {
                        priceInput.value = option.dataset.price;
                    }
                    markTaxDrivingChange();
                    updateTotals();
                });

                [quantityInput, priceInput].forEach((input) => {
                    input?.addEventListener('input', () => {
                        markTaxDrivingChange();
                        updateTotals();
                    });
                });

                removeButton?.addEventListener('click', () => {
                    row.remove();
                    reindexLines();
                    markTaxDrivingChange();
                    updateTotals();
                });
            };

            form.querySelectorAll('[data-draft-line]').forEach((row) => bindLineRow(row));

            form.querySelector('[data-add-line]')?.addEventListener('click', () => {
                if (! lineTemplate || ! linesContainer) {
                    return;
                }

                const html = lineTemplate.innerHTML.replaceAll('__INDEX__', String(lineIndex));
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const row = wrapper.firstElementChild;
                linesContainer.appendChild(row);
                lineIndex += 1;
                bindLineRow(row);
                updateTotals();
            });

            form.querySelectorAll('[data-discount-input], [data-shipping-input]').forEach((input) => {
                input.addEventListener('input', () => {
                    markTaxDrivingChange();
                    updateTotals();
                });
            });

            manualTaxInput?.addEventListener('input', updateTotals);

            form.querySelectorAll('[data-tax-driving-input], [name="shipping_state"], [name="shipping_country"]').forEach((input) => {
                input.addEventListener('input', markTaxDrivingChange);
                input.addEventListener('change', markTaxDrivingChange);
            });

            modeRadios.forEach((radio) => {
                radio.addEventListener('change', () => {
                    const switchingToManual = radio.value === manualMode && radio.checked;

                    if (switchingToManual && persistedSourceIsCalculated && confirmManualSwitch && ! confirmManualSwitch.checked) {
                        const confirmed = window.confirm('Switch to manual tax?\nCalculated rate details will be removed.');
                        if (! confirmed) {
                            const automaticRadio = form.querySelector(`[data-tax-mode-radio][value="${calculatedMode}"]`);
                            if (automaticRadio) {
                                automaticRadio.checked = true;
                            }
                            syncTaxModeUi();
                            syncAutomaticStaleUi();
                            updateTotals();
                            return;
                        }

                        confirmManualSwitch.checked = true;
                    }

                    syncTaxModeUi();
                    syncAutomaticStaleUi();
                    updateTotals();
                    scheduleAutomaticTaxPreview();
                });
            });

            billingSameCheckbox?.addEventListener('change', syncBillingFieldsVisibility);

            form.addEventListener('submit', (event) => {
                [...saveButtons, ...convertButtons].forEach((button) => {
                    if (button && event.submitter === button) {
                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                    }
                });
            });

            syncTaxModeUi();
            syncBillingFieldsVisibility();
            syncAutomaticStaleUi();
            updateTotals();
            scheduleAutomaticTaxPreview();
        });

        document.querySelectorAll('[data-tax-breakdown-disclosure]').forEach((details) => {
            const label = details.querySelector('[data-tax-breakdown-toggle-label]');
            const syncLabel = () => {
                if (label) {
                    label.textContent = details.open ? 'Hide breakdown' : 'View breakdown';
                }
            };

            details.addEventListener('toggle', syncLabel);
            syncLabel();
        });
    })();
</script>
