<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Koala location map shortcode
add_shortcode('koala_location_map', 'koala_location_map_shortcode');
function koala_location_map_shortcode() {
    $post_id = get_the_ID();
    if (!$post_id || get_post_type($post_id) !== 'koala') {
        return '<p>Invalid koala post.</p>';
    }

    // Cache ACF fields
    $cache_key = 'koala_location_' . $post_id;
    $location_data = get_transient($cache_key);
    if (false === $location_data) {
        $location_data = [
            'address'   => get_field('koala_address', $post_id),
            'lat'       => get_field('latitude', $post_id),
            'lng'       => get_field('longitude', $post_id),
            'town'      => get_field('town', $post_id),
            'lga'       => get_field('lga', $post_id),
            'postcode'  => get_field('postcode', $post_id),
            'confirmed' => get_field('location_confirmed_correct_by_rescuer', $post_id),
        ];
        set_transient($cache_key, $location_data, HOUR_IN_SECONDS * 24);
    }
    $address   = $location_data['address'];
    $lat       = $location_data['lat'];
    $lng       = $location_data['lng'];
    $town      = $location_data['town'];
    $lga       = $location_data['lga'];
    $postcode  = $location_data['postcode'];
    $confirmed = !empty($location_data['confirmed']);

    $keep_pin_enabled = (int) get_option('koala_location_keep_pin', 0) === 1;

    ob_start();
    ?>
    <style>
        #koala-location-summary {
            background: #fff;
            margin-bottom: 10px;
            padding: 5px 5px;
        }
        #koala-location-summary p {
            margin: 5px 0;
        }
        #koala-location-fields {
            margin-top: 10px;
            background: white;
        }
        #koala-location-fields label.koala-field-label {
            display: block;
            font-weight: 600;
            margin: 6px 0 0 0;
        }
        #koala-location-fields input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            background: var(--base-2) !important;
            border: 1px solid rgba(75,117,146,0.5);
        }
        #koala-location-fields input[readonly] {
            background: #f0f0f0 !important;
            color: #333;
            cursor: default;
        }
        #koala-location-fields button {
            padding: 5px 15px;
            background: #df6200;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        #koala-location-fields button:hover {
            background: #004226;
        }
        #koala-location-fields button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        #koala-map {
            height: 400px;
            width: 100%;
            margin: 15px 0;
            border: 1px solid #ddd;
            display: block;
            min-height: 400px;
        }
        #koala-location-message {
            margin-top: 10px;
            color: #333;
        }
        #koala-location-message .koala-note {
            margin: 4px 0;
            padding: 6px 8px;
            border-left: 4px solid #999;
            background: #f7f7f7;
        }
        #koala-location-message .koala-note-ok { border-left-color: #1a7f37; }
        #koala-location-message .koala-note-info { border-left-color: #4b7592; }
        #koala-location-message .koala-note-warn { border-left-color: #b26200; background: #fff8e5; }
        #koala-location-message .koala-note-error { border-left-color: #b32d2e; background: #fdeaea; }
        .open-in-google-maps {
            display: inline-block;
            padding: 6px 10px;
            background: white;
            color: #004226;
            border: 1px solid #004226;
            text-decoration: none;
            font-weight: 300;
        }
        .pac-container {
            z-index: 10000 !important;
        }
        #koala-location-form {
            display: none;
        }
        #koala-edit-btn {
            padding: 5px 15px;
            background: #df6200;
            color: #fff;
            border: none;
            cursor: pointer;
            margin-bottom: 10px;
        }
        #koala-edit-btn:hover {
            background: #004226;
        }
        /* Checkbox rows in edit form */
        #koala-confirmed-wrap, #koala-keep-pin-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px 0;
            padding: 6px 0;
        }
        #koala-confirmed-wrap input[type="checkbox"], #koala-keep-pin-wrap input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
            accent-color: #df6200;
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }
        #koala-confirmed-wrap label, #koala-keep-pin-wrap label {
            margin: 0;
            font-size: 0.95em;
            cursor: pointer;
            line-height: 1.2;
        }
    </style>

    <div id="koala-location-summary">
    <?php
    $current_user = wp_get_current_user();
    $user_roles   = (array) $current_user->roles;
    if (is_user_logged_in() && !in_array('read_only', $user_roles)): ?>
        <button id="koala-edit-btn" aria-label="Edit koala location">Edit</button>
    <?php elseif (!is_user_logged_in()): ?>
        <p>Please log in to edit this location.</p>
    <?php endif; ?>
    <p><strong>Address:</strong> <?php echo esc_html($address ?: 'Not set'); ?></p>
    <p><strong>Latitude:</strong> <?php echo esc_html($lat ?: 'Not set'); ?></p>
    <p><strong>Longitude:</strong> <?php echo esc_html($lng ?: 'Not set'); ?></p>
    <p><strong>Town:</strong> <?php echo esc_html($town ?: 'Not set'); ?></p>
    <p><strong>Postcode:</strong> <?php echo esc_html($postcode ?: 'Not set'); ?></p>
    <p><strong>LGA:</strong> <?php echo esc_html($lga ?: 'Not set'); ?></p>
    <p><strong>Location Confirmed:</strong> <?php echo $confirmed ? '<strong>✅</strong> Location Confirmed' : '<strong>❌</strong> Not Confirmed'; ?></p>
    <p>
        <a id="open-in-google-maps"
           href="https://www.google.com/maps?q=<?php echo esc_attr($lat ?: '-28.8136'); ?>,<?php echo esc_attr($lng ?: '153.2773'); ?>"
           target="_blank"
           rel="noopener"
           class="open-in-google-maps">
            Open in Google Maps ↗
        </a>
    </p>
