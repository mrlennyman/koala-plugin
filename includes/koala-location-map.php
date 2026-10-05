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
        #koala-location-fields input[type="text"], #koala-location-fields input[type="hidden"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            background: var(--base-2) !important;
            border: 1px solid rgba(75,117,146,0.5);
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
        #koala-map {
            height: 400px;
            width: 100%;
            margin: 15px 0;
            border: 1px solid #ddd;
            display: block;
            min-height: 400px;
        }
        #koala-location-message {
            font-weight: bold;
            margin-top: 10px;
            color: #333;
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
        .pac-container {
            z-index: 10000 !important;
        }
        #koala-town, #koala-lga, #koala-postcode {
            display: none;
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
        /* Checkbox row in edit form */
        #koala-confirmed-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px 0;
            padding: 6px 0;
        }
        #koala-confirmed-wrap input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
            accent-color: #df6200;
            width: 16px;
            height: 16px;
            cursor: pointer;
            flex-shrink: 0;
        }
        #koala-confirmed-wrap label {
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
            <p><input type="text" id="koala-address" placeholder="Enter address" value="<?php echo esc_attr($address); ?>" /></p>
            <p><input type="text" id="koala-latitude" placeholder="Latitude" value="<?php echo esc_attr($lat); ?>" /></p>
            <p><input type="text" id="koala-longitude" placeholder="Longitude" value="<?php echo esc_attr($lng); ?>" /></p>
            <input type="hidden" id="koala-town" value="<?php echo esc_attr($town); ?>" />
            <input type="hidden" id="koala-lga" value="<?php echo esc_attr($lga); ?>" />
            <input type="hidden" id="koala-postcode" value="<?php echo esc_attr($postcode); ?>" />
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
            <p id="koala-location-message"></p>
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
let skipNextGeocode = false; // To avoid double geocode on autocomplete

