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
    $release_address   = esc_attr($release_data['address']);
    $release_lat       = esc_attr($release_data['lat']);
    $release_lng       = esc_attr($release_data['lng']);
    $release_town      = esc_attr($release_data['town']);
    $release_postcode  = esc_attr($release_data['postcode']);
    $release_confirmed = !empty($release_data['confirmed']);

    ob_start();
    ?>
    <style>
        #koala-release-map-wrapper p {
            margin-bottom: 0px !important;
        }
        #koala-release-map-wrapper input[type="text"] {
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0 !important;
            background: var(--base-2) !important;
            border: 1px solid rgba(75,117,146,0.5);
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
        #release_location_message {
            font-weight: bold;
            margin-top: 10px;
            color: #333;
        }
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
        #release_town, #release_postcode {
            display: none;
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
        #release-confirmed-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 10px 0;
            padding: 6px 0;
        }
        #release-confirmed-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin: 0;
            padding: 0;
            accent-color: #df6200;
            cursor: pointer;
            flex-shrink: 0;
        }
        #release-confirmed-wrap label {
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
            <p><input type="text" id="release_address" placeholder="Address" value="<?php echo esc_attr($release_address); ?>"></p>
            <p><input type="text" id="release_lat" placeholder="Latitude" value="<?php echo esc_attr($release_lat); ?>"></p>
            <p><input type="text" id="release_lng" placeholder="Longitude" value="<?php echo esc_attr($release_lng); ?>"></p>
            <input type="hidden" id="release_town" value="<?php echo esc_attr($release_town); ?>">
            <input type="hidden" id="release_postcode" value="<?php echo esc_attr($release_postcode); ?>">
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
			<div id="release_location_message"></div>
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

