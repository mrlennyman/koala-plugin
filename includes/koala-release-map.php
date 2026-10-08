<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Koala release map shortcode
add_shortcode('koala_release_map', function () {
    if (!is_singular('koala')) {
        return '<p>This map can only be used on a single Koala post.</p>';
    }

    global $post;
    $post_id = $post->ID;

    // Cache ACF fields
    $cache_key = 'koala_release_' . $post_id;
    $release_data = get_transient($cache_key);
    if (false === $release_data) {
        $release_data = [
            'address'   => get_field('release_address', $post_id),
            'lat'       => get_field('release_lat', $post_id),
            'lng'       => get_field('release_long', $post_id),
            'town'      => get_field('release_town', $post_id),
            'postcode'  => get_field('release_post_code', $post_id),
            'confirmed' => get_field('location_confirmed_correct_by_releaser', $post_id),
        ];
        set_transient($cache_key, $release_data, HOUR_IN_SECONDS * 24);
    }
    // Raw values; every output below escapes exactly once
    $release_address   = (string) $release_data['address'];
    $release_lat       = (string) $release_data['lat'];
    $release_lng       = (string) $release_data['lng'];
    $release_town      = (string) $release_data['town'];
    $release_postcode  = (string) $release_data['postcode'];
    $release_confirmed = !empty($release_data['confirmed']);

    $keep_pin_enabled = (int) get_option('koala_location_keep_pin', 0) === 1;

    ob_start();
    ?>
    <style>
        #koala-release-map-wrapper p {
            margin-bottom: 0px !important;
        }
        #koala-release-map-wrapper label.koala-field-label {
            display: block;
            font-weight: 600;
            margin: 6px 0 0 0;
        }
        #koala-release-map-wrapper input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0 !important;
            background: var(--base-2) !important;
            border: 1px solid rgba(75,117,146,0.5);
        }
        #koala-release-map-wrapper input[readonly] {
            background: #f0f0f0 !important;
            color: #333;
            cursor: default;
        }
        #koala_release_map {
            width: 100%;
            height: 400px;
            margin-top: 15px;
            border: 1px solid #ddd;
        }
        #koala-release-map-wrapper button {
            padding: 5px 15px;
            background: #df6200;
            color: #fff;
            border: none;
            cursor: pointer;
            margin: 5px 10px 5px 0;
        }
        #koala-release-map-wrapper button:hover {
            background: #004226;
        }
        #koala-release-map-wrapper button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        #release_location_message {
            margin-top: 10px;
            color: #333;
        }
        #release_location_message .koala-note {
            margin: 4px 0 !important;
            padding: 6px 8px;
            border-left: 4px solid #999;
            background: #f7f7f7;
        }
        #release_location_message .koala-note-ok { border-left-color: #1a7f37; }
        #release_location_message .koala-note-info { border-left-color: #4b7592; }
        #release_location_message .koala-note-warn { border-left-color: #b26200; background: #fff8e5; }
        #release_location_message .koala-note-error { border-left-color: #b32d2e; background: #fdeaea; }
        #koala-release-summary {
            padding: 0px 5px;
            background: #fff;
            margin-bottom: 10px;
        }
        #koala-release-summary p {
            margin: 5px 0;
        }
        #koala-release-form {
            display: none;
        }
        #koala-release-edit-btn {
            padding: 5px 15px;
            background: #df6200;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        #koala-release-edit-btn:hover {
            background: #004226;
        }
        .pac-container {
            z-index: 10000 !important;
        }
        .open-in-google-maps {
            display: inline-block;
            padding: 6px 10px;
            background: white;
            color: #004226;
            border: 1px solid #004226;
            text-decoration: none;
            font-weight: 300;
        }
        #release-confirmed-wrap, #release-keep-pin-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px 0;
            padding: 6px 0;
        }
        #release-confirmed-wrap input[type="checkbox"], #release-keep-pin-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            padding: 0;
            accent-color: #df6200;
            cursor: pointer;
            flex-shrink: 0;
        }
        #release-confirmed-wrap label, #release-keep-pin-wrap label {
            margin: 0;
            font-size: 0.95em;
            cursor: pointer;
            line-height: 1.2;
        }
    </style>

  <div id="koala-release-summary">
    <?php
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles; // Get the user's roles
    if (is_user_logged_in() && !in_array('read_only', $user_roles)): ?>
        <button id="koala-release-edit-btn" aria-label="Edit koala release location">Edit</button>
    <?php elseif (!is_user_logged_in()): ?>
        <p>Please log in to edit this location.</p>
    <?php endif; ?>
    <p><strong>Address:</strong> <?php echo esc_html($release_address ?: 'Not set'); ?></p>
    <p><strong>Latitude:</strong> <?php echo esc_html($release_lat ?: 'Not set'); ?></p>
    <p><strong>Longitude:</strong> <?php echo esc_html($release_lng ?: 'Not set'); ?></p>
    <p><strong>Town:</strong> <?php echo esc_html($release_town ?: 'Not set'); ?></p>
    <p><strong>Postcode:</strong> <?php echo esc_html($release_postcode ?: 'Not set'); ?></p>
    <p><strong>Location Confirmed:</strong> <?php echo $release_confirmed ? '<strong>✅</strong> Location Confirmed' : '<strong>❌</strong> Not Confirmed'; ?></p>
    <p>
        <a id="open-in-google-maps"
           href="https://www.google.com/maps?q=<?php echo esc_attr($release_lat ?: '-28.8136'); ?>,<?php echo esc_attr($release_lng ?: '153.2773'); ?>"
           target="_blank"
           rel="noopener"
           class="open-in-google-maps">
            Open in Google Maps ↗
        </a>
    </p>
