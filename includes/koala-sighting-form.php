<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Koala sighting form shortcode
add_shortcode('koala_sighting_form', 'koala_sighting_form_shortcode');
function koala_sighting_form_shortcode() {
    ob_start();

    // Get settings from public_sighting_form for NSW bounds consistency
    $settings = get_option('koala_sighting_form_settings', []);
    $default_latitude = $settings['default_latitude'] ?? '-28.8136';
    $default_longitude = $settings['default_longitude'] ?? '153.2773';
    $nsw_bounds = [
        'north' => floatval($settings['nsw_bounds_north'] ?? '-28.0'),
        'south' => floatval($settings['nsw_bounds_south'] ?? '-37.5'),
        'east' => floatval($settings['nsw_bounds_east'] ?? '154.0'),
        'west' => floatval($settings['nsw_bounds_west'] ?? '141.0'),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['koala_nonce']) && wp_verify_nonce($_POST['koala_nonce'], 'submit_koala_form')) {
        $post_id = wp_insert_post(array(
            'post_type'   => 'koala',
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field($_POST['koala_address'] ?? 'Untitled Sighting'),
        ));

        if ($post_id && !is_wp_error($post_id)) {
            $first_name = sanitize_text_field($_POST['first_name'] ?? '');
            $last_name  = sanitize_text_field($_POST['last_name'] ?? '');
            $caller_full_name = trim($first_name . ' ' . $last_name);
            $phone = preg_replace('/\D/', '', $_POST['phone_number'] ?? '');

            $datetime_raw = sanitize_text_field($_POST['date_time'] ?? '');
            $incident_date = '';
            $time_sighted = '';
            if (!empty($datetime_raw)) {
                try {
                    $dt = new DateTime($datetime_raw);
                    $incident_date = $dt->format('Ymd');
                    $time_sighted = $dt->format('H:i');
                } catch (Exception $e) {
                    // Silent catch
                }
            }

            $koala_address = sanitize_text_field($_POST['koala_address'] ?? '');
            
            // Extract first line (street name) from koala_address
            $address = '';
            if (!empty($koala_address)) {
                $address_parts = explode(',', $koala_address, 2); // Split at first comma
                $address = trim($address_parts[0]); // Take first part and trim whitespace
            }
            update_field('address', $address, $post_id);

            $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : '';
            $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : '';
            $mop_present = sanitize_text_field($_POST['Is_MOP_present'] ?? '');
            $where_found = sanitize_text_field($_POST['where_found'] ?? '');
            $animal_condition = sanitize_text_field($_POST['animal_condition'] ?? '');
            $encounter_type = sanitize_text_field($_POST['encounter_type'] ?? '');
            $life_stage = sanitize_text_field($_POST['life_stage'] ?? '');
            $sex = sanitize_text_field($_POST['sex'] ?? '');
            $pouch_condition = sanitize_text_field($_POST['pouch_condition'] ?? '');
            $koala_status = sanitize_text_field($_POST['koala_status'] ?? '');
            $koala_notes = sanitize_text_field($_POST['koala_notes'] ?? '');
            $call_referred_by = sanitize_text_field($_POST['call_referred_by'] ?? '');
            $wires_reference_number = sanitize_text_field($_POST['wires_reference_number'] ?? '');
            $fate = sanitize_text_field($_POST['fate'] ?? '');
            $fate_date = sanitize_text_field($_POST['fate_date'] ?? '');

            if (function_exists('update_field')) {
                update_field('first_name', $first_name, $post_id);
                update_field('last_name', $last_name, $post_id);
                update_field('caller_full_name', $caller_full_name, $post_id);
                update_field('caller_phone_number', $phone, $post_id);
                update_field('phone_number', $phone, $post_id);
                update_field('date_time', $datetime_raw, $post_id);
                update_field('incident_date', $incident_date, $post_id);
                update_field('time_sighted', $time_sighted, $post_id);
                update_field('koala_status', $koala_status, $post_id);

                update_field('koala_address', $koala_address, $post_id);
                update_field('latitude', $latitude, $post_id);
                update_field('longitude', $longitude, $post_id);

                update_field('location_map', array(
                    'address' => $koala_address,
                    'lat'     => $latitude,
                    'lng'     => $longitude,
                ), $post_id);

                update_field('town', sanitize_text_field($_POST['town'] ?? ''), $post_id);
                update_field('lga', sanitize_text_field($_POST['lga'] ?? ''), $post_id);
                update_field('postcode', sanitize_text_field($_POST['postcode'] ?? ''), $post_id);
                update_field('location_info', sanitize_text_field($_POST['location_info'] ?? ''), $post_id);
                update_field('Is_MOP_present', $mop_present, $post_id);
                update_field('where_found', $where_found, $post_id);
                update_field('animal_condition', $animal_condition, $post_id);
                update_field('encounter_type', $encounter_type, $post_id);
                update_field('life_stage', $life_stage, $post_id);
                update_field('sex_of_koala', $sex, $post_id);
                update_field('pouch_condition', $pouch_condition, $post_id);
                update_field('koala_notes', $koala_notes, $post_id);
                update_field('call_referred_by', $call_referred_by, $post_id);
                update_field('wires_reference_number', $wires_reference_number, $post_id);
                update_field('fate', $fate, $post_id);
                update_field('fate_date', $fate_date, $post_id);
                update_field('field_6859d17161d59', current_time('d/m/Y'), $post_id);
				update_field('lock_post', 'False', $post_id);
				update_field('field_6864ef6c5f38d', 'Yes', $post_id);
                if (is_user_logged_in()) {
                    update_field('recorder', get_current_user_id(), $post_id);
                }
            }

            // Trigger WP Grid Builder index update
            if (method_exists('\WP_Grid_Builder\Includes\Indexer', 'index_facets')) {
                try {
                    $indexer = new \WP_Grid_Builder\Includes\Indexer();
                    $indexer->index_facets(-1);
                } catch (Exception $e) {
                    // Silent catch
                }
            }

            // Fallback to wpgb_index_post
            if (function_exists('wpgb_index_post')) {
                try {
                    wpgb_index_post($post_id);
                } catch (Exception $e) {
                    // Silent catch
                }
            }

            wp_redirect(get_permalink($post_id));
            exit;
        }
    }
    ?>

    <style>
        #koala-map {
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
            min-height: 400px;
            margin-top: 20px;
        }
        .koala-form {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .koala-form .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .koala-form .form-group {
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            margin-right: 20px;
        }
        .koala-form .form-group:last-child {
            margin-right: 0;
        }
        .koala-form label {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .koala-form input,
        .koala-form select {
            padding: 12px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .koala-form input[type="submit"] {
            background: #004226;
            color: #fff;
            border: none;
            cursor: pointer;
            padding: 14px 20px;
            margin-top: 20px;
            width: 100%;
            font-size: 18px;
            border-radius: 4px;
        }
        .koala-form input[type="submit"]:hover {
            background: #005f35;
        }
        .pac-container {
            z-index: 10000 !important;
        }
        .koala-form .form-row.items-3 .form-group {
            flex: 1 1 calc(33.33% - 13.33px);
            max-width: calc(33.33% - 13.33px);
        }
        .koala-form .form-row.items-2 .form-group {
            flex: 1 1 calc(50% - 10px);
            max-width: calc(50% - 10px);
        }
        .koala-form .form-row.items-1 .form-group {
            flex: 1 1 100%;
            max-width: 100%;
            margin-right: 0;
        }
        .hidden {
            display: none;
        }
        .required {
            color: red;
            margin-left: 5px;
        }
        /* Mobile-specific styles */
        @media (max-width: 767px) {
            .koala-form {
                padding: 15px;
            }
            .koala-form .form-row {
                margin-bottom: 15px;
                gap: 15px; /* Add gap between fields in a row */
            }
            .koala-form .form-group {
                flex: 1 1 100%;
                max-width: 100%;
                margin-right: 0;
                margin-bottom: 20px;
            }
            .koala-form .form-row.items-3 .form-group {
                flex: 1 1 calc(50% - 7.5px); /* Two items per row */
                max-width: calc(50% - 7.5px);
            }
            .koala-form .form-row.items-3 .form-group:nth-child(3) {
                flex: 1 1 100%; /* Third item takes full width */
                max-width: 100%;
            }
            .koala-form .form-group:last-child {
                margin-bottom: 20px;
            }
            .koala-form label {
                font-size: 14px;
                margin-bottom: 6px;
            }
            .koala-form input,
            .koala-form select {
                padding: 10px;
                font-size: 14px;
                height: 48px;
            }
            .koala-form input[type="submit"] {
                padding: 12px;
                font-size: 16px;
            }
            #koala-map {
                height: 300px;
                min-height: 300px;
            }
        }
        @media (max-width: 480px) {
            .koala-form {
                padding: 10px;
            }
            .koala-form .form-row {
                margin-bottom: 10px;
                gap: 10px; /* Smaller gap for very small screens */
            }
            .koala-form .form-group {
                margin-bottom: 15px;
            }
            .koala-form .form-row.items-3 .form-group {
                flex: 1 1 100%; /* Stack all items on very small screens */
                max-width: 100%;
            }
            .koala-form label {
                font-size: 13px;
            }
            .koala-form input,
            .koala-form select {
                font-size: స
            }
            .koala-form input[type="submit"] {
                font-size: 15px;
            }
        }
    </style>

    <div class="koala-form">
    <form method="POST" id="koala-sighting-form">
        <?php wp_nonce_field('submit_koala_form', 'koala_nonce'); ?>

        <!-- Row 1: First Name, Last Name, Phone Number (3 items) -->
        <div class="form-row items-3">
            <div class="form-group">
                <label for="first-name">First Name<span class="required">*</span></label>
                <input type="text" id="first-name" name="first_name" required />
            </div>
            <div class="form-group">
                <label for="last-name">Last Name<span class="required">*</span></label>
                <input type="text" id="last-name" name="last_name" required />
            </div>
            <div class="form-group">
                <label for="phone-number">Phone Number<span class="required">*</span></label>
                <input type="text" id="phone-number" name="phone_number" placeholder="#### ### ###" required pattern="\d{4}\s\d{3}\s\d{3}" />
            </div>
        </div>

        <!-- Row 2: Koala Address (1 item) -->
        <div class="form-row items-1">
            <div class="form-group">
                <label for="koala_address">Koala Address<span class="required">*</span></label>
                <input type="text" id="koala_address" name="koala_address" required />
            </div>
        </div>

        <!-- Row 3: Date and Time, MOP Present (2 items) -->
        <div class="form-row items-2">
            <div class="form-group">
                <label for="date-time">Date and Time<span class="required">*</span></label>
                <?php
                // Set timezone to NSW (Australia/Sydney)
                $nsw_timezone = new DateTimeZone('Australia/Sydney');
                $current_datetime = new DateTime('now', $nsw_timezone);
                $formatted_datetime = $current_datetime->format('Y-m-d\TH:i');
                ?>
                <input type="datetime-local" id="date-time" name="date_time" value="<?php echo esc_attr($formatted_datetime); ?>" required />
            </div>
            <div class="form-group">
                <label for="mop-present">Is MOP Present?</label>
                <select id="mop-present" name="Is_MOP_present">
                    <option value="">Select Option</option>
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                </select>
            </div>
        </div>

        <!-- Row 4: Map (1 item) -->
        <div class="form-row items-1">
            <div class="form-group">
                <div id="koala-map"></div>
            </div>
        </div>

        <!-- Row 5: Latitude, Longitude (2 items) -->
        <div class="form-row items-2">
            <div class="form-group">
                <label for="latitude">Latitude<span class="required">*</span></label>
                <input type="number" id="latitude" name="latitude" step="any" required />
            </div>
            <div class="form-group">
                <label for="longitude">Longitude<span class="required">*</span></label>
                <input type="number" id="longitude" name="longitude" step="any" required />
            </div>
        </div>

        <!-- Hidden Fields -->
        <input type="hidden" id="town" name="town" />
        <input type="hidden" id="lga" name="lga" />
        <input type="hidden" id="postcode" name="postcode" />
        <input type="hidden" id="fate-date" name="fate_date" />

        <!-- Row 6: Additional Location Info (1 item) -->
        <div class="form-row items-1">
            <div class="form-group">
                <label for="additional-location-info">Additional Location Info</label>
                <input type="text" id="additional-location-info" name="location_info"/>
            </div>
        </div>

        <!-- Row 7: Where Found, Animal Condition (2 items) -->
        <div class="form-row items-2">
            <div class="form-group">
                <label for="where-found">Where Found<span class="required">*</span></label>
                <select id="where-found" name="where_found" required>
                    <option value="">Select Option</option>
                    <option value="High in Tree">High in Tree</option>
                    <option value="House area">House area</option>
                    <option value="In house">In house</option>
                    <option value="In tree (height unknown)">In tree (height unknown)</option>
                    <option value="In water">In water</option>
                    <option value="Low in Tree">Low in Tree</option>
                    <option value="On fence">On fence</option>
                    <option value="On ground">On ground</option>
                    <option value="On object">On object</option>
                    <option value="On pole">On pole</option>
                    <option value="On Road">On Road</option>
                    <option value="Side of road">Side of road</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
            <div class="form-group">
                <label for="animal-condition">Animal Condition<span class="required">*</span></label>
                <select id="animal-condition" name="animal_condition" required>
                    <option value="">Select Option</option>
                    <option value="Abnormal Behaviour">Abnormal Behaviour</option>
                    <option value="Burnt">Burnt</option>
                    <option value="Difficulty Breathing">Difficulty Breathing</option>
                    <option value="Diseased">Diseased</option>
                    <option value="Exhausted">Exhausted</option>
                    <option value="Geriatric">Geriatric</option>
                    <option value="Heat Stress / Hyperthermia">Heat Stress / Hyperthermia</option>
                    <option value="Injury to body">Injury to body</option>
                    <option value="Injury to head">Injury to head</option>
                    <option value="Malnourished">Malnourished</option>
                    <option value="Moribund">Moribund</option>
                    <option value="No apparent distress">No apparent distress</option>
                    <option value="No apparent Injury">No apparent injury</option>
                    <option value="Unknown">Unknown</option>
                    <option value="Waterlogged">Waterlogged</option>
                </select>
            </div>
        </div>

        <!-- Row 8: Encounter Type, Life Stage (2 items) -->
        <div class="form-row items-2">
            <div class="form-group">
                <label for="encounter-type">Encounter Type<span class="required">*</span></label>
                <select id="encounter-type" name="encounter_type" required>
                    <option value="">Select Option</option>
                    <option value="Abandoned/Orphaned">Abandoned/Orphaned</option>
                    <option value="Attack - Bird">Attack - Bird</option>
                    <option value="Attack - Dog">Attack - Dog</option>
                    <option value="Attack - Same Species">Attack - Same Species</option>
                    <option value="Attack - Suspected/other">Attack - Suspected/other</option>
                    <option value="Collision - Motor Vehicle">Collision - Motor Vehicle</option>
                    <option value="Collision - Other">Collision - Other</option>
                    <option value="Disease - Chlamydia">Disease - Chlamydia</option>
                    <option value="Disease - Other">Disease - Other</option>
                    <option value="Event - Drought">Event - Drought</option>
                    <option value="Event - Extreme Heat">Event - Extreme Heat</option>
                    <option value="Event - Fire">Event - Fire</option>
                    <option value="Event - Flood">Event - Flood</option>
                    <option value="Event - Storm">Event - Storm</option>
                    <option value="Fallen from nest or tree">Fallen from nest or tree</option>
                    <option value="Fouled By Substance">Fouled By Substance</option>
                    <option value="Healthy">Healthy</option>
                    <option value="Human Impact - habitat alteration /tree felling">Human Impact - habitat alteration /tree felling</option>
                    <option value="Unknown">Unknown</option>
                    <option value="Unsuitable Environment">Unsuitable Environment</option>
                </select>
            </div>
            <div class="form-group">
                <label for="life-stage">Life Stage<span class="required">*</span></label>
                <select id="life-stage" name="life_stage" required>
                    <option value="">Select Option</option>
                    <option value="Adult">Adult</option>
                    <option value="Juvenile">Juvenile</option>
                    <option value="Unknown">Unknown</option>
                    <option value="Young">Young</option>
                </select>
            </div>
        </div>

        <!-- Row 9: Sex, Pouch Condition, Koala Status (3 items) -->
        <div class="form-row items-3">
            <div class="form-group">
                <label for="sex">Sex<span class="required">*</span></label>
                <select id="sex" name="sex" required>
                    <option value="">Select Option</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
            <div class="form-group">
                <label for="pouch-condition">Pouch Condition<span class="required">*</span></label>
                <select id="pouch-condition" name="pouch_condition" required>
                    <option value="">Select Option</option>
                    <option value="Back Young">Back Young</option>
                    <option value="Lactating">Lactating</option>
                    <option value="N/A">N/A</option>
                    <option value="Non-lactating">Non-lactating</option>
                    <option value="Pinkie Attached">Pinkie Attached</option>
                    <option value="Pouch Young">Pouch Young</option>
                </select>
            </div>
            <div class="form-group">
                <label for="koala-status">Koala Status<span class="required">*</span></label>
                <select id="koala-status" name="koala_status" required>
                    <option value="">Select Option</option>
                    <option value="Pending">Pending</option>
                    <option value="Rescuer on way">Rescuer on way</option>
                    <option value="Trap set">Trap set</option>
                    <option value="Complete">Complete</option>
                </select>
            </div>
        </div>

        <!-- Row 10: Message to Rescuers (1 item) -->
        <div class="form-row items-1">
            <div class="form-group">
                <label for="koala-notes">Message to Rescuers</label>
                <input type="text" id="koala-notes" name="koala_notes"/>
            </div>
        </div>

        <!-- Row 11: Fate, Call Referred By, WIRES Ref (3 items) -->
        <div class="form-row items-3">
            <div class="form-group">
                <label for="fate">Fate</label>
                <select id="fate" name="fate">
                    <option value="">Select Option</option>
                    <option value="Advice Provided">Advice Provided</option>
                    <option value="Could not locate for rescue">Could not locate for rescue</option>
                    <option value="Evaded Capture">Evaded Capture</option>
                    <option value="Left and Observed">Left and Observed</option>
                    <option value="Record of sighting - DEAD">Record of sighting - DEAD</option>
                    <option value="Record of sighting - LIVE">Record of sighting - LIVE</option>
                </select>
            </div>
            <div class="form-group">
                <label for="call-referred-by">Call Referred By</label>
                <select id="call-referred-by" name="call_referred_by">
                    <option value="">Select Option</option>
                    <option value="WIRES CV">WIRES CV</option>
                    <option value="WIRES NR">WIRES NR</option>
					<option value="WIRES NR">WIRES NE</option>
                    <option value="TVWC">TVWC</option>
                    <option value="NRWC">NRWC</option>
                    <option value="GBWC">GBWC</option>
                    <option value="WILDCARE">WILDCARE</option>
                    <option value="RSPCA">RSPCA</option>
                </select>
            </div>
            <div class="form-group hidden" id="wires-ref-group">
                <label for="wires-reference-number">WIRES Ref</label>
                <input type="text" id="wires-reference-number" name="wires_reference_number" />
            </div>
        </div>

        <input type="submit" value="Create Koala Record" />
    </form>
</div>

 <script>
function initKoalaSightingMap() {
    const mapDiv = document.getElementById('koala-map');
    const addressInput = document.getElementById('koala_address');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const townInput = document.getElementById('town');
    const lgaInput = document.getElementById('lga');
    const postcodeInput = document.getElementById('postcode');

    if (!mapDiv || !addressInput || !latInput || !lngInput || !townInput || !lgaInput || !postcodeInput) {
        return;
    }

    let lat = parseFloat(latInput.value);
    let lng = parseFloat(lngInput.value);
    if (isNaN(lat) || isNaN(lng)) {
        lat = <?php echo json_encode(floatval($default_latitude)); ?>;
        lng = <?php echo json_encode(floatval($default_longitude)); ?>;
    }
    const mapCenter = { lat, lng };

    const nswBounds = new google.maps.LatLngBounds(
        { lat: <?php echo json_encode($nsw_bounds['south']); ?>, lng: <?php echo json_encode($nsw_bounds['west']); ?> },
        { lat: <?php echo json_encode($nsw_bounds['north']); ?>, lng: <?php echo json_encode($nsw_bounds['east']); ?> }
    );

    // Lismore bias point
    const lismore = { lat: -28.8136, lng: 153.2773 };

    try {
        const map = new google.maps.Map(mapDiv, {
            center: mapCenter,
            zoom: 15,
            mapTypeId: google.maps.MapTypeId.SATELLITE
        });

        const marker = new google.maps.Marker({
            position: mapCenter,
            map: map,
            draggable: true
        });

        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            types: ['address'],
            componentRestrictions: { country: 'au' },
            bounds: nswBounds,
            strictBounds: false,
            fields: ["address_components", "geometry", "formatted_address", "name"]
        });
        autocomplete.bindTo('bounds', map);

        // Bias further toward Lismore
        autocomplete.setOptions({
            locationBias: { lat: lismore.lat, lng: lismore.lng, radius: 50000 }
        });

        // Prevent Enter key from auto-submitting or mis-triggering geocode
        google.maps.event.addDomListener(addressInput, "keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
            }
        });

        // Handle place selection
        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();

            if (place.geometry) {
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();

                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);

                if (place.formatted_address) {
                    addressInput.value = place.formatted_address;
                }

                map.setCenter(place.geometry.location);
                marker.setPosition(place.geometry.location);
                reverseGeocodeSighting(lat, lng);
            } else {
                // Fallback if no geometry
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ address: place.name, region: "au", bounds: nswBounds }, function (results, status) {
                    if (status === "OK" && results[0]) {
                        const loc = results[0].geometry.location;
                        latInput.value = loc.lat().toFixed(6);
                        lngInput.value = loc.lng().toFixed(6);
                        addressInput.value = results[0].formatted_address;
                        map.setCenter(loc);
                        marker.setPosition(loc);
                        reverseGeocodeSighting(loc.lat(), loc.lng());
                    }
                });
            }
        });

        // Marker drag updates lat/lng
        marker.addListener('dragend', () => {
            const pos = marker.getPosition();
            latInput.value = pos.lat().toFixed(6);
            lngInput.value = pos.lng().toFixed(6);
            reverseGeocodeSighting(pos.lat(), pos.lng());
        });

        // Manual lat/lng updates
        latInput.addEventListener('input', updateMapFromCoordinates);
        lngInput.addEventListener('input', updateMapFromCoordinates);

        function updateMapFromCoordinates() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                const newPosition = { lat, lng };
                map.setCenter(newPosition);
                marker.setPosition(newPosition);
                reverseGeocodeSighting(lat, lng);
            }
        }

        function reverseGeocodeSighting(lat, lng) {
            try {
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ 
                    location: { lat: parseFloat(lat), lng: parseFloat(lng) },
                    bounds: nswBounds
                }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        addressInput.value = results[0].formatted_address;
                        const components = results[0].address_components;

                        townInput.value = '';
                        lgaInput.value = '';
                        postcodeInput.value = '';

                        components.forEach(component => {
                            if (component.types.includes('locality')) {
                                townInput.value = component.long_name;
                            }
                            if (component.types.includes('administrative_area_level_2')) {
                                lgaInput.value = component.long_name;
                            }
                            if (component.types.includes('postal_code')) {
                                postcodeInput.value = component.long_name;
                            }
                        });
                    } else {
                        addressInput.value = '';
                    }
                });
            } catch (error) {
                // Silent catch
            }
        }
    } catch (error) {
        // Silent catch
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const phoneField = document.getElementById('phone-number');
    if (phoneField) {
        phoneField.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '').substring(0, 10);
            let formatted = '';
            if (value.length > 0) formatted += value.substring(0, 4);
            if (value.length > 4) formatted += ' ' + value.substring(4, 7);
            if (value.length > 7) formatted += ' ' + value.substring(7, 10);
            e.target.value = formatted.trim();
            if (value.length !== 10) {
                phoneField.setCustomValidity('Please enter exactly 10 digits');
            } else {
                phoneField.setCustomValidity('');
            }
        });
    }

    const callReferredBySelect = document.getElementById('call-referred-by');
    const wiresRefGroup = document.getElementById('wires-ref-group');
    const wiresRefInput = document.getElementById('wires-reference-number');
    const wiresRefLabel = document.querySelector('label[for="wires-reference-number"]');
    if (callReferredBySelect && wiresRefGroup && wiresRefInput && wiresRefLabel) {
        function toggleWiresRef() {
            const isWires = callReferredBySelect.value === 'WIRES CV' || callReferredBySelect.value === 'WIRES NR';
            if (isWires) {
                wiresRefGroup.classList.remove('hidden');
                wiresRefInput.setAttribute('required', 'required');
                if (!wiresRefLabel.querySelector('.required')) {
                    const requiredSpan = document.createElement('span');
                    requiredSpan.className = 'required';
                    requiredSpan.textContent = '*';
                    wiresRefLabel.appendChild(requiredSpan);
                }
            } else {
                wiresRefGroup.classList.add('hidden');
                wiresRefInput.removeAttribute('required');
                wiresRefInput.value = '';
                const requiredSpan = wiresRefLabel.querySelector('.required');
                if (requiredSpan) {
                    requiredSpan.remove();
                }
            }
        }

        toggleWiresRef();
        callReferredBySelect.addEventListener('change', toggleWiresRef);
    }

    const form = document.getElementById('koala-sighting-form');
    const fateSelect = document.getElementById('fate');
    const dateTimeInput = document.getElementById('date-time');
    const fateDateInput = document.getElementById('fate-date');
    if (form && fateSelect && dateTimeInput && fateDateInput) {
        form.addEventListener('submit', function (event) {
            if (fateSelect.value) {
                const dateTimeValue = dateTimeInput.value;
                if (dateTimeValue) {
                    const datePart = dateTimeValue.split('T')[0];
                    fateDateInput.value = datePart;
                } else {
                    fateDateInput.value = '';
                }
            } else {
                fateDateInput.value = '';
            }
        });
    }
	
	// Add logic to prevent multiple form submissions
    if (form) {
        let isSubmitting = false;
        const submitButton = form.querySelector('input[type="submit"]');
        if (submitButton) {
            form.addEventListener('submit', function (event) {
                if (isSubmitting) {
                    event.preventDefault(); // Prevent additional submissions
                    return;
                }
                isSubmitting = true; // Set flag to indicate form is being submitted
                submitButton.disabled = true; // Disable the submit button
                submitButton.value = 'Submitting...'; // Update button text to indicate processing
            });
        }
    }	
});
</script>
<?php
return ob_get_clean();
}