</div>

    <?php if (is_user_logged_in()): ?>
    <div id="koala-location-form">
        <div id="koala-location-fields">
            <label class="koala-field-label" for="koala-address">Address</label>
            <input type="text" id="koala-address" placeholder="Enter address" value="<?php echo esc_attr($address); ?>" />
            <label class="koala-field-label" for="koala-latitude">Latitude</label>
            <input type="text" id="koala-latitude" placeholder="Latitude" value="<?php echo esc_attr($lat); ?>" />
            <label class="koala-field-label" for="koala-longitude">Longitude</label>
            <input type="text" id="koala-longitude" placeholder="Longitude" value="<?php echo esc_attr($lng); ?>" />
            <?php if ($keep_pin_enabled): ?>
            <div id="koala-keep-pin-wrap">
                <input type="checkbox" id="koala-keep-pin" aria-describedby="koala-keep-pin-help" />
                <label for="koala-keep-pin">Keep current pin</label>
            </div>
            <p id="koala-keep-pin-help" style="margin:0 0 10px 0;font-size:0.9em;">When ticked, a typed address updates the town, postcode and LGA but does not move the pin or change latitude and longitude.</p>
            <?php endif; ?>
            <label class="koala-field-label" for="koala-town">Town (set from the address)</label>
            <input type="text" id="koala-town" value="<?php echo esc_attr($town); ?>" readonly />
            <label class="koala-field-label" for="koala-postcode">Postcode (set from the address)</label>
            <input type="text" id="koala-postcode" value="<?php echo esc_attr($postcode); ?>" readonly />
            <label class="koala-field-label" for="koala-lga">LGA (set from the address)</label>
            <input type="text" id="koala-lga" value="<?php echo esc_attr($lga); ?>" readonly />
            <button id="koala-gps-btn" aria-label="Use GPS to find location">Use GPS to Find My Location</button>
            <div id="koala-confirmed-wrap">
                <input type="checkbox"
                       id="koala-confirmed"
                       name="koala-confirmed"
                       <?php checked($confirmed, true); ?> />
                <label for="koala-confirmed">Location confirmed correct by rescuer</label>
            </div>
            <button id="koala-save-btn" aria-label="Save location">Save Location</button>
            <button id="koala-cancel-btn" aria-label="Cancel editing location">Cancel</button>
            <div id="koala-location-message" role="status" aria-live="polite"></div>
            <div id="koala-map"></div>
            <p>
                <a id="open-in-google-maps"
                   href="https://www.google.com/maps?q=<?php echo esc_attr($lat ?: '-28.8136'); ?>,<?php echo esc_attr($lng ?: '153.2773'); ?>"
                   target="_blank"
                   rel="noopener"
                   class="open-in-google-maps">
                    Open in Google Maps ↗
                </a>
            </p>
        </div>
    </div>
    <?php endif; ?>