</div>

    <?php if (is_user_logged_in()): ?>
    <div id="koala-release-form">
        <div id="koala-release-map-wrapper">
            <label class="koala-field-label" for="release_address">Address</label>
            <input type="text" id="release_address" placeholder="Address" value="<?php echo esc_attr($release_address); ?>">
            <label class="koala-field-label" for="release_lat">Latitude</label>
            <input type="text" id="release_lat" placeholder="Latitude" value="<?php echo esc_attr($release_lat); ?>">
            <label class="koala-field-label" for="release_lng">Longitude</label>
            <input type="text" id="release_lng" placeholder="Longitude" value="<?php echo esc_attr($release_lng); ?>">
            <?php if ($keep_pin_enabled): ?>
            <div id="release-keep-pin-wrap">
                <input type="checkbox" id="release-keep-pin" aria-describedby="release-keep-pin-help" />
                <label for="release-keep-pin">Keep current pin</label>
            </div>
            <p id="release-keep-pin-help" style="margin:0 0 10px 0 !important;font-size:0.9em;">When ticked, a typed address updates the town and postcode but does not move the pin or change latitude and longitude.</p>
            <?php endif; ?>
            <label class="koala-field-label" for="release_town">Town (set from the address)</label>
            <input type="text" id="release_town" value="<?php echo esc_attr($release_town); ?>" readonly>
            <label class="koala-field-label" for="release_postcode">Postcode (set from the address)</label>
            <input type="text" id="release_postcode" value="<?php echo esc_attr($release_postcode); ?>" readonly>
            <button type="button" id="release_geolocate" aria-label="Use GPS to find location">Use GPS Location</button>
            <div id="release-confirmed-wrap">
                <input type="checkbox"
                       id="release-confirmed"
                       name="release-confirmed"
                       <?php checked($release_confirmed, true); ?> />
                <label for="release-confirmed">Location confirmed correct by releaser</label>
            </div>
            <button id="release_save" aria-label="Save release location">Save Location</button>
            <button id="release_cancel" aria-label="Cancel editing release location">Cancel</button>
            <div id="release_location_message" role="status" aria-live="polite"></div>
            <div id="koala_release_map"></div>
            <p>
                <a id="open-in-google-maps"
                   href="https://www.google.com/maps?q=<?php echo esc_attr($release_lat ?: '-28.8136'); ?>,<?php echo esc_attr($release_lng ?: '153.2773'); ?>"
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
let releaseMapInitialized = false;
let releaseMap, releaseMarker;
let releaseOriginalLat, releaseOriginalLng;