function initKoalaMap() {
    if (koalaMapInitialized) return;

    const mapEl = document.getElementById('koala-map');
    const latInput = document.getElementById('koala-latitude');
    const lngInput = document.getElementById('koala-longitude');
    const addressInput = document.getElementById('koala-address');
    const townInput = document.getElementById('koala-town');
    const lgaInput = document.getElementById('koala-lga');
    const postcodeInput = document.getElementById('koala-postcode');

    if (!mapEl || !latInput || !lngInput || !addressInput || !townInput || !lgaInput || !postcodeInput) {
        const msgEl = document.getElementById('koala-location-message');
        if (msgEl) msgEl.innerHTML = '❌ Map container or inputs not found';
        return;
    }

    mapEl.style.display = 'block';
    mapEl.style.height = '400px';

    let lat = parseFloat(latInput.value);
    let lng = parseFloat(lngInput.value);

    if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
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
            const msgEl = document.getElementById('koala-location-message');
            if (msgEl) msgEl.innerHTML = '❌ Google Maps API not available';
            return;
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

            if (!place.geometry || !place.geometry.location) {
                console.warn('Autocomplete place has no geometry:', place);
                const msgEl = document.getElementById('koala-location-message');
                if (msgEl) msgEl.innerHTML = '❌ Selected location is not valid. Please choose a full address.';
                return;
            }

            const loc = place.geometry.location;
            koalaMarker.setPosition(loc);
            koalaMap.setCenter(loc);
            latInput.value = loc.lat().toFixed(6);
            lngInput.value = loc.lng().toFixed(6);

            if (place.formatted_address) {
                addressInput.value = place.formatted_address;
            }

            let town = '', lga = '', postcode = '', street = '';
            if (place.address_components) {
                for (let comp of place.address_components) {
                    if (comp.types.includes('locality')) town = comp.long_name;
                    if (comp.types.includes('administrative_area_level_2')) lga = comp.long_name;
                    if (comp.types.includes('postal_code')) postcode = comp.long_name;
                    if (comp.types.includes('route') || comp.types.includes('street_number')) {
                        if (street) street += ' ';
                        street += comp.long_name;
                    }
                }
            }

            townInput.value = town;
            lgaInput.value = lga;
            postcodeInput.value = postcode;

            let streetInput = document.getElementById('koala-street-address');
            if (!streetInput) {
                streetInput = document.createElement('input');
                streetInput.type = 'hidden';
                streetInput.id = 'koala-street-address';
                streetInput.name = 'koala-street-address';
                document.getElementById('koala-location-fields').appendChild(streetInput);
            }
            streetInput.value = street;

            if (!town || !postcode || !lga) {
                console.log('Fallback to reverse geocoding for missing fields');
                reverseGeocode(loc.lat(), loc.lng());
            }

            const msgEl = document.getElementById('koala-location-message');
            if (msgEl) msgEl.innerHTML = '';
        });

        koalaMarker.addListener('dragend', function () {
            const pos = koalaMarker.getPosition();
            latInput.value = pos.lat().toFixed(6);
            lngInput.value = pos.lng().toFixed(6);
            reverseGeocode(pos.lat(), pos.lng());
        });

        // When address input changes manually (not autocomplete), geocode it
        addressInput.addEventListener('change', function () {
            if (skipNextGeocode) {
                skipNextGeocode = false;
                return;
            }
            geocodeAddress(this.value);
        });

        // When lat or lng inputs change manually, reverse geocode to update address
        latInput.addEventListener('change', function () {
            const latVal = parseFloat(latInput.value);
            const lngVal = parseFloat(lngInput.value);
            if (!isNaN(latVal) && !isNaN(lngVal)) {
                koalaMarker.setPosition({ lat: latVal, lng: lngVal });
                koalaMap.setCenter({ lat: latVal, lng: lngVal });
                reverseGeocode(latVal, lngVal);
            }
        });
        lngInput.addEventListener('change', function () {
            const latVal = parseFloat(latInput.value);
            const lngVal = parseFloat(lngInput.value);
            if (!isNaN(latVal) && !isNaN(lngVal)) {
                koalaMarker.setPosition({ lat: latVal, lng: lngVal });
                koalaMap.setCenter({ lat: latVal, lng: lngVal });
                reverseGeocode(latVal, lngVal);
            }
        });

        // GPS button for current location
        document.getElementById('koala-gps-btn').addEventListener('click', function () {
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
                        const msgEl = document.getElementById('koala-location-message');
                        if (msgEl) msgEl.innerHTML = '❌ Geolocation failed: ' + error.message;
                    }
                );
            } else {
                const msgEl = document.getElementById('koala-location-message');
                if (msgEl) msgEl.innerHTML = '❌ Geolocation not supported';
            }
        });

        // Save button - sends data to server via AJAX
        document.getElementById('koala-save-btn').addEventListener('click', function () {
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
                street_address: document.getElementById('koala-street-address') ? document.getElementById('koala-street-address').value : '',
                location_confirmed: document.getElementById('koala-confirmed').checked ? '1' : '0'
            };

            fetch(koalaAjax.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(res => res.json())
            .then(response => {
                const msg = document.getElementById('koala-location-message');
                if (response.success) {
                    msg.innerHTML = '✅ Location saved! Reloading...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = '❌ Error: ' + (response.data || 'Failed to save location');
                }
            })
            .catch(error => {
                const msgEl = document.getElementById('koala-location-message');
                if (msgEl) msgEl.innerHTML = '❌ Error saving location';
            });
        });

        function geocodeAddress(address) {
            if (!address) return;
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: address, region: 'au' }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    const loc = results[0].geometry.location;
                    koalaMarker.setPosition(loc);
                    koalaMap.setCenter(loc);
                    document.getElementById('koala-latitude').value = loc.lat().toFixed(6);
                    document.getElementById('koala-longitude').value = loc.lng().toFixed(6);
                    updateAddressComponents(results[0].address_components);
                } else {
                    if (status !== 'ZERO_RESULTS') {
                        const msgEl = document.getElementById('koala-location-message');
                        if (msgEl) msgEl.innerHTML = '❌ Geocoding failed: ' + status;
                    }
                }
            });
        }

        function reverseGeocode(lat, lng) {
            const geocoder = new google.maps.Geocoder();
            const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    document.getElementById('koala-address').value = results[0].formatted_address;
                    updateAddressComponents(results[0].address_components);
                } else {
                    const msgEl = document.getElementById('koala-location-message');
                    if (msgEl) msgEl.innerHTML = '❌ Reverse geocoding failed';
                }
            });
        }

        function updateAddressComponents(components) {
            let town = '', lga = '', postcode = '', street = '';
            for (let comp of components) {
                if (comp.types.includes('locality')) town = comp.long_name;
                if (comp.types.includes('administrative_area_level_2')) lga = comp.long_name;
                if (comp.types.includes('postal_code')) postcode = comp.long_name;
                if (comp.types.includes('route') || comp.types.includes('street_address') || comp.types.includes('street_number')) {
                    if (street) street += ' ';
                    street += comp.long_name;
                }
            }
            document.getElementById('koala-town').value = town;
            document.getElementById('koala-lga').value = lga;
            document.getElementById('koala-postcode').value = postcode;

            let streetInput = document.getElementById('koala-street-address');
            if (!streetInput) {
                streetInput = document.createElement('input');
                streetInput.type = 'hidden';
                streetInput.id = 'koala-street-address';
                streetInput.name = 'koala-street-address';
                document.getElementById('koala-location-fields').appendChild(streetInput);
            }
            streetInput.value = street;
        }

        koalaMapInitialized = true;
    } catch (error) {
        const msgEl = document.getElementById('koala-location-message');
        if (msgEl) msgEl.innerHTML = '❌ Map initialization failed: ' + error.message;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const editBtn      = document.getElementById('koala-edit-btn');
    const cancelBtn    = document.getElementById('koala-cancel-btn');
    const form         = document.getElementById('koala-location-form');
    const summary      = document.getElementById('koala-location-summary');
    const addressInput = document.getElementById('koala-address');
    const latInput     = document.getElementById('koala-latitude');
    const lngInput     = document.getElementById('koala-longitude');
    const townInput    = document.getElementById('koala-town');
    const lgaInput     = document.getElementById('koala-lga');
    const postcodeInput = document.getElementById('koala-postcode');
    const confirmedInput = document.getElementById('koala-confirmed');
    const mapEl        = document.getElementById('koala-map');

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