<script>
let koalaMapInitialized = false;
let koalaMap, koalaMarker;
let originalLat, originalLng;

const KOALA_MAX_JUMP_M = 2000; // warn when an address and the pin are further apart than this
const koalaLoc = {
    seq: 0,
    notes: {},
    addressLatLng: null,
    lastAutocompleteValue: '',
    state: '', stateShort: '', country: '', countryShort: '',
    streetNumber: '', route: '', placeName: ''
};

function koalaEl(id) { return document.getElementById(id); }

// Messages are shown as a list so several can be visible at once; an empty text removes one
function koalaNote(key, kind, text) {
    if (!text) { delete koalaLoc.notes[key]; } else { koalaLoc.notes[key] = { kind: kind, text: text }; }
    const box = koalaEl('koala-location-message');
    if (!box) return;
    box.textContent = '';
    const icons = { ok: '✅', info: 'ℹ️', warn: '⚠️', error: '❌' };
    Object.keys(koalaLoc.notes).forEach(function (k) {
        const n = koalaLoc.notes[k];
        const p = document.createElement('p');
        p.className = 'koala-note koala-note-' + n.kind;
        p.textContent = (icons[n.kind] || '') + ' ' + n.text;
        box.appendChild(p);
    });
}

function koalaClearNotes() {
    koalaLoc.notes = {};
    koalaNote('', '', '');
}

function koalaPlain(latLng) {
    return (typeof latLng.lat === 'function') ? { lat: latLng.lat(), lng: latLng.lng() } : { lat: latLng.lat, lng: latLng.lng };
}

function koalaDistanceM(a, b) {
    const R = 6371000, rad = function (x) { return x * Math.PI / 180; };
    const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(h));
}

function koalaFormatDistance(m) {
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
}

