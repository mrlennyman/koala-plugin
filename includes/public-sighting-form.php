<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Public sighting form shortcode
add_shortcode('public_sighting_form', 'public_sighting_form_shortcode');
function public_sighting_form_shortcode() {
    ob_start();

    // Get settings
    $settings = get_option('koala_sighting_form_settings', []);
    $field_labels = $settings['field_labels'] ?? [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'date_sighting' => 'Date of Sighting',
        'time_sighting' => 'Time of Sighting',
        'koala_address' => 'Koala Address',
        'where_found' => 'Where Found',
        'life_stage' => 'Life Stage',
        'animal_condition' => 'Animal Condition',
        'sex' => 'Sex',
        'pouch_condition' => 'Does the koala have young?', // Added new label
        'fate' => 'Is the koala alive or dead?',
        'koala_notes' => 'Please tell us all you can about the koala',
    ];
    $field_enabled = $settings['field_enabled'] ?? [
        'phone' => true,
        'koala_notes' => true,
    ];
    $default_latitude = $settings['default_latitude'] ?? '-28.8136';
    $default_longitude = $settings['default_longitude'] ?? '153.2773';
    $nsw_bounds = [
        'north' => floatval($settings['nsw_bounds_north'] ?? '-28.0'),
        'south' => floatval($settings['nsw_bounds_south'] ?? '-37.5'),
        'east' => floatval($settings['nsw_bounds_east'] ?? '154.0'),
        'west' => floatval($settings['nsw_bounds_west'] ?? '141.0'),
    ];

    // Check for successful submission
    $submission_success = isset($_GET['submitted']) && $_GET['submitted'] === '1';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['public_sighting_nonce']) && wp_verify_nonce($_POST['public_sighting_nonce'], 'submit_public_sighting_form')) {
        // Validate phone number
        $phone = $field_enabled['phone'] ? preg_replace('/\D/', '', $_POST['phone'] ?? '') : '';
        if ($field_enabled['phone'] && strlen($phone) !== 10) {
            echo '<p class="error">Error: Please enter a valid 10-digit phone number (e.g., 0412 345 678).</p>';
            return ob_get_clean();
        }

        // Validate koala_notes
        if (empty($_POST['koala_notes'])) {
            echo '<p class="error">Error: Please tell us all you can about the koala is required.</p>';
            return ob_get_clean();
        }

        // Validate pouch_condition
        if (empty($_POST['pouch_condition'])) {
            echo '<p class="error">Error: Please select an option for "Does the koala have young?".</p>';
            return ob_get_clean();
        }
		
		 // Validate sex
			$allowed_sex_values = ['Male', 'Female', 'Unknown'];
			$sex = sanitize_text_field($_POST['sex'] ?? '');
		if (!in_array($sex, $allowed_sex_values, true)) {
			echo '<p class="error">Error: Please select a valid option for Sex.</p>';
			return ob_get_clean();
			}

        $post_id = wp_insert_post([
            'post_type'   => 'koala',
            'post_status' => 'draft',
            'post_title'  => sanitize_text_field($_POST['koala_address'] ?? 'Untitled Public Sighting'),
        ], true);

        if ($post_id && !is_wp_error($post_id)) {
            $first_name = sanitize_text_field($_POST['first_name'] ?? '');
            $last_name = sanitize_text_field($_POST['last_name'] ?? '');
            $caller_full_name = trim($first_name . ' ' . $last_name);
            $email = sanitize_email($_POST['email'] ?? '');
            $date_sighting = sanitize_text_field($_POST['date_sighting'] ?? '');
            // Format date_sighting for incident_date (Ymd format)
            $incident_date = '';
            if (!empty($date_sighting)) {
                try {
                    $dt = new DateTime($date_sighting);
                    $incident_date = $dt->format('Ymd');
                } catch (Exception $e) {
                    // Silent catch
                }
            }
            $time_sighting = sanitize_text_field($_POST['time_sighting'] ?? '');
            $koala_address = sanitize_text_field($_POST['koala_address'] ?? '');
            
            $address = '';
            if (!empty($koala_address)) {
                $address_parts = explode(',', $koala_address, 2);
                $address = trim($address_parts[0]);
            }
            
            $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : '';
            $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : '';
            $where_found = sanitize_text_field($_POST['where_found'] ?? '');
            $life_stage = sanitize_text_field($_POST['life_stage'] ?? '');
            $animal_condition = sanitize_text_field($_POST['animal_condition'] ?? '');
            $sex = sanitize_text_field($_POST['sex'] ?? '');
            $pouch_condition = sanitize_text_field($_POST['pouch_condition'] ?? ''); // Added pouch_condition
            $fate = sanitize_text_field($_POST['fate'] ?? '');
            $koala_notes = sanitize_text_field($_POST['koala_notes'] ?? '');
            $town = sanitize_text_field($_POST['town'] ?? '');
            $lga = sanitize_text_field($_POST['lga'] ?? '');
            $postcode = sanitize_text_field($_POST['postcode'] ?? '');

            if (function_exists('update_field')) {
                update_field('first_name', $first_name, $post_id);
                update_field('last_name', $last_name, $post_id);
                update_field('caller_full_name', $caller_full_name, $post_id);
                update_field('sighting_email', $email, $post_id);
                update_field('field_67a6969879787', $phone, $post_id);
                update_field('incident_date', $incident_date, $post_id);
                update_field('fate_date', $incident_date, $post_id);
                update_field('time_sighted', $time_sighting, $post_id);
                update_field('koala_address', $koala_address, $post_id);
                update_field('address', $address, $post_id);
                update_field('latitude', $latitude, $post_id);
                update_field('longitude', $longitude, $post_id);
                update_field('location_map', [
                    'address' => $koala_address,
                    'lat'     => $latitude,
                    'lng'     => $longitude,
                ], $post_id);
                update_field('town', $town, $post_id);
                update_field('lga', $lga, $post_id);
                update_field('postcode', $postcode, $post_id);
                update_field('where_found', $where_found, $post_id);
                update_field('life_stage', $life_stage, $post_id);
                update_field('animal_condition', $animal_condition, $post_id);
                 // Updated mapping to sex_of_koala
                if (!update_field('field_67a929d73de4e', $sex, $post_id)) {
                    echo '<p class="error">Error: Failed to save Sex field. Please try again.</p>';
                    wp_delete_post($post_id, true);
                    return ob_get_clean();
                }
                update_field('pouch_condition', $pouch_condition, $post_id); // Save pouch_condition
                update_field('field_67a9b7b50212d', $fate, $post_id);
                update_field('field_67ac4bd229209', $koala_notes, $post_id);
                update_field('field_6859d17161d59', current_time('Ymd'), $post_id);
                $web_user = get_user_by('login', 'web');
                if ($web_user) {
                    update_field('recorder', $web_user->ID, $post_id);
                }
                update_field('koala_status', 'Complete', $post_id);
                // ✅ Always set "check_this" radio field to Yes
                update_field('field_6864ef6c5f38d', 'Yes', $post_id);
				// Always set lock_post to False for public sightings
				update_field('lock_post', 'False', $post_id);
            } else {
                echo '<p class="error">Error: Form submission failed. Please try again later.</p>';
                wp_delete_post($post_id, true);
                return ob_get_clean();
            }

            $updated = wp_update_post([
                'ID'          => $post_id,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($updated)) {
                echo '<p class="error">Error: Unable to publish sighting. Please try again.</p>';
                wp_delete_post($post_id, true);
                return ob_get_clean();
            }

            // WP Grid Builder indexing
            if (method_exists('\WP_Grid_Builder\Includes\Indexer', 'index_facets')) {
                try {
                    $indexer = new \WP_Grid_Builder\Includes\Indexer();
                    $indexer->index_facets(-1);
                } catch (Exception $e) {
                    // Silent catch
                }
            }
            if (function_exists('wpgb_index_post')) {
                try {
                    wpgb_index_post($post_id);
                } catch (Exception $e) {
                    // Silent catch
                }
            }

            $redirect_url = add_query_arg('submitted', '1', get_permalink());
            wp_redirect($redirect_url);
            exit;
        } else {
            echo '<p class="error">Error: Unable to create sighting. Please try again.</p>';
        }
    }
    ?>

    <style>
        #public-sighting-map {
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
            min-height: 400px;
            margin-top: 20px;
        }
        .public-sighting-form {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .public-sighting-form .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
            gap: 20px;
        }
        .public-sighting-form .form-group {
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }
        .public-sighting-form label {
            font-weight: bold;
            margin-bottom: 5px;
            white-space: normal;
            line-height: 1.4;
            max-width: 100%;
        }
        .public-sighting-form input,
        .public-sighting-form select,
        .public-sighting-form textarea {
            padding: 10px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        .public-sighting-form textarea {
            min-height: 100px;
        }
        .public-sighting-form input[type="submit"] {
            background: #004226;
            color: #fff;
            border: none;
            cursor: pointer;
            padding: 12px 20px;
            margin-top: 20px;
            width: 100%;
            position: relative;
        }
        .public-sighting-form input[type="submit"]:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .public-sighting-form .error {
            color: red;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .pac-container {
            z-index: 10000 !important;
        }
        .public-sighting-form .form-row.items-3 .form-group {
            flex: 1 1 calc(33.33% - 13.33px);
            min-width: 0;
        }
        .public-sighting-form .form-row.items-2 .form-group {
            flex: 1 1 calc(50% - 10px);
            min-width: 0;
        }
        .public-sighting-form .form-row.items-1 .form-group {
            flex: 1 1 100%;
            margin-right: 0;
        }
        @media (max-width: 767px) {
            .public-sighting-form .form-row.items-2 {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
            }
            .public-sighting-form .form-row.items-2 .form-group {
                flex: 1 1 calc(50% - 7.5px);
                min-width: 0;
                margin-bottom: 15px;
            }
            .public-sighting-form .form-row.items-1 .form-group,
            .public-sighting-form .form-row.items-3 .form-group {
                flex: 1 1 100%;
                margin-bottom: 15px;
            }
            .public-sighting-form .form-row .form-group:empty {
                display: none;
            }
            .public-sighting-form .form-row .form-group:last-child {
                margin-bottom: 0;
            }
        }
        .spinner {
            display: none;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: #004226;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
        .public-sighting-form.submitting .spinner {
            display: inline-block;
        }
        .public-sighting-form.submitting input[type="submit"] {
            color: transparent;
        }
        .thank-you-message {
            background: #e6f3e6;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #004226;
        }
        .thank-you-message h2 {
            margin-top: 0;
            color: #004226;
        }
        .thank-you-message p {
            margin: 10px 0;
        }
        .thank-you-message a {
            color: #004226;
            text-decoration: underline;
        }
        .required {
            color: red;
            margin-left: 5px;
        }
    </style>

    <?php if ($submission_success): ?>
        <div class="thank-you-message">
            <h2>Thank You!</h2>
            <p>Please note, this account is not monitored 24 hours a day. If you have concerns about the koala you have just spotted, please call our Rescue Hotline immediately on <a href="tel:+61266221233">(02) 6622 1233</a>.</p>
            <p>If you have any photos of the koala(s), please send them to us by email.<br>
            Email: <a href="mailto:info@friendsofthekoala.org">info@friendsofthekoala.org</a></p>
            <p>Thanks again. You are helping us build a better picture of koalas in the Northern Rivers Region of NSW.</p>
            <p>Kind regards,<br>
            The Friends of the Koala Team<br>
            Education & Administration Centre<br>
            Phone number: <a href="tel:+61266214664">+61 (0) 2 6621 4664</a></p>
        </div>
    <?php endif; ?>

    <div class="public-sighting-form">
        <form method="POST" id="public-sighting-form">
            <?php wp_nonce_field('submit_public_sighting_form', 'public_sighting_nonce'); ?>

            <!-- Row 1: First Name, Last Name -->
            <div class="form-row items-2">
                <div class="form-group">
                    <label for="first-name"><?php echo esc_html($field_labels['first_name']); ?><span class="required">*</span></label>
                    <input type="text" id="first-name" name="first_name" required />
                </div>
                <div class="form-group">
                    <label for="last-name"><?php echo esc_html($field_labels['last_name']); ?><span class="required">*</span></label>
                    <input type="text" id="last-name" name="last_name" required />
                </div>
            </div>

            <!-- Row 2: Email, Phone (if enabled) -->
            <div class="form-row items-2">
                <div class="form-group">
                    <label for="email"><?php echo esc_html($field_labels['email']); ?><span class="required">*</span></label>
                    <input type="email" id="email" name="email" required />
                </div>
                <?php if ($field_enabled['phone']) : ?>
                    <div class="form-group">
                        <label for="phone"><?php echo esc_html($field_labels['phone']); ?><span class="required">*</span></label>
                        <input type="text" id="phone" name="phone" class="phone-input" placeholder="0412 345 678" required />
                    </div>
                <?php else : ?>
                    <div class="form-group">
                        <!-- Empty div to maintain two-column layout on desktop -->
                    </div>
                <?php endif; ?>
            </div>

            <!-- Row 3: Date of Sighting, Time of Sighting -->
            <div class="form-row items-2">
                <div class="form-group">
                    <label for="date-sighting"><?php echo esc_html($field_labels['date_sighting']); ?><span class="required">*</span></label>
                    <input type="date" id="date-sighting" name="date_sighting" required />
                </div>
                <div class="form-group">
                    <label for="time-sighting"><?php echo esc_html($field_labels['time_sighting']); ?><span class="required">*</span></label>
                    <input type="time" id="time-sighting" name="time_sighting" required />
                </div>
            </div>

            <!-- Row 4: Koala Address -->
            <div class="form-row items-1">
                <div class="form-group">
                    <label for="koala_address"><?php echo esc_html($field_labels['koala_address']); ?><span class="required">*</span></label>
                    <input type="text" id="koala_address" name="koala_address" required />
                </div>
            </div>

            <!-- Row 5: Map -->
            <div class="form-row items-1">
                <div class="form-group">
                    <div id="public-sighting-map"></div>
                </div>
            </div>

            <!-- Row 6: Latitude, Longitude -->
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

            <!-- Row 7: Where Found, Fate -->
            <div class="form-row items-2">
                <div class="form-group">
                    <label for="where-found"><?php echo esc_html($field_labels['where_found']); ?><span class="required">*</span></label>
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
                    <label for="fate"><?php echo esc_html($field_labels['fate']); ?><span class="required">*</span></label>
                    <select id="fate" name="fate" required>
                        <option value="">Select Option</option>
                        <option value="Record of sighting - LIVE">Record of sighting - LIVE</option>
                        <option value="Record of sighting - DEAD">Record of sighting - DEAD</option>
                    </select>
                </div>
            </div>

            <!-- Row 8: Animal Condition, Sex -->
            <div class="form-row items-2">
                <div class="form-group">
                    <label for="animal-condition"><?php echo esc_html($field_labels['animal_condition']); ?><span class="required">*</span></label>
                    <select id="animal-condition" name="animal_condition" required>
                        <option value="">Select Option</option>
                        <option value="No apparent distress">No apparent distress</option>
                        <option value="No apparent injury">No apparent injury</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sex"><?php echo esc_html($field_labels['sex']); ?><span class="required">*</span></label>
                    <select id="sex" name="sex" required>
                        <option value="">Select Option</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Unknown">Unknown</option>
                    </select>
                </div>
            </div>

						<!-- Row 9: Life Stage, Pouch Condition -->
			<div class="form-row items-2">
				<div class="form-group">
					<label for="life-stage"><?php echo esc_html($field_labels['life_stage']); ?><span class="required">*</span></label>
					<select id="life-stage" name="life_stage" required>
						<option value="">Select Option</option>
						<option value="Adult">Adult</option>
						<option value="Juvenile">Juvenile</option>
						<option value="Unknown">Unknown</option>
						<option value="Young">Young</option>
					</select>
				</div>
				<div class="form-group">
					<label for="pouch-condition"><?php echo esc_html($field_labels['pouch_condition']); ?><span class="required">*</span></label>
					<select id="pouch-condition" name="pouch_condition" required>
						<option value="">Select Option</option>
						<option value="N/A">N/A</option>
						<option value="Back Young">Back Young</option>
						<option value="Pouch Young">Pouch Young</option>
					</select>
				</div>
			</div>

          <!-- Row 10: Koala Notes -->
            <div class="form-row items-1">
                <div class="form-group">
                    <label for="koala-notes"><?php echo esc_html($field_labels['koala_notes']); ?><span class="required">*</span></label>
                    <textarea id="koala-notes" name="koala_notes" required></textarea>
                </div>
            </div>

            <!-- Row 11: Submit Button -->
            <div class="form-row items-1">
                <div class="form-group">
                    <input type="submit" value="Submit Public Sighting" />
                    <span class="spinner"></span>
                </div>
            </div>
        </form>
    </div>

    <script>
    function initPublicSightingMap() {
        console.log('Attempting to initialize Public Sighting Map');
        const mapDiv = document.getElementById('public-sighting-map');
        const addressInput = document.getElementById('koala_address');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const townInput = document.getElementById('town');
        const lgaInput = document.getElementById('lga');
        const postcodeInput = document.getElementById('postcode');

        if (!mapDiv || !addressInput || !latInput || !lngInput || !townInput || !lgaInput || !postcodeInput) {
            console.error('Required DOM elements not found for public sighting map');
            return;
        }

        let lat = parseFloat(latInput.value) || <?php echo json_encode(floatval($default_latitude)); ?>;
        let lng = parseFloat(lngInput.value) || <?php echo json_encode(floatval($default_longitude)); ?>;
        const mapCenter = { lat, lng };

        const nswBounds = {
            north: <?php echo json_encode($nsw_bounds['north']); ?>,
            south: <?php echo json_encode($nsw_bounds['south']); ?>,
            east: <?php echo json_encode($nsw_bounds['east']); ?>,
            west: <?php echo json_encode($nsw_bounds['west']); ?>,
        };

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
                strictBounds: true
            });
            autocomplete.bindTo('bounds', map);

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();

                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
                addressInput.value = place.formatted_address;

                map.setCenter(place.geometry.location);
                marker.setPosition(place.geometry.location);
                reverseGeocodePublicSighting(lat, lng);
            });

            marker.addListener('dragend', () => {
                const pos = marker.getPosition();
                latInput.value = pos.lat().toFixed(6);
                lngInput.value = pos.lng().toFixed(6);
                reverseGeocodePublicSighting(pos.lat(), pos.lng());
            });

            addressInput.addEventListener('change', (e) => {
                geocodeAddress(e.target.value);
            });

            latInput.addEventListener('input', updateMapFromCoordinates);
            lngInput.addEventListener('input', updateMapFromCoordinates);

            function updateMapFromCoordinates() {
                const lat = parseFloat(latInput.value);
                const lng = parseFloat(lngInput.value);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const newPosition = { lat, lng };
                    map.setCenter(newPosition);
                    marker.setPosition(newPosition);
                    reverseGeocodePublicSighting(lat, lng);
                }
            }

            function geocodeAddress(address) {
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ 
                    address, 
                    region: 'au',
                    bounds: nswBounds
                }, (results, status) => {
                    if (status === 'OK') {
                        const loc = results[0].geometry.location;
                        marker.setPosition(loc);
                        map.setCenter(loc);
                        latInput.value = loc.lat().toFixed(6);
                        lngInput.value = loc.lng().toFixed(6);
                        reverseGeocodePublicSighting(loc.lat(), loc.lng());
                    }
                });
            }

            function reverseGeocodePublicSighting(lat, lng) {
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
            }
        } catch (error) {
            console.error('Failed to initialize public sighting map:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('public-sighting-form');
        const submitButton = form.querySelector('input[type="submit"]');
        if (form && submitButton) {
            form.addEventListener('submit', function (e) {
                if (form.checkValidity()) {
                    form.classList.add('submitting');
                    submitButton.disabled = true;
                }
            });
        }

        <?php if ($submission_success): ?>
            form.reset();
            // Scroll to thank-you message
            const thankYouMessage = document.querySelector('.thank-you-message');
            if (thankYouMessage) {
                thankYouMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        <?php endif; ?>
    });
    </script>
    <?php
    return ob_get_clean();
}
?>