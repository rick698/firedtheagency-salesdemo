const monthlyLabels = [
    'Nov 24', 'Dec 24', 'Jan 25', 'Feb 25', 'Mar 25', 'Apr 25',
    'May 25', 'Jun 25', 'Jul 25', 'Aug 25', 'Sep 25', 'Oct 25', 'Nov 25', 'Dec 25'
];

const monthlyCost = [
    402.50, 398.07, 400.00, 405.20, 395.50, 400.19,
    401.08, 399.80, 403.55, 400.02, 398.27, 400.50, 401.16, 395.12
];

const monthlyClicks = [
    245, 260, 275, 290, 310, 300,
    313, 308, 325, 330, 315, 340, 340, 350
];

const monthlyImpressions = [
    12558, 12811, 13123, 13401, 13810, 13510,
    13921, 14111, 13525, 14209, 14211, 14320, 14351, 14120
];

let performanceChart;
let setupMap;
let currentMarker;
let currentCircle;
let currentRadius = 17;
const knownCities = {
    'perth': [-31.9523, 115.8613],
    'perth wa': [-31.9523, 115.8613],
    'perth, wa': [-31.9523, 115.8613],
    'perth, wa 6000': [-31.9523, 115.8613],
    'sydney': [-33.8688, 151.2093],
    'sydney, nsw 2000': [-33.8688, 151.2093],
    'melbourne': [-37.8136, 144.9631],
    'brisbane': [-27.4698, 153.0251],
    'adelaide': [-34.9285, 138.6007]
};

$(function () {
    if ($('#performanceChart').length) {
        initChart();
        renderMonthlyView();
        handleProjectChange();
    }

    if ($('.project-wizard').length) {
        initProjectWizard();
    }

    initCheckoutTerms();
});

function toggleSidebar() {
    $('#sidebar').toggleClass('show');
}

function toggleView(view) {
    $('#btn-daily, #btn-monthly').removeClass('active');

    if (view === 'daily') {
        $('#btn-daily').addClass('active');
        alert('Demo Mode: Daily data has not been generated for this view yet.');
        return;
    }

    $('#btn-monthly').addClass('active');
    renderMonthlyView();
}

function handleProjectChange() {
    if (!$('#projectSelector').length) {
        return;
    }

    const selectedText = ($('#projectSelector option:selected').text() || '').toLowerCase();
    const showTargetGroup = selectedText.includes('meta') || selectedText.includes('facebook');
    $('#nav-target-group').toggleClass('hidden', !showTargetGroup);
}