function koalaCoordsValid(lat, lng) {
    return !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

function koalaKeepPin() {
    const el = koalaEl('koala-keep-pin');
    return !!(el && el.checked);
}

// Pulls the parts we store out of Google results; several results can be merged (used for pin lookups)
function koalaComponents(results) {
    const out = { town: '', lga: '', postcode: '', state: '', stateShort: '', country: '', countryShort: '', streetNumber: '', route: '', sublocality: '' };
    (results || []).forEach(function (r) {
        (r.address_components || []).forEach(function (c) {
            const t = c.types || [];
            if (!out.town && (t.includes('locality') || t.includes('postal_town'))) out.town = c.long_name;
            if (!out.sublocality && (t.includes('sublocality') || t.includes('sublocality_level_1'))) out.sublocality = c.long_name;
            if (!out.lga && t.includes('administrative_area_level_2')) out.lga = c.long_name;
            if (!out.postcode && t.includes('postal_code')) out.postcode = c.long_name;
            if (!out.state && t.includes('administrative_area_level_1')) { out.state = c.long_name; out.stateShort = c.short_name; }
            if (!out.country && t.includes('country')) { out.country = c.long_name; out.countryShort = c.short_name; }
            if (!out.streetNumber && t.includes('street_number')) out.streetNumber = c.long_name;
            if (!out.route && t.includes('route')) out.route = c.long_name;
        });
    });
    if (!out.town && out.sublocality) out.town = out.sublocality;
    return out;
}

// Only ever writes a non-blank value, so a failed lookup can never wipe what is already there
function koalaSetIfValue(id, value) {
    if (!value) return false;
    koalaEl(id).value = value;
    return true;
}

function koalaRememberExtras(c) {
    if (c.state) { koalaLoc.state = c.state; koalaLoc.stateShort = c.stateShort; }
    if (c.country) { koalaLoc.country = c.country; koalaLoc.countryShort = c.countryShort; }
    koalaLoc.streetNumber = c.streetNumber || koalaLoc.streetNumber;
    koalaLoc.route = c.route || koalaLoc.route;
}

function koalaGeocoder() { return new google.maps.Geocoder(); }

// Applies components to the visible fields. If Google left out the town, postcode or LGA it asks again
// using the pin position (skipped when the components already came from a pin lookup).
function koalaApplyComponents(c, pos, opts) {
    opts = opts || {};
    const seq = koalaLoc.seq;
    const prevTown = koalaEl('koala-town').value;
    koalaSetIfValue('koala-town', c.town);
    koalaSetIfValue('koala-postcode', c.postcode);
    koalaSetIfValue('koala-lga', c.lga);
    koalaRememberExtras(c);

    const finish = function (googleLga, stillMissing) {
        koalaNote('missing', stillMissing.length ? 'warn' : '',
            stillMissing.length ? 'Not found for this location: ' + stillMissing.join(' and ') + '. The existing value was kept.' : '');
        koalaLgaFallback(googleLga, prevTown, seq);
    };

    const needLookup = !opts.noPinLookup && (!c.town || !c.postcode || !c.lga);
    if (!needLookup) {
        const still = [];
        if (!c.town) still.push('town');
        if (!c.postcode) still.push('postcode');
        finish(c.lga, still);
        return;
    }

    koalaGeocoder().geocode({ location: pos }, function (results, status) {
        if (seq !== koalaLoc.seq) return;
        const still = [];
        let lga = c.lga;
        if (status === 'OK' && results && results.length) {
            const c2 = koalaComponents(results);
            if (!c.town && !koalaSetIfValue('koala-town', c2.town)) still.push('town');
            if (!c.postcode && !koalaSetIfValue('koala-postcode', c2.postcode)) still.push('postcode');
            if (!lga && c2.lga) { koalaEl('koala-lga').value = c2.lga; lga = c2.lga; }
            koalaRememberExtras({ state: c.state || c2.state, stateShort: c.stateShort || c2.stateShort, country: c.country || c2.country, countryShort: c.countryShort || c2.countryShort, streetNumber: c.streetNumber || c2.streetNumber, route: c.route || c2.route });
        } else {
            if (!c.town) still.push('town');
            if (!c.postcode) still.push('postcode');
        }
        finish(lga, still);
    });
}

// When Google gives no LGA: use the LGA other koala records agree on for this town, else keep what is there
function koalaLgaFallback(googleLga, prevTown, seq) {
    const lgaEl = koalaEl('koala-lga');
    const town = koalaEl('koala-town').value.trim();
    if (googleLga) { koalaNote('lga', '', ''); return; }
    const townMoved = town.toLowerCase() !== (prevTown || '').trim().toLowerCase();
    if (!townMoved && lgaEl.value.trim() !== '') { koalaNote('lga', '', ''); return; }
    const keptMsg = 'LGA not set automatically. The existing value was kept; you can set it in wp-admin.';
    if (!town) { koalaNote('lga', 'warn', keptMsg); return; }

    fetch(koalaAjax.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'koala_lookup_lga', nonce: koalaAjax.locationNonce, post_id: '<?php echo esc_js($post_id); ?>', town: town })
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
        if (seq !== koalaLoc.seq) return;
        if (json && json.success && json.data && json.data.lga) {
            lgaEl.value = json.data.lga;
            koalaNote('lga', 'info', 'LGA set from other koala records for ' + town + '.');
        } else {
            koalaNote('lga', 'warn', keptMsg);
        }
    })
    .catch(function () { if (seq === koalaLoc.seq) koalaNote('lga', 'warn', keptMsg); });
}