// Helpers are prefixed kRel so this script can share a page with the location editor
const K_REL_MAX_JUMP_M = 2000; // warn when an address and the pin are further apart than this
const kRel = { seq: 0, notes: {}, lastAutocompleteValue: '' };

function kRelEl(id) { return document.getElementById(id); }

// Messages are shown as a list so several can be visible at once; an empty text removes one
function kRelNote(key, kind, text) {
    if (!text) { delete kRel.notes[key]; } else { kRel.notes[key] = { kind: kind, text: text }; }
    const box = kRelEl('release_location_message');
    if (!box) return;
    box.textContent = '';
    const icons = { ok: '✅', info: 'ℹ️', warn: '⚠️', error: '❌' };
    Object.keys(kRel.notes).forEach(function (k) {
        const n = kRel.notes[k];
        const p = document.createElement('p');
        p.className = 'koala-note koala-note-' + n.kind;
        p.textContent = (icons[n.kind] || '') + ' ' + n.text;
        box.appendChild(p);
    });
}

function kRelPlain(latLng) {
    return (typeof latLng.lat === 'function') ? { lat: latLng.lat(), lng: latLng.lng() } : { lat: latLng.lat, lng: latLng.lng };
}

function kRelDistanceM(a, b) {
    const R = 6371000, rad = function (x) { return x * Math.PI / 180; };
    const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.sqrt(h));
}

function kRelFormatDistance(m) {
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
}

