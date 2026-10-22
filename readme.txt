=== Koala CPT Plugin ===
Contributors: websitealchemy
Tags: acf, custom post type, wildlife, maps, forms
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shortcodes and Google Maps for the koala custom post type: public and staff sighting forms, and rescue and release location maps.

== Description ==

* `[public_sighting_form]` - public koala sighting form
* `[koala_sighting_form]` - staff sighting form
* `[koala_location_map]` and `[koala_release_map]` - set the rescue and release location on a koala record

Requires Advanced Custom Fields and a `koala` custom post type with the ACF field groups these shortcodes reference.

== Installation ==

Install the release zip once via Plugins > Add New > Upload Plugin and activate it. After that, new tagged versions appear as a normal "Update available" notice on the Plugins page (the repository is public, so no token is needed).

Enter your Google Maps API key under Settings > Koala Plugin, or define KRS_MAPS_BROWSER_KEY in wp-config.php (the constant takes priority).

== Changelog ==

= 1.4.2 =
* The release location editor no longer fills in a default location. With no saved release location, the Latitude and Longitude boxes and the map pin start empty until an address, GPS, typed coordinates or a click on the map chooses one (the map is only centred on the region). Saving with nothing chosen is blocked with a clear message, and the "Open in Google Maps" link is hidden until there is a location. Release locations that are already saved behave as before.
* Settings > Koala Plugin: the Default Latitude, Default Longitude and Bounds boxes are wider so the whole value shows instead of being cut off.

= 1.4.1 =
* Fixed the Koalas list in wp-admin loading very slowly: the Location incomplete count is now a single cached query that refreshes when a koala or its location fields change.

= 1.4.0 =
* The LGA drop-down on the location editor now uses a list you control: Settings > Koala Plugin > LGA List, one LGA per line. It starts with exactly eight (Ballina Shire Council, Byron Shire Council, Clarence Valley Council, Inverell Shire Council, Kyogle Council, Lismore City Council, Richmond Valley Council, Tweed Shire Council) and replaces the previous list built from every LGA found on koala records. Free typing of any other LGA is still allowed.
* An LGA returned by Google, the pin lookup or the town lookup is matched to one of the listed names by ignoring words such as Council, Shire, City and Regional (and "(A)"/"(C)" suffixes), so "Clarence Valley" becomes "Clarence Valley Council". If nothing matches, the value is kept as returned and the save is never blocked. The wp-admin sync and the blank-LGA fill on save use the same matching, and spelling variants of the same LGA no longer count as "ambiguous" in the town lookup.
* A typed LGA that matches a listed one apart from case or spacing is stored in the listed spelling; any other typed value is kept exactly as typed.

= 1.3.0 =
* Town, postcode and LGA can now be edited by hand in the rescue location editor (town and postcode in the release editor), so volunteers can fix a missing LGA without going to wp-admin. Automatic lookups still fill the fields, but never overwrite one that has been changed by hand; changing the address text or moving the pin starts afresh.
* LGA has a suggestion list built from the LGA values already used on koala records (case and spacing variants collapsed, sorted A-Z, cached and refreshed whenever a koala's LGA changes). Free text is still allowed; a typed LGA that matches a known one apart from case or spacing is stored in the known spelling.
* A blank LGA is highlighted with the message "LGA not found. Please choose it from the list or type it." It does not block saving, and the save confirmation repeats the warning. If the posted LGA is blank and none is stored, the server fills it from other records for that town (when exactly one LGA is used there) and says so, or says it is still missing.
* "Keep current pin (only update the address details)" is now always available in both editors (the setting has been removed). It is ticked by default when the koala already has valid coordinates and hidden when it has none. A typed address then updates town, postcode and LGA without moving the pin or changing latitude and longitude.
* Save notices are now returned as kind and text, and posted town, postcode and LGA are cleaned (spacing collapsed, lengths capped) before saving. A blank is still never written over an existing value.
* wp-admin: the Koalas list has a "Location incomplete" view with a count, listing koalas where the town, postcode, LGA, latitude or longitude is missing or blank.

= 1.2.1 =
* The release location editor now matches the rescue location editor: town and postcode are visible read-only fields, failed or empty lookups show a message and never blank an existing value, typed addresses are looked up (previously only suggestions, dragging and GPS worked), the pin-lookup fallback fills a missing town or postcode, and warnings appear for an address more than about 2 km from the pin or a partial Google match. The "Keep current pin" setting applies to both editors.
* The release save request now returns notices when it keeps an existing town or postcode.
* Release values are no longer escaped twice (an apostrophe or ampersand in a release address showed as &#039; or &amp;), and the release summary now lists town and postcode.

= 1.2.0 =
* Location fields now stay in step. In wp-admin, changing the Location Map on a koala edit screen fills in koala_address, address (street), latitude, longitude, town and postcode from the map value (no Google call). Anything the editor changed by hand in the same save is kept, and a save where the map did not change touches nothing. Quick Edit, bulk edit, imports and front-end forms do not trigger the sync.
* LGA fallback order: the LGA Google returns, then the LGA other koala records agree on for that town (only when exactly one), otherwise the existing value is kept and a notice says "LGA not set automatically".
* Front-end location editor: town, postcode and LGA are now visible read-only fields. Failed or empty lookups (including no match) show a message and never blank an existing value. The pin lookup fallback now also runs for typed addresses. A warning appears when an address and the pin are more than about 2 km apart, or Google found only a partial match.
* New optional "Keep current pin" checkbox for the location editor, off by default (Settings > Koala Plugin > Location Editor).
* The location and release save requests validate latitude/longitude ranges, no longer write blank town, postcode or LGA over existing values, and the location save now writes the full Location Map value (address, lat, lng, city, state, post_code, country, name).
* The save handlers are registered at priority 1 with unique names so they answer before any duplicate handler. Settings > Koala Plugin has a Diagnostics section listing every handler on those actions.
* The Maps key is read from KRS_MAPS_BROWSER_KEY in wp-config.php when defined (Settings option is the fallback), and wp-admin Location Map fields use the same key.
* The location and release save requests now only accept koala posts, and posted text no longer keeps WordPress escape slashes.

= 1.1.0 =
* New Settings > Koala Plugin page: Google Maps API key, public sighting form map defaults and bounds, field labels and the phone field toggle.
* The Google Maps API key is now read from that setting instead of being hardcoded. Maps will not load until a key is entered.
* Fixed capitalisation of the "No apparent injury" label.
* GitHub-based automatic updates wired in.