// An address (autocomplete choice or typed text) has been resolved to a place
function koalaUseAddressResult(locObj, components, formattedAddress, placeName, partial) {
    const pos = koalaPlain(locObj);
    const prev = koalaPlain(koalaMarker.getPosition());
    koalaLoc.addressLatLng = pos;
    koalaLoc.placeName = placeName || '';
    if (formattedAddress) {
        koalaEl('koala-address').value = formattedAddress;
        koalaLoc.lastAutocompleteValue = formattedAddress;
    }
    if (!koalaKeepPin()) {
        koalaMarker.setPosition(locObj);
        koalaMap.setCenter(locObj);
        koalaEl('koala-latitude').value = pos.lat.toFixed(6);
        koalaEl('koala-longitude').value = pos.lng.toFixed(6);
    }
    const jump = koalaDistanceM(prev, pos);
    if (jump > KOALA_MAX_JUMP_M) {
        koalaNote('distance', 'warn', koalaKeepPin()
            ? 'This address is about ' + koalaFormatDistance(jump) + ' from the pin you kept. A road name on its own can match a different place, so check it is the right one.'
            : 'The pin moved about ' + koalaFormatDistance(jump) + ' from where it was. A road name on its own can match a different place, so check the pin before saving.');
    } else {
        koalaNote('distance', '', '');
    }
    koalaNote('partial', partial ? 'warn' : '', partial ? 'Google found only a partial match for this address. Check the pin and the town, postcode and LGA below.' : '');
    koalaApplyComponents(components, pos);
}

function geocodeAddress(address) {
    address = (address || '').trim();
    if (!address) {
        koalaNote('geocode', 'warn', 'Enter an address to look up.');
        return;
    }
    const seq = ++koalaLoc.seq;
    koalaNote('geocode', 'info', 'Looking up the address…');
    koalaGeocoder().geocode({ address: address, region: 'au', componentRestrictions: { country: 'AU' } }, function (results, status) {
        if (seq !== koalaLoc.seq) return;
        if (status === 'OK' && results && results[0]) {
            koalaNote('geocode', '', '');
            const r = results[0];
            koalaUseAddressResult(r.geometry.location, koalaComponents([r]), '', '', !!r.partial_match);
        } else if (status === 'ZERO_RESULTS') {
            koalaNote('geocode', 'error', 'No match found for "' + address + '". The pin, town, postcode and LGA were not changed. Try a full street address, or drag the pin.');
        } else {
            koalaNote('geocode', 'error', 'Address lookup failed (' + status + '). The pin, town, postcode and LGA were not changed.');
        }
    });
}

function reverseGeocode(lat, lng) {
    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
    const seq = ++koalaLoc.seq;
    koalaNote('geocode', 'info', 'Looking up the address for the pin…');
    koalaGeocoder().geocode({ location: pos }, function (results, status) {
        if (seq !== koalaLoc.seq) return;
        if (status === 'OK' && results && results.length) {
            koalaNote('geocode', '', '');
            koalaEl('koala-address').value = results[0].formatted_address;
            koalaLoc.lastAutocompleteValue = results[0].formatted_address;
            koalaLoc.addressLatLng = pos;
            koalaLoc.placeName = '';
            koalaNote('distance', '', '');
            koalaNote('partial', '', '');
            koalaApplyComponents(koalaComponents(results), pos, { noPinLookup: true });
        } else {
            koalaNote('geocode', 'error', 'Could not look up an address for this pin' + (status && status !== 'ZERO_RESULTS' ? ' (' + status + ')' : '') + '. The address, town, postcode and LGA were not changed.');
        }
    });
}

