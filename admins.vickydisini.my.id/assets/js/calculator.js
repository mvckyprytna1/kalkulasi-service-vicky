(() => {
    const data = window.APP_DATA || {};
    const cfg = data.config || {};
    const presets = data.presets || [];
    const addons = data.addons || [];
    const clients = data.clients || [];
    const rules = cfg.pricing_rules || {};
    const defaults = cfg.defaults || {};
    const brand = cfg.app || {};

    const form = document.querySelector('#pricingForm');
    if (!form) return;

    const $ = (id) => document.querySelector(id);
    const el = {
        servicePreset: $('#servicePreset'), riskLevel: $('#riskLevel'), workHours: $('#workHours'), presetNote: $('#presetNote'),
        minimalPrice: $('#minimalPrice'), idealPrice: $('#idealPrice'), clientRange: $('#clientRange'), negoPrice: $('#negoPrice'),
        breakdownList: $('#breakdownList'), profitText: $('#profitText'), dpText: $('#dpText'), riskBadge: $('#riskBadge'),
        warningBox: $('#warningBox'), waMessage: $('#waMessage'), saveBtn: $('#saveBtn'), copyBtn: $('#copyBtn'), waBtn: $('#waBtn'),
        resetBtn: $('#resetBtn'), saveNote: $('#saveNote'), mockSafe: $('#mockSafe'), mockIdeal: $('#mockIdeal'), mockRange: $('#mockRange'),
        clientType: $('#clientTypeSelect'), existingClientWrap: $('#existingClientWrap'), existingClientSelect: $('#existingClientSelect'),
    };

    let latest = null;

    const num = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;
    const money = (value) => 'Rp' + Math.round(num(value)).toLocaleString('id-ID');
    const roundUp = (value, base) => Math.ceil(num(value) / Math.max(1000, num(base) || 5000)) * Math.max(1000, num(base) || 5000);

    const normalizeWaNumber = (value) => {
        let digits = String(value || '').replace(/\D/g, '');
        if (!digits) return '';

        // Format Indonesia:
        // 0812xxxx -> 62812xxxx
        // 812xxxx  -> 62812xxxx
        if (digits.startsWith('0')) {
            digits = '62' + digits.slice(1);
        } else if (digits.startsWith('8')) {
            digits = '62' + digits;
        }

        return digits;
    };

    const hasAny = (text, words) => {
        text = String(text || '').toLowerCase();
        return words.some((w) => text.includes(String(w).toLowerCase()));
    };

    const setFieldValue = (name, value) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (field && value !== undefined && value !== null) field.value = value;
    };

    const getSelectedClientFromOption = () => {
        const opt = el.existingClientSelect?.selectedOptions?.[0];
        if (!opt || !opt.value) return null;
        return {
            id: opt.value,
            name: opt.dataset.name || '',
            phone: opt.dataset.phone || '',
            address: opt.dataset.address || '',
            type: opt.dataset.type || '',
            notes: opt.dataset.notes || '',
        };
    };

    const applyClientToForm = (client) => {
        if (!client) return;
        setFieldValue('client_name', client.name);
        setFieldValue('client_phone', client.phone);
        setFieldValue('location', client.address);
        if (el.clientType) el.clientType.value = 'langganan';
        const notesField = form.querySelector('[name="client_notes"]');
        if (notesField && !notesField.value.trim()) {
            notesField.value = client.notes || '';
        }
        calculate();
    };

    const updateClientPickerState = () => {
        // Picker client sengaja selalu ditampilkan.
        // Sebelumnya hidden by default dan bergantung JS/cache, jadi di kalkulator estimasi bisa tidak muncul.
        // Sekarang dropdown client lama tetap terlihat, dan memilih client otomatis mengubah tipe menjadi langganan.
        if (!el.existingClientWrap) return;
        el.existingClientWrap.style.display = '';
    };

    el.clientType?.addEventListener('change', () => {
        updateClientPickerState();
        // Jangan kosongkan pilihan client otomatis.
        // Kadang teknisi pilih client lama dulu lalu menyesuaikan tipe; data jangan hilang diam-diam.
    });

    el.existingClientSelect?.addEventListener('change', () => {
        applyClientToForm(getSelectedClientFromOption());
    });


    const formData = () => {
        const fd = new FormData(form);
        const input = {};
        for (const [key, value] of fd.entries()) {
            if (key.endsWith('[]')) continue;
            input[key] = value;
        }
        input.addon_ids = Array.from(form.querySelectorAll('input[name="addon_ids[]"]:checked')).map((x) => x.value);
        input.csrf_token = form.querySelector('input[name="csrf_token"]')?.value || '';
        return input;
    };

    const getPreset = (id) => presets.find((x) => String(x.id) === String(id)) || presets[0];
    const getAddons = (ids) => addons.filter((x) => ids.includes(String(x.id)));

    const getProfile = (input, preset, selectedAddons) => {
        const text = [
            preset?.category, preset?.name, preset?.code, preset?.note,
            input.device_type, input.client_notes,
            selectedAddons.map((a) => a.name).join(' ')
        ].filter(Boolean).join(' ').toLowerCase();

        const modal = num(input.sparepart_cost) + num(input.shipping_cost) + num(input.material_cost)
            + num(input.transport_cost) + num(input.parking_cost) + num(input.third_party_cost);

        const profiles = {
            driver_repair: {
                label: 'Software ringan / driver',
                addonCap: 95000, addonFactors: [1, .65, .35, .25],
                includedExtra: .5, riskFactor: .45, marginCap: 8, warrantyCap: 12000,
                normalCap: 250000, urgentCap: 350000, rangeLow: .88, rangeHigh: 1.12, minProfit: 8
            },
            install_os: {
                label: 'Install / repair OS',
                addonCap: 150000, addonFactors: [1, .75, .45, .30],
                includedExtra: .75, riskFactor: .60, marginCap: 10, warrantyCap: 20000,
                normalCap: 350000, urgentCap: 450000, rangeLow: .90, rangeHigh: 1.15, minProfit: 10
            },
            general_software: {
                label: 'Software umum',
                addonCap: 130000, addonFactors: [1, .70, .40, .30],
                includedExtra: .5, riskFactor: .55, marginCap: 10, warrantyCap: 18000,
                normalCap: 300000, urgentCap: 400000, rangeLow: .90, rangeHigh: 1.15, minProfit: 10
            },
            data_recovery: {
                label: 'Backup / recovery data',
                addonCap: 450000, addonFactors: [1, .85, .65, .50],
                includedExtra: .25, riskFactor: 1, marginCap: 18, warrantyCap: 0,
                normalCap: 0, urgentCap: 0, rangeLow: .90, rangeHigh: 1.25, minProfit: 15
            },
            hardware: {
                label: 'Hardware / sparepart',
                addonCap: 500000, addonFactors: [1, .90, .75, .60],
                includedExtra: .25, riskFactor: .85, marginCap: 15, warrantyCap: 35000,
                normalCap: 0, urgentCap: 0, rangeLow: .92, rangeHigh: 1.18, minProfit: 12
            },
            onsite: {
                label: 'On-site / panggilan',
                addonCap: 450000, addonFactors: [1, .85, .65, .50],
                includedExtra: .25, riskFactor: .70, marginCap: 12, warrantyCap: 25000,
                normalCap: 450000, urgentCap: 600000, rangeLow: .90, rangeHigh: 1.18, minProfit: 12
            }
        };

        if (num(input.transport_cost) > 0 || num(input.parking_cost) > 0 || hasAny(text, ['on-site', 'onsite', 'panggilan', 'transport', 'jaringan', 'router', 'wifi router', 'kantor'])) return profiles.onsite;
        if (modal >= 150000 || hasAny(text, ['ganti lcd', 'lcd', 'keyboard', 'baterai', 'battery', 'ssd', 'hdd', 'ram', 'fan', 'thermal', 'cleaning', 'engsel', 'casing', 'motherboard', 'short', 'konslet', 'hardware', 'sparepart'])) return profiles.hardware;
        if (hasAny(text, ['recovery', 'bad sector', 'backup data besar', 'data besar', 'partisi error', 'clone drive risky'])) return profiles.data_recovery;
        if (hasAny(text, ['install ulang', 'install windows', 'windows siap pakai', 'repair windows', 'boot windows', 'linux', 'dual boot', 'os'])) return profiles.install_os;
        if (hasAny(text, ['driver', 'bluetooth', 'brightness', 'kontras', 'display', 'vga', 'wifi', 'audio', 'touchpad', 'hotkey', 'chipset', 'unknown device', 'device manager', 'pairing'])) return profiles.driver_repair;

        return profiles.general_software;
    };

    const smartAddonTotal = (selectedAddons, profile) => {
        const prices = selectedAddons.map((a) => num(a.price)).sort((a, b) => b - a);
        const raw = prices.reduce((a, b) => a + b, 0);
        let total = 0;
        prices.forEach((price, i) => {
            const factors = profile.addonFactors || [1, .7, .4, .3];
            const factor = factors[i] ?? factors[factors.length - 1];
            total += price * factor;
        });
        if (profile.addonCap > 0) total = Math.min(total, profile.addonCap);
        return { total, raw };
    };

    const difficultyFee = (key, serviceBase, profile) => {
        const isDriver = String(profile.label).toLowerCase().includes('driver');
        const isSoftware = String(profile.label).toLowerCase().includes('software') || String(profile.label).toLowerCase().includes('install');

        if (key === 'mudah') return 0;

        if (isDriver) {
            if (key === 'sedang') return Math.max(10000, serviceBase * .06);
            if (key === 'sulit') return Math.max(30000, serviceBase * .14);
            if (key === 'berat') return Math.max(55000, serviceBase * .25);
        }

        if (isSoftware) {
            if (key === 'sedang') return Math.max(15000, serviceBase * .08);
            if (key === 'sulit') return Math.max(40000, serviceBase * .18);
            if (key === 'berat') return Math.max(75000, serviceBase * .32);
        }

        if (key === 'sedang') return Math.max(20000, serviceBase * .10);
        if (key === 'sulit') return Math.max(60000, serviceBase * .22);
        if (key === 'berat') return Math.max(120000, serviceBase * .38);
        return 0;
    };

    const capPrice = (price, minimal, profile, urgencyKey, modalTotal) => {
        if (modalTotal > 0) return Math.max(minimal, price);
        const cap = urgencyKey === 'normal' ? num(profile.normalCap) : num(profile.urgentCap);
        if (cap <= 0) return Math.max(minimal, price);
        return Math.max(minimal, Math.min(price, cap));
    };

    const buildWarnings = (input, result) => {
        const warnings = [];
        if (result.addon_raw_total > 0 && result.addon_total < result.addon_raw_total) warnings.push('Smart bundle aktif: beberapa tambahan layanan didiskon otomatis supaya harga tidak dobel hitung.');
        if (result.extra_hours <= 0 && result.total_hours > 0) warnings.push('Jam kerja normal sudah dianggap masuk ke harga preset. Biaya jam hanya dihitung kalau melewati estimasi normal.');
        if (result.ideal_price <= result.minimal_price) warnings.push('Harga ideal dekat dengan harga aman. Jangan kasih diskon terlalu besar.');
        if (result.sparepart_cost >= 250000) warnings.push('Modal sparepart besar. Sebaiknya minta DP sebelum beli part.');
        if (['tinggi', 'sangat_tinggi'].includes(input.risk_level) && input.warranty === '30_hari') warnings.push('Risiko tinggi dengan garansi 30 hari. Naikkan harga atau batasi scope garansi.');
        if ((result.service_name || '').toLowerCase().includes('recovery') || (result.service_name || '').toLowerCase().includes('data')) warnings.push('Untuk data/recovery, jangan janjikan data 100% kembali. Sampaikan sebagai estimasi kemungkinan.');
        if (result.profit_percent < result.min_profit_percent) warnings.push('Profit tipis untuk tipe pekerjaan ini. Cek modal, diskon, atau jam kerja tambahan.');
        return warnings;
    };

    const buildWa = (input, result) => {
        const client = input.client_name ? `Kak ${input.client_name}` : 'Kak';
        const device = [input.device_type, input.device_brand, input.device_model].filter(Boolean).join(' ') || 'perangkat';
        const dpLine = result.dp > 0 ? `\n\nUntuk pekerjaan ini disarankan DP sekitar ${money(result.dp)}, terutama jika perlu pembelian sparepart atau risiko pengerjaan cukup tinggi.` : '';
        return `Halo ${client}, untuk kendala ${device} dengan kebutuhan ${result.service_name}, estimasi awal pengerjaan ada di kisaran ${money(result.range_low)} - ${money(result.range_high)}.\n\nHarga final tetap menunggu pengecekan langsung, terutama untuk memastikan kondisi perangkat, driver/sistem, data, dan sparepart jika diperlukan.${dpLine}\n\nEstimasi ini dibuat berdasarkan tingkat kesulitan, risiko, dan scope pekerjaan supaya harga tetap masuk akal.`;
    };

    const calculate = () => {
        const input = formData();
        const preset = getPreset(input.service_preset_id);
        if (!preset) return null;

        if (el.presetNote) el.presetNote.textContent = preset.note || '';
        if (el.riskLevel && preset.risk_level && !el.riskLevel.dataset.manual) {
            el.riskLevel.value = preset.risk_level;
            input.risk_level = preset.risk_level;
        }
        if (el.workHours && preset.default_hours && !el.workHours.dataset.manual) {
            el.workHours.value = preset.default_hours;
            input.work_hours = String(preset.default_hours);
        }

        const selectedAddons = getAddons(input.addon_ids || []);
        const profile = getProfile(input, preset, selectedAddons);
        const addonCalc = smartAddonTotal(selectedAddons, profile);

        const sparepartCost = num(input.sparepart_cost);
        const modalTotal = sparepartCost + num(input.shipping_cost) + num(input.material_cost) + num(input.transport_cost) + num(input.parking_cost) + num(input.third_party_cost);
        const jasaDasar = num(preset.base_price) + addonCalc.total;

        const workHours = num(input.work_hours);
        const totalHours = workHours + (num(input.testing_minutes) / 60) + (num(input.communication_minutes) / 60);
        const includedHours = Math.max(.5, num(preset.default_hours) + num(profile.includedExtra || .5));
        const extraHours = Math.max(0, totalHours - includedHours);
        const timeCost = extraHours * num(input.hourly_rate);

        const difficulty = (rules.difficulty_multipliers || {})[input.difficulty] || { label: input.difficulty, value: 1 };
        const risk = (rules.risk_fees || {})[input.risk_level] || { label: input.risk_level, fee: 0 };
        const urgency = (rules.urgency_fees || {})[input.urgency] || { label: input.urgency, percent: 0 };
        const warranty = (rules.warranty_fees || {})[input.warranty] || { label: input.warranty, percent: 0 };

        const diffFee = difficultyFee(input.difficulty, jasaDasar, profile);
        const riskFee = num(risk.fee) * num(profile.riskFactor || 1);
        const serviceSubtotal = jasaDasar + diffFee + timeCost + riskFee;
        const subtotal = modalTotal + serviceSubtotal;

        const urgencyFee = serviceSubtotal * (num(urgency.percent) / 100);
        let warrantyFee = serviceSubtotal * (num(warranty.percent) / 100);
        if (profile.warrantyCap > 0) warrantyFee = Math.min(warrantyFee, profile.warrantyCap);

        const beforeMargin = subtotal + urgencyFee + warrantyFee;
        const serviceMarginPercent = Math.min(num(input.margin_percent), num(profile.marginCap || 10));
        const modalMarginPercent = modalTotal > 0 ? Math.min(num(input.margin_percent), 18) : 0;
        const marginProfit = (serviceSubtotal * (serviceMarginPercent / 100)) + (modalTotal * (modalMarginPercent / 100));

        const roundingBase = num(input.rounding_base);
        const minimalPrice = roundUp(modalTotal + serviceSubtotal, roundingBase);

        let idealRaw = Math.max(0, beforeMargin + marginProfit - num(input.discount));
        idealRaw = capPrice(idealRaw, minimalPrice, profile, input.urgency, modalTotal);
        const idealPrice = roundUp(idealRaw, roundingBase);

        const minimumMarginPercent = num(input.minimum_margin_percent);
        const negoBase = Math.max(minimalPrice * (1 + minimumMarginPercent / 100), idealPrice * .82);
        const negoPrice = roundUp(Math.min(negoBase, idealPrice), roundingBase);

        const rangeLow = roundUp(Math.max(negoPrice, idealPrice * num(profile.rangeLow || .9)), roundingBase);
        let rangeHighRaw = idealPrice * num(profile.rangeHigh || 1.15);
        rangeHighRaw = capPrice(rangeHighRaw, rangeLow, profile, input.urgency, modalTotal);
        const rangeHigh = roundUp(Math.max(rangeHighRaw, rangeLow), roundingBase);

        const dp = modalTotal >= 150000 ? roundUp(Math.max(modalTotal, sparepartCost * .7), roundingBase) : 0;
        const profit = Math.max(0, idealPrice - modalTotal);
        const profitPercent = idealPrice > 0 ? (profit / idealPrice) * 100 : 0;

        const result = {
            service_name: preset.name,
            service_category: preset.category,
            pricing_profile: profile.label,
            addon_names: selectedAddons.map((x) => x.name),
            addon_total: Math.round(addonCalc.total),
            addon_raw_total: Math.round(addonCalc.raw),
            jasa_dasar: Math.round(jasaDasar),
            sparepart_cost: Math.round(sparepartCost),
            modal_total: Math.round(modalTotal),
            time_cost: Math.round(timeCost),
            included_hours: Number(includedHours.toFixed(2)),
            extra_hours: Number(extraHours.toFixed(2)),
            total_hours: Number(totalHours.toFixed(2)),
            difficulty_label: difficulty.label,
            difficulty_multiplier: num(difficulty.value || 1),
            difficulty_fee: Math.round(diffFee),
            risk_label: risk.label,
            risk_fee: Math.round(riskFee),
            urgency_label: urgency.label,
            urgency_fee: Math.round(urgencyFee),
            warranty_label: warranty.label,
            warranty_fee: Math.round(warrantyFee),
            jasa_total: Math.round(serviceSubtotal),
            subtotal: Math.round(subtotal),
            before_margin: Math.round(beforeMargin),
            margin_profit: Math.round(marginProfit),
            discount: Math.round(num(input.discount)),
            minimal_price: minimalPrice,
            ideal_price: idealPrice,
            range_low: rangeLow,
            range_high: rangeHigh,
            nego_price: negoPrice,
            profit: Math.round(profit),
            profit_percent: Number(profitPercent.toFixed(2)),
            min_profit_percent: num(profile.minProfit || 10),
            dp,
        };

        result.warnings = buildWarnings(input, result);
        result.whatsapp_message = buildWa(input, result);
        latest = { input, result };
        render(result);
        return latest;
    };

    const render = (result) => {
        el.minimalPrice.textContent = money(result.minimal_price);
        el.idealPrice.textContent = money(result.ideal_price);
        el.clientRange.textContent = `${money(result.range_low)} - ${money(result.range_high)}`;
        el.negoPrice.textContent = money(result.nego_price);
        el.profitText.textContent = `${money(result.profit)} (${result.profit_percent.toFixed(0)}%)`;
        el.dpText.textContent = result.dp > 0 ? money(result.dp) : 'Tidak wajib';
        el.riskBadge.textContent = `Risiko: ${result.risk_label || '-'}`;
        el.waMessage.value = result.whatsapp_message;
        if (el.mockSafe) el.mockSafe.textContent = money(result.minimal_price);
        if (el.mockIdeal) el.mockIdeal.textContent = money(result.ideal_price);
        if (el.mockRange) el.mockRange.textContent = `${money(result.range_low)} - ${money(result.range_high)}`;

        const rows = [
            ['Profil harga', result.pricing_profile],
            ['Jasa dasar', result.jasa_dasar],
            ['Tambahan layanan', result.addon_total],
            ['Modal total', result.modal_total],
            ['Jam ekstra tertagih', `${result.extra_hours} jam`],
            ['Biaya waktu ekstra', result.time_cost],
            ['Biaya kesulitan', result.difficulty_fee],
            ['Biaya risiko', result.risk_fee],
            ['Jasa total', result.jasa_total],
            ['Urgency fee', result.urgency_fee],
            ['Garansi fee', result.warranty_fee],
            ['Margin profit', result.margin_profit],
            ['Diskon', -result.discount],
        ];

        el.breakdownList.innerHTML = rows.map(([label, value]) => {
            const shown = typeof value === 'number' ? money(value) : value;
            return `<div class="breakdown-row"><span>${label}</span><strong>${shown}</strong></div>`;
        }).join('');

        el.warningBox.innerHTML = result.warnings.length ? result.warnings.map((w) => `<div class="warning">⚠ ${w}</div>`).join('') : '<div class="warning ok">✓ Tidak ada warning besar. Harga terlihat cukup aman.</div>';
    };

    const setNote = (msg, type = '') => {
        if (!el.saveNote) return;
        el.saveNote.textContent = msg;
        el.saveNote.className = `mini-note ${type}`.trim();
    };

    form.addEventListener('input', (event) => {
        if (event.target.name === 'risk_level') el.riskLevel.dataset.manual = '1';
        if (event.target.name === 'work_hours') el.workHours.dataset.manual = '1';
        calculate();
    });
    form.addEventListener('change', (event) => {
        if (event.target.name === 'service_preset_id') {
            delete el.riskLevel.dataset.manual;
            delete el.workHours.dataset.manual;
        }
        calculate();
    });
    el.resetBtn?.addEventListener('click', () => {
        form.reset();
        delete el.riskLevel.dataset.manual;
        delete el.workHours.dataset.manual;
        setNote(window.EDIT_ESTIMATE?.id ? 'Form dikembalikan ke data awal estimasi.' : 'Form direset. Data akan disimpan ke tabel estimates.');
        calculate();
    });
    el.copyBtn?.addEventListener('click', async () => {
        calculate();
        try {
            await navigator.clipboard.writeText(el.waMessage.value);
            setNote('Pesan WhatsApp berhasil dicopy.', 'success');
        } catch (err) {
            el.waMessage.select();
            document.execCommand('copy');
            setNote('Pesan WhatsApp berhasil dicopy.', 'success');
        }
    });
    el.waBtn?.addEventListener('click', () => {
        const calc = calculate();
        const phone = normalizeWaNumber(calc?.input?.client_phone || '');

        if (!phone) {
            setNote('Nomor WhatsApp client kosong / tidak valid. Isi nomor client dulu.', 'error');
            return;
        }

        window.open(`https://wa.me/${phone}?text=${encodeURIComponent(latest.result.whatsapp_message)}`, '_blank', 'noopener');
    });
    el.saveBtn?.addEventListener('click', async () => {
        const calc = calculate();
        if (!calc?.input?.client_name) {
            setNote('Nama client wajib diisi sebelum simpan.', 'error');
            return;
        }

        const isEditMode = Boolean(window.EDIT_ESTIMATE?.id);
        const endpoint = isEditMode ? 'api/update_estimate.php' : 'api/save_estimate.php';
        const estimateId = isEditMode ? Number(window.EDIT_ESTIMATE.id) : 0;

        el.saveBtn.disabled = true;
        const old = el.saveBtn.textContent;
        el.saveBtn.textContent = isEditMode ? 'Menyimpan Revisi...' : 'Menyimpan...';
        setNote(isEditMode ? 'Menyimpan revisi estimasi...' : 'Menyimpan ke database...');

        try {
            const payload = {
                csrf_token: calc.input.csrf_token,
                input: calc.input
            };

            if (isEditMode) {
                payload.estimate_id = estimateId;
                payload.input.estimate_id = estimateId;
            }

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.message || 'Gagal menyimpan.');

            let msg = isEditMode
                ? `${json.message} Kode: ${json.estimate_code || window.EDIT_ESTIMATE.code || '-'}`
                : `${json.message} Kode: ${json.estimate_code}`;

            if (json.synced_order_id) {
                msg += ' • Job ticket ikut diperbarui.';
            }

            setNote(msg, 'success');
        } catch (err) {
            setNote(err.message, 'error');
        } finally {
            el.saveBtn.disabled = false;
            el.saveBtn.textContent = old;
        }
    });

    document.querySelector('#navToggle')?.addEventListener('click', () => document.querySelector('#navLinks')?.classList.toggle('open'));
    updateClientPickerState();
    if (el.existingClientSelect?.value) applyClientToForm(getSelectedClientFromOption());
    calculate();
})();