function kRelCoordsValid(lat, lng) {
    return !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

function kRelKeepPin() {
    const el = kRelEl('release-keep-pin');
    return !!(el && el.checked);
}

function kRelComponents(results) {
    const out = { town: '', postcode: '', sublocality: '' };
    (results || []).forEach(function (r) {
        (r.address_components || []).forEach(function (c) {
            const t = c.types || [];
            if (!out.town && (t.includes('locality') || t.includes('postal_town'))) out.town = c.long_name;
            if (!out.sublocality && (t.includes('sublocality') || t.includes('sublocality_level_1'))) out.sublocality = c.long_name;
            if (!out.postcode && t.includes('postal_code')) out.postcode = c.long_name;
        });
    });
    if (!out.town && out.sublocality) out.town = out.sublocality;
    return out;
}

// Only ever writes a non-blank value, so a failed lookup can never wipe what is already there
function kRelSetIfValue(id, value) {
    if (!value) return false;
    kRelEl(id).value = value;
    return true;
}

function kRelGeocoder() { return new google.maps.Geocoder(); }

// Applies town/postcode to the visible fields; asks again using the pin position if Google left one out
function kRelApplyComponents(c, pos, opts) {
    opts = opts || {};
    const seq = kRel.seq;
    kRelSetIfValue('release_town', c.town);
    kRelSetIfValue('release_postcode', c.postcode);

    const report = function (still) {
        kRelNote('missing', still.length ? 'warn' : '',
            still.length ? 'Not found for this location: ' + still.join(' and ') + '. The existing value was kept.' : '');
    };

    if (opts.noPinLookup || (c.town && c.postcode)) {
        const still = [];
        if (!c.town) still.push('town');
        if (!c.postcode) still.push('postcode');
        report(still);
        return;
    }

    kRelGeocoder().geocode({ location: pos }, function (results, status) {
        if (seq !== kRel.seq) return;
        const still = [];
        if (status === 'OK' && results && results.length) {
            const c2 = kRelComponents(results);
            if (!c.town && !kRelSetIfValue('release_town', c2.town)) still.push('town');
            if (!c.postcode && !kRelSetIfValue('release_postcode', c2.postcode)) still.push('postcode');
        } else {
            if (!c.town) still.push('town');
            if (!c.postcode) still.push('postcode');
        }
        report(still);
    });
}

// An address (autocomplete choice or typed text) has been resolved to a place
function kRelUseAddressResult(locObj, components, formattedAddress, partial) {
    const pos = kRelPlain(locObj);
    const prev = kRelPlain(releaseMarker.getPosition());
    if (formattedAddress) {
        kRelEl('release_address').value = formattedAddress;
        kRel.lastAutocompleteValue = formattedAddress;
    }
    if (!kRelKeepPin()) {
        releaseMarker.setPosition(locObj);
        releaseMap.setCenter(locObj);
        kRelEl('release_lat').value = pos.lat.toFixed(6);
        kRelEl('release_lng').value = pos.lng.toFixed(6);
    }
    const jump = kRelDistanceM(prev, pos);
    if (jump > K_REL_MAX_JUMP_M) {
        kRelNote('distance', 'warn', kRelKeepPin()
            ? 'This address is about ' + kRelFormatDistance(jump) + ' from the pin you kept. A road name on its own can match a different place, so check it is the right one.'
            : 'The pin moved about ' + kRelFormatDistance(jump) + ' from where it was. A road name on its own can match a different place, so check the pin before saving.');
    } else {
        kRelNote('distance', '', '');
    }
    kRelNote('partial', partial ? 'warn' : '', partial ? 'Google found only a partial match for this address. Check the pin and the town and postcode below.' : '');
    kRelApplyComponents(components, pos);
}

function kRelGeocodeAddress(address) {
    address = (address || '').trim();
    if (!address) {
        kRelNote('geocode', 'warn', 'Enter an address to look up.');
        return;
    }
    const seq = ++kRel.seq;
    kRelNote('geocode', 'info', 'Looking up the address…');
    kRelGeocoder().geocode({ address: address, region: 'au', componentRestrictions: { country: 'AU' } }, function (results, status) {
        if (seq !== kRel.seq) return;
        if (status === 'OK' && results && results[0]) {
            kRelNote('geocode', '', '');
            const r = results[0];
            kRelUseAddressResult(r.geometry.location, kRelComponents([r]), '', !!r.partial_match);
        } else if (status === 'ZERO_RESULTS') {
            kRelNote('geocode', 'error', 'No match found for "' + address + '". The pin, town and postcode were not changed. Try a full street address, or drag the pin.');
        } else {
            kRelNote('geocode', 'error', 'Address lookup failed (' + status + '). The pin, town and postcode were not changed.');
        }
    });
}

function reverseGeocodeRelease(lat, lng) {
    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
    const seq = ++kRel.seq;
    kRelNote('geocode', 'info', 'Looking up the address for the pin…');
    kRelGeocoder().geocode({ location: pos }, function (results, status) {
        if (seq !== kRel.seq) return;
        if (status === 'OK' && results && results.length) {
            kRelNote('geocode', '', '');
            kRelEl('release_address').value = results[0].formatted_address;
            kRel.lastAutocompleteValue = results[0].formatted_address;
            kRelNote('distance', '', '');
            kRelNote('partial', '', '');
            kRelApplyComponents(kRelComponents(results), pos, { noPinLookup: true });
        } else {
            kRelNote('geocode', 'error', 'Could not look up an address for this pin' + (status && status !== 'ZERO_RESULTS' ? ' (' + status + ')' : '') + '. The address, town and postcode were not changed.');
        }
    });
}

function initReleaseMap() {
    if (releaseMapInitialized) {
        return;
    }
    const releaseMapElement = kRelEl('koala_release_map');
    const releaseLatInput = kRelEl('release_lat');
    const releaseLngInput = kRelEl('release_lng');
    const releaseAddressInput = kRelEl('release_address');
    const releaseTownInput = kRelEl('release_town');
    const releasePostcodeInput = kRelEl('release_postcode');

    if (!releaseMapElement || !releaseLatInput || !releaseLngInput || !releaseAddressInput || !releaseTownInput || !releasePostcodeInput) {
        kRelNote('init', 'error', 'Map container or inputs not found');
        return;
    }

    let lat = parseFloat(releaseLatInput.value);
    let lng = parseFloat(releaseLngInput.value);

    if (!kRelCoordsValid(lat, lng)) {
        lat = -28.8136;
        lng = 153.2773;
        releaseLatInput.value = lat.toFixed(6);
        releaseLngInput.value = lng.toFixed(6);
    }

    releaseOriginalLat = lat;
    releaseOriginalLng = lng;

    const mapCenter = { lat, lng };

    try {
        if (!google || !google.maps) {
            kRelNote('init', 'error', 'Google Maps API not available');
            return;
        }

        const mapScripts = document.querySelectorAll('script[src*="maps.googleapis.com/maps/api/js"]').length;
        if (mapScripts > 1) {
            console.warn('Koala: the Google Maps script is loaded ' + mapScripts + ' times on this page. Deactivate the duplicate loader.');
        }

        releaseMap = new google.maps.Map(releaseMapElement, {
            zoom: 15,
            center: mapCenter,
            mapTypeId: google.maps.MapTypeId.SATELLITE
        });

        releaseMarker = new google.maps.Marker({
            position: mapCenter,
            map: releaseMap,
            draggable: true,
            title: 'Drag to set release location'
        });

        releaseMarker.addListener('dragend', function () {
            const pos = releaseMarker.getPosition();
            releaseLatInput.value = pos.lat().toFixed(6);
            releaseLngInput.value = pos.lng().toFixed(6);
            reverseGeocodeRelease(pos.lat(), pos.lng());
        });

        const autocomplete = new google.maps.places.Autocomplete(releaseAddressInput, {
            types: ['address'],
            componentRestrictions: { country: 'au' }
        });
        autocomplete.bindTo('bounds', releaseMap);

        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();

            // Enter pressed on free text: no place was chosen, so look the typed text up instead
            if (!place || !place.geometry || !place.geometry.location) {
                kRelGeocodeAddress(releaseAddressInput.value || (place && place.name) || '');
                return;
            }

            ++kRel.seq;
            kRelNote('geocode', '', '');
            kRelUseAddressResult(
                place.geometry.location,
                kRelComponents([{ address_components: place.address_components || [] }]),
                place.formatted_address || '',
                false
            );
        });

        // Typed address (not an autocomplete choice): look it up when the box loses focus
        releaseAddressInput.addEventListener('change', function () {
            const value = this.value.trim();
            if (value && value === kRel.lastAutocompleteValue) {
                kRel.lastAutocompleteValue = '';
                return;
            }
            kRelGeocodeAddress(value);
        });

        function updateReleaseMapFromLatLngInputs() {
            const lat = parseFloat(releaseLatInput.value);
            const lng = parseFloat(releaseLngInput.value);
            if (!kRelCoordsValid(lat, lng)) {
                kRelNote('coords', 'error', 'Latitude must be between -90 and 90 and longitude between -180 and 180.');
                return;
            }
            kRelNote('coords', '', '');
            const newLatLng = { lat, lng };
            releaseMap.setCenter(newLatLng);
            releaseMarker.setPosition(newLatLng);
            reverseGeocodeRelease(lat, lng);
        }
        releaseLatInput.addEventListener('change', updateReleaseMapFromLatLngInputs);
        releaseLngInput.addEventListener('change', updateReleaseMapFromLatLngInputs);

        kRelEl('release_geolocate').addEventListener('click', function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        releaseLatInput.value = lat.toFixed(6);
                        releaseLngInput.value = lng.toFixed(6);
                        const latLng = { lat, lng };
                        releaseMap.setCenter(latLng);
                        releaseMarker.setPosition(latLng);
                        reverseGeocodeRelease(lat, lng);
                    },
                    function (error) {
                        kRelNote('gps', 'error', 'Geolocation failed: ' + error.message);
                    }
                );
            } else {
                kRelNote('gps', 'error', 'Geolocation not supported');
            }
        });

        kRelEl('release_save').addEventListener('click', function () {
            const btn = this;
            const latVal = parseFloat(releaseLatInput.value);
            const lngVal = parseFloat(releaseLngInput.value);
            if (!kRelCoordsValid(latVal, lngVal)) {
                kRelNote('save', 'error', 'Latitude must be between -90 and 90 and longitude between -180 and 180. Nothing was saved.');
                return;
            }
            const data = {
                action: 'save_koala_release_location',
                post_id: <?php echo (int) $post->ID; ?>,
                nonce: koalaAjax.releaseNonce,
                address: releaseAddressInput.value,
                latitude: releaseLatInput.value,
                longitude: releaseLngInput.value,
                town: releaseTownInput.value,
                postcode: releasePostcodeInput.value,
                location_confirmed: kRelEl('release-confirmed').checked ? '1' : '0'
            };

            btn.disabled = true;
            kRelNote('save', 'info', 'Saving…');
            fetch(koalaAjax.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.success) {
                    const notices = (response.data && response.data.notices) || [];
                    kRelNote('save', 'ok', 'Location saved. Reloading...');
                    notices.forEach(function (text, i) { kRelNote('server' + i, 'warn', text); });
                    setTimeout(function () { location.reload(); }, notices.length ? 4000 : 1500);
                } else {
                    btn.disabled = false;
                    kRelNote('save', 'error', 'Error: ' + (typeof response.data === 'string' ? response.data : 'Failed to save location'));
                }
            })
            .catch(function () {
                btn.disabled = false;
                kRelNote('save', 'error', 'Error saving location');
            });
        });

        releaseMapInitialized = true;
    } catch (error) {
        kRelNote('init', 'error', 'Map initialization failed: ' + error.message);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const editBtn = kRelEl('koala-release-edit-btn');
    const cancelBtn = kRelEl('release_cancel');
    const form = kRelEl('koala-release-form');
    const summary = kRelEl('koala-release-summary');
    const releaseMapElement = kRelEl('koala_release_map');
    const releaseAddressInput = kRelEl('release_address');
    const releaseLatInput = kRelEl('release_lat');
    const releaseLngInput = kRelEl('release_lng');
    const releaseTownInput = kRelEl('release_town');
    const releasePostcodeInput = kRelEl('release_postcode');

    if (editBtn && form && summary && releaseMapElement && releaseAddressInput && releaseLatInput && releaseLngInput && releaseTownInput && releasePostcodeInput) {
        editBtn.addEventListener('click', function () {
            form.style.display = 'block';
            summary.style.display = 'none';
            if (!releaseMapInitialized && typeof initReleaseMap === 'function' && !releaseMapElement.children.length) {
                initReleaseMap();
            }
        });
    }

    if (cancelBtn && form && summary && releaseAddressInput && releaseLatInput && releaseLngInput && releaseTownInput && releasePostcodeInput) {
        cancelBtn.addEventListener('click', function () {
            form.style.display = 'none';
            summary.style.display = 'block';
            // Reset form values
            releaseAddressInput.value = '<?php echo esc_js($release_address); ?>';
            releaseLatInput.value = '<?php echo esc_js($release_lat); ?>';
            releaseLngInput.value = '<?php echo esc_js($release_lng); ?>';
            releaseTownInput.value = '<?php echo esc_js($release_town); ?>';
            releasePostcodeInput.value = '<?php echo esc_js($release_postcode); ?>';
            const releaseConfirmedInput = kRelEl('release-confirmed');
            if (releaseConfirmedInput) {
                releaseConfirmedInput.checked = <?php echo $release_confirmed ? 'true' : 'false'; ?>;
            }
            ++kRel.seq;
            kRel.lastAutocompleteValue = '';
            kRel.notes = {};
            kRelNote('', '', '');
            // Reset map and marker
            if (releaseMap && releaseMarker && releaseOriginalLat !== undefined && releaseOriginalLng !== undefined) {
                const lat = parseFloat('<?php echo esc_js($release_lat); ?>') || -28.8136;
                const lng = parseFloat('<?php echo esc_js($release_lng); ?>') || 153.2773;
                const latLng = { lat, lng };
                releaseMap.setCenter(latLng);
                releaseMarker.setPosition(latLng);
            }
        });
    }
});
</script>

    <?php
    return ob_get_clean();
});