function initKoalaMap() {
    if (koalaMapInitialized) return;

    const mapEl = koalaEl('koala-map');
    const latInput = koalaEl('koala-latitude');
    const lngInput = koalaEl('koala-longitude');
    const addressInput = koalaEl('koala-address');
    const townInput = koalaEl('koala-town');
    const lgaInput = koalaEl('koala-lga');
    const postcodeInput = koalaEl('koala-postcode');

    if (!mapEl || !latInput || !lngInput || !addressInput || !townInput || !lgaInput || !postcodeInput) {
        koalaNote('init', 'error', 'Map container or inputs not found');
        return;
    }

    mapEl.style.display = 'block';
    mapEl.style.height = '400px';

    let lat = parseFloat(latInput.value);
    let lng = parseFloat(lngInput.value);

    if (!koalaCoordsValid(lat, lng)) {
        lat = -28.8136;
        lng = 153.2773;
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    }

    originalLat = lat;
    originalLng = lng;

    const mapCenter = { lat, lng };

    try {
        if (!google || !google.maps) {
            koalaNote('init', 'error', 'Google Maps API not available');
            return;
        }

        const mapScripts = document.querySelectorAll('script[src*="maps.googleapis.com/maps/api/js"]').length;
        if (mapScripts > 1) {
            console.warn('Koala: the Google Maps script is loaded ' + mapScripts + ' times on this page. Deactivate the duplicate loader.');
        }

        koalaMap = new google.maps.Map(mapEl, {
            zoom: 15,
            center: mapCenter,
            mapTypeId: google.maps.MapTypeId.SATELLITE
        });

        koalaMarker = new google.maps.Marker({
            position: mapCenter,
            map: koalaMap,
            draggable: true,
            title: 'Drag to set location'
        });

        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            types: ['address'],
            componentRestrictions: { country: 'au' }
        });
        autocomplete.bindTo('bounds', koalaMap);

        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();

            // Enter pressed on free text: no place was chosen, so look the typed text up instead
            if (!place || !place.geometry || !place.geometry.location) {
                geocodeAddress(addressInput.value || (place && place.name) || '');
                return;
            }

            ++koalaLoc.seq;
            koalaNote('geocode', '', '');
            koalaUseAddressResult(
                place.geometry.location,
                koalaComponents([{ address_components: place.address_components || [] }]),
                place.formatted_address || '',
                place.name || '',
                false
            );
        });

        koalaMarker.addListener('dragend', function () {
            const pos = koalaMarker.getPosition();
            latInput.value = pos.lat().toFixed(6);
            lngInput.value = pos.lng().toFixed(6);
            reverseGeocode(pos.lat(), pos.lng());
        });

        // Typed address (not an autocomplete choice): look it up when the box loses focus
        addressInput.addEventListener('change', function () {
            const value = this.value.trim();
            if (value && value === koalaLoc.lastAutocompleteValue) {
                koalaLoc.lastAutocompleteValue = '';
                return;
            }
            geocodeAddress(value);
        });

        function moveToTypedCoordinates() {
            const latVal = parseFloat(latInput.value);
            const lngVal = parseFloat(lngInput.value);
            if (!koalaCoordsValid(latVal, lngVal)) {
                koalaNote('coords', 'error', 'Latitude must be between -90 and 90 and longitude between -180 and 180.');
                return;
            }
            koalaNote('coords', '', '');
            koalaMarker.setPosition({ lat: latVal, lng: lngVal });
            koalaMap.setCenter({ lat: latVal, lng: lngVal });
            reverseGeocode(latVal, lngVal);
        }
        latInput.addEventListener('change', moveToTypedCoordinates);
        lngInput.addEventListener('change', moveToTypedCoordinates);

        // GPS button for current location
        koalaEl('koala-gps-btn').addEventListener('click', function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        latInput.value = lat.toFixed(6);
                        lngInput.value = lng.toFixed(6);
                        const latlng = { lat, lng };
                        koalaMap.setCenter(latlng);
                        koalaMarker.setPosition(latlng);
                        reverseGeocode(lat, lng);
                    },
                    function (error) {
                        koalaNote('gps', 'error', 'Geolocation failed: ' + error.message);
                    }
                );
            } else {
                koalaNote('gps', 'error', 'Geolocation not supported');
            }
        });

        // Save button - sends data to server via AJAX
        koalaEl('koala-save-btn').addEventListener('click', function () {
            const btn = this;
            const latVal = parseFloat(latInput.value);
            const lngVal = parseFloat(lngInput.value);
            if (!koalaCoordsValid(latVal, lngVal)) {
                koalaNote('save', 'error', 'Latitude must be between -90 and 90 and longitude between -180 and 180. Nothing was saved.');
                return;
            }
            const data = {
                action: 'save_koala_location',
                nonce: koalaAjax.locationNonce,
                post_id: '<?php echo esc_js($post_id); ?>',
                address: addressInput.value,
                latitude: latInput.value,
                longitude: lngInput.value,
                town: townInput.value,
                lga: lgaInput.value,
                postcode: postcodeInput.value,
                state: koalaLoc.state,
                state_short: koalaLoc.stateShort,
                country: koalaLoc.country,
                country_short: koalaLoc.countryShort,
                street_number: koalaLoc.streetNumber,
                street_name: koalaLoc.route,
                street_address: (koalaLoc.streetNumber + ' ' + koalaLoc.route).trim(),
                place_name: koalaLoc.placeName,
                location_confirmed: koalaEl('koala-confirmed').checked ? '1' : '0'
            };

            btn.disabled = true;
            koalaNote('save', 'info', 'Saving…');
            fetch(koalaAjax.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.success) {
                    const notices = (response.data && response.data.notices) || [];
                    koalaNote('save', 'ok', 'Location saved. Reloading...');
                    notices.forEach(function (text, i) { koalaNote('server' + i, 'warn', text); });
                    setTimeout(function () { location.reload(); }, notices.length ? 4000 : 1500);
                } else {
                    btn.disabled = false;
                    koalaNote('save', 'error', 'Error: ' + (typeof response.data === 'string' ? response.data : 'Failed to save location'));
                }
            })
            .catch(function () {
                btn.disabled = false;
                koalaNote('save', 'error', 'Error saving location');
            });
        });

        koalaMapInitialized = true;
    } catch (error) {
        koalaNote('init', 'error', 'Map initialization failed: ' + error.message);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const editBtn      = koalaEl('koala-edit-btn');
    const cancelBtn    = koalaEl('koala-cancel-btn');
    const form         = koalaEl('koala-location-form');
    const summary      = koalaEl('koala-location-summary');
    const addressInput = koalaEl('koala-address');
    const latInput     = koalaEl('koala-latitude');
    const lngInput     = koalaEl('koala-longitude');
    const townInput    = koalaEl('koala-town');
    const lgaInput     = koalaEl('koala-lga');
    const postcodeInput = koalaEl('koala-postcode');
    const confirmedInput = koalaEl('koala-confirmed');
    const mapEl        = koalaEl('koala-map');

    if (editBtn && form && summary) {
        editBtn.addEventListener('click', function () {
            form.style.display = 'block';
            summary.style.display = 'none';
            if (!koalaMapInitialized && typeof initKoalaMap === 'function' && mapEl && !mapEl.children.length) {
                initKoalaMap();
            }
        });
    }

    if (cancelBtn && form && summary && addressInput && latInput && lngInput && townInput && lgaInput && postcodeInput) {
        cancelBtn.addEventListener('click', function () {
            form.style.display = 'none';
            summary.style.display = 'block';
            // Reset form values
            addressInput.value  = '<?php echo esc_js($address); ?>';
            latInput.value      = '<?php echo esc_js($lat); ?>';
            lngInput.value      = '<?php echo esc_js($lng); ?>';
            townInput.value     = '<?php echo esc_js($town); ?>';
            lgaInput.value      = '<?php echo esc_js($lga); ?>';
            postcodeInput.value = '<?php echo esc_js($postcode); ?>';
            if (confirmedInput) {
                confirmedInput.checked = <?php echo $confirmed ? 'true' : 'false'; ?>;
            }
            ++koalaLoc.seq;
            koalaLoc.addressLatLng = null;
            koalaLoc.lastAutocompleteValue = '';
            koalaClearNotes();
            // Reset map and marker
            if (koalaMap && koalaMarker && originalLat !== undefined && originalLng !== undefined) {
                const lat = parseFloat('<?php echo esc_js($lat); ?>') || -28.8136;
                const lng = parseFloat('<?php echo esc_js($lng); ?>') || 153.2773;
                const latLng = { lat, lng };
                koalaMap.setCenter(latLng);
                koalaMarker.setPosition(latLng);
            }
        });
    }
});
</script>

    <?php
    return ob_get_clean();
}