function initReleaseMap() {
    if (releaseMapInitialized) {
        console.log('Release map already initialized');
        return;
    }
    console.log('initReleaseMap started');
    const releaseMapElement = document.getElementById('koala_release_map');
    const releaseLatInput = document.getElementById('release_lat');
    const releaseLngInput = document.getElementById('release_lng');
    const releaseAddressInput = document.getElementById('release_address');
    const releaseTownInput = document.getElementById('release_town');
    const releasePostcodeInput = document.getElementById('release_postcode');

    if (!releaseMapElement || !releaseLatInput || !releaseLngInput || !releaseAddressInput || !releaseTownInput || !releasePostcodeInput) {
        console.error('Missing required DOM elements for release map:', {
            map: !!releaseMapElement,
            lat: !!releaseLatInput,
            lng: !!releaseLngInput,
            address: !!releaseAddressInput,
            town: !!releaseTownInput,
            postcode: !!releasePostcodeInput
        });
        const msgEl = document.getElementById('release_location_message');
        if (msgEl) msgEl.innerHTML = '❌ Map container or inputs not found';
        return;
    }

    let lat = parseFloat(releaseLatInput.value);
    let lng = parseFloat(releaseLngInput.value);

    if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        console.warn('Invalid coordinates, using default:', { lat, lng });
        lat = -28.8136;
        lng = 153.2773;
        releaseLatInput.value = lat.toFixed(6);
        releaseLngInput.value = lng.toFixed(6);
    }

    releaseOriginalLat = lat;
    releaseOriginalLng = lng;

    const mapCenter = { lat, lng };
    console.log('Release map center:', mapCenter);

    try {
        if (!google || !google.maps) {
            console.error('Google Maps API not available');
            const msgEl = document.getElementById('release_location_message');
            if (msgEl) msgEl.innerHTML = '❌ Google Maps API not available';
            return;
        }

        releaseMap = new google.maps.Map(releaseMapElement, {
            zoom: 15,
            center: mapCenter,
            mapTypeId: google.maps.MapTypeId.SATELLITE
        });
        console.log('Release map initialized');

        releaseMarker = new google.maps.Marker({
            position: mapCenter,
            map: releaseMap,
            draggable: true,
            title: 'Drag to set release location'
        });
        console.log('Release marker created');

        releaseMarker.addListener('dragend', function () {
            console.log('Release marker dragged');
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
        console.log('Release autocomplete initialized');

        autocomplete.addListener('place_changed', function () {
            console.log('Release autocomplete place changed');
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                console.warn('No geometry for selected place');
                return;
            }
            const loc = place.geometry.location;
            releaseLatInput.value = loc.lat().toFixed(6);
            releaseLngInput.value = loc.lng().toFixed(6);
            releaseMap.setCenter(loc);
            releaseMarker.setPosition(loc);
            reverseGeocodeRelease(loc.lat(), loc.lng());
        });

        // ======== NEW: Update map when lat/lng inputs change manually ========
        function updateReleaseMapFromLatLngInputs() {
            const lat = parseFloat(releaseLatInput.value);
            const lng = parseFloat(releaseLngInput.value);
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                const newLatLng = { lat, lng };
                releaseMap.setCenter(newLatLng);
                releaseMarker.setPosition(newLatLng);
                reverseGeocodeRelease(lat, lng);
            }
        }
        releaseLatInput.addEventListener('change', updateReleaseMapFromLatLngInputs);
        releaseLngInput.addEventListener('change', updateReleaseMapFromLatLngInputs);
        // =======================================================================

        document.getElementById('release_geolocate').addEventListener('click', function () {
            console.log('Release geolocate clicked');
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        console.log('Release GPS position:', { lat, lng });
                        releaseLatInput.value = lat.toFixed(6);
                        releaseLngInput.value = lng.toFixed(6);
                        const latLng = { lat, lng };
                        releaseMap.setCenter(latLng);
                        releaseMarker.setPosition(latLng);
                        reverseGeocodeRelease(lat, lng);
                    },
                    function (error) {
                        console.error('Release geolocation error:', error.message);
                        const msgEl = document.getElementById('release_location_message');
                        if (msgEl) msgEl.innerHTML = '❌ Geolocation failed: ' + error.message;
                    }
                );
            } else {
                console.error('Geolocation not supported');
                const msgEl = document.getElementById('release_location_message');
                if (msgEl) msgEl.innerHTML = '❌ Geolocation not supported';
            }
        });

        document.getElementById('release_save').addEventListener('click', function () {
            console.log('Release save clicked');
            const data = {
                action: 'save_koala_release_location',
                post_id: <?php echo $post->ID; ?>,
                nonce: koalaAjax.releaseNonce,
                address: releaseAddressInput.value,
                latitude: releaseLatInput.value,
                longitude: releaseLngInput.value,
                town: releaseTownInput.value,
                postcode: releasePostcodeInput.value,
                location_confirmed: document.getElementById('release-confirmed').checked ? '1' : '0'
            };

            console.log('Release save data:', data);

            fetch(koalaAjax.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(res => res.json())
            .then(response => {
                const msg = document.getElementById('release_location_message');
                if (response.success) {
                    console.log('Release location saved');
                    msg.innerHTML = '✅ Location saved! Reloading...';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    console.error('Release save error:', response);
                    msg.innerHTML = '❌ Error: ' + (response.data || 'Failed to save location');
                }
            })
            .catch(error => {
                console.error('Release fetch error:', error);
                const msgEl = document.getElementById('release_location_message');
                if (msgEl) msgEl.innerHTML = '❌ Error saving location';
            });
        });

        function reverseGeocodeRelease(lat, lng) {
            console.log('Reverse geocoding release:', { lat, lng });
            const geocoder = new google.maps.Geocoder();
            const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
            geocoder.geocode({ location: latlng }, function (results, status) {
                if (status === 'OK' && results[0]) {
                    console.log('Release reverse geocode successful');
                    releaseAddressInput.value = results[0].formatted_address;
                    let town = '', postcode = '';
                    for (let comp of results[0].address_components) {
                        if (comp.types.includes('locality')) town = comp.long_name;
                        if (comp.types.includes('postal_code')) postcode = comp.long_name;
                    }
                    releaseTownInput.value = town;
                    releasePostcodeInput.value = postcode;
                } else {
                    console.error('Release reverse geocoding failed:', status);
                    const msgEl = document.getElementById('release_location_message');
                    if (msgEl) msgEl.innerHTML = '❌ Reverse geocoding failed';
                }
            });
        }

        releaseMapInitialized = true;
    } catch (error) {
        console.error('initReleaseMap error:', error);
        const msgEl = document.getElementById('release_location_message');
        if (msgEl) msgEl.innerHTML = '❌ Map initialization failed: ' + error.message;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    console.log('Setting up koala release map toggle');
    const editBtn = document.getElementById('koala-release-edit-btn');
    const cancelBtn = document.getElementById('release_cancel');
    const form = document.getElementById('koala-release-form');
    const summary = document.getElementById('koala-release-summary');
    const releaseMapElement = document.getElementById('koala_release_map');
    const releaseAddressInput = document.getElementById('release_address');
    const releaseLatInput = document.getElementById('release_lat');
    const releaseLngInput = document.getElementById('release_lng');
    const releaseTownInput = document.getElementById('release_town');
    const releasePostcodeInput = document.getElementById('release_postcode');

    if (editBtn && form && summary && releaseMapElement && releaseAddressInput && releaseLatInput && releaseLngInput && releaseTownInput && releasePostcodeInput) {
        editBtn.addEventListener('click', function () {
            console.log('Release edit button clicked');
            form.style.display = 'block';
            summary.style.display = 'none';
            if (!releaseMapInitialized && typeof initReleaseMap === 'function' && !releaseMapElement.children.length) {
                console.log('Initializing release map');
                initReleaseMap();
            }
        });
    } else {
        console.error('Release toggle elements missing:', {
            editBtn: !!editBtn,
            form: !!form,
            summary: !!summary,
            map: !!releaseMapElement,
            address: !!releaseAddressInput,
            lat: !!releaseLatInput,
            lng: !!releaseLngInput,
            town: !!releaseTownInput,
            postcode: !!releasePostcodeInput
        });
    }

    if (cancelBtn && form && summary && releaseAddressInput && releaseLatInput && releaseLngInput && releaseTownInput && releasePostcodeInput) {
        cancelBtn.addEventListener('click', function () {
            console.log('Release location edit cancelled');
            form.style.display = 'none';
            summary.style.display = 'block';
            // Reset form values
            releaseAddressInput.value = '<?php echo esc_js($release_address); ?>';
            releaseLatInput.value = '<?php echo esc_js($release_lat); ?>';
            releaseLngInput.value = '<?php echo esc_js($release_lng); ?>';
            releaseTownInput.value = '<?php echo esc_js($release_town); ?>';
            releasePostcodeInput.value = '<?php echo esc_js($release_postcode); ?>';
            const releaseConfirmedInput = document.getElementById('release-confirmed');
            if (releaseConfirmedInput) {
                releaseConfirmedInput.checked = <?php echo $release_confirmed ? 'true' : 'false'; ?>;
            }
            // Reset map and marker
            if (releaseMap && releaseMarker && releaseOriginalLat !== undefined && releaseOriginalLng !== undefined) {
                const lat = parseFloat('<?php echo esc_js($release_lat); ?>') || -28.8136;
                const lng = parseFloat('<?php echo esc_js($release_lng); ?>') || 153.2773;
                const latLng = { lat, lng };
                releaseMap.setCenter(latLng);
                releaseMarker.setPosition(latLng);
            }
        });
    } else {
        console.error('Release cancel elements missing:', {
            cancelBtn: !!cancelBtn,
            form: !!form,
            summary: !!summary,
            address: !!releaseAddressInput,
            lat: !!releaseLatInput,
            lng: !!releaseLngInput,
            town: !!releaseTownInput,
            postcode: !!releasePostcodeInput
        });
    }
});
</script>

    <?php
    return ob_get_clean();
});