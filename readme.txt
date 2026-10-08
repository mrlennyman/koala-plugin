=== Koala CPT Plugin ===
Contributors: websitealchemy
Tags: acf, custom post type, wildlife, maps, forms
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shortcodes and Google Maps for the koala custom post type: public and staff sighting forms, and rescue and release location maps.

== Description ==

* `[public_sighting_form]` - public koala sighting form
* `[koala_sighting_form]` - staff sighting form
* `[koala_location_map]` and `[koala_release_map]` - set the rescue and release location on a koala record

Requires Advanced Custom Fields and a `koala` custom post type with the ACF field groups these shortcodes reference.

== Installation ==

Install the release zip once via Plugins > Add New > Upload Plugin and activate it. After that, new tagged versions appear as a normal "Update available" notice on the Plugins page (the repository is public, so no token is needed).Enter your Google Maps API key under Settings > Koala Plugin, or define KRS_MAPS_BROWSER_KEY in wp-config.php (the constant takes priority).

== Changelog ==

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