function initProjectWizard() {
    let currentStep = 1;
    const totalSteps = 3;
    const form = $('.project-wizard');

    function renderStep() {
        $('.wizard-step').removeClass('active');
        $(`.wizard-step[data-step="${currentStep}"]`).addClass('active');
        $('#wizardBack').prop('disabled', currentStep === 1);
        $('#wizardNext').toggleClass('hidden', currentStep === totalSteps);
        $('#wizardSave').toggleClass('hidden', currentStep !== totalSteps);

        if (currentStep === 1) {
            initServiceRadiusMap();
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('#wizardBack').on('click', function () {
        if (currentStep > 1) {
            currentStep -= 1;
            renderStep();
        }
    });

    $('#wizardNext').on('click', function () {
        if (currentStep < totalSteps) {
            if (!validateCurrentStep(currentStep)) {
                return;
            }

            saveWizardStep(currentStep, function () {
                currentStep += 1;
                renderStep();
            });
        }
    });

    $('#wizardSave').on('click', function (event) {
        const passwordField = $('input[name="account_password"]');

        if (passwordField.length && passwordField.val().length < 8) {
            event.preventDefault();
            $('#passwordModal').addClass('active').attr('aria-hidden', 'false');
            setTimeout(function () {
                passwordField.trigger('focus');
            }, 50);
        }
    });

    $('#passwordCancel').on('click', function () {
        $('#passwordModal').removeClass('active').attr('aria-hidden', 'true');
    });

    $('#passwordContinue').on('click', function () {
        const passwordField = $('input[name="account_password"]');

        if (passwordField.val().length < 8) {
            alert('Password must be at least 8 characters.');
            passwordField.trigger('focus');
            return;
        }

        $('#passwordModal').removeClass('active').attr('aria-hidden', 'true');
        $('#wizardSave').text('Saving...');
        form.trigger('submit');
    });

    form.on('submit', function () {
        $('#wizardSave').text('Saving...');
    });

    renderStep();
}

function validateCurrentStep(step) {
    const fields = $(`.wizard-step[data-step="${step}"]`).find('input, textarea, select').toArray();

    for (const field of fields) {
        if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
            field.reportValidity();
            return false;
        }
    }

    return true;
}

function saveWizardStep(step, onSuccess) {
    const form = $('.project-wizard');
    const saveUrl = form.data('step-save-url');
    const button = $('#wizardNext');
    const originalText = button.text();

    if (!saveUrl) {
        onSuccess();
        return;
    }

    button.text('Saving...').prop('disabled', true);

    $.ajax({
        url: saveUrl,
        method: 'POST',
        data: form.serialize() + '&step=' + encodeURIComponent(step),
        dataType: 'json'
    }).done(function (response) {
        if (response && response.ok) {
            onSuccess();
            return;
        }

        alert(response && response.message ? response.message : 'Could not save this step.');
    }).fail(function (xhr) {
        const response = xhr.responseJSON || {};
        let message = response.message || '';

        if (!message && xhr.responseText) {
            try {
                message = JSON.parse(xhr.responseText).message || '';
            } catch (error) {
                message = '';
            }
        }

        alert(message || 'Could not save this step. Please try again.');
    }).always(function () {
        button.text(originalText).prop('disabled', false);
    });
}

function initServiceRadiusMap() {
    const mapEl = document.getElementById('map');

    if (!mapEl || setupMap) {
        return;
    }

    const savedLat = parseFloat($('input[name="target_lat"]').val());
    const savedLng = parseFloat($('input[name="target_lng"]').val());
    const savedRadius = parseInt($('input[name="target_radius_km"]').val(), 10);
    const serviceArea = ($('input[name="service_area"]').val() || $('#manualCity').val() || '').trim();
    const defaultCoords = getSavedMapCoords(savedLat, savedLng, serviceArea) || [-31.9523, 115.8613];
    currentRadius = Number.isFinite(savedRadius) ? Math.max(1, Math.min(17, savedRadius)) : 17;

    $('#radiusValue').text(currentRadius);
    $('#radiusSlider').val(currentRadius);
    $('input[name="target_radius_km"]').val(currentRadius);

    setupMap = L.map('map').setView(defaultCoords, 10);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(setupMap);

    updateZone(defaultCoords, currentRadius, true);

    if (!Number.isFinite(savedLat) || !Number.isFinite(savedLng)) {
        hydrateCityLocation(serviceArea);
    }

    setupMap.on('click', function (event) {
        updateZone(event.latlng, currentRadius, true);
        currentMarker.openPopup();

        const lat = event.latlng.lat.toFixed(4);
        const lng = event.latlng.lng.toFixed(4);
        $('#manualCity').val(`Custom Pin: ${lat}, ${lng}`);
        $('input[name="service_area"]').val(`Custom Pin: ${lat}, ${lng}`);
    });

    setTimeout(function () {
        setupMap.invalidateSize();
        fitRadiusBounds();
    }, 150);
}

function getSavedMapCoords(savedLat, savedLng, serviceArea) {
    if (Number.isFinite(savedLat) && Number.isFinite(savedLng)) {
        return [savedLat, savedLng];
    }

    const customPin = serviceArea.match(/Custom Pin:\s*(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/i);

    if (customPin) {
        return [parseFloat(customPin[1]), parseFloat(customPin[2])];
    }

    return knownCities[serviceArea.toLowerCase()] || null;
}

function updateZone(latlng, radiusKm, fitBounds) {
    if (!setupMap) {
        return;
    }

    const lat = Array.isArray(latlng) ? latlng[0] : latlng.lat;
    const lng = Array.isArray(latlng) ? latlng[1] : latlng.lng;

    if (currentMarker) {
        currentMarker.setLatLng(latlng);
    } else {
        currentMarker = L.marker(latlng).addTo(setupMap)
            .bindPopup('<b>Target Center</b><br>Ads launch from here.')
            .openPopup();
    }

    if (currentCircle) {
        currentCircle.setLatLng(latlng);
        currentCircle.setRadius(radiusKm * 1000);
    } else {
        currentCircle = L.circle(latlng, {
            color: '#0f766e',
            fillColor: '#0f766e',
            fillOpacity: 0.16,
            radius: radiusKm * 1000
        }).addTo(setupMap);
    }

    $('input[name="target_lat"]').val(Number(lat).toFixed(6));
    $('input[name="target_lng"]').val(Number(lng).toFixed(6));
    $('input[name="target_radius_km"]').val(radiusKm);

    if (fitBounds) {
        fitRadiusBounds();
    }
}

function fitRadiusBounds() {
    if (setupMap && currentCircle) {
        setupMap.fitBounds(currentCircle.getBounds(), {
            padding: [55, 55],
            maxZoom: 10
        });
    }
}

function updateRadius(value) {
    currentRadius = value;
    $('#radiusValue').text(value);
    $('input[name="target_radius_km"]').val(value);

    if (currentCircle) {
        currentCircle.setRadius(value * 1000);
        fitRadiusBounds();
    }
}

function findCity() {
    const city = $('#manualCity').val().trim();

    if (city === '') {
        return;
    }

    $('input[name="service_area"]').val(city);

    const coords = knownCities[city.toLowerCase()];

    if (coords && setupMap) {
        updateZone(coords, currentRadius, true);
        currentMarker.openPopup();
        return;
    }

    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(city + ', Australia');

    fetch(url)
        .then(response => response.json())
        .then(results => {
            if (!results.length || !setupMap) {
                alert('City not found. Try adding the state, for example Perth WA.');
                return;
            }

            const latlng = [
                parseFloat(results[0].lat),
                parseFloat(results[0].lon)
            ];

            updateZone(latlng, currentRadius, true);
            currentMarker.openPopup();
        })
        .catch(() => {
            alert('City search is unavailable right now. Please click the map to place the pin.');
        });
}

function hydrateCityLocation(serviceArea) {
    if (!serviceArea || knownCities[serviceArea.toLowerCase()]) {
        return;
    }

    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(serviceArea + ', Australia');

    fetch(url)
        .then(response => response.json())
        .then(results => {
            if (!results.length || !setupMap) {
                return;
            }

            updateZone([
                parseFloat(results[0].lat),
                parseFloat(results[0].lon)
            ], currentRadius, true);
        })
        .catch(() => {});
}

function initCheckoutTerms() {
    $('.checkout-form').on('submit', function (event) {
        const form = $(this);
        const checkbox = form.find('input[name="accept_terms"]');

        if (!checkbox.is(':checked')) {
            event.preventDefault();
            form.addClass('terms-error');
            checkbox.trigger('focus');
            return;
        }

        form.removeClass('terms-error');
        form.find('.pricing-button').text('Opening Stripe...');
    });

    $('.checkout-form input[name="accept_terms"]').on('change', function () {
        if ($(this).is(':checked')) {
            $(this).closest('.checkout-form').removeClass('terms-error');
        }
    });
}

function initChart() {
    const canvas = document.getElementById('performanceChart');
    const ctx = canvas.getContext('2d');
    const accent = getComputedStyle(document.body).getPropertyValue('--brand-accent').trim() || '#0f766e';

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, hexToRgba(accent, 0.32));
    gradient.addColorStop(1, hexToRgba(accent, 0));

    performanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Cost ($)',
                    data: [],
                    backgroundColor: '#cbd5e1',
                    borderColor: '#94a3b8',
                    borderWidth: 1,
                    order: 2,
                    yAxisID: 'y',
                    barPercentage: 0.6
                },
                {
                    type: 'line',
                    label: 'Visitors',
                    data: [],
                    borderColor: accent,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    order: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: '#64748b' } }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b' }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    grid: { color: '#e2e8f0' },
                    ticks: {
                        color: '#64748b',
                        callback: value => '$' + value
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { display: false },
                    ticks: { color: accent }
                }
            }
        }
    });
}

function renderMonthlyView() {
    if (!performanceChart) {
        return;
    }

    performanceChart.data.labels = monthlyLabels;
    performanceChart.data.datasets[0].data = monthlyCost;
    performanceChart.data.datasets[1].data = monthlyClicks;
    performanceChart.update();

    const rows = [];

    for (let i = monthlyLabels.length - 1; i >= 0; i--) {
        const ctr = ((monthlyClicks[i] / monthlyImpressions[i]) * 100).toFixed(2);
        const cpc = (monthlyCost[i] / monthlyClicks[i]).toFixed(2);

        rows.push(`
            <tr>
                <td>${monthlyLabels[i]}</td>
                <td class="text-end">${monthlyImpressions[i].toLocaleString()}</td>
                <td class="text-end"><strong>${monthlyClicks[i]}</strong></td>
                <td class="text-end">${ctr}%</td>
                <td class="text-end">$${monthlyCost[i].toFixed(2)}</td>
                <td class="text-end">$${cpc}</td>
            </tr>
        `);
    }

    $('#tableBody').html(rows.join(''));
}

function hexToRgba(hex, alpha) {
    const normalized = hex.replace('#', '');
    const bigint = parseInt(normalized, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
